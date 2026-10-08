<?php
/** پیگیری سفارش با شماره سفارش یا کد رهگیری (بدون نیاز به ورود) */
$q = en_digits(get('no'));
$order = null;
$rows = [];
$invoice = null;

if ($q !== '') {
    $stmt = $db->prepare('SELECT * FROM orders WHERE order_no = ? OR tracking_code = ?');
    $stmt->execute([$q, $q]);
    $order = $stmt->fetch();
    if ($order) {
        $items = $db->prepare('SELECT * FROM order_items WHERE order_id = ?');
        $items->execute([$order['id']]);
        $rows = $items->fetchAll();
        $inv = $db->prepare('SELECT * FROM invoices WHERE order_id = ?');
        $inv->execute([$order['id']]);
        $invoice = $inv->fetch();
    }
}
$steps = order_timeline_steps();
$currentIdx = array_search($order['status'] ?? '', $steps, true);
?>

<div class="track-box">
  <h1 class="sec-title">پیگیری سفارش و صورتحساب</h1>
  <p class="sec-sub">شماره سفارش (مثلاً PSA-2509-0001) یا کد رهگیری مرسوله را وارد کنید.</p>
  <form class="track-form" method="GET" action="index.php" role="search">
    <input type="hidden" name="page" value="track">
    <label class="visually-hidden" for="track-no">شماره سفارش یا کد رهگیری</label>
    <input id="track-no" type="text" name="no" value="<?= e($q) ?>" placeholder="شماره سفارش یا کد رهگیری مرسوله" required autocomplete="off" dir="ltr">
    <button class="btn btn-primary" type="submit">🔍 پیگیری</button>
  </form>

  <?php if ($q !== '' && !$order): ?>
    <div class="alert danger">سفارشی با این شماره یافت نشد. لطفاً شماره را بررسی کنید یا با پشتیبانی تماس بگیرید.</div>
  <?php endif; ?>
</div>

<?php if ($order): ?>
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">سفارش <span class="mono"><?= e($order['order_no']) ?></span></h3>
      <div class="flex-gap">
        <span class="status <?= order_status_class($order['status']) ?>"><?= order_status_icon($order['status']) ?> <?= e(order_status_label($order['status'])) ?></span>
        <span class="pill <?= $order['payment_status'] === 'paid' ? 'success' : 'warn' ?>"><?= e(payment_status_label($order['payment_status'])) ?></span>
      </div>
    </div>

    <?php if ($order['status'] !== 'canceled'): ?>
      <div class="timeline" aria-label="مراحل سفارش">
        <?php foreach ($steps as $i => $s):
            $isCurrent = $currentIdx !== false && $i === $currentIdx;
            $isDone = $currentIdx !== false && $i <= $currentIdx; ?>
          <div class="tl-step<?= $isDone ? ' done' : '' ?><?= $isCurrent ? ' current' : '' ?>"<?= $isCurrent ? ' aria-current="step"' : '' ?>>
            <div class="tl-dot" aria-hidden="true"><?= order_status_icon($s) ?></div>
            <div class="tl-label"><?= e(order_status_label($s)) ?><?= $isCurrent ? ' <span class="visually-hidden">(مرحله فعلی)</span>' : '' ?></div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php else: ?>
      <div class="alert danger">این سفارش لغو شده است.</div>
    <?php endif; ?>

    <div class="detail-grid">
      <div>
        <h4 class="sub-title">اقلام سفارش</h4>
        <table class="data-table">
          <thead><tr><th scope="col">کالا</th><th scope="col">تعداد</th><th scope="col">مبلغ</th></tr></thead>
          <tbody>
            <?php foreach ($rows as $r): ?>
              <tr><td><?= e($r['name']) ?><div class="mini-note mono"><?= e($r['tax_id']) ?></div></td><td><?= fa_num($r['qty']) ?></td><td><?= money($r['total']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div>
        <h4 class="sub-title">اطلاعات ارسال</h4>
        <div class="kv"><span>تحویل‌گیرنده:</span><strong><?= e($order['customer_name']) ?></strong></div>
        <div class="kv"><span>شرکت:</span><strong><?= e($order['company'] ?: '—') ?></strong></div>
        <div class="kv"><span>نشانی:</span><strong><?= e($order['address']) ?></strong></div>
        <div class="kv"><span>کد رهگیری:</span><strong class="mono"><?= e($order['tracking_code'] ?: 'در انتظار ارسال') ?></strong></div>
        <div class="kv"><span>تاریخ ثبت:</span><strong><?= jdate($order['created_at'], true) ?></strong></div>
        <div class="kv"><span>مبلغ کل:</span><strong><?= money($order['total']) ?></strong></div>
        <?php if ($invoice): ?>
          <a class="btn btn-primary btn-sm" href="index.php?page=invoice&id=<?= e($invoice['tax_unique_id']) ?>">🧾 مشاهده صورتحساب الکترونیکی</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
<?php endif; ?>
