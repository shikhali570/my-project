<?php
/** فرم عمومی استعلام قیمت پروژه (RFQ) */
$me = current_user();
$rfqBenefitItems = site_content_items('rfq_benefits', [
    '✅ مشاور فنی تأمین کالا داریم',
    '✅ صدور صورتحساب رسمی با شناسه یکتای مؤدیان',
    '✅ ارسال مستقیم به کارگاه‌های پروژه در سراسر کشور',
]);
$doneCode = get('done');
$doneRfq = null;
$doneAttachments = [];
if ($doneCode !== '') {
    $doneLookup = $db->prepare('SELECT id, user_id, attachments_json FROM rfqs WHERE rfq_code = ?');
    $doneLookup->execute([$doneCode]);
    $candidateRfq = $doneLookup->fetch();
    if ($candidateRfq) {
        $accountOwner = !empty($candidateRfq['user_id']) && (int)$candidateRfq['user_id'] === user_id();
        $guestSessionOwner = empty($candidateRfq['user_id']) && rfq_guest_session_owns((int)$candidateRfq['id']);
        if ($accountOwner || $guestSessionOwner) {
            $doneRfq = $candidateRfq;
            $doneAttachments = rfq_attachments_decode($doneRfq['attachments_json'] ?? '[]');
        }
    }
}
$myRfqs = [];
if ($me && $me['role'] === 'buyer') {
    $stmt = $db->prepare('SELECT * FROM rfqs WHERE user_id = ? ORDER BY id DESC LIMIT 5');
    $stmt->execute([$me['id']]);
    $myRfqs = $stmt->fetchAll();
}

// ورودی‌ها و خطاهای فرم پس از ارسال ناموفق (یک‌بار مصرف)
$formState = take_form_state('rfq');
$old = $formState['old'];
$errs = $formState['errors'];
$fieldVal = function ($key, $default = '') use ($old) {
    return array_key_exists($key, $old) ? (string)$old[$key] : (string)$default;
};

// پشتیبانی از لینک‌های قدیمی «استعلام این کالا» که متن را با ?items= می‌فرستادند
$itemSource = $old['items'] ?? null;
if (!is_array($itemSource)) {
    if (isset($old['description']) && is_scalar($old['description']) && trim((string)$old['description']) !== '') {
        $itemSource = [['description' => (string)$old['description']]];
    } elseif (isset($_GET['items']) && is_array($_GET['items'])) {
        $itemSource = $_GET['items'];
    } elseif (isset($_GET['items']) && is_scalar($_GET['items']) && trim((string)$_GET['items']) !== '') {
        $itemSource = [['description' => (string)$_GET['items']]];
    }
}
$rfqItemRows = rfq_form_item_rows($itemSource);
?>

<?php if ($doneCode): ?>
  <div class="success-box compact">
    <div class="success-icon" aria-hidden="true">📋</div>
    <h2><?= e(site_content('rfq_done_heading', 'استعلام شما ثبت شد')) ?></h2>
    <p><?= e(site_content('rfq_done_code_label', 'کد پیگیری استعلام:')) ?> <strong class="mono"><?= e($doneCode) ?></strong></p>
    <p class="mini-note"><?= nl2br(e(site_content('rfq_done_note', 'کارشناسان فروش تا حداکثر ۲۴ ساعت کاری پیش‌فاکتور سازمانی را در پنل خریدار ثبت می‌کنند.'))) ?></p>
    <?php if (is_logged_in()): ?>
      <a class="btn btn-secondary btn-sm" href="index.php?page=panel_rfqs">مشاهده استعلام‌های من</a>
    <?php endif; ?>
    <?php if ($doneAttachments && $doneRfq): ?>
      <div class="rfq-attachments">
        <strong>پیوست‌های این استعلام</strong>
        <ul>
          <?php foreach ($doneAttachments as $attachment): ?>
            <li>
              <a href="index.php?action=rfq_attachment_download&amp;rfq_id=<?= (int)$doneRfq['id'] ?>&amp;attachment=<?= e($attachment['token']) ?>"><?= e($attachment['name']) ?></a>
              <small><?= fa_num(round($attachment['size'] / 1024)) ?> کیلوبایت</small>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="rfq-layout">
  <div class="card rfq-box">
    <h1 class="sec-title"><?= e(site_content('rfq_heading', 'استعلام قیمت پروژه')) ?></h1>
    <p class="sec-sub"><?= nl2br(e(site_content('rfq_intro', 'اقلام پروژه و راه‌های تماس را ثبت کنید؛ کارشناسان فروش بر اساس درخواست شما پیش‌فاکتور را آماده می‌کنند.'))) ?></p>

    <?php if (!$me): ?>
      <div class="alert info">
        ارسال استعلام بدون ورود هم ممکن است. برای پیگیری در پنل خریدار، <a href="index.php?page=register">ثبت‌نام خریدار حقیقی یا حقوقی</a> یا <a href="index.php?page=login">ورود</a> کنید.
      </div>
    <?php endif; ?>

    <?php if ($errs): ?>
      <div class="form-errors" role="alert">
        <strong>استعلام ارسال نشد. موارد زیر را اصلاح کنید:</strong>
        <ul>
          <?php foreach ($errs as $msg): ?>
            <li><?= e($msg) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>

    <form method="POST" action="index.php" class="rfq-form" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rfq_submit">
      <input type="hidden" name="return_to" value="rfq">
      <p class="form-legend"><span class="req" aria-hidden="true">*</span> فیلدهای الزامی</p>

      <?php require __DIR__ . '/partials/rfq_contact_fields.php'; ?>

      <div class="input-group">
        <label for="r-title">عنوان پروژه یا درخواست <span class="muted">(اختیاری)</span></label>
        <input id="r-title" type="text" name="title" maxlength="180" autocomplete="off" placeholder="مثلاً: تجهیزات دفتر فنی پروژه مسکونی ۱۲ طبقه"
               value="<?= e($fieldVal('title', '')) ?>"<?= field_invalid_attr($errs, 'title') ?>>
        <?= field_error($errs, 'title') ?>
      </div>

      <?php require __DIR__ . '/partials/rfq_items_fields.php'; ?>
      <?php require __DIR__ . '/partials/rfq_attachment_field.php'; ?>

      <div class="form-actions">
        <button class="btn btn-orange btn-lg" type="submit"><?= e(site_content('rfq_submit_label', '📤 ارسال استعلام رسمی')) ?></button>
        <span class="mini-note"><?= e(site_content('rfq_response_note', 'پاسخ‌دهی حداکثر ۲۴ ساعت کاری')) ?></span>
      </div>
    </form>
  </div>

  <aside class="side-column">
    <div class="card">
      <h2 class="card-title"><?= e(site_content('rfq_benefits_heading', 'اطلاعات خرید سازمانی')) ?></h2>
      <ul class="feature-list">
        <?php foreach ($rfqBenefitItems as $benefit): ?><li><?= e($benefit) ?></li><?php endforeach; ?>
      </ul>
    </div>

    <div class="card">
      <h2 class="card-title">تماس مستقیم با کارشناس</h2>
      <div class="kv"><span>تلفن:</span><strong><?= e(settings('phone')) ?></strong></div>
      <div class="kv"><span>ایمیل:</span><strong><?= e(settings('email')) ?></strong></div>
      <div class="kv"><span>ساعات کاری:</span><strong><?= e(settings('work_hours')) ?></strong></div>
    </div>
  </aside>
</div>

<?php if ($myRfqs): ?>
  <section class="sec-block">
    <div class="sec-head"><h2 class="sec-title">آخرین استعلام‌های شما</h2><a class="link-more" href="index.php?page=panel_rfqs">همه استعلام‌ها ←</a></div>
    <div class="table-wrap">
      <table class="data-table">
        <caption class="visually-hidden">آخرین استعلام‌های قیمت شما</caption>
        <thead><tr><th scope="col">کد</th><th scope="col">عنوان</th><th scope="col">تاریخ</th><th scope="col">وضعیت</th><th scope="col">مبلغ پیشنهادی</th></tr></thead>
        <tbody>
          <?php foreach ($myRfqs as $r): ?>
            <tr>
              <td class="mono"><?= e($r['rfq_code']) ?></td>
              <td><?= e($r['title'] ?: mb_substr($r['description'], 0, 40) . '…') ?></td>
              <td><?= jdate($r['created_at']) ?></td>
              <td><span class="status <?= rfq_status_class($r['status']) ?>"><?= e(rfq_status_label($r['status'])) ?></span></td>
              <td><?= $r['quote_amount'] ? money($r['quote_amount']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
