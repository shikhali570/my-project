<?php
/** صفحه ثبت سفارش و وضعیت پرداخت */
$no = get('no');
$stmt = $db->prepare('SELECT * FROM orders WHERE order_no = ?');
$stmt->execute([$no]);
$order = $stmt->fetch();

if (!$order) {
    echo '<div class="empty-state"><span>🧾</span><h3>سفارش یافت نشد</h3><a class="btn btn-primary" href="index.php?page=home">بازگشت به فروشگاه</a></div>';
    return;
}

$inv = $db->prepare('SELECT * FROM invoices WHERE order_id = ?');
$inv->execute([$order['id']]);
$invoice = $inv->fetch();
$items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$order['id']]);
$rows = $items->fetchAll();
$lastMellatAttempt = $order['payment_method'] === 'mellat' ? mellat_latest_attempt($db, (int)$order['id']) : null;
$currentOrderUser = current_user();
$canManageOrderPayment = is_admin()
    || ($currentOrderUser && !empty($order['user_id']) && (int)$order['user_id'] === (int)$currentOrderUser['id'])
    || (empty($order['user_id']) && (int)($_SESSION['recent_order_id'] ?? 0) === (int)$order['id']);
$attemptStatusLabels = [
    'redirected' => 'در انتظار بازگشت از درگاه', 'verifying' => 'در حال بررسی پاسخ درگاه',
    'verification_pending' => 'در انتظار تأیید بانکی',
    'verified' => 'تأیید اولیه؛ در انتظار تسویه',
    'settle_pending' => 'در انتظار تسویه نهایی',
    'request_failed' => 'شروع پرداخت ناموفق',
    'declined' => 'پرداخت تکمیل نشد',
    'verify_failed' => 'تأیید پرداخت ناموفق',
    'paid' => 'پرداخت تأیید شده',
];
?>

<div class="success-box">
  <div class="success-icon">✅</div>
  <h1>سفارش شما ثبت شد</h1>
  <p>شماره سفارش: <strong class="mono"><?= e($order['order_no']) ?></strong> — تاریخ: <?= jdate($order['created_at'], true) ?></p>
  <p class="mini-note">صورتحساب الکترونیکی سفارش شما صادر و در سامانه مؤدیان ثبت شد. وضعیت سفارش از همین صفحه یا کارپوشه خریدار قابل پیگیری است.</p>

  <?php if ($order['payment_method'] === 'mellat' && $order['payment_status'] === 'paid'): ?>
    <div class="alert success"><strong>پرداخت آنلاین تأیید شد.</strong> به‌پرداخت ملت نتیجهٔ نهایی پرداخت را ثبت کرده است.</div>
  <?php elseif ($order['payment_method'] === 'mellat'): ?>
    <?php $attemptState = $lastMellatAttempt['status'] ?? ''; ?>
    <div class="alert <?= in_array($attemptState, ['verifying', 'verification_pending', 'verified', 'settle_pending'], true) ? 'info' : 'warn' ?>">
      <strong>وضعیت پرداخت: <?= e($attemptStatusLabels[$attemptState] ?? 'پرداخت انجام نشده') ?>.</strong>
      <?php if (in_array($attemptState, ['verifying', 'verification_pending', 'verified', 'settle_pending'], true)): ?>
        نتیجهٔ نهایی از درگاه هنوز تأیید نشده است؛ تا تأیید نهایی، سفارش پرداخت‌شده محسوب نمی‌شود.
      <?php else: ?>
        سفارش ثبت شده است، اما پرداخت آنلاین تکمیل نشده است.
      <?php endif; ?>
    </div>
    <?php if ($canManageOrderPayment && $order['status'] !== 'canceled'): ?>
      <form method="POST" action="index.php" class="inline-payment-action">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="mellat_continue">
        <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
        <button class="btn btn-primary" type="submit">
          <?= in_array($attemptState, ['redirected', 'verifying', 'verification_pending', 'verified', 'settle_pending'], true) ? 'ادامه پرداخت / بررسی نتیجه درگاه' : 'شروع یا تلاش دوباره پرداخت' ?>
        </button>
      </form>
    <?php endif; ?>
  <?php endif; ?>

  <div class="success-actions">
    <?php if ($invoice): ?>
      <a class="btn btn-primary" href="index.php?page=invoice&amp;id=<?= e($invoice['tax_unique_id']) ?>">🧾 مشاهده و چاپ صورتحساب</a>
    <?php endif; ?>
    <?php if (is_logged_in()): ?>
      <a class="btn btn-secondary" href="index.php?page=panel_order&amp;no=<?= e($order['order_no']) ?>">📦 پیگیری در پنل خریدار</a>
    <?php else: ?>
      <a class="btn btn-secondary" href="index.php?page=track&amp;no=<?= e($order['order_no']) ?>">🔍 پیگیری سفارش</a>
    <?php endif; ?>
    <a class="btn btn-outline" href="index.php?page=home">ادامه خرید</a>
  </div>
</div>

<div class="detail-grid">
  <div class="card">
    <h3 class="card-title">اقلام سفارش</h3>
    <table class="data-table">
      <thead><tr><th>#</th><th>کالا</th><th>تعداد</th><th>مبلغ</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $i => $r): ?>
          <tr>
            <td><?= fa_num($i + 1) ?></td>
            <td><strong><?= e($r['name']) ?></strong><div class="mini-note mono">شناسه: <?= e($r['tax_id']) ?></div></td>
            <td><?= fa_num($r['qty']) ?></td>
            <td><?= money($r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="sum-line"><span>جمع اقلام:</span><span><?= money($order['subtotal']) ?></span></div>
    <?php if ($order['discount'] > 0): ?><div class="sum-line discount"><span>تخفیف کد:</span><span>− <?= money($order['discount']) ?></span></div><?php endif; ?>
    <div class="sum-line"><span>ارزش افزوده:</span><span><?= money($order['tax_amount']) ?></span></div>
    <div class="sum-line"><span>ارسال:</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'رایگان' ?></span></div>
    <div class="sum-line total"><span>مبلغ نهایی:</span><span><?= money($order['total']) ?></span></div>
  </div>

  <div class="card">
    <h3 class="card-title">اطلاعات پرداخت و ارسال</h3>
    <div class="kv"><span>وضعیت سفارش:</span><strong class="status <?= order_status_class($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></strong></div>
    <div class="kv"><span>وضعیت پرداخت:</span><strong><?= e(payment_status_label($order['payment_status'])) ?></strong></div>
    <div class="kv"><span>روش پرداخت:</span><strong><?= e(payment_method_label($order['payment_method'])) ?></strong></div>
    <?php if ($lastMellatAttempt && !empty($lastMellatAttempt['sale_reference_id'])): ?>
      <div class="kv"><span>شماره مرجع بانک:</span><strong class="mono"><?= e($lastMellatAttempt['sale_reference_id']) ?></strong></div>
    <?php endif; ?>
    <div class="kv"><span>تحویل‌گیرنده:</span><strong><?= e($order['customer_name']) ?></strong></div>
    <div class="kv"><span>تماس:</span><strong class="mono"><?= e($order['phone']) ?></strong></div>
    <div class="kv"><span>نشانی:</span><strong><?= e($order['address']) ?></strong></div>
    <?php if ($order['payment_method'] === 'transfer' && $order['payment_status'] !== 'paid'): ?>
      <div class="bank-box">
        <strong>اطلاعات واریز وجه</strong>
        <p class="mono"><?= e(settings('bank_info')) ?></p>
        <small>پس از واریز، شماره پیگیری را در پنل خریدار یا با پشتیبانی <?= e(settings('phone')) ?> به اشتراک بگذارید.</small>
      </div>
    <?php endif; ?>
  </div>
</div>
