<?php
/** قالب بالای سایت (بخش عمومی) */
if (!isset($pageTitle)) {
    $pageTitle = 'فروشگاه تجهیزات مهندسی';
}
$me = current_user();
$unread = $me ? unread_notifications($me['id']) : 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?> | <?= e(settings('site_name', 'پارس سازه و آفیس')) ?></title>
  <meta name="description" content="خرید آنلاین تجهیزات نقشه‌برداری، ایمنی کارگاهی، رول پلاتر و ملزومات دفاتر فنی با صورتحساب الکترونیکی معتبر سامانه مؤدیان.">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?= APP_VERSION ?>">
</head>
<body>

<div class="top-bar no-print">
  <div class="container flex-between">
    <div class="top-links">
      <a href="index.php?page=home">کاتالوگ تجهیزات</a>
      <a href="index.php?page=rfq">استعلام پروژه (RFQ)</a>
      <a href="index.php?page=track">پیگیری سفارش</a>
      <a href="index.php?page=about">استانداردهای ممیزی و مالیات</a>
    </div>
    <div class="top-contact">
      <span>☎ مرکز پشتیبانی و تدارکات: <?= e(settings('phone')) ?></span>
      <span class="hide-sm">| <?= e(settings('work_hours')) ?></span>
    </div>
  </div>
</div>

<div class="modiyan-strip no-print">
  <div class="container flex-center gap-10">
    <span class="badge-gold">سامانه مؤدیان و پایانه‌های فروشگاهی</span>
    <span class="hide-sm">صدور آنی صورتحساب الکترونیکی نوع ۱ با شناسه یکتای مالیاتی کالا و انتقال اعتبار ارزش افزوده به کارپوشه خریدار.</span>
  </div>
</div>

<header class="header no-print">
  <div class="container flex-between gap-10">
    <a href="index.php?page=home" class="logo">
      <div class="logo-icon">🏗️</div>
      <div>
        <?= e(settings('site_name', 'پارس سازه و آفیس')) ?>
        <small><?= e(settings('site_slogan')) ?></small>
      </div>
    </a>

    <form class="header-search hide-md" method="GET" action="index.php">
      <input type="hidden" name="page" value="home">
      <input type="text" name="q" value="<?= e(get('q')) ?>" placeholder="جست‌وجوی کالا، برند یا شناسه مالیاتی…">
      <button type="submit" aria-label="جست‌وجو">🔍</button>
    </form>

    <div class="header-actions">
      <?php if ($me): ?>
        <details class="drop">
          <summary class="btn btn-secondary">
            <span class="avatar-tag"><?= e(initials($me['company'] ?: $me['name'])) ?></span>
            <span class="hide-sm"><?= e($me['role'] === 'admin' ? 'پنل مدیریت' : 'پنل خریدار') ?></span>
            <?php if ($unread): ?><span class="dot-badge"><?= fa_num($unread) ?></span><?php endif; ?>
          </summary>
          <div class="drop-menu">
            <div class="drop-head">
              <strong><?= e($me['name']) ?></strong>
              <small><?= e($me['company'] ?: 'حساب شخصی') ?></small>
              <span class="pill <?= $me['role'] === 'admin' ? 'primary' : 'success' ?>"><?= $me['role'] === 'admin' ? 'مدیر سیستم' : 'خریدار سازمانی' ?></span>
            </div>
            <a href="index.php?page=<?= $me['role'] === 'admin' ? 'admin' : 'panel' ?>">📊 پیشخوان</a>
            <?php if ($me['role'] === 'buyer'): ?>
              <a href="index.php?page=panel_orders">📦 سفارش‌های من</a>
              <a href="index.php?page=panel_invoices">🧾 صورتحساب‌ها</a>
              <a href="index.php?page=panel_rfqs">📋 استعلام‌های قیمت</a>
              <a href="index.php?page=panel_favorites">⭐ علاقه‌مندی‌ها</a>
              <a href="index.php?page=panel_notifications">🔔 اعلان‌ها <?= $unread ? '(' . fa_num($unread) . ')' : '' ?></a>
            <?php else: ?>
              <a href="index.php?page=admin_orders">📦 سفارش‌ها</a>
              <a href="index.php?page=admin_products">🏷️ کالاها</a>
              <a href="index.php?page=admin_reports">📈 گزارش‌ها</a>
              <a href="index.php?page=admin_settings">⚙️ تنظیمات</a>
            <?php endif; ?>
            <a href="index.php?page=<?= $me['role'] === 'admin' ? 'admin_profile' : 'panel_profile' ?>">👤 کارپوشه / پروفایل</a>
            <a class="danger" href="index.php?action=logout">🚪 خروج از حساب</a>
          </div>
        </details>
      <?php else: ?>
        <a href="index.php?page=login" class="btn btn-secondary">👤 ورود</a>
        <a href="index.php?page=register" class="btn btn-outline hide-sm">ثبت‌نام خریدار سازمانی</a>
      <?php endif; ?>

      <a href="index.php?page=cart" class="btn btn-primary">
        🛒 سبد سفارش <span class="badge-count"><?= fa_num($cartCount) ?></span>
      </a>
    </div>
  </div>
</header>

<nav class="nav no-print">
  <div class="container nav-scroll">
    <a class="nav-link <?= $currentPage === 'home' && !get('cat') ? 'active' : '' ?>" href="index.php?page=home">همه تجهیزات</a>
    <?php foreach ($navCategories as $cat): ?>
      <a class="nav-link <?= get('cat') === $cat['slug'] ? 'active' : '' ?>" href="index.php?page=home&cat=<?= e($cat['slug']) ?>">
        <?= e($cat['icon']) ?> <?= e($cat['title']) ?>
      </a>
    <?php endforeach; ?>
    <a class="nav-link rfq" href="index.php?page=rfq">📋 استعلام پیش‌فاکتور رسمی (RFQ)</a>
  </div>
</nav>

<?php if (!empty($flashes)): ?>
  <div class="toast-wrap no-print">
    <?php foreach ($flashes as $f): ?>
      <div class="toast-bar <?= e($f['type']) ?>"><?= $f['message'] ?></div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($notFound)): ?>
  <div class="container" style="margin-top:20px">
    <div class="alert danger">صفحه درخواستی یافت نشد؛ کاتالوگ کالاها نمایش داده می‌شود.</div>
  </div>
<?php endif; ?>

<div class="container page-wrapper">
