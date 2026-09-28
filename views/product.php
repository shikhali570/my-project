<?php
/** صفحه جزئیات کالا */
$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM products WHERE id = ?');
$stmt->execute([$id]);
$p = $stmt->fetch();

if (!$p || (!(int)$p['is_active'] && !is_admin())) {
    echo '<div class="empty-state"><span>🔎</span><h3>کالای مورد نظر یافت نشد</h3><p>ممکن است این کالا از کاتالوگ حذف یا غیرفعال شده باشد.</p><a class="btn btn-primary" href="index.php?page=home">بازگشت به کاتالوگ</a></div>';
    return;
}

$db->prepare('UPDATE products SET views = views + 1 WHERE id = ?')->execute([$id]);
$badge = stock_badge($p);
$isFav = is_favorite($p['id']);
$specLines = array_filter(array_map('trim', explode('|', (string)$p['specs'])));

$related = $db->prepare('SELECT * FROM products WHERE category = ? AND id != ? AND is_active = 1 ORDER BY sold DESC LIMIT 3');
$related->execute([$p['category'], $id]);
$relatedRows = $related->fetchAll();

$reviews = [
    ['name' => 'مهندس رضایی', 'company' => 'پیمانکاری سازه گستر', 'rate' => 5, 'text' => 'کیفیت کالا مطابق نمونه اعلامی بود و فاکتور رسمی همان روز صادر شد؛ برای ممیزی مالیاتی مشکلی نداشتیم.'],
    ['name' => 'واحد تدارکات', 'company' => 'شرکت عمرانی پارس', 'rate' => 4, 'text' => 'ارسال سریع و بسته‌بندی مناسب. پیگیری سفارش از پنل خریدار خیلی راحت بود.'],
];
?>

<div class="crumbs" style="margin-bottom:14px">
  <a href="index.php?page=home">فروشگاه</a> <span>/</span>
  <a href="index.php?page=home&cat=<?= e($p['category']) ?>"><?= e(category_title($p['category'])) ?></a> <span>/</span>
  <span><?= e($p['name']) ?></span>
</div>

<div class="product-layout">
  <div class="product-visual">
    <div class="product-image">
      <?php if (!empty($p['image'])): ?>
        <img src="<?= e($p['image']) ?>" alt="<?= e($p['name']) ?>">
      <?php else: ?>
        <span><?= e($p['icon']) ?></span>
      <?php endif; ?>
    </div>
    <div class="thumb-row">
      <span class="thumb"><?= e($p['icon']) ?></span>
      <span class="thumb">📦</span>
      <span class="thumb">🧾</span>
      <span class="thumb">🚚</span>
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
    <div class="product-top">
      <span class="pill info"><?= e($p['brand']) ?></span>
      <span class="pill muted"><?= e(category_title($p['category'])) ?></span>
      <span class="stock <?= $badge['class'] ?>"><?= e($badge['text']) ?></span>
    </div>
    <h1 class="product-title"><?= e($p['name']) ?></h1>
    <div class="product-rate"><?= stars(5) ?> <span>۵ از ۵</span> — <small><?= fa_num($p['sold']) ?> <?= e($p['unit']) ?> فروش موفق</small></div>
    <p class="product-desc"><?= nl2br(e($p['description'] ?: 'توضیحات تکمیلی این کالا توسط واحد فنی در حال تکمیل است.')) ?></p>

    <div class="price-panel">
      <div>
        <span class="price-label">قیمت واحد (با احتساب مالیات بر ارزش افزوده)</span>
        <div class="price-value">
          <?php if (!empty($p['old_price']) && $p['old_price'] > $p['price']): ?>
            <del><?= fa_num($p['old_price']) ?></del>
          <?php endif; ?>
          <?= money($p['price']) ?>
        </div>
        <small>ارزش افزوده ۱۰٪ در فاکتور رسمی به صورت مجزا محاسبه و به کارپوشه شما منتقل می‌شود.</small>
      </div>
      <div class="price-side">
        <div>موجودی انبار: <strong><?= fa_num($p['stock']) ?> <?= e($p['unit']) ?></strong></div>
        <div>واحد فروش: <strong><?= e($p['unit']) ?></strong></div>
        <div>تضمین اصالت: <strong>✅ کالای اورجینال</strong></div>
      </div>
    </div>

    <form class="add-to-cart" method="POST" action="index.php">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="add_cart">
      <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
      <input type="hidden" name="redirect" value="index.php?page=cart">
      <div class="qty-stepper">
        <label>تعداد</label>
        <div class="stepper">
          <button type="button" data-step="-1">−</button>
          <input type="number" name="qty" id="qtyInput" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>">
          <button type="button" data-step="1">+</button>
        </div>
      </div>
      <button class="btn btn-primary btn-lg" type="submit" <?= (int)$p['stock'] <= 0 ? 'disabled' : '' ?>>🛒 افزودن به سبد سفارش</button>
      <?php if (is_logged_in() && !is_admin()): ?>
        <button class="btn btn-secondary btn-lg" type="button" onclick="this.closest('form').querySelector('[name=action]').value='favorite_toggle';this.closest('form').submit();">
          <?= $isFav ? '★ در علاقه‌مندی‌ها' : '☆ افزودن به علاقه‌مندی' ?>
        </button>
      <?php endif; ?>
    </form>

    <?php if ((int)$p['stock'] <= 0): ?>
      <div class="alert warn">این کالا موقتاً ناموجود است. می‌توانید استعلام قیمت و زمان تأمین ثبت کنید.</div>
    <?php endif; ?>

    <div class="assurance-row">
      <div><span>🧾</span> صورتحساب الکترونیکی</div>
      <div><span>🚚</span> ارسال به سراسر کشور</div>
      <div><span>🛠️</span> گارانتی و خدمات پس از فروش</div>
      <div><span>↩️</span> بازگشت کالا تا ۷ روز</div>
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

<section class="sec-block">
  <div class="sec-head"><h2 class="sec-title">نظرات خریداران سازمانی</h2><span class="sec-sub">تجربه ثبت‌شده در فاکتورهای رسمی</span></div>
  <div class="review-grid">
    <?php foreach ($reviews as $r): ?>
      <div class="review-card">
        <div class="review-head">
          <div class="user-avatar-sm"><?= e(initials($r['name'])) ?></div>
          <div>
            <strong><?= e($r['name']) ?></strong>
            <small><?= e($r['company']) ?></small>
          </div>
          <span class="review-stars"><?= stars($r['rate']) ?></span>
        </div>
        <p><?= e($r['text']) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if ($relatedRows): ?>
  <section class="sec-block">
    <div class="sec-head"><h2 class="sec-title">کالاهای مرتبط</h2></div>
    <div class="pro-grid">
      <?php foreach ($relatedRows as $p) {
          require 'views/partials/product_card.php';
      } ?>
    </div>
  </section>
<?php endif; ?>
