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
?>

<?php if ($doneCode): ?>
  <div class="success-box compact">
    <div class="success-icon">📋</div>
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
    <h2 class="sec-title">📋 فرم رسمی استعلام قیمت پروژه (RFQ)</h2>
    <p class="sec-sub">
      فهرست اقلام و مقادیر مورد نیاز پروژه را ثبت کنید؛ کارشناسان تدارکات با اعمال تخفیف سازمانی،
      پیش‌فاکتور رسمی با شناسه مالیاتی صادر می‌کنند.
    </p>

    <form method="POST" action="index.php" class="rfq-form">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="rfq_submit">
      <div class="grid-2">
        <div class="input-group">
          <label>نام شرکت / پیمانکار *</label>
          <input type="text" name="company" required value="<?= e($me['company'] ?? '') ?>" placeholder="شرکت مهندسی بناسازان">
        </div>
        <div class="input-group">
          <label>شماره تماس مسئول تدارکات *</label>
          <input type="text" name="phone" required maxlength="11" value="<?= e($me['phone'] ?? '') ?>" placeholder="09121111111">
        </div>
      </div>
      <div class="input-group">
        <label>عنوان درخواست</label>
        <input type="text" name="title" placeholder="مثلاً: تجهیزات دفتر فنی پروژه مسکونی ۱۲ طبقه">
      </div>
      <div class="input-group">
        <label>شرح اقلام و مقادیر درخواستی *</label>
        <textarea name="description" rows="7" required placeholder="۱- ۱۰ حلقه رول پلاتر عرض ۹۰&#10;۲- ۵ عدد کارتریج مشکی پلاتر&#10;۳- ۲ دستگاه متر لیزری ۱۰۰ متری"><?= e(get('items')) ?></textarea>
      </div>
      <div class="flex-between">
        <button class="btn btn-orange btn-lg" type="submit">📤 ارسال استعلام رسمی</button>
        <span class="mini-note">پاسخ‌دهی حداکثر ۲۴ ساعت کاری</span>
      </div>
    </form>
  </div>

  <aside class="side-column">
    <div class="card">
      <h3 class="card-title">مزایای خرید سازمانی</h3>
      <ul class="feature-list">
        <li>✅ تخفیف پلکانی بر اساس حجم سفارش</li>
        <li>✅ تسویه اعتباری ۳۰ روزه برای پیمانکاران دارای سابقه</li>
        <li>✅ صدور صورتحساب رسمی با شناسه یکتای مؤدیان</li>
        <li>✅ ارسال مستقیم به کارگاه‌های پروژه در سراسر کشور</li>
        <li>✅ کارشناس فنی اختصاصی برای انتخاب تجهیزات</li>
      </ul>
    </div>

    <div class="card">
      <h3 class="card-title">دسته‌های پرتقاضای استعلام</h3>
      <div class="chip-row">
        <?php foreach ($navCategories as $c): ?>
          <a class="chip" href="index.php?page=home&cat=<?= e($c['slug']) ?>"><?= e($c['icon']) ?> <?= e($c['title']) ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <h3 class="card-title">تماس مستقیم با کارشناس</h3>
      <div class="kv"><span>تلفن:</span><strong><?= e(settings('phone')) ?></strong></div>
      <div class="kv"><span>ایمیل:</span><strong><?= e(settings('email')) ?></strong></div>
      <div class="kv"><span>ساعات کاری:</span><strong><?= e(settings('work_hours')) ?></strong></div>
    </div>
  </aside>
</div>

<?php if ($myRfqs): ?>
  <section class="sec-block">
    <div class="sec-head"><h2 class="sec-title">آخرین استعلام‌های شما</h2><a class="link-more" href="index.php?page=panel_rfqs">همه استعلام‌ها ←</a></div>
    <table class="data-table">
      <thead><tr><th>کد</th><th>عنوان</th><th>تاریخ</th><th>وضعیت</th><th>مبلغ پیشنهادی</th></tr></thead>
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
  </section>
<?php endif; ?>
