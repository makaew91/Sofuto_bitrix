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

### Шаги

```bash
# 1. Клонировать репозиторий
git clone git@github.com:makaew91/Sofuto_bitrix.git
cd Sofuto_bitrix

# 2. Поднять контейнеры
docker compose up -d --build

# 3. Скачать установщик Битрикс
bash docker/setup.sh

# 4. Открыть установщик в браузере
open http://localhost:8080/bitrixsetup.php
```

### Установка Битрикс

1. Выбрать редакцию **«Малый бизнес»** (содержит модуль `catalog`)
2. На шаге БД указать:
   - **Хост:** `db`
   - **База:** `bitrix`
   - **Пользователь:** `bitrix`
   - **Пароль:** `bitrix`
3. Создать администратора
4. Выбрать решение **«Интернет-магазин»**

### Настройка инфоблоков

После установки в админке Битрикс создать два инфоблока:

**1. Инфоблок «Товары»** (`kids_products`)
- Тип инфоблока: «Каталог» (или создать свой тип `kids`)
- В настройках инфоблока включить свойство `MORE_PHOTO` (файл, множественное)
- Свойство `CML2_ARTICLE` (строка) — обычно создаётся автоматически

**2. Инфоблок «Торговые предложения»** (`kids_offers`)
- Тип: «Торговые предложения»
- Привязка к `kids_products` через поле `CML2_LINK`
- Добавить свойства:
  - `COLOR_REF` — список, значения: `Красный`, `Синий`, `Зелёный`
  - `SIZE_REF` — список, значения: `92`, `98`, `104`, `110`

### Наполнение тестовыми данными

После создания инфоблоков и их свойств открыть в браузере:

```
http://localhost:8080/local/seed/seed.php?products_iblock=X&offers_iblock=Y
```

где `X` и `Y` — ID созданных инфоблоков (можно посмотреть в админке: Контент → Инфоблоки).

Скрипт создаст:
- Футболка «Мишка» — 3 цвета × 3 размера (9 SKU, один out-of-stock)
- Платье «Ромашка» — 2 цвета × 4 размера (8 SKU со случайным наличием)

### Подключение шаблона на странице

Создать страницу (например `/catalog/product.php`), вызвать компонент с параметром `product_card`:

```php
<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php'; ?>

<?php $APPLICATION->IncludeComponent(
    'bitrix:catalog.element',
    'product_card',
    [
        'IBLOCK_TYPE' => 'kids',
        'IBLOCK_ID' => 'X',
        'ELEMENT_ID' => '<ID товара>',
        'PROPERTY_CODE' => ['CML2_ARTICLE', 'MORE_PHOTO'],
        'OFFERS_PROPERTY_CODE' => ['COLOR_REF', 'SIZE_REF'],
        'OFFER_TREE_PROPS' => ['COLOR_REF', 'SIZE_REF'],
        'PRICE_CODE' => ['BASE'],
        'USE_PRICE_COUNT' => 'Y',
        'CACHE_TYPE' => 'N',
    ]
); ?>

<?php require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php'; ?>
```

## Структура проекта

```
Sofuto_bitrix/
├── docker/
│   ├── Dockerfile              # PHP 8.1-apache + extensions
│   ├── setup.sh                # downloads bitrixsetup.php
│   └── db/                     # DB init scripts (auto-restore)
├── docker-compose.yml          # web + MySQL
├── local/
│   ├── ajax/
│   │   └── add_to_cart.php     # AJAX endpoint
│   ├── seed/
│   │   └── seed.php            # test data seeder
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
