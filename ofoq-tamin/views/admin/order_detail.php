<?php
/** پرونده سفارش در پنل مدیریت */
require_admin();
$id = (int)get('id');
$stmt = $db->prepare('SELECT * FROM orders WHERE id = ?');
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="empty-state"><span>📦</span><h3>سفارش یافت نشد</h3><a class="btn btn-primary" href="index.php?page=admin_orders">بازگشت</a></div>';
    return;
}

$items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$id]);
$rows = $items->fetchAll();

$inv = $db->prepare('SELECT * FROM invoices WHERE order_id = ?');
$inv->execute([$id]);
$invoice = $inv->fetch();

$customer = null;
if ($order['user_id']) {
    $c = $db->prepare('SELECT * FROM users WHERE id = ?');
    $c->execute([$order['user_id']]);
    $customer = $c->fetch();
}

$logs = $db->prepare("SELECT * FROM activity_logs WHERE entity = 'order' AND entity_id = ? ORDER BY id DESC LIMIT 10");
$logs->execute([$id]);
$logRows = $logs->fetchAll();

$customerOrders = [];
if ($customer) {
    $co = $db->prepare('SELECT id, order_no, total, status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 5');
    $co->execute([$customer['id']]);
    $customerOrders = $co->fetchAll();
}

$paidTotal = (int)$db->query('SELECT COALESCE(SUM(total),0) FROM orders WHERE user_id = ' . (int)$order['user_id'] . " AND payment_status = 'paid' AND status != 'canceled'")->fetchColumn();
$steps = order_timeline_steps();
$currentIdx = array_search($order['status'], $steps, true);
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title">پرونده سفارش <span class="mono"><?= e($order['order_no']) ?></span></h2>
    <p class="sec-sub">ثبت: <?= jdate($order['created_at'], true) ?> | آخرین تغییر: <?= jdate($order['updated_at'], true) ?></p>
  </div>
  <div class="flex-gap">
    <span class="status <?= order_status_class($order['status']) ?>"><?= order_status_icon($order['status']) ?> <?= e(order_status_label($order['status'])) ?></span>
    <span class="pill <?= $order['payment_status'] === 'paid' ? 'success' : 'warn' ?>"><?= e(payment_status_label($order['payment_status'])) ?></span>
    <button class="btn btn-secondary btn-sm" onclick="window.print()">🖨️ چاپ</button>
  </div>
</div>

<?php if ($order['status'] !== 'canceled'): ?>
  <div class="card">
    <div class="timeline">
      <?php foreach ($steps as $i => $s): ?>
        <div class="tl-step <?= $currentIdx !== false && $i <= $currentIdx ? 'done' : '' ?> <?= $currentIdx === $i ? 'current' : '' ?>">
          <div class="tl-dot"><?= order_status_icon($s) ?></div>
          <div class="tl-label"><?= e(order_status_label($s)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="detail-grid wide-left">
  <div>
    <div class="card">
      <div class="card-head"><h3 class="card-title">اقلام سفارش</h3><span class="pill muted"><?= fa_num(count($rows)) ?> قلم</span></div>
      <table class="data-table">
        <thead><tr><th>#</th><th>کالا</th><th>شناسه مالیاتی</th><th>قیمت واحد</th><th>تعداد</th><th>مبلغ</th></tr></thead>
        <tbody>
          <?php foreach ($rows as $i => $r): ?>
            <tr>
              <td><?= fa_num($i + 1) ?></td>
              <td><strong><?= e($r['name']) ?></strong><div class="mini-note"><?= e($r['brand']) ?></div></td>
              <td class="mono small"><?= e($r['tax_id']) ?></td>
              <td><?= money($r['price']) ?></td>
              <td><?= fa_num($r['qty']) ?></td>
              <td><strong><?= money($r['total']) ?></strong></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div class="sum-line"><span>جمع اقلام:</span><span><?= money($order['subtotal']) ?></span></div>
      <?php if ($order['discount'] > 0): ?>
        <div class="sum-line discount"><span>تخفیف <?= e($order['coupon_code']) ?>:</span><span>− <?= money($order['discount']) ?></span></div>
      <?php endif; ?>
      <div class="sum-line"><span>ارزش افزوده:</span><span><?= money($order['tax_amount']) ?></span></div>
      <div class="sum-line"><span>هزینه ارسال:</span><span><?= money($order['shipping']) ?></span></div>
      <div class="sum-line total"><span>مبلغ کل سفارش:</span><span><?= money($order['total']) ?></span></div>
    </div>

    <div class="card">
      <h3 class="card-title">به‌روزرسانی وضعیت و اطلاعات ارسال</h3>
      <form method="POST" action="index.php" data-confirm-canceled>
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order_update">
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
        <div class="grid-2">
          <div class="input-group">
            <label for="od-status">وضعیت سفارش</label>
            <select id="od-status" name="status" data-original="<?= e($order[\'status\']) ?>">
              <?php foreach (order_statuses() as $k => $label): ?>
                <option value="<?= $k ?>" <?= $order['status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="input-group">
            <label>وضعیت پرداخت</label>
            <select name="payment_status">
              <?php foreach (payment_statuses() as $k => $label): ?>
                <option value="<?= $k ?>" <?= $order['payment_status'] === $k ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="grid-2">
          <div class="input-group">
            <label>کد رهگیری مرسوله / بارنامه</label>
            <input type="text" name="tracking_code" value="<?= e($order['tracking_code']) ?>" placeholder="TRK-123456">
          </div>
          <div class="input-group">
            <label>روش پرداخت ثبت‌شده</label>
            <input type="text" value="<?= $order['payment_method'] === 'credit' ? 'تسویه اعتباری' : ($order['payment_method'] === 'wallet' ? 'اعتبار کارپوشه' : 'انتقال بانکی') ?>" disabled>
          </div>
        </div>
        <div class="input-group">
          <label>پیام / یادداشت برای خریدار (در پنل خریدار نمایش داده می‌شود)</label>
          <textarea name="admin_note" rows="3"><?= e($order['admin_note']) ?></textarea>
        </div>
        <div class="form-actions">
          <button class="btn btn-primary" type="submit">💾 ثبت به‌روزرسانی</button>
          <button class="btn btn-danger" type="button" data-confirm="این سفارش و صورتحساب آن حذف شود؟" data-form="delOrder">🗑️ حذف سفارش</button>
        </div>
      </form>
      <form id="delOrder" method="POST" action="index.php" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="order_delete">
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
      </form>
    </div>

    <?php if ($logRows): ?>
      <div class="card">
        <h3 class="card-title">تاریخچه اقدامات روی سفارش</h3>
        <ul class="log-list">
          <?php foreach ($logRows as $l): ?>
            <li>
              <strong><?= e($l['actor_name']) ?></strong>
              <span><?= e($l['details']) ?></span>
              <small><?= jdate($l['created_at'], true) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>

  <aside>
    <div class="card">
      <h3 class="card-title">مشتری</h3>
      <?php if ($customer): ?>
        <div class="customer-head">
          <div class="side-avatar"><?= e(initials($customer['company'] ?: $customer['name'])) ?></div>
          <div>
            <strong><?= e($customer['company'] ?: $customer['name']) ?></strong>
            <small><?= e($customer['name']) ?></small>
          </div>
        </div>
        <div class="kv"><span>تلفن:</span><strong class="mono"><?= e($customer['phone']) ?></strong></div>
        <div class="kv"><span>ایمیل:</span><strong><?= e($customer['email'] ?: '—') ?></strong></div>
        <div class="kv"><span>شناسه ملی:</span><strong class="mono"><?= e($customer['national_id'] ?: '—') ?></strong></div>
        <div class="kv"><span>اعتبار کارپوشه:</span><strong><?= money($customer['credit']) ?></strong></div>
        <div class="kv"><span>مجموع خرید تأییدشده:</span><strong><?= money($paidTotal) ?></strong></div>
        <div class="flex-gap wrap">
          <a class="btn btn-sm btn-primary" href="index.php?page=admin_user&id=<?= (int)$customer['id'] ?>">پرونده مشتری</a>
          <a class="btn btn-sm btn-secondary" href="tel:<?= e($customer['phone']) ?>">تماس</a>
        </div>
        <?php if ($customerOrders): ?>
          <h4 class="sub-title">آخرین سفارش‌های این مشتری</h4>
          <div class="note-list">
            <?php foreach ($customerOrders as $co): ?>
              <a class="note-item" href="index.php?page=admin_order&id=<?= (int)$co['id'] ?>">
                <strong class="mono"><?= e($co['order_no']) ?></strong>
                <p><?= money_short($co['total']) ?> — <?= e(order_status_label($co['status'])) ?></p>
                <small><?= jdate($co['created_at']) ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="kv"><span>خریدار مهمان:</span><strong><?= e($order['customer_name']) ?></strong></div>
        <div class="kv"><span>شرکت:</span><strong><?= e($order['company'] ?: '—') ?></strong></div>
        <div class="kv"><span>تلفن:</span><strong class="mono"><?= e($order['phone']) ?></strong></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">اطلاعات ارسال و مالی</h3>
      <div class="kv"><span>نشانی تحویل:</span><strong><?= e($order['address']) ?></strong></div>
      <div class="kv"><span>استان / شهر:</span><strong><?= e(($order['province'] ?: '—') . ' / ' . ($order['city'] ?: '—')) ?></strong></div>
      <div class="kv"><span>شناسه ملی خریدار:</span><strong class="mono"><?= e($order['tax_id'] ?: '—') ?></strong></div>
      <?php if ($order['note']): ?>
        <div class="kv"><span>توضیح خریدار:</span><strong><?= e($order['note']) ?></strong></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">سند مالیاتی</h3>
      <?php if ($invoice): ?>
        <div class="kv"><span>شماره فاکتور:</span><strong class="mono"><?= e($invoice['invoice_no']) ?></strong></div>
        <div class="kv"><span>شناسه یکتا:</span><strong class="mono small"><?= e($invoice['tax_unique_id']) ?></strong></div>
        <div class="kv"><span>وضعیت:</span><strong class="status success"><?= e($invoice['status']) ?></strong></div>
        <a class="btn btn-primary btn-sm" href="index.php?page=invoice&id=<?= e($invoice['tax_unique_id']) ?>">مشاهده / چاپ</a>
      <?php else: ?>
        <div class="alert warn">برای این سفارش صورتحساب صادر نشده است.</div>
        <form method="POST" action="index.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="order_issue_invoice">
          <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
          <button class="btn btn-green btn-sm" type="submit">🧾 صدور صورتحساب الکترونیکی</button>
        </form>
      <?php endif; ?>
    </div>
  </aside>
</div>
