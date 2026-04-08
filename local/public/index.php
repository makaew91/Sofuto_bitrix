<?php
/**
 * Главная страница — список товаров + карточка через ?eid=.
 *
 * Этот файл должен быть скопирован в корень Bitrix (/var/www/html/index.php)
 * поверх стандартного, чтобы заменить демо-главную на список наших товаров.
 * Восстанавливается автоматически скриптом docker/restore.sh.
 */

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/header.php';
$APPLICATION->SetTitle('Детская одежда');

$elementId = (int) ($_GET["eid"] ?? 0);

if ($elementId > 0) {
    $APPLICATION->IncludeComponent(
        'bitrix:catalog.element',
        'product_card',
        [
            'IBLOCK_TYPE' => 'kids',
            'IBLOCK_ID' => '4',
            'ELEMENT_ID' => $elementId,
            'SET_TITLE' => 'Y',
            'PROPERTY_CODE' => ['CML2_ARTICLE', 'MORE_PHOTO'],
            'OFFERS_PROPERTY_CODE' => ['COLOR_REF', 'SIZE_REF'],
            'OFFERS_FIELD_CODE' => ['NAME'],
            'PRICE_CODE' => ['BASE'],
            'USE_PRICE_COUNT' => 'Y',
            'CACHE_TYPE' => 'N',
        ]
    );
} else {
    ?>
    <style>
        .products-list { display: flex; flex-wrap: wrap; gap: 20px; padding: 20px; max-width: 900px; margin: 0 auto; font-family: -apple-system, sans-serif; }
        .products-list__item { flex: 1 1 260px; background: #fff; border: 1px solid #eee; border-radius: 12px; overflow: hidden; text-decoration: none; color: #1a1a1a; transition: transform .15s, box-shadow .15s; }
        .products-list__item:hover { transform: translateY(-4px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        .products-list__img { width: 100%; aspect-ratio: 1; object-fit: cover; background: #f5f5f5; }
        .products-list__info { padding: 16px; }
        .products-list__name { font-size: 16px; font-weight: 600; margin: 0 0 6px; }
        .products-list__price { font-size: 18px; font-weight: 700; }
        .products-list__title { font-size: 24px; font-weight: 700; max-width: 900px; margin: 30px auto 0; padding: 0 20px; font-family: -apple-system, sans-serif; }
    </style>
    <h1 class="products-list__title">Товары</h1>
    <div class="products-list">
        <?php
        \Bitrix\Main\Loader::includeModule('iblock');
        $res = CIBlockElement::GetList([], ['IBLOCK_ID' => 4, 'ACTIVE' => 'Y'], false, false, ['ID', 'NAME', 'DETAIL_PICTURE']);
        while ($el = $res->Fetch()) {
            $img = $el['DETAIL_PICTURE'] ? CFile::GetPath($el['DETAIL_PICTURE']) : '';
            $price = CPrice::GetBasePrice($el['ID']);
            $priceTxt = $price ? number_format($price['PRICE'], 0, '.', ' ') . ' ₽' : '—';
            ?>
            <a class="products-list__item" href="/?eid=<?= (int) $el['ID'] ?>">
                <?php if ($img): ?>
                    <img class="products-list__img" src="<?= htmlspecialcharsbx($img) ?>" alt="">
                <?php else: ?>
                    <div class="products-list__img"></div>
                <?php endif; ?>
                <div class="products-list__info">
                    <div class="products-list__name"><?= htmlspecialcharsbx($el['NAME']) ?></div>
                    <div class="products-list__price">от <?= $priceTxt ?></div>
                </div>
            </a>
            <?php
        }
        ?>
    </div>
    <?php
}

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/footer.php';
