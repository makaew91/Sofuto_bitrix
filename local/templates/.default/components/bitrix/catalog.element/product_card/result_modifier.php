<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Fallback HEX map — admin should configure file/reference HEX; this keeps demo simple
$hexMap = [
    'Красный' => '#E74C3C',
    'Синий' => '#3498DB',
    'Зелёный' => '#27AE60',
    'Жёлтый' => '#F1C40F',
    'Чёрный' => '#1a1a1a',
    'Белый' => '#F5F5F5',
];

// Build SKU matrix: { colorId => { sizeId => offerData } }
$matrix = [];
$colors = [];
$sizes = [];

$offerIds = [];
foreach ($arResult['OFFERS'] ?? [] as $offer) {
    $offerIds[] = (int) $offer['ID'];
}

// Load COLOR_REF and SIZE_REF properties directly from iblock for each offer.
// GetPropertyValues() returns data keyed by property ID, so resolve codes → IDs first.
$propsByOffer = [];
$colorPropId = 0;
$sizePropId = 0;
if (!empty($offerIds)) {
    $offersIblockId = (int) ($arResult['OFFERS'][0]['IBLOCK_ID'] ?? 0);

    $colorProp = CIBlockProperty::GetByID('COLOR_REF', $offersIblockId)->Fetch();
    $sizeProp = CIBlockProperty::GetByID('SIZE_REF', $offersIblockId)->Fetch();
    $colorPropId = (int) ($colorProp['ID'] ?? 0);
    $sizePropId = (int) ($sizeProp['ID'] ?? 0);

    $propsResult = CIBlockElement::GetPropertyValues(
        $offersIblockId,
        ['ID' => $offerIds],
        false,
        ['ID' => [$colorPropId, $sizePropId]]
    );
    while ($row = $propsResult->Fetch()) {
        $propsByOffer[(int) $row['IBLOCK_ELEMENT_ID']] = $row;
    }
}

foreach ($arResult['OFFERS'] ?? [] as $offer) {
    $offerId = (int) $offer['ID'];
    $props = $propsByOffer[$offerId] ?? [];

    $colorId = (int) ($props[$colorPropId] ?? 0);
    $sizeId = (int) ($props[$sizePropId] ?? 0);

    if (!$colorId || !$sizeId) {
        continue;
    }

    // Resolve enum values → readable names (GetByID returns array directly)
    $colorEnum = CIBlockPropertyEnum::GetByID($colorId);
    $sizeEnum = CIBlockPropertyEnum::GetByID($sizeId);
    $colorName = is_array($colorEnum) ? ($colorEnum['VALUE'] ?? '') : '';
    $sizeName = is_array($sizeEnum) ? ($sizeEnum['VALUE'] ?? '') : '';

    // Price: catalog.element puts it in ITEM_PRICES[0]
    $priceData = $offer['ITEM_PRICES'][0] ?? [];
    $price = (float) ($priceData['PRICE'] ?? 0);
    $basePrice = (float) ($priceData['BASE_PRICE'] ?? $price);

    $quantity = (int) ($offer['CATALOG_QUANTITY'] ?? 0);

    $matrix[$colorId][$sizeId] = [
        'offer_id' => $offerId,
        'price' => $price,
        'price_formatted' => number_format($price, 0, '.', ' ') . ' ₽',
        'old_price' => $basePrice > $price ? $basePrice : null,
        'old_price_formatted' => $basePrice > $price
            ? number_format($basePrice, 0, '.', ' ') . ' ₽'
            : null,
        'quantity' => $quantity,
        'available' => $quantity > 0,
        'image' => !empty($offer['DETAIL_PICTURE']['SRC']) ? $offer['DETAIL_PICTURE']['SRC'] : null,
    ];

    if (!isset($colors[$colorId])) {
        $colors[$colorId] = [
            'id' => $colorId,
            'name' => $colorName,
            'hex' => $hexMap[$colorName] ?? '#CCCCCC',
        ];
    }
    if (!isset($sizes[$sizeId])) {
        $sizes[$sizeId] = [
            'id' => $sizeId,
            'name' => $sizeName,
        ];
    }
}

// Sort sizes numerically when possible
uasort($sizes, static fn($a, $b) => (int) $a['name'] <=> (int) $b['name']);

// Pick default selection: first available combination
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
    $defaultSize = $defaultColor !== null ? array_key_first($matrix[$defaultColor]) : null;
}

$arResult['SKU_MATRIX'] = $matrix;
$arResult['SKU_COLORS'] = array_values($colors);
$arResult['SKU_SIZES'] = array_values($sizes);
$arResult['DEFAULT_COLOR'] = $defaultColor;
$arResult['DEFAULT_SIZE'] = $defaultSize;
