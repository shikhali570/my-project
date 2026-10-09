<?php
/** نمایش و چاپ صورتحساب الکترونیکی (A4) */
$taxUid = get('id');
$stmt = $db->prepare('SELECT * FROM invoices WHERE tax_unique_id = ? OR invoice_no = ?');
$stmt->execute([$taxUid, $taxUid]);
$inv = $stmt->fetch();

if (!$inv) {
    echo '<div class="empty-state"><span>🧾</span><h3>صورتحسابی با این شماره یافت نشد</h3><a class="btn btn-primary" href="index.php?page=home">بازگشت به فروشگاه</a></div>';
    return;
}

// کنترل دسترسی: خریدار فقط فاکتورهای خودش
$me = current_user();
if ($me && $me['role'] === 'buyer' && (int)$inv['user_id'] !== (int)$me['id']) {
    echo '<div class="alert danger">شما به این صورتحساب دسترسی ندارید.</div>';
    return;
}

$items = json_decode($inv['items_json'], true) ?: [];
$order = null;
if ($inv['order_id']) {
    $o = $db->prepare('SELECT * FROM orders WHERE id = ?');
    $o->execute([$inv['order_id']]);
    $order = $o->fetch();
}

// مبالغ کل از خود صورتحساب خوانده می‌شود (همان مبالغی که هنگام ثبت سفارش ذخیره شده)
$subtotal   = (int)$inv['subtotal'];
$vatAmount  = (int)$inv['tax_amount'];
$grandTotal = (int)$inv['total_amount'];
$shipping   = $order ? (int)$order['shipping'] : 0;
$discount   = $order ? (int)$order['discount'] : max(0, $subtotal + $vatAmount + $shipping - $grandTotal);
// ردیف‌ها با سهم تخفیف و ارزش افزوده‌ای تفکیک می‌شوند که جمعشان دقیقاً برابر جمع کل است
$lines      = invoice_line_breakdown($items, $discount, $vatAmount);
$vatPercent = fa_num((float)settings('vat_rate', 10), 0);
$verifyUrl  = invoice_verify_url($inv['tax_unique_id']);
$paymentMethod = $order ? ($order['payment_method'] ?: 'transfer') : 'transfer';
$bankLines  = array_values(array_filter(array_map('trim', explode('|', (string)settings('bank_info', '')))));
$showBank   = $order && $paymentMethod === 'transfer' && $bankLines;

// بازگشت: مدیر به پرونده سفارش مدیریت، خریدار به سفارش‌های خودش
if ($order && is_admin()) {
    $backUrl = 'index.php?page=admin_order&id=' . (int)$order['id'];
} elseif ($order) {
    $backUrl = 'index.php?page=panel_order&no=' . $order['order_no'];
} else {
    $backUrl = 'index.php?page=home';
}
?>

<div class="invoice-toolbar no-print">
  <a class="btn btn-secondary btn-sm" href="<?= e($backUrl) ?>">← بازگشت</a>
  <div class="flex-gap">
    <button class="btn btn-primary btn-sm" type="button" onclick="window.print()">🖨️ چاپ / ذخیره PDF</button>
    <button class="btn btn-secondary btn-sm" type="button" onclick="copyText('<?= e($inv['tax_unique_id']) ?>', this)">📋 کپی شناسه یکتا</button>
  </div>
</div>

<div class="invoice-box-printable">
  <div class="inv-head">
    <div>
      <h2>صورتحساب الکترونیکی فروش کالا و خدمات</h2>
      <small>مطابق ماده ۵ قانون پایانه‌های فروشگاهی و سامانه مؤدیان — صورتحساب نوع ۱</small>
    </div>
    <div class="inv-head-meta">
      <div class="inv-logo" aria-hidden="true">🏗️</div>
      <div>
        <strong><?= e(settings('site_name')) ?></strong>
        <small><?= e(settings('address')) ?></small>
      </div>
    </div>
  </div>

  <div class="inv-status-bar">
    <div><span>شماره منحصربه‌فرد مالیاتی</span><code><?= e($inv['tax_unique_id']) ?></code></div>
    <div><span>شماره صورتحساب</span><code><?= e($inv['invoice_no']) ?></code></div>
    <div><span>تاریخ و ساعت صدور</span><strong><?= jdate($inv['created_at'], true) ?></strong></div>
    <div><span>وضعیت ثبت</span><strong class="status success"><?= e($inv['status']) ?></strong></div>
  </div>

  <div class="inv-parties">
    <div>
      <h4>فروشنده</h4>
      <strong><?= e(settings('site_name')) ?></strong>
      <div>شناسه ملی: <?= e(settings('company_national_id')) ?> | کد اقتصادی: <?= e(settings('company_economic_code')) ?></div>
      <div>تلفن: <?= e(settings('phone')) ?> | ایمیل: <?= e(settings('email')) ?></div>
      <div>نشانی: <?= e(settings('address')) ?></div>
    </div>
    <div>
      <h4>خریدار</h4>
      <strong><?= e($inv['buyer_name']) ?></strong>
      <div>شناسه ملی / کد اقتصادی: <?= e($inv['buyer_tax_id'] ?: '—') ?></div>
      <div>تلفن رابط: <span class="mono"><?= e($inv['buyer_phone']) ?></span></div>
      <?php if ($order): ?>
        <div>نشانی تحویل: <?= e(implode('، ', array_filter([$order['province'] ?? '', $order['city'] ?? '', $order['address'] ?? '']))) ?></div>
        <div>شماره سفارش: <span class="mono"><?= e($order['order_no']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <div class="inv-table-wrap">
  <table class="data-table inv-table">
    <thead>
      <tr>
        <th scope="col">ردیف</th>
        <th scope="col">شرح کالا و شناسه مؤدیان</th>
        <th scope="col">واحد</th>
        <th scope="col">تعداد</th>
        <th scope="col">قیمت واحد</th>
        <th scope="col">جمع ردیف</th>
        <th scope="col">تخفیف</th>
        <th scope="col">ارزش افزوده <?= $vatPercent ?>٪</th>
        <th scope="col">مبلغ نهایی</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lines as $i => $it): ?>
        <tr>
          <td><?= fa_num($i + 1) ?></td>
          <td>
            <strong><?= e($it['name']) ?></strong>
            <div class="inv-item-meta"><?= e($it['brand'] ?: '—') ?> | <span class="mono">شناسه مؤدیان: <?= e($it['tax_id'] ?: '—') ?></span></div>
          </td>
          <td><?= ($it['unit'] ?? '') !== '' ? e($it['unit']) : '—' ?></td>
          <td><?= fa_num($it['qty']) ?></td>
          <td><?= fa_num($it['price']) ?></td>
          <td><?= fa_num($it['total']) ?></td>
          <td><?= $it['discount'] > 0 ? fa_num($it['discount']) : '—' ?></td>
          <td><?= fa_num($it['vat']) ?></td>
          <td><strong><?= fa_num($it['final']) ?></strong></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$lines): ?>
        <tr><td colspan="9" class="empty-mini">ردیفی برای این صورتحساب ثبت نشده است.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>

  <div class="inv-totals">
    <div class="inv-note">
      <strong>📌 نکات مالیاتی</strong>
      <ul>
        <li>این صورتحساب به صورت الکترونیکی در سامانه مؤدیان ثبت شده و اعتبار ارزش افزوده آن به کارپوشه خریدار منتقل شده است.</li>
        <li>مبلغ هر ردیف پس از تخفیف و ارزش افزوده محاسبه شده و جمع ردیف‌ها با مبلغ کل صورتحساب برابر است.</li>
        <li>در صورت مغایرت، حداکثر تا ۷ روز از تاریخ صدور با واحد فروش تماس بگیرید.</li>
      </ul>
      <?php if ($showBank): ?>
        <div class="inv-pay">
          <strong>💳 اطلاعات پرداخت</strong>
          <?php foreach ($bankLines as $bankLine): ?>
            <div><?= e($bankLine) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div>
      <div class="inv-sum">
        <div><span>جمع اقلام (قبل از تخفیف):</span><span><?= money($subtotal) ?></span></div>
        <?php if ($discount > 0): ?>
          <div><span>تخفیف<?= ($order && !empty($order['coupon_code'])) ? ' (کد ' . e($order['coupon_code']) . ')' : '' ?>:</span><span>− <?= money($discount) ?></span></div>
        <?php endif; ?>
        <div><span>مبلغ مشمول مالیات:</span><span><?= money($subtotal - $discount) ?></span></div>
        <div><span>ارزش افزوده (<?= $vatPercent ?>٪):</span><span><?= money($vatAmount) ?></span></div>
        <?php if ($order): ?>
          <div><span>هزینه ارسال:</span><span><?= $shipping > 0 ? money($shipping) : 'رایگان' ?></span></div>
        <?php endif; ?>
        <div class="inv-grand"><span>مبلغ کل قابل پرداخت:</span><span><?= money($grandTotal) ?></span></div>
      </div>
      <div class="inv-words"><span>مبلغ قابل پرداخت به حروف:</span><strong><?= e(amount_in_words($grandTotal)) ?> تومان</strong></div>
      <?php if ($order): ?>
        <div class="inv-pay-status">
          <span>روش پرداخت: <strong><?= e(payment_method_label($paymentMethod)) ?></strong></span>
          <span>وضعیت پرداخت: <strong><?= e(payment_status_label($order['payment_status'])) ?></strong></span>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="inv-sign">
    <div>
      <span>مهر و امضای فروشنده</span>
      <div class="sign-box"></div>
    </div>
    <div>
      <span>امضای خریدار</span>
      <div class="sign-box"></div>
    </div>
    <div class="inv-qr">
      <div id="inv-qr" class="inv-qr-code" data-verify="<?= e($verifyUrl) ?>" role="img" aria-label="کد QR تأیید صورتحساب"></div>
      <small>اسکن برای تأیید صورتحساب</small>
    </div>
  </div>
  <p class="inv-verify">نشانی تأیید صورتحساب: <span class="mono" dir="ltr"><?= e($verifyUrl) ?></span></p>
</div>

<script src="vendor/qrcode.js?v=<?= APP_VERSION ?>"></script>
<script>
(function () {
  var box = document.getElementById('inv-qr');
  if (!box || typeof qrcode !== 'function') {
    return;
  }
  var qr = qrcode(0, 'M');
  qr.addData(box.getAttribute('data-verify'));
  qr.make();
  box.innerHTML = qr.createSvgTag(3, 0, 'کد QR تأیید صورتحساب');
})();
</script>
