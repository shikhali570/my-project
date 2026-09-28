<?php
/** استعلام‌های قیمت خریدار + فرم ثبت استعلام جدید */
require_buyer();
$me = current_user();

$stmt = $db->prepare('SELECT * FROM rfqs WHERE user_id = ? OR phone = ? ORDER BY id DESC');
$stmt->execute([$me['id'], $me['phone']]);
$rows = $stmt->fetchAll();

$counts = [];
foreach ($rows as $r) {
    $counts[$r['status']] = ($counts[$r['status']] ?? 0) + 1;
}
?>

<div class="panel-grid">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">درخواست قیمت جدید</h3>
      <span class="pill info">پاسخ حداکثر ۲۴ ساعت کاری</span>
    </div>
    <form method="POST" action="index.php" class="rfq-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rfq_submit">
      <input type="hidden" name="company" value="<?= e($me['company'] ?: $me['name']) ?>">
      <input type="hidden" name="phone" value="<?= e($me['phone']) ?>">
      <div class="input-group">
        <label>عنوان درخواست</label>
        <input type="text" name="title" placeholder="مثلاً: تأمین تجهیزات فاز دوم پروژه">
      </div>
      <div class="input-group">
        <label>شرح اقلام و مقادیر *</label>
        <textarea name="description" rows="5" required placeholder="۱- ۱۰ عدد …&#10;۲- ۵ عدد …"></textarea>
      </div>
      <button class="btn btn-orange" type="submit">📤 ارسال استعلام</button>
    </form>
  </div>

  <div class="card">
    <h3 class="card-title">وضعیت استعلام‌های شما</h3>
    <div class="stat-rows">
      <?php foreach (rfq_statuses() as $key => $label): ?>
        <div class="stat-row">
          <span class="status <?= rfq_status_class($key) ?>"><?= e($label) ?></span>
          <strong><?= fa_num($counts[$key] ?? 0) ?></strong>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="alert info" style="margin-top:12px">
      استعلام‌هایی که کارشناس فروش قیمت‌گذاری کرده است، مبلغ پیشنهادی و توضیحات کارشناس را در جدول زیر می‌بینید.
    </div>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">تاریخچه استعلام‌ها (<?= fa_num(count($rows)) ?> مورد)</h3></div>
  <?php if (!$rows): ?>
    <div class="empty-mini">هنوز استعلامی ثبت نکرده‌اید.</div>
  <?php else: foreach ($rows as $r): ?>
    <div class="rfq-card">
      <div class="rfq-card-head">
        <div>
          <strong class="mono"><?= e($r['rfq_code']) ?></strong>
          <h4><?= e($r['title'] ?: 'درخواست استعلام قیمت') ?></h4>
          <small>ثبت‌شده در <?= jdate($r['created_at'], true) ?></small>
        </div>
        <div class="rfq-card-side">
          <span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span>
          <?php if ($r['quote_amount']): ?>
            <div class="quote-amount">پیشنهاد قیمت: <strong><?= money($r['quote_amount']) ?></strong></div>
            <small><?= jdate($r['quoted_at'], true) ?></small>
          <?php endif; ?>
        </div>
      </div>
      <pre class="rfq-desc"><?= e($r['description']) ?></pre>
      <?php if ($r['admin_reply']): ?>
        <div class="alert success">
          <strong>پاسخ کارشناس فروش:</strong> <?= e($r['admin_reply']) ?>
        </div>
      <?php endif; ?>
      <?php if ($r['quote_amount'] && $r['status'] === 'quoted'): ?>
        <div class="flex-gap wrap">
          <a class="btn btn-primary btn-sm" href="index.php?page=home">مشاهده کاتالوگ و ثبت سفارش</a>
          <a class="btn btn-secondary btn-sm" href="index.php?page=contact">مذاکره و نهایی‌سازی</a>
        </div>
      <?php endif; ?>
    </div>
  <?php endforeach; endif; ?>
</div>
