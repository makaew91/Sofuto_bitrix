<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

/** @var array $arResult */
/** @var string $templateFolder */
/** @var CBitrixComponentTemplate $this */

$this->addExternalCss($templateFolder . '/style.css');
$this->addExternalJs($templateFolder . '/script.js');

$colors = $arResult['SKU_COLORS'];
$sizes = $arResult['SKU_SIZES'];
$matrix = $arResult['SKU_MATRIX'];
$defaultColor = $arResult['DEFAULT_COLOR'];
$defaultSize = $arResult['DEFAULT_SIZE'];

// Collect gallery images: detail picture + MORE_PHOTO
$gallery = [];
if (!empty($arResult['DETAIL_PICTURE']['SRC'])) {
    $gallery[] = $arResult['DETAIL_PICTURE']['SRC'];
}
$morePhoto = $arResult['PROPERTIES']['MORE_PHOTO']['VALUE'] ?? [];
if (is_array($morePhoto)) {
    foreach ($morePhoto as $fileId) {
        $src = CFile::GetPath($fileId);
        if ($src) {
            $gallery[] = $src;
        }
    }
}
if (empty($gallery)) {
    $gallery[] = $templateFolder . '/images/placeholder.svg';
}
?>

<div class="product-card" data-product-id="<?= (int) $arResult['ID'] ?>">
    <div class="product-card__gallery">
        <div class="product-card__slides">
            <?php foreach ($gallery as $i => $src): ?>
                <img
                    class="product-card__slide <?= $i === 0 ? 'is-active' : '' ?>"
                    src="<?= htmlspecialcharsbx($src) ?>"
                    alt="<?= htmlspecialcharsbx($arResult['NAME']) ?>"
                    loading="<?= $i === 0 ? 'eager' : 'lazy' ?>"
                >
            <?php endforeach; ?>
        </div>
        <?php if (count($gallery) > 1): ?>
            <div class="product-card__dots">
                <?php foreach ($gallery as $i => $_): ?>
                    <button
                        class="product-card__dot <?= $i === 0 ? 'is-active' : '' ?>"
                        data-slide="<?= $i ?>"
                        aria-label="Слайд <?= $i + 1 ?>"
                        type="button"
                    ></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <h1 class="product-card__title"><?= htmlspecialcharsbx($arResult['NAME']) ?></h1>

    <?php if (!empty($arResult['PROPERTIES']['CML2_ARTICLE']['VALUE'])): ?>
        <div class="product-card__article">
            Арт. <?= htmlspecialcharsbx($arResult['PROPERTIES']['CML2_ARTICLE']['VALUE']) ?>
        </div>
    <?php endif; ?>

    <div class="product-card__price-row">
        <span class="product-card__price" data-price></span>
        <span class="product-card__old-price" data-old-price></span>
    </div>

    <div class="product-card__availability" data-availability></div>

    <?php if (!empty($colors)): ?>
        <div class="product-card__sku-group">
            <div class="product-card__sku-label">
                Цвет: <span class="product-card__sku-selected" data-selected-color></span>
            </div>
            <div class="product-card__colors">
                <?php foreach ($colors as $color): ?>
                    <button
                        type="button"
                        class="product-card__color"
                        data-color-id="<?= (int) $color['id'] ?>"
                        data-color-name="<?= htmlspecialcharsbx($color['name']) ?>"
                        style="background-color: <?= htmlspecialcharsbx($color['hex']) ?>"
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
                        type="button"
                        class="product-card__size"
                        data-size-id="<?= (int) $size['id'] ?>"
                    ><?= htmlspecialcharsbx($size['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <button type="button" class="product-card__buy" data-buy-btn>
        <span class="product-card__buy-label">В корзину</span>
    </button>
</div>

<div class="product-card__sticky" data-sticky hidden>
    <img
        class="product-card__sticky-img"
        src="<?= htmlspecialcharsbx($gallery[0]) ?>"
        alt=""
        loading="lazy"
    >
    <div class="product-card__sticky-info">
        <div class="product-card__sticky-name"><?= htmlspecialcharsbx($arResult['NAME']) ?></div>
        <div class="product-card__sticky-price" data-sticky-price></div>
    </div>
    <button type="button" class="product-card__sticky-buy" data-buy-btn>
        <span class="product-card__buy-label">В корзину</span>
    </button>
</div>

<script type="application/json" data-sku-matrix><?= json_encode([
    'matrix' => $matrix,
    'colors' => $colors,
    'sizes' => $sizes,
    'defaultColor' => $defaultColor,
    'defaultSize' => $defaultSize,
    'sessid' => bitrix_sessid(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
