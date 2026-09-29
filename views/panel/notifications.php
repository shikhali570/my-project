<?php
/** اعلان‌ها و پیام‌های خریدار */
require_buyer();
$me = current_user();

$stmt = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read, id DESC LIMIT 40');
$stmt->execute([$me['id']]);
$rows = $stmt->fetchAll();
$unread = unread_notifications($me['id']);
?>

<div class="card">
  <div class="card-head">
    <h3 class="card-title">صندوق پیام‌ها <?= $unread ? '<span class="pill warn">' . fa_num($unread) . ' خوانده‌نشده</span>' : '' ?></h3>
    <?php if ($unread): ?>
      <form method="POST" action="index.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="notify_read_all">
        <button class="btn btn-sm btn-secondary" type="submit">علامت‌گذاری همه به‌عنوان خوانده‌شده</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$rows): ?>
    <div class="empty-mini">صندوق پیام شما خالی است.</div>
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
              <a class="btn btn-sm btn-primary" href="index.php?action=notify_read&id=<?= (int)$n['id'] ?>&back=<?= urlencode($n['link']) ?>">مشاهده جزئیات</a>
            <?php endif; ?>
            <?php if (!(int)$n['is_read']): ?>
              <a class="btn btn-sm btn-secondary" href="index.php?action=notify_read&id=<?= (int)$n['id'] ?>&back=<?= urlencode('index.php?page=panel_notifications') ?>">علامت‌گذاری خوانده‌شده</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
