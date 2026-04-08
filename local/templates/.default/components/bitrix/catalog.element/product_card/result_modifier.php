<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */

use Bitrix\Main\Loader;

Loader::includeModule('iblock');
Loader::includeModule('catalog');

// Build SKU matrix: { colorId => { sizeId => offerData } }
$matrix = [];
$colors = [];
$sizes = [];

// Fallback HEX map — admin should configure file/reference HEX; this keeps demo simple
$hexMap = [
    'Красный' => '#E74C3C',
    'Синий' => '#3498DB',
    'Зелёный' => '#27AE60',
    'Жёлтый' => '#F1C40F',
    'Чёрный' => '#1a1a1a',
    'Белый' => '#F5F5F5',
];

foreach ($arResult['OFFERS'] ?? [] as $offer) {
    $colorId = (int) ($offer['PROPERTIES']['COLOR_REF']['VALUE'] ?? 0);
    $sizeId = (int) ($offer['PROPERTIES']['SIZE_REF']['VALUE'] ?? 0);

    if (!$colorId || !$sizeId) {
        continue;
    }

    $colorName = $offer['DISPLAY_PROPERTIES']['COLOR_REF']['DISPLAY_VALUE']
        ?? $offer['PROPERTIES']['COLOR_REF']['VALUE']
        ?? '';
    $sizeName = $offer['DISPLAY_PROPERTIES']['SIZE_REF']['DISPLAY_VALUE']
        ?? $offer['PROPERTIES']['SIZE_REF']['VALUE']
        ?? '';

    $price = (float) ($offer['PRICE']['DISCOUNT_VALUE'] ?? $offer['PRICE']['VALUE'] ?? 0);
    $oldPrice = (float) ($offer['PRICE']['VALUE'] ?? 0);
    $quantity = (int) ($offer['CATALOG_QUANTITY'] ?? 0);

    $matrix[$colorId][$sizeId] = [
        'offer_id' => (int) $offer['ID'],
        'price' => $price,
        'price_formatted' => number_format($price, 0, '.', ' ') . ' ₽',
        'old_price' => $oldPrice > $price ? $oldPrice : null,
        'old_price_formatted' => $oldPrice > $price
            ? number_format($oldPrice, 0, '.', ' ') . ' ₽'
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
