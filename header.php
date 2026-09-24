<?php
require_once 'config.php';
$cartCount = isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0;
$currentPage = $_GET['page'] ?? 'home';
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>پارس سازه و آفیس | تامین تجهیزات مهندسی و کارگاهی</title>
  <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet">
  <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="top-bar no-print">
  <div class="container flex-between">
    <div class="top-links">
      <a href="index.php?page=home">کاتالوگ تجهیزات</a>
      <a href="index.php?page=rfq">استعلام پروژه (RFQ)</a>
      <a href="index.php?page=about">استانداردهای ممیزی و مالیات</a>
    </div>
    <div style="font-size:12px">مرکز پشتیبانی و تدارکات: ۰۲۱-۸۸۷۷۶۶۵۵ | شنبه تا چهارشنبه ۸ الی ۱۷:۳۰</div>
  </div>
</div>

<div class="modiyan-strip no-print">
  <div class="container flex-center gap-10">
    <span class="badge-gold">سامانه مؤدیان و پایانه‌های فروشگاهی</span>
    صدور آنی فاکتور رسمی نوع ۱ با شناسه ۲۲ رقمی اختصاصی کالا و انتقال ۱۰٪ اعتبار ارزش افزوده به کارپوشه مؤدیان.
  </div>
</div>

<header class="header no-print">
  <div class="container flex-between">
    <a href="index.php?page=home" class="logo">
      <div class="logo-icon">🏗️</div>
      <div>
        پارس سازه و آفیس
        <small>مرجع تأمین تجهیزات دفاتر فنی و عمرانی</small>
      </div>
    </a>
    <div class="header-actions">
      <?php if (!empty($_SESSION['user'])): ?>
        <a href="index.php?page=dashboard" class="btn btn-secondary">
          <span class="avatar-tag">م</span>
          <span>پنل کاربری (<?= e($_SESSION['user']['company'] ?? $_SESSION['user']['name']) ?>)</span>
        </a>
      <?php else: ?>
        <button class="btn btn-secondary" onclick="openModal('authModal')">👤 ورود / ثبت‌نام خریدار</button>
      <?php endif; ?>
      <a href="index.php?page=cart" class="btn btn-primary">
        🛒 سبد سفارش <span class="badge-count"><?= $cartCount ?></span>
      </a>
    </div>
  </div>
</header>

<nav class="nav no-print">
  <div class="container flex-gap">
    <a class="nav-link <?= $currentPage === 'home' && !isset($_GET['cat']) ? 'active' : '' ?>" href="index.php?page=home">همه تجهیزات</a>
    <a class="nav-link <?= ($_GET['cat'] ?? '') === 'surveying' ? 'active' : '' ?>" href="index.php?page=home&cat=surveying">📏 ابزار دقیق و نقشه‌برداری</a>
    <a class="nav-link <?= ($_GET['cat'] ?? '') === 'hse' ? 'active' : '' ?>" href="index.php?page=home&cat=hse">🦺 ایمنی و HSE کارگاهی</a>
    <a class="nav-link <?= ($_GET['cat'] ?? '') === 'plotter' ? 'active' : '' ?>" href="index.php?page=home&cat=plotter">🖨️ رول و چاپ نقشه</a>
    <a class="nav-link <?= ($_GET['cat'] ?? '') === 'stationery' ? 'active' : '' ?>" href="index.php?page=home&cat=stationery">📁 بایگانی و زونکن</a>
    <a class="nav-link rfq" href="index.php?page=rfq">📋 استعلام پیش‌فاکتور رسمی (RFQ)</a>
  </div>
</nav>

<div class="container page-wrapper">