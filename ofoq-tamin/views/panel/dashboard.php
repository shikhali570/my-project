<?php
/** پیشخوان پنل خریدار */
require_buyer();
$me = current_user();
$stats = buyer_stats($db, $me['id']);

$recent = $db->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5');
$recent->execute([$me['id']]);
$recentOrders = $recent->fetchAll();

$open = $db->prepare("SELECT o.* FROM orders o WHERE o.user_id = ? AND o.status IN ('pending','approved','preparing','shipped') ORDER BY o.id DESC LIMIT 4");
$open->execute([$me['id']]);
$openOrders = $open->fetchAll();

$notes = $db->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 4');
$notes->execute([$me['id']]);
$notifications = $notes->fetchAll();

$rfqs = $db->prepare('SELECT * FROM rfqs WHERE user_id = ? ORDER BY id DESC LIMIT 3');
$rfqs->execute([$me['id']]);
$rfqRows = $rfqs->fetchAll();

$favCount = $db->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
$favCount->execute([$me['id']]);
$lastOrder = $recentOrders[0] ?? null;
?>

<div class="kpi-grid">
  <div class="kpi-card">
    <span class="kpi-ico blue">📦</span>
    <div>
      <span>سفارش‌های ثبت‌شده</span>
      <strong><?= fa_num($stats['orders']) ?></strong>
      <small>شامل <?= fa_num($stats['open']) ?> سفارش در جریان</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico green">💰</span>
    <div>
      <span>مجموع خرید (بدون سفارش‌های لغوشده)</span>
      <strong><?= money_short($stats['spent']) ?> تومان</strong>
      <small>میانگین هر سفارش: <?= money($stats['orders'] ? (int)round($stats['spent'] / $stats['orders']) : 0) ?></small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico orange">🎁</span>
    <div>
      <span>اعتبار سازمانی کارپوشه</span>
      <strong><?= money_short($me['credit']) ?> تومان</strong>
      <small>قابل کسر در سفارش‌های بعدی</small>
    </div>
  </div>
  <div class="kpi-card">
    <span class="kpi-ico purple">⭐</span>
    <div>
      <span>علاقه‌مندی‌ها و استعلام‌ها</span>
      <strong><?= fa_num((int)$favCount->fetchColumn()) ?> / <?= fa_num(count($rfqRows)) ?></strong>
      <small><a href="index.php?page=panel_favorites">مشاهده لیست علاقه‌مندی</a></small>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">تکمیل کارپوشه مالیاتی</h3></div>
  <?php
  $missing = [];
  foreach (['national_id' => 'شناسه ملی / کد اقتصادی', 'economic_code' => 'کد اقتصادی', 'postal_code' => 'کد پستی', 'address' => 'نشانی'] as $key => $label) {
      if (empty($me[$key])) {
          $missing[] = $label;
      }
  }
  ?>
  <?php if (!$missing): ?>
    <div class="alert success">کارپوشه شما کامل است ✅ صورتحساب‌ها با اطلاعات رسمی شما صادر می‌شود.</div>
  <?php else: ?>
    <div class="alert warn">
      موارد زیر در کارپوشه شما تکمیل نشده است: <strong><?= e(implode(' | ', $missing)) ?></strong>
      <a class="btn btn-sm btn-primary" href="index.php?page=panel_profile">تکمیل اطلاعات</a>
    </div>
  <?php endif; ?>
</div>

<?php if ($lastOrder): ?>
  <div class="card highlight-card">
    <div class="card-head">
      <h3 class="card-title">آخرین سفارش: <span class="mono"><?= e($lastOrder['order_no']) ?></span></h3>
      <span class="status <?= order_status_class($lastOrder['status']) ?>"><?= order_status_icon($lastOrder['status']) ?> <?= e(order_status_label($lastOrder['status'])) ?></span>
    </div>
    <div class="progress-bar"><span style="width: <?= order_progress($lastOrder['status']) ?>%"></span></div>
    <div class="flex-between detail-grid-tight">
      <div class="kv"><span>تاریخ:</span><strong><?= jdate($lastOrder['created_at'], true) ?></strong></div>
      <div class="kv"><span>مبلغ:</span><strong><?= money($lastOrder['total']) ?></strong></div>
      <div class="kv"><span>وضعیت پرداخت:</span><strong><?= e(payment_status_label($lastOrder['payment_status'])) ?></strong></div>
      <a class="btn btn-primary btn-sm" href="index.php?page=panel_order&no=<?= e($lastOrder['order_no']) ?>">جزئیات و پیگیری</a>
    </div>
  </div>
<?php endif; ?>

<div class="panel-grid">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">سفارش‌های اخیر</h3>
      <a class="link-more" href="index.php?page=panel_orders">همه سفارش‌ها ←</a>
    </div>
    <?php if (!$recentOrders): ?>
      <div class="empty-mini">هنوز سفارشی ثبت نکرده‌اید. <a href="index.php?page=home">شروع خرید</a></div>
    <?php else: ?>
      <div class="table-wrap"><table class="data-table">
        <thead><tr><th>شماره</th><th>تاریخ</th><th>مبلغ</th><th>وضعیت</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($recentOrders as $o): ?>
            <tr>
              <td class="mono"><?= e($o['order_no']) ?></td>
              <td><?= jdate_short($o['created_at']) ?></td>
              <td><?= money($o['total']) ?></td>
              <td><span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span></td>
              <td><a class="btn btn-sm btn-secondary" href="index.php?page=panel_order&no=<?= e($o['order_no']) ?>">مشاهده</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    <?php endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <h3 class="card-title">اعلان‌های اخیر</h3>
      <a class="link-more" href="index.php?page=panel_notifications">همه ←</a>
    </div>
    <div class="note-list">
      <?php if (!$notifications): ?>
        <div class="empty-mini">اعلان جدیدی وجود ندارد.</div>
      <?php else: foreach ($notifications as $n): ?>
        <a class="note-item <?= (int)$n['is_read'] ? '' : 'unread' ?>" href="<?= e($n['link'] ?: 'index.php?page=panel_notifications') ?>">
          <strong><?= e($n['title']) ?></strong>
          <p><?= e($n['body']) ?></p>
          <small><?= time_ago($n['created_at']) ?></small>
        </a>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>

<div class="panel-grid">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">سفارش‌های در جریان</h3>
      <span class="pill warn"><?= fa_num(count($openOrders)) ?> مورد</span>
    </div>
    <?php if (!$openOrders): ?>
      <div class="empty-mini">همه سفارش‌های شما نهایی شده‌اند. ✅</div>
    <?php else: foreach ($openOrders as $o): ?>
      <div class="flow-row">
        <div>
          <strong class="mono"><?= e($o['order_no']) ?></strong>
          <small><?= jdate($o['created_at']) ?> | <?= money($o['total']) ?></small>
        </div>
        <div class="flow-status">
          <span class="status <?= order_status_class($o['status']) ?>"><?= e(order_status_label($o['status'])) ?></span>
          <div class="progress-bar sm"><span style="width: <?= order_progress($o['status']) ?>%"></span></div>
        </div>
        <a class="btn btn-sm btn-secondary" href="index.php?page=panel_order&no=<?= e($o['order_no']) ?>">پیگیری</a>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <div class="card-head">
      <h3 class="card-title">استعلام‌های قیمت</h3>
      <a class="link-more" href="index.php?page=panel_rfqs">همه ←</a>
    </div>
    <?php if (!$rfqRows): ?>
      <div class="empty-mini">استعلامی ثبت نشده است. <a href="index.php?page=rfq">ثبت استعلام پروژه</a></div>
    <?php else: foreach ($rfqRows as $r): ?>
      <div class="flow-row">
        <div>
          <strong class="mono"><?= e($r['rfq_code']) ?></strong>
          <small><?= e($r['title'] ?: mb_substr($r['description'], 0, 30) . '…') ?></small>
        </div>
        <span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span>
        <strong><?= $r['quote_amount'] ? money_short($r['quote_amount']) : '—' ?></strong>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>
