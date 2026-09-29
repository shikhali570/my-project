<?php
/** جزئیات سفارش خریدار + پیگیری */
require_buyer();
$me = current_user();
$no = get('no');
$stmt = $db->prepare('SELECT * FROM orders WHERE order_no = ? AND user_id = ?');
$stmt->execute([$no, $me['id']]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="empty-state"><span>📦</span><h3>سفارش یافت نشد</h3><a class="btn btn-primary" href="index.php?page=panel_orders">بازگشت به لیست سفارش‌ها</a></div>';
    return;
}

$items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$order['id']]);
$rows = $items->fetchAll();

$inv = $db->prepare('SELECT * FROM invoices WHERE order_id = ?');
$inv->execute([$order['id']]);
$invoice = $inv->fetch();

$logs = $db->prepare("SELECT * FROM activity_logs WHERE entity = 'order' AND entity_id = ? ORDER BY id DESC LIMIT 8");
$logs->execute([$order['id']]);
$logRows = $logs->fetchAll();

$steps = order_timeline_steps();
$currentIdx = array_search($order['status'], $steps, true);
$vatPercent = fa_num((float)settings('vat_rate', 10), 0);
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title">سفارش <span class="mono"><?= e($order['order_no']) ?></span></h2>
    <p class="sec-sub">ثبت‌شده در <?= jdate($order['created_at'], true) ?> | آخرین به‌روزرسانی: <?= jdate($order['updated_at'], true) ?></p>
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
    <div class="progress-bar"><span style="width: <?= order_progress($order['status']) ?>%"></span></div>
  </div>
<?php else: ?>
  <div class="alert danger">این سفارش لغو شده است. در صورت نیاز به ثبت مجدد، اقلام را از سبد خرید یا علاقه‌مندی‌ها انتخاب کنید.</div>
<?php endif; ?>

<div class="detail-grid">
  <div class="card">
    <h3 class="card-title">اقلام سفارش</h3>
    <table class="data-table">
      <thead><tr><th>#</th><th>کالا</th><th>قیمت واحد</th><th>تعداد</th><th>مبلغ</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= fa_num($i + 1) ?></td>
            <td>
              <strong><?= e($r['name']) ?></strong>
              <div class="mini-note"><?= e($r['brand']) ?> | <span class="mono"><?= e($r['tax_id']) ?></span></div>
            </td>
            <td><?= money($r['price']) ?></td>
            <td><?= fa_num($r['qty']) ?></td>
            <td><strong><?= money($r['total']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="sum-line"><span>جمع اقلام:</span><span><?= money($order['subtotal']) ?></span></div>
    <?php if ($order['discount'] > 0): ?>
      <div class="sum-line discount"><span>تخفیف <?= $order['coupon_code'] ? '(' . e($order['coupon_code']) . ')' : '' ?>:</span><span>− <?= money($order['discount']) ?></span></div>
    <?php endif; ?>
    <div class="sum-line"><span>ارزش افزوده (<?= $vatPercent ?>٪):</span><span><?= money($order['tax_amount']) ?></span></div>
    <div class="sum-line"><span>هزینه ارسال:</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'رایگان' ?></span></div>
    <div class="sum-line total"><span>مبلغ کل:</span><span><?= money($order['total']) ?></span></div>
  </div>

  <div>
    <div class="card">
      <h3 class="card-title">اطلاعات ارسال و مالی</h3>
      <div class="kv"><span>تحویل‌گیرنده:</span><strong><?= e($order['customer_name']) ?></strong></div>
      <div class="kv"><span>شرکت:</span><strong><?= e($order['company'] ?: '—') ?></strong></div>
      <div class="kv"><span>تماس:</span><strong class="mono"><?= e($order['phone']) ?></strong></div>
      <div class="kv"><span>شناسه ملی:</span><strong class="mono"><?= e($order['tax_id'] ?: '—') ?></strong></div>
      <div class="kv"><span>نشانی:</span><strong><?= e($order['address']) ?></strong></div>
      <div class="kv"><span>روش پرداخت:</span><strong><?= $order['payment_method'] === 'credit' ? 'تسویه اعتباری' : ($order['payment_method'] === 'wallet' ? 'کسر از اعتبار کارپوشه' : 'انتقال بانکی') ?></strong></div>
      <div class="kv"><span>کد رهگیری مرسوله:</span><strong class="mono"><?= e($order['tracking_code'] ?: 'در انتظار ارسال') ?></strong></div>
      <?php if ($order['note']): ?>
        <div class="kv"><span>توضیحات شما:</span><strong><?= e($order['note']) ?></strong></div>
      <?php endif; ?>
      <?php if ($order['admin_note']): ?>
        <div class="alert info">پیام واحد فروش: <?= e($order['admin_note']) ?></div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">سند مالیاتی</h3>
      <?php if ($invoice): ?>
        <div class="kv"><span>شماره صورتحساب:</span><strong class="mono"><?= e($invoice['invoice_no']) ?></strong></div>
        <div class="kv"><span>شناسه یکتای مالیاتی:</span><strong class="mono"><?= e($invoice['tax_unique_id']) ?></strong></div>
        <div class="kv"><span>وضعیت:</span><strong class="status success"><?= e($invoice['status']) ?></strong></div>
        <a class="btn btn-primary btn-sm" href="index.php?page=invoice&id=<?= e($invoice['tax_unique_id']) ?>">🧾 مشاهده و چاپ صورتحساب</a>
      <?php else: ?>
        <div class="alert warn">صورتحساب این سفارش در حال صدور است.</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3 class="card-title">اقدامات</h3>
      <div class="flex-gap wrap">
        <?php if (in_array($order['status'], ['pending', 'approved'], true)): ?>
          <button class="btn btn-sm btn-danger" data-confirm="آیا از لغو این سفارش مطمئن هستید؟" data-form="cancelForm">لغو سفارش</button>
        <?php endif; ?>
        <a class="btn btn-sm btn-secondary" href="index.php?page=rfq&items=<?= urlencode('سفارش ' . $order['order_no'] . ' مجدداً نیاز است') ?>">ثبت استعلام مجدد</a>
        <a class="btn btn-sm btn-secondary" href="index.php?page=contact">تماس با پشتیبانی</a>
      </div>
      <form id="cancelForm" method="POST" action="index.php" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="buyer_cancel_order">
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
      </form>
    </div>

    <?php if ($logRows): ?>
      <div class="card">
        <h3 class="card-title">تاریخچه تغییرات</h3>
        <ul class="log-list">
          <?php foreach ($logRows as $l): ?>
            <li>
              <strong><?= e($l['action']) ?></strong>
              <span><?= e($l['details']) ?></span>
              <small><?= jdate($l['created_at'], true) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
</div>
