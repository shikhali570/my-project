<?php
/** کارت کالا – متغیر ورودی: $p (ردیف کالا) */
$badge = stock_badge($p);
$isFav = is_favorite($p['id']);
$inStock = (int)$p['stock'] > 0;
$hasDiscount = !empty($p['old_price']) && $p['old_price'] > $p['price'];
?>
<div class="pro-card <?= (int)$p['is_active'] ? '' : 'inactive' ?>">
  <div class="pro-media">
    <a class="pro-thumb" href="<?= product_url($p['id']) ?>" tabindex="-1" aria-hidden="true">
      <?php $imgSrc = product_image_src($p['image']); ?>
      <?php if ($imgSrc !== ''): ?>
        <img src="<?= e($imgSrc) ?>" alt="" loading="lazy" width="300" height="150">
      <?php else: ?>
        <span class="pro-emoji"><?= e($p['icon']) ?></span>
      <?php endif; ?>
    </a>
    <?php if ($hasDiscount): ?>
      <span class="pro-off">٪<?= fa_num(round((1 - $p['price'] / $p['old_price']) * 100)) ?> تخفیف</span>
    <?php endif; ?>
    <?php if (!(int)$p['is_active']): ?><span class="pro-off gray">غیرفعال</span><?php endif; ?>
    <?php if (is_logged_in() && !is_admin()): ?>
      <form method="POST" action="index.php" class="pro-fav">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="favorite_toggle">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
        <button class="btn btn-icon <?= $isFav ? 'active' : '' ?>" type="submit" data-name="<?= e($p['name']) ?>"
                aria-pressed="<?= $isFav ? 'true' : 'false' ?>"
                aria-label="<?= $isFav ? 'حذف از علاقه‌مندی‌ها: ' : 'افزودن به علاقه‌مندی‌ها: ' ?><?= e($p['name']) ?>"
                title="<?= $isFav ? 'حذف از علاقه‌مندی‌ها' : 'افزودن به علاقه‌مندی‌ها' ?>"><?= $isFav ? '★' : '☆' ?></button>
      </form>
    <?php endif; ?>
  </div>

  <div class="pro-body">
    <a class="pro-brand" href="index.php?page=home&cat=<?= e($p['category']) ?>"><?= e($p['brand']) ?> • <?= e(category_title($p['category'])) ?></a>
    <h3 class="pro-name"><a href="<?= product_url($p['id']) ?>"><?= e($p['name']) ?></a></h3>
    <div class="pro-meta">
      <span class="stock <?= $badge['class'] ?>"><?= e($badge['text']) ?></span>
      <span class="pro-taxcode" title="شناسه کالای سامانه مؤدیان">شناسه مالیاتی: <?= e($p['tax_id']) ?></span>
    </div>

    <div class="pro-price">
      <?php if ($hasDiscount): ?>
        <del><?= fa_num($p['old_price']) ?></del>
      <?php endif; ?>
      <span><?= money($p['price']) ?></span>
      <small>بدون ارزش افزوده · هر <?= e($p['unit']) ?></small>
    </div>

    <div class="pro-actions">
      <?php if ($inStock): ?>
        <form method="POST" action="index.php" class="pro-add">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add_cart">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
          <label class="visually-hidden" for="qty-<?= (int)$p['id'] ?>">تعداد <?= e($p['name']) ?></label>
          <input type="number" id="qty-<?= (int)$p['id'] ?>" class="qty-input" name="qty" value="1" min="1" max="<?= (int)$p['stock'] ?>" inputmode="numeric">
          <button class="btn btn-primary btn-sm grow" type="submit">افزودن به سبد</button>
        </form>
      <?php else: ?>
        <a class="btn btn-outline btn-sm grow" href="<?= e(rfq_prefill_url($p)) ?>">استعلام قیمت این کالا</a>
      <?php endif; ?>
    </div>
  </div>
</div>
