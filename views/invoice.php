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
$vatPercent = fa_num((float)settings('vat_rate', 10), 0);
$sumTax = 0;
foreach ($items as $it) {
    $sumTax += (int)round($it['total'] * vat_rate());
}
?>

<div class="invoice-toolbar no-print">
  <a class="btn btn-secondary btn-sm" href="<?= $order ? 'index.php?page=panel_order&no=' . e($order['order_no']) : 'index.php?page=home' ?>">← بازگشت</a>
  <div class="flex-gap">
    <button class="btn btn-primary btn-sm" onclick="window.print()">🖨️ چاپ رسمی A4</button>
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
      <div class="inv-logo">🏗️</div>
      <div>
        <strong><?= e(settings('site_name')) ?></strong>
        <small><?= e(settings('address')) ?></small>
      </div>
    </div>
  </div>

  <div class="inv-status-bar">
    <div><span>شماره منحصربه‌فرد مالیاتی</span><code><?= e($inv['tax_unique_id']) ?></code></div>
    <div><span>شماره صورتحساب</span><code><?= e($inv['invoice_no']) ?></code></div>
    <div><span>تاریخ صدور</span><strong><?= jdate($inv['created_at'], true) ?></strong></div>
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
        <div>نشانی تحویل: <?= e($order['address']) ?></div>
        <div>شماره سفارش: <span class="mono"><?= e($order['order_no']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <table class="data-table inv-table">
    <thead>
      <tr>
        <th>ردیف</th>
        <th>کد کالا (شناسه مؤدیان)</th>
        <th>شرح کالا یا خدمات</th>
        <th>برند</th>
        <th>تعداد</th>
        <th>قیمت واحد (ریال/تومان)</th>
        <th>مبلغ کل</th>
        <th>ارزش افزوده <?= $vatPercent ?>٪</th>
        <th>مجموع</th>
      </tr>
    </thead>
    <tbody>
      <?php $sub = 0; foreach ($items as $i => $it):
          $lineTax = (int)round($it['total'] * vat_rate());
          $sub += (int)$it['total']; ?>
        <tr>
          <td><?= fa_num($i + 1) ?></td>
          <td class="mono"><?= e($it['tax_id']) ?></td>
          <td><?= e($it['name']) ?></td>
          <td><?= e($it['brand'] ?: '—') ?></td>
          <td><?= fa_num($it['qty']) ?></td>
          <td><?= fa_num($it['price']) ?></td>
          <td><?= fa_num($it['total']) ?></td>
          <td><?= fa_num($lineTax) ?></td>
          <td><strong><?= fa_num((int)$it['total'] + $lineTax) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="inv-totals">
    <div class="inv-note">
      <strong>📌 نکات مالیاتی</strong>
      <ul>
        <li>این صورتحساب به صورت الکترونیکی در سامانه مؤدیان ثبت و اعتبار ارزش افزوده آن به کارپوشه خریدار منتقل شده است.</li>
        <li>مبلغ قابل پرداخت نهایی پس از احتساب تخفیف‌ها، ارزش افزوده و هزینه ارسال محاسبه گردیده است.</li>
        <li>در صورت مغایرت، حداکثر تا ۷ روز از تاریخ صدور با واحد فروش تماس بگیرید.</li>
      </ul>
    </div>
    <div class="inv-sum">
      <div><span>جمع اقلام:</span><span><?= money($inv['subtotal']) ?></span></div>
      <?php if ($order && $order['discount'] > 0): ?>
        <div><span>تخفیف اعمال‌شده:</span><span>− <?= money($order['discount']) ?></span></div>
      <?php endif; ?>
      <div><span>مالیات بر ارزش افزوده:</span><span><?= money($inv['tax_amount']) ?></span></div>
      <?php if ($order && $order['shipping'] > 0): ?>
        <div><span>هزینه ارسال:</span><span><?= money($order['shipping']) ?></span></div>
      <?php endif; ?>
      <div class="inv-grand"><span>مبلغ کل قابل پرداخت:</span><span><?= money($inv['total_amount']) ?></span></div>
      <?php if ($order): ?>
        <div><span>وضعیت پرداخت:</span><span><?= e(payment_status_label($order['payment_status'])) ?></span></div>
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
      <div class="fake-qr">▦</div>
      <small>کد یکتای صورتحساب<br><?= e(substr($inv['tax_unique_id'], -12)) ?></small>
    </div>
  </div>
</div>
