<?php
/** اعلان‌های مدیریت */
require_admin();
$me = current_user();
$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read, id DESC LIMIT 50');
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();
$unread = unread_notifications($me['id']);

$pending = [
    ['⏳ سفارش‌های در انتظار تأیید', (int)$db->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn(), 'index.php?page=admin_orders&status=pending'],
    ['📋 استعلام‌های پاسخ‌نداده', (int)$db->query("SELECT COUNT(*) FROM rfqs WHERE status = 'new'")->fetchColumn(), 'index.php?page=admin_rfqs&status=new'],
    ['⚠️ کالاهای نیازمند تأمین', (int)$db->query('SELECT COUNT(*) FROM products WHERE stock <= min_stock')->fetchColumn(), 'index.php?page=admin_products&stock=low'],
    ['💳 سفارش‌های پرداخت‌نشده', (int)$db->query("SELECT COUNT(*) FROM orders WHERE payment_status = 'unpaid' AND status != 'canceled'")->fetchColumn(), 'index.php?page=admin_orders&payment=unpaid'],
    ['👤 حساب‌های غیرفعال خریدار', (int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'buyer' AND status = 'inactive'")->fetchColumn(), 'index.php?page=admin_users&status=inactive'],
];
?>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">کارتابل اقدامات مدیریت</h3>
  </div>
  <div class="action-grid">
    <?php foreach ($pending as $p): ?>
      <a class="action-card <?= $p[1] > 0 ? 'due' : '' ?>" href="<?= e($p[2]) ?>">
        <strong><?= e($p[0]) ?></strong>
        <span><?= fa_num($p[1]) ?> مورد</span>
      </a>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">اعلان‌های سیستم <?= $unread ? '<span class="pill warn">' . fa_num($unread) . ' خوانده‌نشده</span>' : '' ?></h3>
    <?php if ($unread): ?>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notify_read_all">
        <button class="btn btn-sm btn-secondary" type="submit">علامت‌گذاری همه خوانده‌شده</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="empty-mini">اعلانی برای نمایش وجود ندارد.</div>
  <?php else: ?>
    <div class="note-list">
      <?php foreach ($rows as $n): ?>
        <div class="note-item <?= (int)$n['is_read'] ? '' : 'unread' ?>">
          <div class="note-head">
            <strong><?= e($n['title']) ?></strong>
            <small><?= jdate($n['created_at'], true) ?> • <?= time_ago($n['created_at']) ?></small>
          </div>
          <p><?= e($n['body']) ?></p>
          <div class="note-actions">
            <?php if ($n['link']): ?>
              <a class="btn btn-sm btn-primary" href="index.php?action=notify_read&id=<?= (int)$n['id'] ?>&back=<?= urlencode($n['link']) ?>">اقدام</a>
            <?php endif; ?>
            <?php if (!(int)$n['is_read']): ?>
              <a class="btn btn-sm btn-secondary" href="index.php?action=notify_read&id=<?= (int)$n['id'] ?>&back=<?= urlencode('index.php?page=admin_notifications') ?>">خوانده شد</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
