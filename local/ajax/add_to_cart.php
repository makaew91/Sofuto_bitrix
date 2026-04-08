<?php

define('NO_KEEP_STATISTIC', true);
define('NO_AGENT_CHECK', true);
define('NOT_CHECK_PERMISSIONS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

use Bitrix\Main\Loader;
use Bitrix\Sale\Basket;
use Bitrix\Sale\Fuser;

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Method not allowed');
    }

    if (!check_bitrix_sessid()) {
        throw new RuntimeException('Invalid session');
    }

    if (!Loader::includeModule('sale') || !Loader::includeModule('catalog')) {
        throw new RuntimeException('Required modules not installed');
    }

    $offerId = (int) ($_POST['offer_id'] ?? 0);
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

    if ($offerId <= 0) {
        throw new RuntimeException('Invalid offer_id');
    }

    $basket = Basket::loadItemsForFUser(Fuser::getId(), SITE_ID);
    $item = $basket->getExistsItem('catalog', $offerId);

    if ($item) {
        $item->setField('QUANTITY', $item->getQuantity() + $quantity);
    } else {
        $item = $basket->createItem('catalog', $offerId);
        $result = $item->setFields([
            'QUANTITY' => $quantity,
            'CURRENCY' => 'RUB',
            'LID' => SITE_ID,
            'PRODUCT_PROVIDER_CLASS' => \CCatalogProductProvider::class,
        ]);

        if (!$result->isSuccess()) {
            throw new RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    $saveResult = $basket->save();
    if (!$saveResult->isSuccess()) {
        throw new RuntimeException(implode('; ', $saveResult->getErrorMessages()));
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
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_after.php';
