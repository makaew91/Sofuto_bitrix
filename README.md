# Mobile-first блок покупки в карточке товара

Тестовое задание: реализация mobile-first блока покупки в карточке товара интернет-магазина детской одежды на 1С-Битрикс.

## Что реализовано

- Кастомный шаблон компонента `bitrix:catalog.element` в `/local/templates/`
- Галерея с точками-индикаторами и swipe-жестами
- Название, артикул, цена, старая цена, наличие
- SKU-селектор:
  - **Цвет** — круглые свотчи (44×44px)
  - **Размер** — чипы (без select)
  - Недоступные комбинации показываются, но disabled
  - При выборе SKU обновляются цена, наличие, подпись цвета **без AJAX** (матрица SKU сериализована в JSON на странице)
- Кнопка «В корзину»:
  - AJAX-добавление через свой эндпоинт `/local/ajax/add_to_cart.php`
  - Защита от повторного клика (`disabled` на время запроса)
  - Визуальный фидбек: «Добавлено ✓» / «Ошибка»
  - Восстанавливает исходное состояние через 1.5 сек
- **Sticky purchase bar** снизу экрана:
  - Появляется при скролле ниже основной кнопки (IntersectionObserver)
  - Миниатюра + название + цена + кнопка
  - Скрыт на десктопе (`@media min-width: 768px`)

## Архитектурные решения

**Разделение логики:**
- `result_modifier.php` — подготовка данных (матрица SKU, дефолтная комбинация)
- `template.php` — только HTML, без логики
- `style.css` — mobile-first, BEM, tap-зоны ≥44px
- `script.js` — ES6-класс `ProductCard`, инкапсулирующий всю интерактивность

**Почему матрица SKU в JSON на странице, а не AJAX при каждом переключении:**
- Быстрее (ноль задержек)
- Надёжнее (работает оффлайн после загрузки)
- Проще код
- Данные SKU компактные (цена + количество + ID)

**Почему свой AJAX-эндпоинт, а не `Add2BasketByProductID`:**
- Явный контроль над валидацией и ответом
- Можно вернуть корректный JSON с количеством и суммой корзины
- Проверка `check_bitrix_sessid()` предотвращает CSRF

**Не трогаем ядро Битрикса:**
- Всё в `/local/`
- Шаблон компонента — кастомный, стандартный `bitrix:catalog.element` не модифицирован
- При обновлении ядра или шаблона сайта файлы модуля не затрагиваются

## Запуск в Docker

### Предварительно

Нужен Docker + Docker Compose.

### Быстрый запуск

```bash
# 1. Клонировать репозиторий
git clone git@github.com:makaew91/Sofuto_bitrix.git
cd Sofuto_bitrix

# 2. Поднять контейнеры
docker compose up -d --build

# 3. Скачать установщик Битрикс в контейнер
bash docker/setup.sh

# 4. Пройти установку в браузере:
open http://localhost:8080/bitrixsetup.php
```

**При установке Битрикс:**
1. Редакция — **«Малый бизнес»** (содержит модуль `catalog`)
2. БД: хост `db`, база `bitrix`, юзер `bitrix`, пароль `bitrix`
3. Создать администратора (любые учётные данные)
4. Решение — **«Интернет-магазин»** (не важно, дамп его всё равно заменит)
5. Дождаться завершения установки

```bash
# 5. Восстановить БД с готовыми инфоблоками, товарами, картинками
bash docker/restore.sh

# 6. Открыть сайт
open http://localhost:8080/
```

После этого на главной видны 2 товара (футболка и платье), клик — открывает карточку со всеми SKU, картинками, sticky-баром и AJAX-корзиной.

### Если хочется делать всё с нуля вручную

Если хочется воссоздать инфоблоки без использования дампа — см. `local/seed/seed.php` и `local/seed/seed_images.php`. Требуется сначала создать инфоблоки «Товары» и «Торговые предложения» со свойствами `COLOR_REF` (список: Красный/Синий/Зелёный), `SIZE_REF` (список: 92/98/104/110), `CML2_LINK` (привязка к товару), затем запустить:

```
/local/seed/seed.php?products_iblock=<ID>&offers_iblock=<ID>
/local/seed/seed_images.php?offers_iblock=<ID>
```

### Подключение шаблона

Главная страница реализована в `local/public/index.php` (копируется в web root скриптом restore.sh). Минимальный пример вызова компонента:

```php
<?php $APPLICATION->IncludeComponent(
    'bitrix:catalog.element',
    'product_card',
    [
        'IBLOCK_TYPE' => 'kids',
        'IBLOCK_ID' => '4',
        'ELEMENT_ID' => '317',
        'PROPERTY_CODE' => ['CML2_ARTICLE', 'MORE_PHOTO'],
        'OFFERS_PROPERTY_CODE' => ['COLOR_REF', 'SIZE_REF'],
        'OFFERS_FIELD_CODE' => ['NAME'],
        'PRICE_CODE' => ['BASE'],
        'USE_PRICE_COUNT' => 'Y',
        'CACHE_TYPE' => 'N',
    ]
); ?>
```

## Структура проекта

```
Sofuto_bitrix/
├── docker/
│   ├── Dockerfile                  # PHP 8.2-apache + Bitrix extensions
│   ├── setup.sh                    # downloads bitrixsetup.php
│   ├── restore.sh                  # restores DB dump + upload archive
│   └── db/
│       ├── dump.sql.gz             # preconfigured DB (iblocks, products, offers)
│       └── upload.tar.gz           # uploaded product images
├── docker-compose.yml              # web + MySQL
├── local/
│   ├── ajax/
│   │   └── add_to_cart.php         # AJAX endpoint (Bitrix\Sale\Basket)
│   ├── public/
│   │   └── index.php               # main page (product list + card)
│   ├── seed/
│   │   ├── seed.php                # products + SKUs seeder
│   │   └── seed_images.php         # per-color image seeder
│   └── templates/
│       └── .default/
│           └── components/
│               └── bitrix/
│                   └── catalog.element/
│                       └── product_card/
│                           ├── result_modifier.php
│                           ├── template.php
│                           ├── style.css
│                           ├── script.js
│                           └── images/
│                               └── placeholder.svg
└── README.md
```

## Оценка времени

| Этап | Факт |
|------|------|
| Дизайн + план | ~0.5 ч |
| Docker | ~0.5 ч |
| Шаблон компонента (result_modifier, template, css, js) | ~1.5 ч |
| AJAX-эндпоинт | ~0.5 ч |
| Seeder | ~0.5 ч |
| Установка Битрикса + настройка инфоблоков + прогон | ~1.5 ч |
| Отладка + README | ~0.5 ч |
| **Итого** | **~5.5 ч** |
