<?php
/** استعلام‌های قیمت خریدار: فرم ثبت استعلام جدید و تاریخچهٔ استعلام‌ها */
require_buyer();
$me = current_user();

$stmt = $db->prepare('SELECT * FROM rfqs WHERE user_id = ? OR phone = ? ORDER BY id DESC');
$stmt->execute([$me['id'], $me['phone']]);
$rows = $stmt->fetchAll();

$formState = take_form_state('rfq');
$old = $formState['old'];
$errs = $formState['errors'];
$fieldVal = function ($key, $default = '') use ($old) {
    return array_key_exists($key, $old) ? (string)$old[$key] : (string)$default;
};
$itemSource = $old['items'] ?? null;
if (!is_array($itemSource) && isset($old['description']) && is_scalar($old['description']) && trim((string)$old['description']) !== '') {
    $itemSource = [['description' => (string)$old['description']]];
}
$rfqItemRows = rfq_form_item_rows($itemSource);
$doneCode = get('done');
?>

<?php if ($doneCode): ?>
  <div class="alert success"><strong>استعلام ثبت شد.</strong> کد پیگیری: <span class="mono"><?= e($doneCode) ?></span></div>
<?php endif; ?>

<section class="card">
  <div class="card-head">
    <h3 class="card-title">درخواست قیمت جدید</h3>
    <span class="mini-note">پاسخ کارشناسان حداکثر ۲۴ ساعت کاری</span>
  </div>
  <?php if ($errs): ?>
    <div class="form-errors" role="alert">
      <strong>استعلام ارسال نشد. موارد زیر را اصلاح کنید:</strong>
      <ul><?php foreach ($errs as $msg): ?><li><?= e($msg) ?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>
  <form method="POST" action="index.php" class="rfq-form" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="rfq_submit">
    <input type="hidden" name="return_to" value="panel_rfqs">

    <?php require __DIR__ . '/../partials/rfq_contact_fields.php'; ?>

    <div class="input-group">
      <label for="r-title">عنوان درخواست <span class="muted">(اختیاری)</span></label>
      <input id="r-title" type="text" name="title" maxlength="180" placeholder="مثلاً: تأمین تجهیزات فاز دوم پروژه"
             value="<?= e($fieldVal('title', '')) ?>"<?= field_invalid_attr($errs, 'title') ?>>
      <?= field_error($errs, 'title') ?>
    </div>

    <?php require __DIR__ . '/../partials/rfq_items_fields.php'; ?>
    <?php require __DIR__ . '/../partials/rfq_attachment_field.php'; ?>

    <button class="btn btn-orange" type="submit">📤 ارسال استعلام</button>
  </form>
</section>

<section class="card">
  <div class="card-head"><h3 class="card-title">استعلام‌های من (<?= fa_num(count($rows)) ?>)</h3></div>
  <?php if (!$rows): ?>
    <div class="empty-mini">هنوز استعلامی ثبت نکرده‌اید.</div>
  <?php else: foreach ($rows as $r): ?>
    <?php
      $rfqItems = rfq_items_decode($r['items_json'] ?? '[]');
      $rfqAttachments = (int)($r['user_id'] ?? 0) === (int)$me['id']
          ? rfq_attachments_decode($r['attachments_json'] ?? '[]')
          : [];
    ?>
    <article class="rfq-card">
      <div class="rfq-card-head">
        <div>
          <strong class="mono"><?= e($r['rfq_code']) ?></strong>
          <h4><?= e($r['title'] ?: 'درخواست استعلام قیمت') ?></h4>
          <small>ثبت‌شده در <?= jdate($r['created_at'], true) ?></small>
          <?php if (!empty($r['email'])): ?><small class="rfq-contact-line">ایمیل: <?= e($r['email']) ?></small><?php endif; ?>
          <?php if (!empty($r['messenger'])): ?><small class="rfq-contact-line">پیام‌رسان پاسخگو: <?= e($r['messenger']) ?></small><?php endif; ?>
        </div>
        <div class="rfq-card-side">
          <span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span>
          <?php if ($r['quote_amount']): ?>
            <div class="quote-amount">پیشنهاد قیمت: <strong><?= money($r['quote_amount']) ?></strong></div>
            <small><?= jdate($r['quoted_at'], true) ?></small>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($rfqItems): ?>
        <div class="rfq-saved-items">
          <?php foreach ($rfqItems as $index => $item): ?>
            <div class="rfq-saved-item">
              <strong>قلم <?= fa_num($index + 1) ?>:</strong> <?= e($item['description']) ?>
              <span>مقدار: <?= e(fa_text($item['quantity'])) ?></span>
              <?php if ($item['item_code'] !== ''): ?><span>شناسه کالا: <?= e($item['item_code']) ?></span><?php endif; ?>
              <?php if ($item['category'] !== ''): ?><span>دسته‌بندی: <?= e(category_title($item['category'])) ?></span><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <pre class="rfq-desc"><?= e($r['description']) ?></pre>
      <?php endif; ?>

      <?php if ($rfqAttachments): ?>
        <div class="rfq-attachments">
          <strong>پیوست‌های شما</strong>
          <ul>
            <?php foreach ($rfqAttachments as $attachment): ?>
              <li>
                <a href="index.php?action=rfq_attachment_download&amp;rfq_id=<?= (int)$r['id'] ?>&amp;attachment=<?= e($attachment['token']) ?>"><?= e($attachment['name']) ?></a>
                <small><?= fa_num(round($attachment['size'] / 1024)) ?> کیلوبایت</small>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

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
    </article>
  <?php endforeach; endif; ?>
</section>
