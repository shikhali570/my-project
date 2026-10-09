<?php
/** قالب بالای پنل خریدار: کاربر، منوی بخش‌ها و عنوان صفحه */
$me = current_user();
require __DIR__ . '/header.php';

$panelMenu = [
    'panel'               => ['📊', 'پیشخوان'],
    'panel_orders'        => ['📦', 'سفارش‌های من'],
    'panel_invoices'      => ['🧾', 'صورتحساب‌ها'],
    'panel_rfqs'          => ['📋', 'استعلام‌های قیمت'],
    'panel_favorites'     => ['⭐', 'علاقه‌مندی‌ها'],
    'panel_notifications' => ['🔔', 'اعلان‌ها'],
    'panel_profile'       => ['👤', 'کارپوشه و پروفایل'],
];
// صفحه‌های فرعی، بخش مادر خود را در منو هایلایت می‌کنند (جزئیات سفارش زیر «سفارش‌های من»)
$menuParent = ['panel_order' => 'panel_orders'];
$menuActive = $menuParent[$currentPage] ?? $currentPage;
$unreadPanel = unread_notifications($me['id']);
?>

<div class="panel-layout">
  <aside class="panel-side">
    <div class="side-user">
      <div class="side-avatar" aria-hidden="true"><?= e(initials($me['company'] ?: $me['name'])) ?></div>
      <div class="side-user-text">
        <strong><?= e($me['company'] ?: $me['name']) ?></strong>
        <small><?= e($me['name']) ?> · <span class="mono"><?= e($me['phone']) ?></span></small>
        <?php if ($me['status'] !== 'active'): ?>
          <span class="pill danger">حساب غیرفعال است</span>
        <?php endif; ?>
      </div>
    </div>

    <nav class="panel-menu" aria-label="بخش‌های پنل خریدار">
      <?php foreach ($panelMenu as $key => $item): ?>
        <a class="panel-link <?= $menuActive === $key ? 'active' : '' ?>" href="index.php?page=<?= $key ?>"<?= $menuActive === $key ? ' aria-current="page"' : '' ?>>
          <span aria-hidden="true"><?= $item[0] ?></span>
          <span><?= $item[1] ?></span>
          <?php if ($key === 'panel_notifications' && $unreadPanel): ?>
            <span class="count-chip" aria-label="<?= fa_num($unreadPanel) ?> پیام خوانده‌نشده"><?= fa_num($unreadPanel) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
      <a class="panel-link danger" href="index.php?action=logout"><span aria-hidden="true">🚪</span> <span>خروج از حساب</span></a>
    </nav>
  </aside>

  <main class="panel-main">
    <header class="panel-head">
      <h1 class="panel-title"><?= e($pageTitle) ?></h1>
    </header>
