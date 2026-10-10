<?php
/** جزئیات سفارش خریدار: پیگیری مرحله‌ای، اقلام، ارسال و مالی، صورتحساب و اقدامات */
require_buyer();
$me = current_user();
$no = get('no');
$stmt = $db->prepare('SELECT * FROM orders WHERE order_no = ? AND user_id = ?');
$stmt->execute([$no, $me['id']]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="empty-state"><span aria-hidden="true">📦</span><h3>سفارش یافت نشد</h3><a class="btn btn-primary" href="index.php?page=panel_orders">بازگشت به لیست سفارش‌ها</a></div>';
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
$canCancel = in_array($order['status'], ['pending', 'approved'], true);
$lastMellatAttempt = $order['payment_method'] === 'mellat' ? mellat_latest_attempt($db, (int)$order['id']) : null;
$attemptStateLabels = [
    'redirected' => 'در انتظار بازگشت از درگاه', 'verifying' => 'در حال بررسی پاسخ درگاه', 'verification_pending' => 'در انتظار تأیید بانکی',
    'verified' => 'تأیید اولیه؛ در انتظار تسویه', 'settle_pending' => 'در انتظار تسویه نهایی',
    'request_failed' => 'شروع پرداخت ناموفق', 'declined' => 'پرداخت تکمیل نشد',
    'verify_failed' => 'تأیید پرداخت ناموفق', 'paid' => 'پرداخت تأیید شده',
];
$canContinueMellat = $order['payment_method'] === 'mellat' && $order['payment_status'] !== 'paid' && $order['status'] !== 'canceled';
?>

<a class="back-link" href="index.php?page=panel_orders">→ همه سفارش‌ها</a>

<div class="detail-head">
  <div>
    <h2 class="sec-title">سفارش <span class="mono"><?= e($order['order_no']) ?></span></h2>
    <p class="sec-sub">ثبت‌شده در <?= jdate($order['created_at'], true) ?> | آخرین به‌روزرسانی: <?= jdate($order['updated_at'], true) ?></p>
  </div>
  <div class="flex-gap wrap">
    <span class="status <?= order_status_class($order['status']) ?>"><?= order_status_icon($order['status']) ?> <?= e(order_status_label($order['status'])) ?></span>
    <span class="pill <?= $order['payment_status'] === 'paid' ? 'success' : 'warn' ?>"><?= e(payment_status_label($order['payment_status'])) ?></span>
    <button class="btn btn-secondary btn-sm" type="button" onclick="window.print()">🖨️ چاپ</button>
  </div>
</div>

<?php if ($order['status'] !== 'canceled'): ?>
  <section class="card">
    <div class="timeline">
      <?php foreach ($steps as $i => $s): ?>
        <div class="tl-step <?= $currentIdx !== false && $i <= $currentIdx ? 'done' : '' ?> <?= $currentIdx === $i ? 'current' : '' ?>">
          <div class="tl-dot" aria-hidden="true"><?= order_status_icon($s) ?></div>
          <div class="tl-label"><?= e(order_status_label($s)) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php else: ?>
  <div class="alert danger">این سفارش لغو شده است. در صورت نیاز به ثبت مجدد، اقلام را از کاتالوگ انتخاب کنید.</div>
<?php endif; ?>

<div class="detail-grid wide-left">
  <section class="card">
    <h3 class="card-title">اقلام سفارش</h3>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>#</th><th>کالا</th><th>قیمت واحد</th><th>تعداد</th><th>مبلغ</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= fa_num($i + 1) ?></td>
            <td class="cell-name">
              <strong><?= e($r['name']) ?></strong>
              <div class="mini-note"><?= e($r['brand']) ?> | <span class="mono"><?= e($r['tax_id']) ?></span></div>
            </td>
            <td><?= money($r['price']) ?></td>
            <td><?= fa_num($r['qty']) ?></td>
            <td><strong><?= money($r['total']) ?></strong></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table></div>

    <div class="sum-line"><span>جمع اقلام:</span><span><?= money($order['subtotal']) ?></span></div>
    <?php if ($order['discount'] > 0): ?>
      <div class="sum-line discount"><span>تخفیف <?= $order['coupon_code'] ? '(' . e($order['coupon_code']) . ')' : '' ?>:</span><span>− <?= money($order['discount']) ?></span></div>
    <?php endif; ?>
    <div class="sum-line"><span>ارزش افزوده (<?= $vatPercent ?>٪):</span><span><?= money($order['tax_amount']) ?></span></div>
    <div class="sum-line"><span>هزینه ارسال:</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'رایگان' ?></span></div>
    <div class="sum-line total"><span>مبلغ کل:</span><span><?= money($order['total']) ?></span></div>
  </section>

  <div class="detail-side">
    <section class="card">
      <h3 class="card-title">ارسال و پرداخت</h3>
      <div class="kv"><span>تحویل‌گیرنده:</span><strong><?= e($order['customer_name']) ?></strong></div>
      <div class="kv"><span>شرکت:</span><strong><?= e($order['company'] ?: '—') ?></strong></div>
      <div class="kv"><span>تماس:</span><strong class="mono"><?= e($order['phone']) ?></strong></div>
      <div class="kv"><span>شناسه ملی:</span><strong class="mono"><?= e($order['tax_id'] ?: '—') ?></strong></div>
      <div class="kv"><span>نشانی:</span><strong><?= e($order['address']) ?></strong></div>
      <div class="kv"><span>روش پرداخت:</span><strong><?= e(payment_method_label($order['payment_method'])) ?></strong></div>
      <?php if ($lastMellatAttempt): ?>
        <div class="kv"><span>وضعیت تلاش پرداخت:</span><strong><?= e($attemptStateLabels[$lastMellatAttempt['status']] ?? 'ثبت شده') ?></strong></div>
        <?php if (!empty($lastMellatAttempt['sale_reference_id'])): ?><div class="kv"><span>شماره مرجع بانک:</span><strong class="mono"><?= e($lastMellatAttempt['sale_reference_id']) ?></strong></div><?php endif; ?>
      <?php endif; ?>
      <div class="kv"><span>کد رهگیری مرسوله:</span><strong class="mono"><?= e($order['tracking_code'] ?: 'در انتظار ارسال') ?></strong></div>
      <?php if ($order['note']): ?>
        <div class="kv"><span>توضیحات شما:</span><strong><?= e($order['note']) ?></strong></div>
      <?php endif; ?>
      <?php if ($order['admin_note']): ?>
        <div class="alert info">پیام واحد فروش: <?= e($order['admin_note']) ?></div>
      <?php endif; ?>
    </section>

    <section class="card">
      <h3 class="card-title">صورتحساب و اقدامات</h3>
      <?php if ($invoice): ?>
        <div class="kv"><span>شماره صورتحساب:</span><strong class="mono"><?= e($invoice['invoice_no']) ?></strong></div>
        <div class="kv"><span>شناسه یکتای مالیاتی:</span><strong class="mono"><?= e($invoice['tax_unique_id']) ?></strong></div>
        <div class="kv"><span>وضعیت:</span><strong class="status success"><?= e($invoice['status']) ?></strong></div>
        <div class="action-row">
          <a class="btn btn-primary btn-sm" href="index.php?page=invoice&id=<?= e($invoice['tax_unique_id']) ?>">🧾 مشاهده و چاپ صورتحساب</a>
        </div>
      <?php else: ?>
        <div class="alert warn">صورتحساب این سفارش در حال صدور است.</div>
      <?php endif; ?>

      <div class="action-row">
        <?php if ($canContinueMellat): ?>
          <form method="POST" action="index.php" class="inline-payment-action">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="mellat_continue">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <button class="btn btn-sm btn-primary" type="submit">ادامه پرداخت / تلاش دوباره</button>
          </form>
        <?php endif; ?>
        <?php if ($canCancel): ?>
          <button class="btn btn-sm btn-danger" type="button" data-confirm="آیا از لغو این سفارش مطمئن هستید؟" data-form="cancelForm">لغو سفارش</button>
        <?php endif; ?>
        <a class="btn btn-sm btn-secondary" href="index.php?page=rfq&items=<?= urlencode('سفارش ' . $order['order_no'] . ' مجدداً نیاز است') ?>">ثبت استعلام مجدد</a>
        <a class="btn btn-sm btn-secondary" href="index.php?page=contact">تماس با پشتیبانی</a>
      </div>
      <form id="cancelForm" method="POST" action="index.php" class="hidden">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="buyer_cancel_order">
        <input type="hidden" name="id" value="<?= (int)$order['id'] ?>">
      </form>
    </section>

    <?php if ($logRows): ?>
      <details class="card collapsible">
        <summary>تاریخچه تغییرات (<?= fa_num(count($logRows)) ?>)</summary>
        <ul class="log-list">
          <?php foreach ($logRows as $l): ?>
            <li>
              <strong><?= e(log_action_label($l['action'])) ?></strong>
              <span><?= e($l['details']) ?></span>
              <small><?= jdate($l['created_at'], true) ?></small>
            </li>
          <?php endforeach; ?>
        </ul>
      </details>
    <?php endif; ?>
  </div>
</div>
