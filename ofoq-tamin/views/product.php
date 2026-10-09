<?php
/** صفحه جزئیات کالا */
$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p || (!(int)$p['is_active'] && !is_admin())) {
    echo '<div class="empty-state"><span aria-hidden="true">🔎</span><h3>کالای مورد نظر یافت نشد</h3><p>ممکن است این کالا از کاتالوگ حذف یا غیرفعال شده باشد.</p><a class="btn btn-primary" href="index.php?page=home">بازگشت به کاتالوگ</a></div>';
    return;
}

$db->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$id]);
$badge = stock_badge($p);
$isFav = is_favorite($p['id']);
$inStock = (int)$p['stock'] > 0;
$hasDiscount = !empty($p['old_price']) && $p['old_price'] > $p['price'];
$specLines = array_filter(array_map('trim', explode('|', (string)$p['specs'])));
$vatPercent = fa_num((float)settings('vat_rate', 10), 0);

$related = $db->prepare('SELECT * FROM products WHERE category = ? AND id != ? AND is_active = 1 ORDER BY sold DESC LIMIT 3');
$related->execute([$p['category'], $id]);
$relatedRows = $related->fetchAll();
?>

<nav class="crumbs" aria-label="مسیر صفحه">
  <a href="index.php?page=home">فروشگاه</a> <span aria-hidden="true">/</span>
  <a href="index.php?page=home&cat=<?= e($p['category']) ?>"><?= e(category_title($p['category'])) ?></a> <span aria-hidden="true">/</span>
  <span aria-current="page"><?= e($p['name']) ?></span>
</nav>

<div class="product-layout">
  <div class="product-visual">
    <div class="product-image">
      <?php $imgSrc = product_image_src($p['image']); ?>
      <?php if ($imgSrc !== ''): ?>
        <img src="<?= e($imgSrc) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <span aria-hidden="true"><?= e($p['icon']) ?></span>
      <?php endif; ?>
    </div>
    <div class="tax-box">
      <div>
        <span>شناسه کالای سامانه مؤدیان</span>
        <code><?= e($p['tax_id']) ?></code>
      </div>
      <div>
        <span>کد کالا (SKU)</span>
        <code><?= e($p['sku'] ?: '—') ?></code>
      </div>
    </div>
  </div>

  <div class="product-info">
    <?php if (!(int)$p['is_active']): ?>
      <div class="alert info">این کالا در کاتالوگ عمومی نمایش داده نمی‌شود؛ فقط مدیران آن را می‌بینند.</div>
    <?php endif; ?>

    <div class="product-top">
      <span class="pill info"><?= e($p['brand']) ?></span>
      <span class="pill muted"><?= e(category_title($p['category'])) ?></span>
      <span class="stock <?= $badge['class'] ?>"><?= e($badge['text']) ?></span>
    </div>
    <h1 class="product-title"><?= e($p['name']) ?></h1>
    <p class="product-sold">فروش تاکنون: <strong><?= fa_num($p['sold']) ?> <?= e($p['unit']) ?></strong></p>
    <p class="product-desc"><?= nl2br(e($p['description'] ?: 'توضیحات تکمیلی این کالا توسط واحد فنی در حال تکمیل است.')) ?></p>

    <div class="price-panel">
      <div>
        <span class="price-label">قیمت واحد (بدون ارزش افزوده)</span>
        <div class="price-value">
          <?php if ($hasDiscount): ?>
            <del><?= fa_num($p['old_price']) ?></del>
          <?php endif; ?>
          <?= money($p['price']) ?>
        </div>
        <small class="price-vat">ارزش افزوده <?= $vatPercent ?>٪ جداگانه در صورتحساب رسمی اضافه می‌شود.</small>
      </div>
      <div class="price-side">
        <div>موجودی انبار: <strong><?= fa_num($p['stock']) ?> <?= e($p['unit']) ?></strong></div>
        <div>واحد فروش: <strong><?= e($p['unit']) ?></strong></div>
        <div>تضمین اصالت: <strong>✅ کالای اورجینال</strong></div>
      </div>
    </div>

    <?php if ($inStock): ?>
      <form class="add-to-cart" method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_cart">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <input type="hidden" name="redirect" value="index.php?page=cart">
        <div class="qty-stepper">
          <label for="qtyInput">تعداد (حداکثر <?= fa_num($p['stock']) ?> <?= e($p['unit']) ?>)</label>
          <div class="stepper">
            <button type="button" data-step="-1" aria-label="کاهش تعداد">−</button>
            <input type="number" name="qty" id="qtyInput" value="1" min="1" max="<?= (int)$p['stock'] ?>" inputmode="numeric">
            <button type="button" data-step="1" aria-label="افزایش تعداد">+</button>
          </div>
        </div>
        <button class="btn btn-primary btn-lg grow" type="submit">🛒 افزودن به سبد سفارش</button>
      </form>
    <?php else: ?>
      <div class="alert warn">
        <strong>این کالا فعلاً ناموجود است.</strong>
        می‌توانید برای آن استعلام قیمت و زمان تأمین ثبت کنید.
        <a class="btn btn-orange btn-sm" href="<?= e(rfq_prefill_url($p)) ?>">ثبت استعلام برای این کالا</a>
      </div>
    <?php endif; ?>

    <div class="product-secondary">
      <?php if (is_logged_in() && !is_admin()): ?>
        <form class="fav-form" method="POST" action="index.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="favorite_toggle">
          <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
          <input type="hidden" name="redirect" value="<?= e(current_url()) ?>">
          <button class="btn btn-secondary" type="submit" aria-pressed="<?= $isFav ? 'true' : 'false' ?>">
            <?= $isFav ? '★ در علاقه‌مندی‌ها' : '☆ افزودن به علاقه‌مندی‌ها' ?>
          </button>
        </form>
      <?php endif; ?>
      <p class="b2b-cta">خرید عمده یا برای یک پروژه؟ <a href="<?= e(rfq_prefill_url($p)) ?>">برای این کالا استعلام قیمت بگیرید</a></p>
    </div>

    <div class="assurance-row">
      <div><span aria-hidden="true">🧾</span> صورتحساب الکترونیکی</div>
      <div><span aria-hidden="true">🚚</span> ارسال به سراسر کشور</div>
      <div><span aria-hidden="true">🛠️</span> گارانتی و خدمات پس از فروش</div>
      <div><span aria-hidden="true">↩️</span> بازگشت کالا تا ۷ روز</div>
    </div>
  </div>
</div>

<?php if ($specLines): ?>
  <section class="sec-block">
    <div class="sec-head"><h2 class="sec-title">مشخصات فنی</h2></div>
    <div class="spec-grid">
      <?php foreach ($specLines as $line):
          $parts = explode(':', $line, 2); ?>
        <div class="spec-row">
          <span><?= e(trim($parts[0])) ?></span>
          <strong><?= e(trim($parts[1] ?? '—')) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php if ($relatedRows): ?>
  <section class="sec-block">
    <div class="sec-head"><h2 class="sec-title">کالاهای مرتبط</h2></div>
    <div class="pro-grid pro-grid--related">
      <?php foreach ($relatedRows as $p) {
          require APP_ROOT . '/views/partials/product_card.php';
      } ?>
    </div>
  </section>
<?php endif; ?>
