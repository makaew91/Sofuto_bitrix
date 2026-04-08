# Implementation Plan — Mobile-first Product Card

**Goal:** Реализовать mobile-first блок покупки в карточке товара на 1С-Битрикс с кастомным шаблоном компонента `catalog.element`, AJAX-корзиной и sticky purchase bar.

**Architecture:** Bitrix «Малый бизнес» в Docker + свой шаблон в `/local/templates/` + AJAX-эндпоинт + MySQL-дамп для воспроизводимости.

**Tech Stack:** PHP 8.1, Bitrix D7, vanilla JS (ES6 classes), Docker, MySQL 8.0.

---

## File Map

| File | Responsibility |
|------|---------------|
| `docker-compose.yml` | web + MySQL контейнеры |
| `docker/Dockerfile` | PHP 8.1-apache + расширения |
| `docker/db/dump.sql.gz` | Дамп БД с настроенными инфоблоками и товарами |
| `docker/setup.sh` | Скачивание bitrixsetup.php (для первого запуска) |
| `local/ajax/add_to_cart.php` | AJAX-эндпоинт корзины |
| `local/templates/.default/components/bitrix/catalog.element/product_card/result_modifier.php` | Подготовка SKU-матрицы |
| `local/templates/.default/components/bitrix/catalog.element/product_card/template.php` | HTML карточки |
| `local/templates/.default/components/bitrix/catalog.element/product_card/style.css` | Mobile-first CSS |
| `local/templates/.default/components/bitrix/catalog.element/product_card/script.js` | Класс ProductCard |
| `local/seed/seed.php` | Сидер товаров/SKU (запасной вариант) |
| `README.md` | Инструкция запуска + описание |

---

### Task 1: Docker окружение

**Files:**
- Create: `docker-compose.yml`
- Create: `docker/Dockerfile`
- Create: `docker/setup.sh`
- Create: `.gitignore`

**Step 1:** Скопировать Docker-шаблон из `vit_bitrix` (протестирован, работает), адаптировать под новый проект.

**Step 2:** В `Dockerfile` PHP 8.1-apache с расширениями: `gd, mysqli, pdo_mysql, zip, intl, opcache, xml, mbstring` + `date.timezone = Europe/Moscow`.

**Step 3:** `docker-compose.yml` — два сервиса (`web:8080`, `db:3306`), monted volume `./local:/var/www/html/local`, persistent volumes `bitrix_data` и `db_data`. При наличии `docker/db/dump.sql.gz` — смонтировать в `/docker-entrypoint-initdb.d/` у MySQL контейнера.

**Step 4:** `.gitignore`: `.DS_Store`, `docs/superpowers/`, `docker/db/data/` (если будет).

**Step 5:** Commit: `feat: add docker environment`.

---

### Task 2: Установка Битрикс «Малый бизнес»

**Ручные действия (не в git):**

**Step 1:** `docker compose up -d --build`.
**Step 2:** `bash docker/setup.sh` → скачивает `bitrixsetup.php`.
**Step 3:** Открыть `http://localhost:8080/bitrixsetup.php`, выбрать редакцию **«Малый бизнес»** (Small Business).
**Step 4:** На шаге БД: host=`db`, db=`bitrix`, user=`bitrix`, pass=`bitrix`.
**Step 5:** Создать админа: `admin` / `admin123`.
**Step 6:** Solution: «Интернет-магазин».
**Step 7:** Дождаться завершения установки.

**Проверка:** `http://localhost:8080/` открывается, `http://localhost:8080/bitrix/admin/` доступна.

---

### Task 3: Инфоблоки и свойства

**Ручные действия в админке Битрикс:**

**Step 1:** Удалить демо-каталог или создать новый тип инфоблока «kids».

**Step 2:** Создать инфоблок «Товары» (`kids_products`):
- Тип: «Каталог»
- Символьный код: `kids_products`
- Включить: «Есть торговые предложения»

**Step 3:** Создать инфоблок «Торговые предложения» (`kids_offers`):
- Тип: «Торговые предложения»
- Символьный код: `kids_offers`
- Привязка к `kids_products` через поле `CML2_LINK`

**Step 4:** В `kids_offers` добавить свойства:
- `COLOR_REF` — тип «Справочник» или «Список», значения: Красный (#E74C3C), Синий (#3498DB), Зелёный (#27AE60)
- `SIZE_REF` — тип «Список», значения: 92, 98, 104, 110

**Step 5:** Установить эти свойства как «свойства торгового предложения» через настройки инфоблока.

**Step 6:** Включить в `kids_products`: поле `MORE_PHOTO` (множественное, файл).

**Проверка:** В админке при редактировании товара появляется таб «Торговые предложения», при добавлении SKU показываются поля Цвет и Размер.

---

### Task 4: Seeder товаров и SKU

**Files:**
- Create: `local/seed/seed.php`

**Step 1:** Создать файл с импортом `Bitrix\Main\Loader`, `CIBlockElement`, `CPrice`.

**Step 2:** Функция `seedProduct(iblockId, name, article, basePrice, oldPrice, offersIblockId, colorSizes)`:
```php
<?php
use Bitrix\Main\Loader;
use Bitrix\Main\Application;

define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC','Y');
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

Loader::includeModule('iblock');
Loader::includeModule('catalog');

$productsIblockId = (int) ($_GET['products_iblock'] ?? 0);
$offersIblockId = (int) ($_GET['offers_iblock'] ?? 0);

if (!$productsIblockId || !$offersIblockId) {
    die('Usage: seed.php?products_iblock=N&offers_iblock=M');
}

// Reference lists — get property enum IDs for COLOR_REF/SIZE_REF
$colorEnumByName = [];
$sizeEnumByName = [];

$colorProp = CIBlockProperty::GetByID('COLOR_REF', $offersIblockId)->Fetch();
$sizeProp = CIBlockProperty::GetByID('SIZE_REF', $offersIblockId)->Fetch();

$enums = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $offersIblockId, 'PROPERTY_ID' => $colorProp['ID']]);
while ($row = $enums->Fetch()) $colorEnumByName[$row['VALUE']] = $row['ID'];

$enums = CIBlockPropertyEnum::GetList(['SORT' => 'ASC'], ['IBLOCK_ID' => $offersIblockId, 'PROPERTY_ID' => $sizeProp['ID']]);
while ($row = $enums->Fetch()) $sizeEnumByName[$row['VALUE']] = $row['ID'];

function createProduct($iblockId, $name, $article, $price, $oldPrice = null) {
    $el = new CIBlockElement();
    $id = $el->Add([
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'PROPERTY_VALUES' => ['CML2_ARTICLE' => $article],
    ]);
    CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => 0, 'QUANTITY_TRACE' => 'N']);
    CPrice::Add(['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1, 'PRICE' => $price, 'CURRENCY' => 'RUB']);
    return $id;
}

function createOffer($offersIblockId, $productId, $name, $colorEnumId, $sizeEnumId, $price, $quantity) {
    $el = new CIBlockElement();
    $id = $el->Add([
        'IBLOCK_ID' => $offersIblockId,
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'PROPERTY_VALUES' => [
            'CML2_LINK' => $productId,
            'COLOR_REF' => $colorEnumId,
            'SIZE_REF' => $sizeEnumId,
        ],
    ]);
    CCatalogProduct::Add(['ID' => $id, 'QUANTITY' => $quantity, 'QUANTITY_TRACE' => 'Y']);
    CPrice::Add(['PRODUCT_ID' => $id, 'CATALOG_GROUP_ID' => 1, 'PRICE' => $price, 'CURRENCY' => 'RUB']);
    return $id;
}

// Product 1: футболка
$p1 = createProduct($productsIblockId, 'Футболка Мишка', 'KIDS-001', 1200);
foreach (['Красный', 'Синий', 'Зелёный'] as $color) {
    foreach (['92', '98', '104'] as $size) {
        $qty = ($color === 'Зелёный' && $size === '104') ? 0 : rand(3, 10);
        createOffer($offersIblockId, $p1, "Футболка Мишка $color $size", $colorEnumByName[$color], $sizeEnumByName[$size], 1200, $qty);
    }
}

// Product 2: платье
$p2 = createProduct($productsIblockId, 'Платье Ромашка', 'KIDS-002', 2500);
foreach (['Красный', 'Синий'] as $color) {
    foreach (['92', '98', '104', '110'] as $size) {
        $qty = rand(0, 8);
        createOffer($offersIblockId, $p2, "Платье Ромашка $color $size", $colorEnumByName[$color], $sizeEnumByName[$size], 2500, $qty);
    }
}

echo "Seeded: product 1 ID=$p1, product 2 ID=$p2\n";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
```

**Step 3:** Запустить через браузер: `http://localhost:8080/local/seed/seed.php?products_iblock=X&offers_iblock=Y` (ID инфоблоков взять из админки).

**Step 4:** Commit: `feat: add product seeder script`.

---

### Task 5: Дамп БД

**Step 1:** После успешного сидирования сделать дамп:
```bash
docker compose exec db mysqldump -ubitrix -pbitrix bitrix | gzip > docker/db/dump.sql.gz
```

**Step 2:** Обновить `docker-compose.yml`: примонтировать `./docker/db:/docker-entrypoint-initdb.d:ro`. MySQL автоматически выполняет `.sql` и `.sql.gz` файлы при первой инициализации.

**Step 3:** Commit: `feat: add DB dump for reproducibility`.

---

### Task 6: result_modifier.php

**Files:**
- Create: `local/templates/.default/components/bitrix/catalog.element/product_card/result_modifier.php`

**Step 1:** Код:
```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

$product = $arResult;

// Build SKU matrix from arResult['OFFERS']
$matrix = [];
$colors = [];
$sizes = [];

foreach ($arResult['OFFERS'] ?? [] as $offer) {
    $colorId = (int) ($offer['PROPERTIES']['COLOR_REF']['VALUE'] ?? 0);
    $sizeId = (int) ($offer['PROPERTIES']['SIZE_REF']['VALUE'] ?? 0);

    $colorName = $offer['DISPLAY_PROPERTIES']['COLOR_REF']['DISPLAY_VALUE'] ?? '';
    $sizeName = $offer['DISPLAY_PROPERTIES']['SIZE_REF']['DISPLAY_VALUE'] ?? '';

    if (!$colorId || !$sizeId) continue;

    $price = (float) ($offer['PRICE']['DISCOUNT_VALUE'] ?? $offer['PRICE']['VALUE'] ?? 0);
    $oldPrice = (float) ($offer['PRICE']['VALUE'] ?? 0);
    $quantity = (int) ($offer['CATALOG_QUANTITY'] ?? 0);

    $matrix[$colorId][$sizeId] = [
        'offer_id' => (int) $offer['ID'],
        'price' => $price,
        'price_formatted' => number_format($price, 0, '.', ' ') . ' ₽',
        'old_price' => $oldPrice > $price ? $oldPrice : null,
        'old_price_formatted' => $oldPrice > $price ? number_format($oldPrice, 0, '.', ' ') . ' ₽' : null,
        'quantity' => $quantity,
        'available' => $quantity > 0,
        'image' => !empty($offer['DETAIL_PICTURE']['SRC']) ? $offer['DETAIL_PICTURE']['SRC'] : null,
    ];

    $colors[$colorId] = [
        'id' => $colorId,
        'name' => $colorName,
        'hex' => $offer['DISPLAY_PROPERTIES']['COLOR_REF']['FILE_VALUE']['SRC'] ?? self::hexFromName($colorName),
    ];
    $sizes[$sizeId] = ['id' => $sizeId, 'name' => $sizeName];
}

// Sort sizes numerically
uasort($sizes, fn($a, $b) => (int) $a['name'] <=> (int) $b['name']);

$arResult['SKU_MATRIX'] = $matrix;
$arResult['SKU_COLORS'] = array_values($colors);
$arResult['SKU_SIZES'] = array_values($sizes);

// Default selection: first available combination
$defaultColor = null;
$defaultSize = null;
foreach ($matrix as $colorId => $bySize) {
    foreach ($bySize as $sizeId => $data) {
        if ($data['available']) {
            $defaultColor = $colorId;
            $defaultSize = $sizeId;
            break 2;
        }
    }
}
if ($defaultColor === null) {
    $defaultColor = array_key_first($matrix) ?? null;
    $defaultSize = $defaultColor ? array_key_first($matrix[$defaultColor]) : null;
}

$arResult['DEFAULT_COLOR'] = $defaultColor;
$arResult['DEFAULT_SIZE'] = $defaultSize;

// Fallback HEX map (simple hardcoded mapping — adjust if admin sets HEX via file/ref)
$arResult['COLOR_HEX_MAP'] = [
    'Красный' => '#E74C3C',
    'Синий' => '#3498DB',
    'Зелёный' => '#27AE60',
];
```

**Step 2:** Commit: `feat: add result_modifier with SKU matrix builder`.

---

### Task 7: template.php

**Files:**
- Create: `local/templates/.default/components/bitrix/catalog.element/product_card/template.php`

**Step 1:** HTML-структура:
```php
<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

/** @var array $arResult */
$this->addExternalCss($templateFolder . '/style.css');
$this->addExternalJs($templateFolder . '/script.js');

$matrix = $arResult['SKU_MATRIX'];
$colors = $arResult['SKU_COLORS'];
$sizes = $arResult['SKU_SIZES'];
$hexMap = $arResult['COLOR_HEX_MAP'];
$defaultColor = $arResult['DEFAULT_COLOR'];
$defaultSize = $arResult['DEFAULT_SIZE'];

$gallery = [];
if (!empty($arResult['DETAIL_PICTURE']['SRC'])) $gallery[] = $arResult['DETAIL_PICTURE']['SRC'];
foreach ($arResult['PROPERTIES']['MORE_PHOTO']['VALUE'] ?? [] as $fileId) {
    $src = CFile::GetPath($fileId);
    if ($src) $gallery[] = $src;
}
if (empty($gallery)) $gallery[] = '/local/templates/.default/components/bitrix/catalog.element/product_card/images/placeholder.png';
?>

<div class="product-card" data-product-id="<?= (int) $arResult['ID'] ?>">
    <div class="product-card__gallery">
        <div class="product-card__slides">
            <?php foreach ($gallery as $i => $src): ?>
                <img class="product-card__slide <?= $i === 0 ? 'is-active' : '' ?>" src="<?= htmlspecialcharsbx($src) ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
            <?php endforeach; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
            <div class="product-card__dots">
                <?php foreach ($gallery as $i => $_): ?>
                    <button class="product-card__dot <?= $i === 0 ? 'is-active' : '' ?>" data-slide="<?= $i ?>" aria-label="Slide <?= $i + 1 ?>"></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <h1 class="product-card__title"><?= htmlspecialcharsbx($arResult['NAME']) ?></h1>

    <div class="product-card__price-row">
        <span class="product-card__price" data-price></span>
        <span class="product-card__old-price" data-old-price></span>
    </div>

    <div class="product-card__availability" data-availability></div>

    <?php if (!empty($colors)): ?>
        <div class="product-card__sku-group">
            <div class="product-card__sku-label">Цвет: <span data-selected-color></span></div>
            <div class="product-card__colors">
                <?php foreach ($colors as $color): ?>
                    <?php $hex = $hexMap[$color['name']] ?? '#CCCCCC'; ?>
                    <button
                        class="product-card__color"
                        data-color-id="<?= $color['id'] ?>"
                        data-color-name="<?= htmlspecialcharsbx($color['name']) ?>"
                        style="background-color: <?= $hex ?>"
                        aria-label="<?= htmlspecialcharsbx($color['name']) ?>"
                    ></button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($sizes)): ?>
        <div class="product-card__sku-group">
            <div class="product-card__sku-label">Размер</div>
            <div class="product-card__sizes">
                <?php foreach ($sizes as $size): ?>
                    <button
                        class="product-card__size"
                        data-size-id="<?= $size['id'] ?>"
                    ><?= htmlspecialcharsbx($size['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <button class="product-card__buy" data-buy-btn>
        <span class="product-card__buy-label">В корзину</span>
    </button>
</div>

<div class="product-card__sticky" data-sticky hidden>
    <img class="product-card__sticky-img" src="<?= htmlspecialcharsbx($gallery[0]) ?>" alt="">
    <div class="product-card__sticky-info">
        <div class="product-card__sticky-name"><?= htmlspecialcharsbx($arResult['NAME']) ?></div>
        <div class="product-card__sticky-price" data-sticky-price></div>
    </div>
    <button class="product-card__sticky-buy" data-buy-btn>В корзину</button>
</div>

<script type="application/json" data-sku-matrix><?= json_encode([
    'matrix' => $matrix,
    'colors' => $colors,
    'sizes' => $sizes,
    'hexMap' => $hexMap,
    'defaultColor' => $defaultColor,
    'defaultSize' => $defaultSize,
    'sessid' => bitrix_sessid(),
], JSON_UNESCAPED_UNICODE) ?></script>
```

**Step 2:** Commit: `feat: add product card template`.

---

### Task 8: style.css (mobile-first)

**Files:**
- Create: `local/templates/.default/components/bitrix/catalog.element/product_card/style.css`

**Step 1:** Mobile-first CSS. Основные блоки:

```css
.product-card {
    max-width: 100%;
    padding: 16px;
    font-family: -apple-system, BlinkMacSystemFont, sans-serif;
    color: #1a1a1a;
}

.product-card__gallery {
    position: relative;
    margin: -16px -16px 16px;
    aspect-ratio: 1;
    overflow: hidden;
    background: #f5f5f5;
}

.product-card__slides { display: flex; height: 100%; transition: transform .3s; }
.product-card__slide { width: 100%; height: 100%; object-fit: cover; flex-shrink: 0; display: none; }
.product-card__slide.is-active { display: block; }

.product-card__dots {
    position: absolute; bottom: 12px; left: 0; right: 0;
    display: flex; justify-content: center; gap: 6px;
}
.product-card__dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: rgba(255,255,255,.5); border: none; padding: 0; cursor: pointer;
}
.product-card__dot.is-active { background: #fff; }

.product-card__title {
    font-size: 20px; font-weight: 600; margin: 0 0 12px;
    line-height: 1.3;
}

.product-card__price-row { display: flex; align-items: baseline; gap: 10px; margin-bottom: 8px; }
.product-card__price { font-size: 24px; font-weight: 700; }
.product-card__old-price { font-size: 16px; color: #999; text-decoration: line-through; }
.product-card__old-price:empty { display: none; }

.product-card__availability { font-size: 14px; margin-bottom: 20px; }
.product-card__availability.is-available { color: #27AE60; }
.product-card__availability.is-unavailable { color: #E74C3C; }

.product-card__sku-group { margin-bottom: 20px; }
.product-card__sku-label { font-size: 14px; color: #666; margin-bottom: 8px; }

.product-card__colors { display: flex; gap: 10px; flex-wrap: wrap; }
.product-card__color {
    width: 44px; height: 44px; border-radius: 50%;
    border: 2px solid transparent; padding: 0; cursor: pointer;
    box-shadow: inset 0 0 0 2px #fff;
    transition: border-color .15s;
}
.product-card__color.is-selected { border-color: #1a1a1a; }
.product-card__color.is-disabled { opacity: .3; cursor: not-allowed; }

.product-card__sizes { display: flex; gap: 8px; flex-wrap: wrap; }
.product-card__size {
    min-width: 56px; height: 44px; padding: 0 14px;
    border: 1px solid #ddd; background: #fff; border-radius: 8px;
    font-size: 15px; cursor: pointer;
    transition: all .15s;
}
.product-card__size.is-selected { border-color: #1a1a1a; background: #1a1a1a; color: #fff; }
.product-card__size.is-disabled { opacity: .4; cursor: not-allowed; text-decoration: line-through; }

.product-card__buy {
    width: 100%; height: 52px;
    background: #1a1a1a; color: #fff;
    border: none; border-radius: 10px;
    font-size: 16px; font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
.product-card__buy:hover { background: #333; }
.product-card__buy[disabled] { background: #999; cursor: not-allowed; }
.product-card__buy.is-success { background: #27AE60; }

.product-card__sticky {
    position: fixed; bottom: 0; left: 0; right: 0;
    display: flex; align-items: center; gap: 12px;
    padding: 10px 16px;
    background: #fff;
    box-shadow: 0 -4px 16px rgba(0,0,0,.08);
    z-index: 100;
    transform: translateY(100%);
    transition: transform .3s;
}
.product-card__sticky.is-visible { transform: translateY(0); }
.product-card__sticky[hidden] { display: none; }
.product-card__sticky-img { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; }
.product-card__sticky-info { flex: 1; min-width: 0; }
.product-card__sticky-name { font-size: 13px; color: #666; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.product-card__sticky-price { font-size: 16px; font-weight: 700; }
.product-card__sticky-buy {
    padding: 12px 18px; height: 44px;
    background: #1a1a1a; color: #fff; border: none; border-radius: 8px;
    font-size: 14px; font-weight: 600; cursor: pointer;
}

@media (min-width: 768px) {
    .product-card { max-width: 480px; margin: 0 auto; }
    .product-card__sticky { display: none !important; }
}
```

**Step 2:** Commit: `feat: add mobile-first product card styles`.

---

### Task 9: script.js — класс ProductCard

**Files:**
- Create: `local/templates/.default/components/bitrix/catalog.element/product_card/script.js`

**Step 1:** Код:
```javascript
(function () {
    'use strict';

    class ProductCard {
        constructor(rootEl) {
            this.root = rootEl;
            const dataEl = document.querySelector('[data-sku-matrix]');
            const data = JSON.parse(dataEl.textContent);

            this.matrix = data.matrix;
            this.colors = data.colors;
            this.sizes = data.sizes;
            this.hexMap = data.hexMap;
            this.sessid = data.sessid;

            this.selectedColor = data.defaultColor;
            this.selectedSize = data.defaultSize;

            this.priceEl = this.root.querySelector('[data-price]');
            this.oldPriceEl = this.root.querySelector('[data-old-price]');
            this.availabilityEl = this.root.querySelector('[data-availability]');
            this.selectedColorNameEl = this.root.querySelector('[data-selected-color]');
            this.stickyPriceEl = document.querySelector('[data-sticky-price]');
            this.stickyEl = document.querySelector('[data-sticky]');

            this.bindEvents();
            this.updateView();
            this.setupSticky();
        }

        bindEvents() {
            this.root.querySelectorAll('[data-color-id]').forEach(btn => {
                btn.addEventListener('click', () => this.selectColor(parseInt(btn.dataset.colorId, 10)));
            });
            this.root.querySelectorAll('[data-size-id]').forEach(btn => {
                btn.addEventListener('click', () => this.selectSize(parseInt(btn.dataset.sizeId, 10)));
            });
            document.querySelectorAll('[data-buy-btn]').forEach(btn => {
                btn.addEventListener('click', () => this.addToCart(btn));
            });
        }

        selectColor(colorId) {
            // If target combination unavailable, try to keep current size, else pick first available size
            const bySize = this.matrix[colorId] || {};
            if (!bySize[this.selectedSize] || !bySize[this.selectedSize].available) {
                for (const sizeId of Object.keys(bySize)) {
                    if (bySize[sizeId].available) { this.selectedSize = parseInt(sizeId, 10); break; }
                }
            }
            this.selectedColor = colorId;
            this.updateView();
        }

        selectSize(sizeId) {
            const cell = (this.matrix[this.selectedColor] || {})[sizeId];
            if (!cell || !cell.available) return; // disabled
            this.selectedSize = sizeId;
            this.updateView();
        }

        getCurrentOffer() {
            return ((this.matrix[this.selectedColor] || {})[this.selectedSize]) || null;
        }

        updateView() {
            const offer = this.getCurrentOffer();

            // Price
            if (offer) {
                this.priceEl.textContent = offer.price_formatted;
                this.oldPriceEl.textContent = offer.old_price_formatted || '';
                if (this.stickyPriceEl) this.stickyPriceEl.textContent = offer.price_formatted;
            }

            // Availability
            if (offer && offer.available) {
                this.availabilityEl.textContent = 'В наличии';
                this.availabilityEl.className = 'product-card__availability is-available';
            } else {
                this.availabilityEl.textContent = 'Нет в наличии';
                this.availabilityEl.className = 'product-card__availability is-unavailable';
            }

            // Color swatches
            this.root.querySelectorAll('[data-color-id]').forEach(btn => {
                const id = parseInt(btn.dataset.colorId, 10);
                btn.classList.toggle('is-selected', id === this.selectedColor);
                // Check if any size is available for this color
                const bySize = this.matrix[id] || {};
                const anyAvailable = Object.values(bySize).some(c => c.available);
                btn.classList.toggle('is-disabled', !anyAvailable);
            });

            // Sizes
            this.root.querySelectorAll('[data-size-id]').forEach(btn => {
                const id = parseInt(btn.dataset.sizeId, 10);
                const cell = (this.matrix[this.selectedColor] || {})[id];
                btn.classList.toggle('is-selected', id === this.selectedSize);
                btn.classList.toggle('is-disabled', !cell || !cell.available);
            });

            // Selected color name
            if (this.selectedColorNameEl) {
                const colorObj = this.colors.find(c => c.id === this.selectedColor);
                this.selectedColorNameEl.textContent = colorObj ? colorObj.name : '';
            }

            // Buy button state
            document.querySelectorAll('[data-buy-btn]').forEach(btn => {
                btn.disabled = !offer || !offer.available;
            });
        }

        async addToCart(btn) {
            const offer = this.getCurrentOffer();
            if (!offer || !offer.available || btn.disabled) return;

            btn.disabled = true;
            const originalText = btn.querySelector('.product-card__buy-label')?.textContent || btn.textContent;

            try {
                const formData = new FormData();
                formData.append('offer_id', String(offer.offer_id));
                formData.append('quantity', '1');
                formData.append('sessid', this.sessid);

                const res = await fetch('/local/ajax/add_to_cart.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await res.json();

                if (data.status === 'ok') {
                    btn.classList.add('is-success');
                    this.setBtnText(btn, 'Добавлено ✓');
                    setTimeout(() => {
                        btn.classList.remove('is-success');
                        this.setBtnText(btn, originalText);
                        btn.disabled = false;
                    }, 1500);
                } else {
                    this.setBtnText(btn, 'Ошибка, повторите');
                    setTimeout(() => { this.setBtnText(btn, originalText); btn.disabled = false; }, 1500);
                }
            } catch (e) {
                this.setBtnText(btn, 'Ошибка сети');
                setTimeout(() => { this.setBtnText(btn, originalText); btn.disabled = false; }, 1500);
            }
        }

        setBtnText(btn, text) {
            const label = btn.querySelector('.product-card__buy-label');
            if (label) label.textContent = text;
            else btn.textContent = text;
        }

        setupSticky() {
            if (!this.stickyEl) return;
            const mainBtn = this.root.querySelector('.product-card__buy');
            if (!mainBtn) return;

            this.stickyEl.hidden = false;
            const obs = new IntersectionObserver(entries => {
                for (const e of entries) {
                    this.stickyEl.classList.toggle('is-visible', !e.isIntersecting);
                }
            }, { rootMargin: '0px 0px -50px 0px' });
            obs.observe(mainBtn);
        }

        // Gallery swipe/dots
        initGallery() {
            const dots = this.root.querySelectorAll('[data-slide]');
            const slides = this.root.querySelectorAll('.product-card__slide');
            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    const idx = parseInt(dot.dataset.slide, 10);
                    slides.forEach((s, i) => s.classList.toggle('is-active', i === idx));
                    dots.forEach((d, i) => d.classList.toggle('is-active', i === idx));
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('.product-card');
        if (root) {
            const card = new ProductCard(root);
            card.initGallery();
        }
    });
})();
```

**Step 2:** Commit: `feat: add ProductCard JS class`.

---

### Task 10: AJAX add-to-cart endpoint

**Files:**
- Create: `local/ajax/add_to_cart.php`

**Step 1:** Код:
```php
<?php
define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    if (!check_bitrix_sessid()) {
        throw new Exception('Invalid session');
    }

    Loader::includeModule('sale');
    Loader::includeModule('catalog');

    $offerId = (int) ($_POST['offer_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    if ($offerId <= 0) {
        throw new Exception('Invalid offer_id');
    }

    $basket = Basket::loadItemsForFUser(Fuser::getId(), SITE_ID);
    $item = $basket->getExistsItem('catalog', $offerId);

    if ($item) {
        $item->setField('QUANTITY', $item->getQuantity() + $quantity);
    } else {
        $item = $basket->createItem('catalog', $offerId);
        $item->setFields([
            'QUANTITY' => $quantity,
            'CURRENCY' => 'RUB',
            'LID' => SITE_ID,
            'PRODUCT_PROVIDER_CLASS' => \CCatalogProductProvider::class,
        ]);
    }

    $result = $basket->save();
    if (!$result->isSuccess()) {
        throw new Exception(implode('; ', $result->getErrorMessages()));
    }

    $count = 0;
    $total = 0.0;
    foreach ($basket as $b) {
        $count += $b->getQuantity();
        $total += $b->getFinalPrice();
    }

    echo json_encode([
        'status' => 'ok',
        'cart_count' => (int) $count,
        'cart_total' => number_format($total, 0, '.', ' ') . ' ₽',
    ]);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
```

**Step 2:** Commit: `feat: add AJAX add-to-cart endpoint`.

---

### Task 11: Подключение шаблона на странице каталога

**Ручные действия:**

**Step 1:** Создать страницу `/catalog/index.php` (или изменить существующую detail-страницу), которая вызывает `bitrix:catalog.element` с `TEMPLATE=product_card`:

```php
<?php require($_SERVER['DOCUMENT_ROOT'].'/bitrix/header.php'); ?>
<?php $APPLICATION->SetTitle('Тест карточки товара'); ?>

<?php $APPLICATION->IncludeComponent(
    "bitrix:catalog.element",
    "product_card",
    [
        "IBLOCK_TYPE" => "kids",
        "IBLOCK_ID" => "#IBLOCK_ID#",
        "ELEMENT_ID" => "#ELEMENT_ID#",
        "USE_ELEMENT_COUNTER" => "N",
        "USE_COMMENTS" => "N",
        "SET_TITLE" => "Y",
        "PROPERTY_CODE" => ["CML2_ARTICLE", "MORE_PHOTO"],
        "OFFERS_PROPERTY_CODE" => ["COLOR_REF", "SIZE_REF"],
        "OFFER_TREE_PROPS" => ["COLOR_REF", "SIZE_REF"],
        "PRICE_CODE" => ["BASE"],
        "USE_PRICE_COUNT" => "Y",
        "SHOW_PRICE_COUNT" => "1",
        "CACHE_TYPE" => "N",
        "CACHE_TIME" => "0",
    ]
); ?>

<?php require($_SERVER['DOCUMENT_ROOT'].'/bitrix/footer.php'); ?>
```

**Step 2:** Проверить в браузере, что страница отрисовывает карточку с рабочим SKU-селектором.

**Step 3:** Commit: `feat: add catalog element test page`.

---

### Task 12: README + скринкаст

**Files:**
- Create: `README.md`

**Step 1:** README с секциями:
- Описание решения
- Что реализовано
- Архитектурные решения
- Инструкция запуска (`docker compose up -d`)
- Тестовые товары (список с ссылками)
- Что бы улучшил дальше

**Step 2:** (по возможности) записать короткое видео через QuickTime, залить как `demo.mov` или на YouTube, добавить ссылку в README.

**Step 3:** Commit: `docs: add README and demo`.

---

## Verification Checklist

- [ ] `docker compose up -d` поднимает сайт без ручных действий (дамп восстанавливается)
- [ ] Карточка товара открывается в мобильном viewport и выглядит целостно
- [ ] Свотчи цвета переключаются, недоступные затемнены
- [ ] Размеры переключаются, недоступные disabled + перечёркнуты
- [ ] При смене SKU обновляются цена и наличие
- [ ] Кнопка «В корзину» работает, AJAX успешен, фидбек виден
- [ ] Повторный клик во время запроса игнорируется
- [ ] Sticky-bar появляется при скролле ниже основной кнопки
- [ ] В десктопе sticky скрыт
- [ ] README воспроизводим с нуля
