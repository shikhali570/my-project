<?php
/** درباره ما و استانداردهای مالیاتی */
$aboutTaxPoints = site_content_items('about_tax_points', [
    '🧾 صدور صورتحساب الکترونیکی نوع ۱ برای همه سفارش‌ها با شناسه یکتای مالیاتی',
    '🔢 ثبت شناسه کالا (IRK) برای تمام اقلام کاتالوگ مطابق فهرست سازمان امور مالیاتی',
    '🔁 انتقال اعتبار ارزش افزوده به کارپوشه مؤدیان خریدار در همان لحظه صدور',
    '🛡️ نگهداری سابقه صورتحساب‌ها و امکان دریافت رونوشت رسمی در هر زمان',
]);
$aboutProcurementPoints = site_content_items('about_procurement_points', [
    '🤝 مشاور فنی تأمین کالا داریم',
    '🚚 ارسال مستقیم به کارگاه، انبار پروژه یا دفتر فنی',
    '🧰 بسته‌بندی‌های آماده کارگاهی (پک ایمنی، پک دفتر فنی)',
    '🛠️ خدمات کالیبراسیون و سرویس دوره‌ای ابزار دقیق',
]);
$aboutSteps = [
    [
        site_content('about_step_1_title', 'انتخاب کالا یا ثبت استعلام'),
        site_content('about_step_1_text', 'از کاتالوگ کالاها را به سبد اضافه کنید یا فهرست اقلام پروژه را در فرم RFQ ثبت کنید.'),
    ],
    [
        site_content('about_step_2_title', 'تأیید پیش‌فاکتور و پرداخت'),
        site_content('about_step_2_text', 'پیش‌فاکتور رسمی با مبالغ شفاف در پنل خریدار نمایش داده می‌شود؛ پس از پرداخت، سفارش وارد جریان آماده‌سازی می‌شود.'),
    ],
    [
        site_content('about_step_3_title', 'صدور صورتحساب و ارسال'),
        site_content('about_step_3_text', 'صورتحساب الکترونیکی با شناسه یکتا صادر و کد رهگیری مرسوله در پنل شما ثبت می‌گردد.'),
    ],
];
?>
<div class="about-hero">
  <div>
    <span class="pill info"><?= e(site_content('about_badge', 'درباره ' . settings('site_name'))) ?></span>
    <h1><?= e(site_content('about_hero_title', 'تأمین تخصصی تجهیزات پروژه، با اسناد مالیاتی بی‌نقص')) ?></h1>
    <p><?= nl2br(e(site_content('about_intro', 'ما از سال ۱۳۹۲ در حوزه تأمین ابزار دقیق نقشه‌برداری، تجهیزات ایمنی کارگاهی و ملزومات دفاتر فنی پروژه‌های عمرانی فعالیت می‌کنیم. تمرکز ما بر دو موضوع است: تأمین کالا و صدور اسناد مالیاتی معتبر.'))) ?></p>
  </div>
  <div class="about-stats">
    <div><strong><?= e(site_content('about_stat_years_value', '۱۲+')) ?></strong><span><?= e(site_content('about_stat_years_label', 'سال سابقه تأمین پروژه‌ای')) ?></span></div>
    <div><strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn()) ?>+</strong><span><?= e(site_content('about_stat_products_label', 'قلم کالای فعال')) ?></span></div>
    <div><strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()) ?></strong><span><?= e(site_content('about_stat_orders_label', 'سفارش رسمی ثبت‌شده')) ?></span></div>
    <div><strong><?= e(site_content('about_stat_compliance_value', '۱۰۰٪')) ?></strong><span><?= e(site_content('about_stat_compliance_label', 'انطباق با سامانه مؤدیان')) ?></span></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3 class="card-title"><?= e(site_content('about_tax_heading', 'استاندارد مالیاتی ما')) ?></h3>
    <ul class="feature-list">
      <?php foreach ($aboutTaxPoints as $point): ?><li><?= e($point) ?></li><?php endforeach; ?>
    </ul>
  </div>
  <div class="card">
    <h3 class="card-title"><?= e(site_content('about_procurement_heading', 'خدمات تدارکات پروژه')) ?></h3>
    <ul class="feature-list">
      <?php foreach ($aboutProcurementPoints as $point): ?><li><?= e($point) ?></li><?php endforeach; ?>
    </ul>
  </div>
</div>

<section class="sec-block">
  <div class="sec-head"><h2 class="sec-title"><?= e(site_content('about_steps_heading', 'مسیر ثبت و صدور سند در سه گام')) ?></h2></div>
  <div class="steps-grid">
    <?php foreach ($aboutSteps as $index => $step): ?>
      <div class="step-card">
        <span class="step-no"><?= fa_num($index + 1) ?></span>
        <h4><?= e($step[0]) ?></h4>
        <p><?= nl2br(e($step[1])) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="cta-band">
  <div>
    <h3><?= e(site_content('about_cta_title', 'سؤالی درباره خرید سازمانی یا اسناد مالیاتی دارید؟')) ?></h3>
    <p><?= e(site_content('about_cta_text', 'کارشناسان ما در ساعات کاری پاسخگوی شما هستند.')) ?></p>
  </div>
  <a class="btn btn-orange btn-lg" href="index.php?page=contact"><?= e(site_content('about_cta_button', 'تماس با واحد فروش')) ?></a>
</section>
