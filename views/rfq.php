<?php
/** فرم استعلام قیمت پروژه (RFQ) */
$me = current_user();
$doneCode = get('done');
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
// اگر از لینک «استعلام برای این کالا» آمده‌ایم، فهرست اقلام از پیش نوشته می‌شود
$descDefault = array_key_exists('description', $old) ? $old['description'] : (string)get('items');
?>

<?php if ($doneCode): ?>
  <div class="success-box compact">
    <div class="success-icon" aria-hidden="true">📋</div>
    <h2>استعلام شما ثبت شد</h2>
    <p>کد پیگیری استعلام: <strong class="mono"><?= e($doneCode) ?></strong></p>
    <p class="mini-note">کارشناسان فروش تا حداکثر ۲۴ ساعت کاری پیش‌فاکتور سازمانی را در پنل خریدار ثبت می‌کنند.</p>
    <?php if (is_logged_in()): ?>
      <a class="btn btn-secondary btn-sm" href="index.php?page=panel_rfqs">مشاهده استعلام‌های من</a>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="rfq-layout">
  <div class="card rfq-box">
    <h1 class="sec-title">استعلام قیمت پروژه</h1>
    <p class="sec-sub">
      فهرست اقلام پروژه را بنویسید؛ کارشناسان تدارکات بر اساس آن، پیش‌فاکتور رسمی با شناسه مالیاتی و تخفیف سازمانی صادر می‌کنند.
    </p>

    <?php if (!$me): ?>
      <div class="alert info">
        ارسال استعلام بدون ورود هم ممکن است. برای پیگیری در پنل خریدار، <a href="index.php?page=register">ثبت‌نام خریدار سازمانی</a> یا <a href="index.php?page=login">ورود</a> کنید.
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

    <form method="POST" action="index.php" class="rfq-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rfq_submit">
      <p class="form-legend"><span class="req" aria-hidden="true">*</span> فیلدهای الزامی</p>

      <div class="form-grid">
        <div class="input-group">
          <label for="r-company">نام شرکت / پیمانکار <span class="req" aria-hidden="true">*</span></label>
          <input id="r-company" type="text" name="company" required autocomplete="organization" placeholder="مثلاً شرکت مهندسی بناسازان"
                 value="<?= e($fieldVal('company', $me['company'] ?? '')) ?>"<?= field_invalid_attr($errs, 'company') ?>>
          <?= field_error($errs, 'company') ?>
        </div>
        <div class="input-group">
          <label for="r-phone">شماره همراه مسئول تدارکات <span class="req" aria-hidden="true">*</span></label>
          <input id="r-phone" type="tel" name="phone" required maxlength="11" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09121111111"
                 value="<?= e($fieldVal('phone', $me['phone'] ?? '')) ?>"<?= field_invalid_attr($errs, 'phone') ?>>
          <?= field_error($errs, 'phone') ?>
        </div>
      </div>

      <div class="input-group">
        <label for="r-title">عنوان پروژه یا درخواست <span class="muted">(اختیاری)</span></label>
        <input id="r-title" type="text" name="title" autocomplete="off" placeholder="مثلاً: تجهیزات دفتر فنی پروژه مسکونی ۱۲ طبقه"
               value="<?= e($fieldVal('title', '')) ?>">
      </div>

      <div class="input-group">
        <label for="r-desc">فهرست اقلام و مقادیر <span class="req" aria-hidden="true">*</span></label>
        <p class="field-hint" id="r-desc-hint">هر قلم را در یک خط بنویسید: نام کالا، مشخصات، تعداد و واحد. مثال: ۱۰ حلقه رول پلاتر عرض ۹۰ سانتی‌متر</p>
        <textarea id="r-desc" name="description" rows="7" required
                  placeholder="۱- ۱۰ حلقه رول پلاتر عرض ۹۰&#10;۲- ۵ عدد کارتریج مشکی پلاتر&#10;۳- ۲ دستگاه متر لیزری ۱۰۰ متری"<?= field_invalid_attr($errs, 'description', 'r-desc-hint') ?>><?= e($descDefault) ?></textarea>
        <?= field_error($errs, 'description') ?>
      </div>

      <div class="form-actions">
        <button class="btn btn-orange btn-lg" type="submit">📤 ارسال استعلام رسمی</button>
        <span class="mini-note">پاسخ‌دهی حداکثر ۲۴ ساعت کاری</span>
      </div>
    </form>
  </div>

  <aside class="side-column">
    <div class="card">
      <h2 class="card-title">مزایای خرید سازمانی</h2>
      <ul class="feature-list">
        <li>✅ تخفیف پلکانی بر اساس حجم سفارش</li>
        <li>✅ تسویه اعتباری ۳۰ روزه برای پیمانکاران دارای سابقه</li>
        <li>✅ صدور صورتحساب رسمی با شناسه یکتای مؤدیان</li>
        <li>✅ ارسال مستقیم به کارگاه‌های پروژه در سراسر کشور</li>
        <li>✅ کارشناس فنی اختصاصی برای انتخاب تجهیزات</li>
      </ul>
    </div>

    <div class="card">
      <h2 class="card-title">دسته‌های پرتقاضای استعلام</h2>
      <div class="chip-row">
        <?php foreach ($navCategories as $c): ?>
          <a class="chip" href="index.php?page=home&cat=<?= e($c['slug']) ?>"><?= e($c['icon']) ?> <?= e($c['title']) ?></a>
        <?php endforeach; ?>
      </div>
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
