<?php

/**
 * Seeder for test products.
 *
 * Usage:
 *   Open in browser: http://localhost:8080/local/seed/seed.php?products_iblock=X&offers_iblock=Y
 *   where X and Y are iblock IDs from Bitrix admin (Контент → Инфоблоки → Типы).
 *
 * Preconditions:
 *   1. Bitrix installed with catalog module
 *   2. Two iblocks created: products (with catalog flag) and offers
 *   3. Offers iblock has properties CML2_LINK (element), COLOR_REF (list), SIZE_REF (list)
 *   4. COLOR_REF has enum values: Красный, Синий, Зелёный
 *   5. SIZE_REF has enum values: 92, 98, 104, 110
 */

define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', 'Y');
define('NO_AGENT_CHECK', true);
define('DisableEventsCheck', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    die('iblock and catalog modules required');
}

global $USER;
if (!$USER->IsAdmin()) {
    die('Admin access required');
}

$productsIblockId = (int) ($_GET['products_iblock'] ?? 0);
$offersIblockId = (int) ($_GET['offers_iblock'] ?? 0);

if (!$productsIblockId || !$offersIblockId) {
    die('Usage: seed.php?products_iblock=N&offers_iblock=M');
}

/**
 * Load property enum values by property code.
 *
 * @return array<string,int> name => enum ID
 */
function loadEnumMap(int $iblockId, string $propertyCode): array
{
    $map = [];
    $prop = CIBlockProperty::GetByID($propertyCode, $iblockId)->Fetch();
    if (!$prop) {
        return $map;
    }

    $enums = CIBlockPropertyEnum::GetList(
        ['SORT' => 'ASC'],
        ['IBLOCK_ID' => $iblockId, 'PROPERTY_ID' => $prop['ID']]
    );
    while ($row = $enums->Fetch()) {
        $map[$row['VALUE']] = (int) $row['ID'];
    }

    return $map;
}

function createProduct(int $iblockId, string $name, string $article, float $price): int
{
    $el = new CIBlockElement();
    $id = $el->Add([
        'IBLOCK_ID' => $iblockId,
        'NAME' => $name,
        'ACTIVE' => 'Y',
        'PROPERTY_VALUES' => [
            'CML2_ARTICLE' => $article,
        ],
    ]);

    if (!$id) {
        throw new RuntimeException("Failed to create product $name: " . $el->LAST_ERROR);
    }

    CCatalogProduct::Add([
        'ID' => $id,
        'QUANTITY' => 0,
        'QUANTITY_TRACE' => 'N',
    ]);

    CPrice::Add([
        'PRODUCT_ID' => $id,
        'CATALOG_GROUP_ID' => 1,
        'PRICE' => $price,
        'CURRENCY' => 'RUB',
    ]);

    return (int) $id;
}

function createOffer(
    int $offersIblockId,
    int $productId,
    string $name,
    int $colorEnumId,
    int $sizeEnumId,
    float $price,
    int $quantity
): int {
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

    if (!$id) {
        throw new RuntimeException("Failed to create offer $name: " . $el->LAST_ERROR);
    }

    CCatalogProduct::Add([
        'ID' => $id,
        'QUANTITY' => $quantity,
        'QUANTITY_TRACE' => 'Y',
    ]);

    CPrice::Add([
        'PRODUCT_ID' => $id,
        'CATALOG_GROUP_ID' => 1,
        'PRICE' => $price,
        'CURRENCY' => 'RUB',
    ]);

    return (int) $id;
}

$colorMap = loadEnumMap($offersIblockId, 'COLOR_REF');
$sizeMap = loadEnumMap($offersIblockId, 'SIZE_REF');

if (empty($colorMap) || empty($sizeMap)) {
    die('COLOR_REF or SIZE_REF enum values not found. Create them in iblock properties first.');
}

header('Content-Type: text/plain; charset=utf-8');

try {
    // Product 1: футболка 3 colors x 3 sizes
    $p1 = createProduct($productsIblockId, 'Футболка Мишка', 'KIDS-001', 1200);
    echo "Created product 1: ID=$p1\n";

    foreach (['Красный', 'Синий', 'Зелёный'] as $color) {
        if (!isset($colorMap[$color])) {
            echo "  Color $color not in enum, skipping\n";
            continue;
        }
        foreach (['92', '98', '104'] as $size) {
            if (!isset($sizeMap[$size])) {
                continue;
            }
            // Make one combination out of stock for demo
            $qty = ($color === 'Зелёный' && $size === '104') ? 0 : random_int(3, 10);
            $offerId = createOffer(
                $offersIblockId,
                $p1,
                "Футболка Мишка $color $size",
                $colorMap[$color],
                $sizeMap[$size],
                1200,
                $qty
            );
            echo "  Offer $color/$size: ID=$offerId, qty=$qty\n";
        }
    }

    // Product 2: платье 2 colors x 4 sizes
    $p2 = createProduct($productsIblockId, 'Платье Ромашка', 'KIDS-002', 2500);
    echo "Created product 2: ID=$p2\n";

    foreach (['Красный', 'Синий'] as $color) {
        if (!isset($colorMap[$color])) {
            continue;
        }
        foreach (['92', '98', '104', '110'] as $size) {
            if (!isset($sizeMap[$size])) {
                continue;
            }
            $qty = random_int(0, 8);
            $offerId = createOffer(
                $offersIblockId,
                $p2,
                "Платье Ромашка $color $size",
                $colorMap[$color],
                $sizeMap[$size],
                2500,
                $qty
            );
            echo "  Offer $color/$size: ID=$offerId, qty=$qty\n";
        }
    }

    echo "\nDone!\n";
    echo "Product 1 (футболка): http://localhost:8080/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=$productsIblockId&ID=$p1\n";
    echo "Product 2 (платье): http://localhost:8080/bitrix/admin/iblock_element_edit.php?IBLOCK_ID=$productsIblockId&ID=$p2\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
