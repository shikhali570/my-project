<?php
/** صفحه موفقیت ثبت سفارش */
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
?>

<div class="success-box">
  <div class="success-icon">✅</div>
  <h1>سفارش شما با موفقیت ثبت شد</h1>
  <p>شماره سفارش: <strong class="mono"><?= e($order['order_no']) ?></strong> — تاریخ: <?= jdate($order['created_at'], true) ?></p>
  <p class="mini-note">صورتحساب الکترونیکی سفارش شما صادر و در سامانه مؤدیان ثبت شد. اعتبار ارزش افزوده آن به کارپوشه خریدار منتقل می‌گردد.</p>

  <div class="success-actions">
    <?php if ($invoice): ?>
      <a class="btn btn-primary" href="index.php?page=invoice&id=<?= e($invoice['tax_unique_id']) ?>">🧾 مشاهده و چاپ صورتحساب</a>
    <?php endif; ?>
    <?php if (is_logged_in()): ?>
      <a class="btn btn-secondary" href="index.php?page=panel_order&no=<?= e($order['order_no']) ?>">📦 پیگیری در پنل خریدار</a>
    <?php else: ?>
      <a class="btn btn-secondary" href="index.php?page=track&no=<?= e($order['order_no']) ?>">🔍 پیگیری سفارش</a>
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
            <td>
              <strong><?= e($r['name']) ?></strong>
              <div class="mini-note mono">شناسه: <?= e($r['tax_id']) ?></div>
            </td>
            <td><?= fa_num($r['qty']) ?></td>
            <td><?= money($r['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div class="sum-line"><span>جمع اقلام:</span><span><?= money($order['subtotal']) ?></span></div>
    <?php if ($order['discount'] > 0): ?><div class="sum-line discount"><span>تخفیف:</span><span>− <?= money($order['discount']) ?></span></div><?php endif; ?>
    <div class="sum-line"><span>ارزش افزوده:</span><span><?= money($order['tax_amount']) ?></span></div>
    <div class="sum-line"><span>ارسال:</span><span><?= $order['shipping'] > 0 ? money($order['shipping']) : 'رایگان' ?></span></div>
    <div class="sum-line total"><span>مبلغ نهایی:</span><span><?= money($order['total']) ?></span></div>
  </div>

  <div class="card">
    <h3 class="card-title">اطلاعات پرداخت و ارسال</h3>
    <div class="kv"><span>وضعیت سفارش:</span><strong class="status <?= order_status_class($order['status']) ?>"><?= e(order_status_label($order['status'])) ?></strong></div>
    <div class="kv"><span>وضعیت پرداخت:</span><strong><?= e(payment_status_label($order['payment_status'])) ?></strong></div>
    <div class="kv"><span>روش پرداخت:</span><strong><?= $order['payment_method'] === 'credit' ? 'تسویه اعتباری ۳۰ روزه' : ($order['payment_method'] === 'wallet' ? 'کسر از اعتبار کارپوشه' : 'انتقال بانکی') ?></strong></div>
    <div class="kv"><span>تحویل‌گیرنده:</span><strong><?= e($order['customer_name']) ?></strong></div>
    <div class="kv"><span>تماس:</span><strong class="mono"><?= e($order['phone']) ?></strong></div>
    <div class="kv"><span>نشانی:</span><strong><?= e($order['address']) ?></strong></div>
    <div class="bank-box">
      <strong>اطلاعات واریز وجه</strong>
      <p class="mono"><?= e(settings('bank_info')) ?></p>
      <small>پس از واریز، شماره پیگیری را در پنل خریدار یا با پشتیبانی <?= e(settings('phone')) ?> به اشتراک بگذارید.</small>
    </div>
  </div>
</div>
