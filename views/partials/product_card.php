<?php
/** کارت کالا – متغیر ورودی: $p (ردیف کالا) و اختیاری $compact */
$badge = stock_badge($p);
$isFav = is_favorite($p['id']);
?>
<div class="pro-card <?= (int)$p['is_active'] ? '' : 'inactive' ?>">
  <a class="pro-thumb" href="<?= product_url($p['id']) ?>">
    <?php if (!empty($p['image'])): ?>
      <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <?php else: ?>
      <span class="pro-emoji"><?= e($p['icon']) ?></span>
    <?php endif; ?>
    <?php if (!empty($p['old_price']) && $p['old_price'] > $p['price']): ?>
      <span class="pro-off">٪<?= fa_num(round((1 - $p['price'] / $p['old_price']) * 100)) ?> تخفیف</span>
    <?php endif; ?>
    <?php if (!(int)$p['is_active']): ?><span class="pro-off gray">غیرفعال</span><?php endif; ?>
  </a>
  <div class="pro-body">
    <a class="pro-brand" href="index.php?page=home&cat=<?= e($p['category']) ?>"><?= e($p['brand']) ?> • <?= e(category_title($p['category'])) ?></a>
    <h4 class="pro-name"><a href="<?= product_url($p['id']) ?>"><?= e($p['name']) ?></a></h4>
    <div class="pro-meta">
      <span class="stock <?= $badge['class'] ?>"><?= e($badge['text']) ?></span>
      <span class="pill muted"><?= e($p['unit']) ?></span>
    </div>
    <span class="pro-taxcode" title="شناسه کالای سامانه مؤدیان">شناسه: <?= e($p['tax_id']) ?></span>
    <div class="pro-bottom">
      <div class="pro-price">
        <?php if (!empty($p['old_price']) && $p['old_price'] > $p['price']): ?>
          <del><?= fa_num($p['old_price']) ?></del>
        <?php endif; ?>
        <?= money($p['price']) ?>
      </div>
    </div>
    <div class="pro-actions">
      <form method="POST" action="index.php" class="inline-form grow">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_cart">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
        <input type="number" class="qty-input" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>" aria-label="تعداد">
        <button class="btn btn-primary btn-sm grow" type="submit" <?= (int)$p['stock'] <= 0 ? 'disabled' : '' ?>>افزودن به سبد</button>
      </form>
      <?php if (is_logged_in() && !is_admin()): ?>
        <form method="POST" action="index.php" class="inline-form">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="favorite_toggle">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
          <button class="btn btn-icon <?= $isFav ? 'active' : '' ?>" type="submit" title="علاقه‌مندی"><?= $isFav ? '★' : '☆' ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
