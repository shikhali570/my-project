<?php
require_once 'config.php';
$cartCount = isset($_SESSION['cart']) ? (int)array_sum($_SESSION['cart']) : 0;
$currentPage = $_GET['page'] ?? 'home';
if (!isset($catCounts)) {
    $catCounts = ['' => 0];
    foreach ($db->query("SELECT category, COUNT(*) AS c FROM products GROUP BY category") as $row) {
        $catCounts[$row['category']] = (int)$row['c'];
    }
    $catCounts[''] = array_sum($catCounts);
}
$activeCat = (string)($_GET['cat'] ?? '');
if (!isset(CATEGORIES[$activeCat])) {
    $activeCat = '';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="فروشگاه تخصصی پارس سازه و آفیس؛ خرید بی‌واسطه ابزار دقیق، نقشه‌برداری، ایمنی HSE و ملزومات دفاتر فنی با صورتحساب الکترونیکی رسمی سامانه مؤدیان.">
  <meta name="csrf" content="<?= e($_SESSION['csrf_token']) ?>">
  <title><?= e(SITE['name']) ?> | تأمین تجهیزات مهندسی و کارگاهی</title>
  <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%230B1226'/><text x='50' y='68' font-size='52' text-anchor='middle'>🏗️</text></svg>">
  <link rel="stylesheet" href="fonts/vazirmatn.css">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="top-bar no-print">
  <div class="container flex-between">
    <div class="top-phone">
      <span>☎️</span>
      <span dir="ltr"><?= e(SITE['phone']) ?></span>
    </div>
    <div class="top-links">
      <a href="index.php?page=home">کاتالوگ تجهیزات</a>
      <a href="index.php?page=rfq">استعلام پروژه (RFQ)</a>
      <a href="index.php?page=about">استانداردهای ممیزی و مالیات</a>
    </div>
  </div>
</div>

<header class="header no-print" id="siteHeader">
  <div class="container header-inner">
    <button class="icon-btn menu-btn no-print" id="menuBtn" aria-label="باز کردن منو" aria-expanded="false">
      <span></span><span></span><span></span>
    </button>

    <a href="index.php?page=home" class="logo">
      <span class="logo-icon">🏗️</span>
      <span class="logo-text">
        <strong><?= e(SITE['name']) ?></strong>
        <small><?= e(SITE['tagline']) ?></small>
      </span>
    </a>

    <form class="header-search" action="index.php" method="GET" role="search">
      <svg class="search-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M15.5 14h-.79l-.28-.27a6.5 6.5 0 1 0-.7.7l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0A4.5 4.5 0 1 1 14 9.5 4.5 4.5 0 0 1 9.5 14z"/></svg>
      <input type="search" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="جستجوی کالا، برند یا شناسه مالیاتی…" aria-label="جستجوی کالا" autocomplete="off">
    </form>

    <div class="header-actions">
      <?php if (!empty($_SESSION['user'])): ?>
        <a href="index.php?page=dashboard" class="user-chip">
          <span class="avatar-tag"><?= e(firstChar($_SESSION['user']['company'] ?? $_SESSION['user']['name'])) ?></span>
          <span class="user-chip-name"><?= e($_SESSION['user']['company'] ?? $_SESSION['user']['name']) ?></span>
        </a>
      <?php else: ?>
        <button class="btn btn-ghost" onclick="openModal('authModal')">👤 ورود / ثبت‌نام</button>
      <?php endif; ?>
      <a href="index.php?page=cart" class="btn btn-primary cart-btn">
        <svg class="cart-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 2-2-.9-2-2-2zM1 2v2h2l3.6 7.59-1.35 2.45c-.16.28-.25.61-.25.96 0 1.1.9 2 2 2h12v-2H7.42c-.14 0-.25-.11-.25-.25l.03-.12.9-1.63h7.45c.75 0 1.41-.41 1.75-1.03l3.58-6.49A1 1 0 0 0 20 4H5.21l-.94-2H1zm16 16c-1.1 0-1.99.9-1.99 2s.89 2 1.99 2 2-.9 2-2-.9-2-2-2z"/></svg>
        <span class="cart-btn-label">سبد</span>
        <span class="badge-count" id="cartBadge" data-count="<?= $cartCount ?>"><?= faNum($cartCount) ?></span>
      </a>
    </div>
  </div>
</header>

<nav class="nav no-print" aria-label="دسته‌بندی‌ها">
  <div class="container chip-row nav-chiprow">
    <a class="chip <?= $activeCat === '' && !isset($_GET['q']) ? 'active' : '' ?>" href="index.php?page=home">همه تجهیزات <i><?= faNum($catCounts['']) ?></i></a>
    <?php foreach (CATEGORIES as $key => $c): ?>
      <a class="chip <?= $activeCat === $key ? 'active' : '' ?>" href="index.php?page=home&cat=<?= e($key) ?>"><?= $c['icon'] ?> <?= e($c['label']) ?> <i><?= faNum($catCounts[$key] ?? 0) ?></i></a>
    <?php endforeach; ?>
    <a class="chip chip-rfq" href="index.php?page=rfq">📋 استعلام پیش‌فاکتور رسمی (RFQ)</a>
  </div>
</nav>

<div class="container page-wrapper">

<?php // منوی موبایل
?>
<div class="mobile-drawer" id="mobileDrawer" aria-hidden="true">
  <div class="drawer-backdrop" data-close-drawer></div>
  <div class="drawer-panel">
    <div class="drawer-head">
      <span class="logo-icon">🏗️</span>
      <strong><?= e(SITE['name']) ?></strong>
      <button class="icon-btn" data-close-drawer aria-label="بستن منو">✕</button>
    </div>
    <form class="drawer-search" action="index.php" method="GET" role="search">
      <input type="search" name="q" value="<?= e((string)($_GET['q'] ?? '')) ?>" placeholder="جستجوی کالا…" aria-label="جستجوی کالا">
      <button type="submit" class="btn btn-primary">جستجو</button>
    </form>
    <div class="drawer-links">
      <a href="index.php?page=home">🏠 همه تجهیزات</a>
      <?php foreach (CATEGORIES as $key => $c): ?>
        <a href="index.php?page=home&cat=<?= e($key) ?>"><?= $c['icon'] ?> <?= e($c['label']) ?></a>
      <?php endforeach; ?>
      <a href="index.php?page=rfq" class="drawer-rfq">📋 استعلام پروژه (RFQ)</a>
      <a href="index.php?page=about">🏢 درباره شرکت</a>
    </div>
    <?php if (empty($_SESSION['user'])): ?>
      <button class="btn btn-secondary drawer-auth" onclick="closeDrawer(); openModal('authModal')">👤 ورود / ثبت‌نام خریدار</button>
    <?php endif; ?>
    <div class="drawer-foot" dir="ltr"><?= e(SITE['phone']) ?></div>
  </div>
</div>
