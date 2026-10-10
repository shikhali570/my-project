<?php
/** قالب سربرگ عمومی و نقطه ورود یکپارچه به فروشگاه */
if (!isset($pageTitle)) {
    $pageTitle = 'فروشگاه تجهیزات مهندسی';
}
$me = current_user();
$unread = $me ? unread_notifications($me['id']) : 0;
$buyerPages = ['panel', 'panel_orders', 'panel_order', 'panel_invoices', 'panel_rfqs', 'panel_favorites', 'panel_notifications', 'panel_profile'];
$isBuyerPanel = in_array($currentPage ?? '', $buyerPages, true);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#252b2e">
  <title><?= e($pageTitle) ?> | <?= e(settings('site_name', 'پارس سازه و آفیس')) ?></title>
  <meta name="description" content="خرید آنلاین تجهیزات نقشه‌برداری، ایمنی کارگاهی، رول پلاتر و ملزومات دفاتر فنی با صورتحساب الکترونیکی معتبر سامانه مؤدیان.">
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="theme.css?v=<?= APP_VERSION ?>">
</head>
<body class="site-body site-page--<?= e($currentPage ?? 'home') ?><?= $isBuyerPanel ? ' buyer-workspace-body' : '' ?>">

<a class="skip-link" href="#main">رفتن به محتوای اصلی</a>

<div class="top-bar no-print">
  <div class="container top-bar__inner">
    <div class="top-links hide-md">
      <a href="index.php?page=home">کاتالوگ تجهیزات</a>
      <a href="index.php?page=rfq">استعلام پروژه (RFQ)</a>
      <a href="index.php?page=track">پیگیری سفارش</a>
      <a href="index.php?page=about">استانداردهای ممیزی و مالیات</a>
    </div>
    <div class="top-contact">
      <span class="top-contact__label"><span class="top-contact__dot" aria-hidden="true"></span>مرکز پشتیبانی و تدارکات</span>
      <a class="top-contact__phone" href="tel:<?= e(preg_replace('/[^0-9+]/', '', (string)settings('phone'))) ?>"><?= e(settings('phone')) ?></a>
      <span class="top-contact__hours hide-sm"><?= e(settings('work_hours')) ?></span>
    </div>
  </div>
</div>

<header class="header site-header no-print">
  <div class="container site-header__inner">
    <a href="index.php?page=home" class="logo site-brand" aria-label="<?= e(settings('site_name', 'پارس سازه و آفیس')) ?>، صفحهٔ اصلی">
      <span class="logo-icon" aria-hidden="true">🏗️</span>
      <span class="site-brand__copy">
        <strong><?= e(settings('site_name', 'پارس سازه و آفیس')) ?></strong>
        <small><?= e(settings('site_slogan')) ?></small>
      </span>
    </a>

    <?php if (!$isBuyerPanel): ?>
      <div class="search-wrap">
        <form class="header-search" method="GET" action="index.php" role="search">
          <input type="hidden" name="page" value="home">
          <label class="visually-hidden" for="site-search">جست‌وجوی کالا</label>
          <input id="site-search" type="search" name="q" value="<?= e(get('q')) ?>" placeholder="نام کالا، برند یا شناسه مالیاتی…" autocomplete="off">
          <button type="submit" aria-label="جست‌وجو"><span aria-hidden="true">⌕</span></button>
        </form>
      </div>
    <?php else: ?>
      <a class="header-store-link hide-sm" href="index.php?page=home"><span aria-hidden="true">←</span> بازگشت به فروشگاه</a>
    <?php endif; ?>

    <div class="header-actions">
      <?php if ($me): ?>
        <details class="drop">
          <summary class="btn btn-secondary account-trigger">
            <span class="avatar-tag"><?= e(initials($me['company'] ?: $me['name'])) ?></span>
            <span class="account-trigger__label hide-sm"><?= e($me['role'] === 'admin' ? 'پنل مدیریت' : 'کارپوشه خرید') ?></span>
            <?php if ($unread): ?><span class="dot-badge" aria-label="<?= fa_num($unread) ?> اعلان خوانده‌نشده"><?= fa_num($unread) ?></span><?php endif; ?>
            <span class="account-chevron" aria-hidden="true">⌄</span>
          </summary>
          <div class="drop-menu">
            <div class="drop-head">
              <strong><?= e($me['name']) ?></strong>
              <small><?= e($me['company'] ?: 'حساب شخصی') ?></small>
              <span class="pill <?= $me['role'] === 'admin' ? 'primary' : 'success' ?>"><?= $me['role'] === 'admin' ? 'مدیر سیستم' : (($me['entity_type'] ?? '') === 'individual' ? 'خریدار حقیقی' : ((($me['entity_type'] ?? '') === 'legal') ? 'خریدار حقوقی' : 'خریدار')) ?></span>
            </div>
            <a href="index.php?page=<?= $me['role'] === 'admin' ? 'admin' : 'panel' ?>">▦ <span>پیشخوان</span></a>
            <?php if ($me['role'] === 'buyer'): ?>
              <a href="index.php?page=panel_orders">□ <span>سفارش‌های من</span></a>
              <a href="index.php?page=panel_invoices">▤ <span>صورتحساب‌ها</span></a>
              <a href="index.php?page=panel_rfqs">☷ <span>استعلام‌های قیمت</span></a>
              <a href="index.php?page=panel_favorites">☆ <span>علاقه‌مندی‌ها</span></a>
              <a href="index.php?page=panel_notifications">◉ <span>اعلان‌ها <?= $unread ? '(' . fa_num($unread) . ')' : '' ?></span></a>
            <?php else: ?>
              <a href="index.php?page=admin_orders">□ <span>سفارش‌ها</span></a>
              <a href="index.php?page=admin_products">▧ <span>کالاها</span></a>
              <a href="index.php?page=admin_reports">▥ <span>گزارش‌ها</span></a>
              <a href="index.php?page=admin_settings">⚙ <span>تنظیمات</span></a>
            <?php endif; ?>
            <a href="index.php?page=<?= $me['role'] === 'admin' ? 'admin_profile' : 'panel_profile' ?>">◎ <span>کارپوشه / پروفایل</span></a>
            <a class="danger" href="index.php?action=logout">↗ <span>خروج از حساب</span></a>
          </div>
        </details>
      <?php else: ?>
        <a href="index.php?page=login" class="btn btn-secondary account-login"><span aria-hidden="true">↪</span><span>ورود</span></a>
        <a href="index.php?page=register" class="btn btn-outline hide-sm">ثبت‌نام خریدار</a>
      <?php endif; ?>

      <a href="index.php?page=cart" class="btn btn-primary cart-btn" aria-label="سبد سفارش، <?= fa_num($cartCount) ?> قلم">
        <span class="cart-btn__icon" aria-hidden="true">▣</span><span class="hide-sm">سبد سفارش</span><span class="badge-count"><?= fa_num($cartCount) ?></span>
      </a>
    </div>
  </div>
</header>

<?php if (!$isBuyerPanel): ?>
  <nav class="nav site-nav no-print" aria-label="دسته‌بندی کالاها">
    <div class="container nav-scroll">
      <a class="nav-link <?= $currentPage === 'home' && !get('cat') ? 'active' : '' ?>" href="index.php?page=home">همه تجهیزات</a>
      <?php foreach ($navCategories as $cat): ?>
        <a class="nav-link <?= get('cat') === $cat['slug'] ? 'active' : '' ?>" href="index.php?page=home&cat=<?= e($cat['slug']) ?>">
          <span class="nav-link__icon" aria-hidden="true"><?= e($cat['icon']) ?></span> <?= e($cat['title']) ?>
        </a>
      <?php endforeach; ?>
      <a class="nav-link <?= $currentPage === 'track' ? 'active' : '' ?>" href="index.php?page=track">پیگیری سفارش</a>
      <a class="nav-link rfq" href="index.php?page=rfq">استعلام قیمت پروژه <span aria-hidden="true">←</span></a>
    </div>
  </nav>
<?php endif; ?>

<?php if (!$isBuyerPanel): ?>
  <div class="modiyan-strip no-print">
    <div class="container modiyan-strip__inner">
      <span class="modiyan-mark" aria-hidden="true">✓</span>
      <div class="modiyan-copy">
        <strong>صدور صورتحساب الکترونیکی در سامانه مؤدیان</strong>
        <span>صدور صورتحساب الکترونیک مودیان با شناسه یکتای مالیاتی کالا و انتقال اعتبار ارزش افزوده به کارپوشه خریدار.</span>
      </div>
      <a href="index.php?page=about">جزئیات بیشتر <span aria-hidden="true">←</span></a>
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($flashes)): ?>
  <div class="toast-wrap no-print" aria-live="polite">
    <?php foreach ($flashes as $f): ?>
      <div class="toast-bar <?= e($f['type']) ?>" role="status">
        <span class="toast-text"><?= e($f['message']) ?></span>
        <button type="button" class="toast-close" aria-label="بستن پیام">×</button>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (!empty($notFound)): ?>
  <div class="container" style="margin-top:20px">
    <div class="alert danger">صفحه درخواستی یافت نشد؛ کاتالوگ کالاها نمایش داده می‌شود.</div>
  </div>
<?php endif; ?>

<main class="container page-wrapper site-main" id="main" tabindex="-1">
