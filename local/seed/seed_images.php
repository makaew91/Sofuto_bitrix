<?php

/**
 * Attach placeholder images to existing SKU offers grouped by color.
 *
 * Usage:
 *   http://localhost:8080/local/seed/seed_images.php?offers_iblock=6
 *
 * Downloads a unique placeholder image for each (product, color) combination
 * from picsum.photos and sets it as DETAIL_PICTURE for all SKUs of that color.
 */

define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', 'Y');
define('NO_AGENT_CHECK', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;

if (!Loader::includeModule('iblock') || !Loader::includeModule('catalog')) {
    die('iblock/catalog required');
}

global $USER;
if (!$USER->IsAdmin()) {
    die('Admin access required');
}

$offersIblockId = (int) ($_GET['offers_iblock'] ?? 0);
if (!$offersIblockId) {
    die('Usage: seed_images.php?offers_iblock=N');
}

header('Content-Type: text/plain; charset=utf-8');

$colorProp = CIBlockProperty::GetByID('COLOR_REF', $offersIblockId)->Fetch();
$linkProp = CIBlockProperty::GetByID('CML2_LINK', $offersIblockId)->Fetch();
if (!$colorProp || !$linkProp) {
    die('Properties COLOR_REF or CML2_LINK not found');
}

$colorPropId = (int) $colorProp['ID'];
$linkPropId = (int) $linkProp['ID'];

// Get all offers
$allOffers = [];
$res = CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $offersIblockId],
    false,
    false,
    ['ID', 'NAME', 'IBLOCK_ID']
);
while ($row = $res->Fetch()) {
    $allOffers[(int) $row['ID']] = $row;
}

if (empty($allOffers)) {
    die('No offers found');
}

// Load properties for all offers
$offerIds = array_keys($allOffers);
$propsRes = CIBlockElement::GetPropertyValues(
    $offersIblockId,
    ['ID' => $offerIds],
    false,
    ['ID' => [$colorPropId, $linkPropId]]
);

$groups = []; // [productId][colorId] => [offerIds]
while ($row = $propsRes->Fetch()) {
    $offerId = (int) $row['IBLOCK_ELEMENT_ID'];
    $colorId = (int) ($row[$colorPropId] ?? 0);
    $productId = (int) ($row[$linkPropId] ?? 0);
    if ($colorId && $productId) {
        $groups[$productId][$colorId][] = $offerId;
    }
}

$seed = 100;
foreach ($groups as $productId => $byColor) {
    echo "Product $productId:\n";
    foreach ($byColor as $colorId => $offerIds) {
        $seed++;
        $url = "https://picsum.photos/seed/$seed/600/600";
        echo "  Color $colorId → downloading $url\n";

        $tmpPath = tempnam(sys_get_temp_dir(), 'bx_img_');
        $img = @file_get_contents($url);
        if ($img === false) {
            echo "    FAILED to download\n";
            continue;
        }
        file_put_contents($tmpPath, $img);

        foreach ($offerIds as $offerId) {
            $fileArr = CFile::MakeFileArray($tmpPath);
            $fileArr['MODULE_ID'] = 'iblock';
            $fileArr['name'] = "product_{$productId}_color_{$colorId}.jpg";

            $el = new CIBlockElement();
            $ok = $el->Update($offerId, ['DETAIL_PICTURE' => $fileArr]);
            echo "    Offer $offerId: " . ($ok ? "OK" : "FAIL: " . $el->LAST_ERROR) . "\n";
        }

        @unlink($tmpPath);
    }
}

// Also attach one image per product to the main product element
foreach ($groups as $productId => $_) {
    $seed++;
    $url = "https://picsum.photos/seed/$seed/800/800";
    echo "Main product $productId → $url\n";

    $tmpPath = tempnam(sys_get_temp_dir(), 'bx_img_');
    $img = @file_get_contents($url);
    if ($img !== false) {
        file_put_contents($tmpPath, $img);
        $fileArr = CFile::MakeFileArray($tmpPath);
        $fileArr['MODULE_ID'] = 'iblock';
        $fileArr['name'] = "product_{$productId}_main.jpg";

        // Get product iblock (SKU iblock's parent)
        $sku = CCatalogSku::GetInfoByOfferIBlock($productId > 0 ? $offersIblockId : 0);
        if ($sku && !empty($sku['PRODUCT_IBLOCK_ID'])) {
            $el = new CIBlockElement();
            $ok = $el->Update($productId, ['DETAIL_PICTURE' => $fileArr]);
            echo "  Main: " . ($ok ? "OK" : "FAIL: " . $el->LAST_ERROR) . "\n";
        }
        @unlink($tmpPath);
    }
}

echo "\nDone!\n";

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
