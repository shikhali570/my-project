<?php
/** مرکز مدیریت محتوا، اطلاعات عمومی، اعلان‌ها و درگاه پرداخت */
require_admin();
$s = settings();
$gateway = mellat_gateway_status();
$contentField = function ($key, $label, $default, $rows = 1, $max = 1200) {
    $name = 'content_' . $key;
    $id = 'content-' . str_replace('_', '-', $key);
    $value = site_content($key, $default);
    ob_start();
    ?>
    <div class="input-group">
      <label for="<?= e($id) ?>"><?= e($label) ?></label>
      <?php if ((int)$rows > 1): ?>
        <textarea id="<?= e($id) ?>" name="<?= e($name) ?>" rows="<?= (int)$rows ?>" maxlength="<?= (int)$max ?>"><?= e($value) ?></textarea>
      <?php else: ?>
        <input id="<?= e($id) ?>" type="text" name="<?= e($name) ?>" maxlength="<?= (int)$max ?>" value="<?= e($value) ?>">
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
};
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title">مرکز مدیریت سایت</h2>
    <p class="sec-sub">اطلاعات فروشگاه، اعلان همگانی، متن صفحات عمومی و درگاه آنلاین را از همین بخش تنظیم کنید. مدیریت کالا، سفارش و مشتری نیز از پیوندهای همین پنل در دسترس است.</p>
  </div>
  <span class="pill <?= $gateway['ready'] ? 'success' : 'warn' ?>">
    <?= $gateway['ready'] ? 'تنظیمات درگاه ملت کامل است' : 'تنظیمات درگاه ملت نیاز به تکمیل دارد' ?>
  </span>
</div>

<form method="POST" action="index.php" class="card control-form">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="site_control_save">

  <section class="form-section" aria-labelledby="control-store-title">
    <h3 id="control-store-title" class="card-title">هویت و اطلاعات فروشگاه</h3>
    <div class="grid-2">
      <div class="input-group">
        <label for="ctl-site-name">نام فروشگاه / شرکت</label>
        <input id="ctl-site-name" type="text" name="site_name" maxlength="180" value="<?= e($s['site_name'] ?? '') ?>" required>
      </div>
      <div class="input-group">
        <label for="ctl-slogan">شعار یا شرح کوتاه</label>
        <input id="ctl-slogan" type="text" name="site_slogan" maxlength="240" value="<?= e($s['site_slogan'] ?? '') ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label for="ctl-phone">تلفن پشتیبانی و تدارکات</label>
        <input id="ctl-phone" type="text" name="phone" maxlength="80" value="<?= e($s['phone'] ?? '') ?>">
      </div>
      <div class="input-group">
        <label for="ctl-email">ایمیل</label>
        <input id="ctl-email" type="email" name="email" maxlength="254" value="<?= e($s['email'] ?? '') ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label for="ctl-address">نشانی دفتر / انبار</label>
        <input id="ctl-address" type="text" name="address" maxlength="500" value="<?= e($s['address'] ?? '') ?>">
      </div>
      <div class="input-group">
        <label for="ctl-hours">ساعات کاری</label>
        <input id="ctl-hours" type="text" name="work_hours" maxlength="180" value="<?= e($s['work_hours'] ?? '') ?>">
      </div>
    </div>
    <div class="input-group">
      <label for="ctl-site-url">نشانی عمومی HTTPS سایت برای بازگشت درگاه</label>
      <input id="ctl-site-url" type="url" name="site_url" dir="ltr" placeholder="https://example.ir" value="<?= e($s['site_url'] ?? '') ?>">
      <small class="field-hint">برای درگاه ملت لازم است؛ فقط نشانی HTTPS ریشهٔ سایت، بدون پارامتر و علامت #، وارد کنید.</small>
    </div>
  </section>

  <section class="form-section" aria-labelledby="control-finance-title">
    <h3 id="control-finance-title" class="card-title">مالیات، ارسال و اطلاعات صورتحساب</h3>
    <div class="grid-3">
      <div class="input-group">
        <label for="ctl-vat">نرخ مالیات بر ارزش افزوده (٪)</label>
        <input id="ctl-vat" type="number" name="vat_rate" min="0" max="30" step="0.5" value="<?= e($s['vat_rate'] ?? '10') ?>">
      </div>
      <div class="input-group">
        <label for="ctl-shipping">هزینه ثابت ارسال (تومان)</label>
        <input id="ctl-shipping" type="number" name="shipping_cost" min="0" step="1000" value="<?= (int)($s['shipping_cost'] ?? 0) ?>">
      </div>
      <div class="input-group">
        <label for="ctl-free-shipping">حد ارسال رایگان (تومان)</label>
        <input id="ctl-free-shipping" type="number" name="free_shipping_min" min="0" step="1000" value="<?= (int)($s['free_shipping_min'] ?? 0) ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label for="ctl-company-national">شناسه ملی فروشنده</label>
        <input id="ctl-company-national" type="text" name="company_national_id" value="<?= e($s['company_national_id'] ?? '') ?>">
      </div>
      <div class="input-group">
        <label for="ctl-company-economic">کد اقتصادی فروشنده</label>
        <input id="ctl-company-economic" type="text" name="company_economic_code" value="<?= e($s['company_economic_code'] ?? '') ?>">
      </div>
    </div>
    <div class="input-group">
      <label for="ctl-bank">اطلاعات واریز بانکی (برای سفارش و تماس)</label>
      <textarea id="ctl-bank" name="bank_info" rows="3" maxlength="1200"><?= e($s['bank_info'] ?? '') ?></textarea>
    </div>
    <div class="input-group">
      <label for="ctl-invoice-prefix">پیش‌شماره صورتحساب</label>
      <input id="ctl-invoice-prefix" type="text" name="invoice_prefix" maxlength="16" value="<?= e($s['invoice_prefix'] ?? 'PSA') ?>">
    </div>
  </section>

  <section class="form-section" aria-labelledby="control-announcement-title">
    <h3 id="control-announcement-title" class="card-title">اعلان همگانی سایت</h3>
    <label class="check-row" for="ctl-announcement-enabled">
      <input id="ctl-announcement-enabled" type="checkbox" name="announcement_enabled" value="1" <?= ($s['announcement_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
      <span>نمایش اعلان در بالای صفحات فروشگاه و کارپوشه خریدار</span>
    </label>
    <div class="grid-2">
      <div class="input-group">
        <label for="ctl-announcement-heading">عنوان اعلان</label>
        <input id="ctl-announcement-heading" type="text" name="announcement_title" maxlength="120" value="<?= e($s['announcement_title'] ?? 'اطلاعیه') ?>">
      </div>
      <div class="input-group">
        <label for="ctl-announcement-link">پیوند داخلی (اختیاری)</label>
        <input id="ctl-announcement-link" type="text" name="announcement_link" dir="ltr" maxlength="240" placeholder="index.php?page=contact" value="<?= e($s['announcement_link'] ?? '') ?>">
      </div>
    </div>
    <div class="input-group">
      <label for="ctl-announcement-body">متن اعلان عمومی</label>
      <textarea id="ctl-announcement-body" name="announcement_body" rows="3" maxlength="1200"><?= e($s['announcement_body'] ?? '') ?></textarea>
      <small class="field-hint">متن به‌صورت امن و بدون اجرای HTML نمایش داده می‌شود.</small>
    </div>
  </section>

  <section class="form-section" aria-labelledby="control-pages-title">
    <h3 id="control-pages-title" class="card-title">عنوان و اطلاعات صفحات عمومی</h3>
    <p class="mini-note">متن‌ها ساده و امن ذخیره می‌شوند. دادهٔ کالاها و گروه‌های کالا همچنان از بخش‌های فعلی مدیریت کاتالوگ ویرایش می‌شود.</p>
    <div class="control-page-grid">
      <fieldset class="control-page-card">
        <legend>صفحهٔ اصلی</legend>
        <div class="input-group"><label for="content-page-title-home">عنوان مرورگر</label><input id="content-page-title-home" name="content_page_title_home" maxlength="180" value="<?= e(site_content('page_title_home', 'فروشگاه تجهیزات مهندسی')) ?>"></div>
        <div class="input-group"><label for="content-home-hero-badge">نشان کوتاه</label><input id="content-home-hero-badge" name="content_home_hero_badge" maxlength="180" value="<?= e(site_content('home_hero_badge', 'هر سفارش با صورتحساب الکترونیکی رسمی')) ?>"></div>
        <div class="input-group"><label for="content-home-hero-title">تیتر اصلی</label><textarea id="content-home-hero-title" name="content_home_hero_title" rows="2" maxlength="240"><?= e(site_content('home_hero_title', 'تجهیزات مهندسی و دفتر فنی پروژه، با فاکتور رسمی')) ?></textarea></div>
        <div class="input-group"><label for="content-home-hero-text">معرفی کوتاه</label><textarea id="content-home-hero-text" name="content_home_hero_text" rows="3" maxlength="1200"><?= e(site_content('home_hero_text', 'متر و تراز لیزری، رول پلاتر، تجهیزات HSE و لوازم اداری؛ هر کالا با شناسه مالیاتی، و هر سفارش با صورتحساب الکترونیکی در کارپوشه شما. ارسال به سراسر کشور.')) ?></textarea></div>
        <div class="grid-2">
          <div class="input-group"><label for="content-home-cta-catalog">متن پیوند کاتالوگ</label><input id="content-home-cta-catalog" name="content_home_cta_catalog" maxlength="80" value="<?= e(site_content('home_cta_catalog', 'مشاهده کالاها')) ?>"></div>
          <div class="input-group"><label for="content-home-cta-rfq">متن پیوند استعلام</label><input id="content-home-cta-rfq" name="content_home_cta_rfq" maxlength="80" value="<?= e(site_content('home_cta_rfq', 'استعلام قیمت پروژه')) ?>"></div>
        </div>
        <div class="grid-2">
          <div class="input-group"><label for="content-home-support-value">عدد پاسخ‌گویی</label><input id="content-home-support-value" name="content_home_stat_support_value" maxlength="40" value="<?= e(site_content('home_stat_support_value', '۲۴ ساعت')) ?>"></div>
          <div class="input-group"><label for="content-home-support-label">شرح پاسخ‌گویی</label><input id="content-home-support-label" name="content_home_stat_support_label" maxlength="120" value="<?= e(site_content('home_stat_support_label', 'پاسخ‌دهی کارشناسان به استعلام قیمت')) ?>"></div>
        </div>
        <div class="grid-2">
          <?= $contentField('home_stat_products_label', 'برچسب تعداد کالاها', 'قلم کالای فعال با شناسه مالیاتی', 1, 120) ?>
          <?= $contentField('home_stat_buyers_label', 'برچسب تعداد خریداران', 'خریدار', 1, 120) ?>
        </div>
        <?= $contentField('home_stat_orders_label', 'برچسب تعداد سفارش‌ها', 'سفارش ثبت‌شده با فاکتور رسمی', 1, 120) ?>
        <div class="grid-2">
          <?= $contentField('home_catalog_heading', 'عنوان کاتالوگ', 'تجهیزات و ادوات مهندسی', 1, 180) ?>
          <?= $contentField('home_featured_heading', 'عنوان پرفروش‌ها', 'پرفروش‌ترین کالاها', 1, 180) ?>
        </div>
        <?= $contentField('home_categories_heading', 'عنوان گروه‌های کالا', 'گروه‌های کالایی', 1, 180) ?>
        <?= $contentField('home_cta_band_title', 'تیتر پایانی صفحهٔ اصلی', 'برای تأمین اقلام پروژه به مشاور نیاز دارید؟', 1, 240) ?>
        <?= $contentField('home_cta_band_text', 'متن پایانی صفحهٔ اصلی', 'فهرست اقلام و مقادیر را ثبت کنید؛ مشاور فنی تأمین کالا به انتخاب اقلام و پیگیری استعلام شما کمک می‌کند.', 3, 1000) ?>
        <?= $contentField('home_cta_band_button', 'متن دکمهٔ پایانی', 'ثبت استعلام قیمت پروژه', 1, 80) ?>
      </fieldset>

      <fieldset class="control-page-card">
        <legend>صفحهٔ دربارهٔ ما</legend>
        <div class="input-group"><label for="content-page-title-about">عنوان مرورگر</label><input id="content-page-title-about" name="content_page_title_about" maxlength="180" value="<?= e(site_content('page_title_about', 'درباره ما و استانداردهای مالیاتی')) ?>"></div>
        <div class="input-group"><label for="content-about-title">تیتر اصلی</label><textarea id="content-about-title" name="content_about_hero_title" rows="2" maxlength="240"><?= e(site_content('about_hero_title', 'تأمین تخصصی تجهیزات پروژه، با اسناد مالیاتی بی‌نقص')) ?></textarea></div>
        <div class="input-group"><label for="content-about-intro">معرفی صفحه</label><textarea id="content-about-intro" name="content_about_intro" rows="4" maxlength="1600"><?= e(site_content('about_intro', 'ما از سال ۱۳۹۲ در حوزه تأمین ابزار دقیق نقشه‌برداری، تجهیزات ایمنی کارگاهی و ملزومات دفاتر فنی پروژه‌های عمرانی فعالیت می‌کنیم. تمرکز ما بر دو موضوع است: تأمین کالا و صدور اسناد مالیاتی معتبر.')) ?></textarea></div>
        <?= $contentField('about_badge', 'نشان بالای تیتر', 'درباره ' . settings('site_name'), 1, 180) ?>
        <div class="grid-2">
          <?= $contentField('about_stat_years_value', 'عدد سابقه', '۱۲+', 1, 40) ?>
          <?= $contentField('about_stat_years_label', 'شرح سابقه', 'سال سابقه تأمین پروژه‌ای', 1, 120) ?>
        </div>
        <div class="grid-2">
          <?= $contentField('about_stat_products_label', 'شرح شمار کالاها', 'قلم کالای فعال', 1, 120) ?>
          <?= $contentField('about_stat_orders_label', 'شرح شمار سفارش‌ها', 'سفارش رسمی ثبت‌شده', 1, 120) ?>
        </div>
        <div class="grid-2">
          <?= $contentField('about_stat_compliance_value', 'عدد انطباق', '۱۰۰٪', 1, 40) ?>
          <?= $contentField('about_stat_compliance_label', 'شرح انطباق', 'انطباق با سامانه مؤدیان', 1, 120) ?>
        </div>
        <?= $contentField('about_tax_heading', 'عنوان استاندارد مالیاتی', 'استاندارد مالیاتی ما', 1, 180) ?>
        <?= $contentField('about_tax_points', 'موارد استاندارد مالیاتی (هر مورد در یک خط)', "🧾 صدور صورتحساب الکترونیکی نوع ۱ برای همه سفارش‌ها با شناسه یکتای مالیاتی\n🔢 ثبت شناسه کالا (IRK) برای تمام اقلام کاتالوگ مطابق فهرست سازمان امور مالیاتی\n🔁 انتقال اعتبار ارزش افزوده به کارپوشه مؤدیان خریدار در همان لحظه صدور\n🛡️ نگهداری سابقه صورتحساب‌ها و امکان دریافت رونوشت رسمی در هر زمان", 5, 1600) ?>
        <?= $contentField('about_procurement_heading', 'عنوان تدارکات', 'خدمات تدارکات پروژه', 1, 180) ?>
        <?= $contentField('about_procurement_points', 'موارد تدارکات (هر مورد در یک خط)', "🤝 مشاور فنی تأمین کالا داریم\n🚚 ارسال مستقیم به کارگاه، انبار پروژه یا دفتر فنی\n🧰 بسته‌بندی‌های آماده کارگاهی (پک ایمنی، پک دفتر فنی)\n🛠️ خدمات کالیبراسیون و سرویس دوره‌ای ابزار دقیق", 5, 1600) ?>
        <?= $contentField('about_steps_heading', 'عنوان بخش مراحل', 'مسیر ثبت و صدور سند در سه گام', 1, 180) ?>
        <?= $contentField('about_step_1_title', 'گام یک — عنوان', 'انتخاب کالا یا ثبت استعلام', 1, 180) ?>
        <?= $contentField('about_step_1_text', 'گام یک — توضیح', 'از کاتالوگ کالاها را به سبد اضافه کنید یا فهرست اقلام پروژه را در فرم RFQ ثبت کنید.', 2, 800) ?>
        <?= $contentField('about_step_2_title', 'گام دو — عنوان', 'تأیید پیش‌فاکتور و پرداخت', 1, 180) ?>
        <?= $contentField('about_step_2_text', 'گام دو — توضیح', 'پیش‌فاکتور رسمی با مبالغ شفاف در پنل خریدار نمایش داده می‌شود؛ پس از پرداخت، سفارش وارد جریان آماده‌سازی می‌شود.', 2, 800) ?>
        <?= $contentField('about_step_3_title', 'گام سه — عنوان', 'صدور صورتحساب و ارسال', 1, 180) ?>
        <?= $contentField('about_step_3_text', 'گام سه — توضیح', 'صورتحساب الکترونیکی با شناسه یکتا صادر و کد رهگیری مرسوله در پنل شما ثبت می‌گردد.', 2, 800) ?>
        <?= $contentField('about_cta_title', 'تیتر دعوت به تماس', 'سؤالی درباره خرید سازمانی یا اسناد مالیاتی دارید؟', 1, 240) ?>
        <?= $contentField('about_cta_text', 'متن دعوت به تماس', 'کارشناسان ما در ساعات کاری پاسخگوی شما هستند.', 2, 800) ?>
        <?= $contentField('about_cta_button', 'متن دکمهٔ تماس', 'تماس با واحد فروش', 1, 80) ?>
      </fieldset>

      <fieldset class="control-page-card">
        <legend>صفحهٔ تماس</legend>
        <div class="input-group"><label for="content-page-title-contact">عنوان مرورگر</label><input id="content-page-title-contact" name="content_page_title_contact" maxlength="180" value="<?= e(site_content('page_title_contact', 'تماس با واحد فروش')) ?>"></div>
        <div class="input-group"><label for="content-contact-heading">تیتر اصلی</label><textarea id="content-contact-heading" name="content_contact_heading" rows="2" maxlength="240"><?= e(site_content('contact_heading', 'تماس با واحد فروش و پشتیبانی فنی')) ?></textarea></div>
        <div class="input-group"><label for="content-contact-intro">معرفی صفحه</label><textarea id="content-contact-intro" name="content_contact_intro" rows="3" maxlength="1000"><?= e(site_content('contact_intro', 'برای استعلام قیمت، پیگیری اسناد مالیاتی و پشتیبانی فنی در ساعات کاری با ما در ارتباط باشید.')) ?></textarea></div>
        <?= $contentField('contact_email_note', 'یادداشت زیر ایمیل', 'برای پیگیری از طریق ایمیل با ما در تماس باشید.', 1, 300) ?>
        <?= $contentField('contact_faq_invoice_q', 'سؤال صورتحساب', 'صورتحساب الکترونیکی چه زمانی صادر می‌شود؟', 1, 240) ?>
        <?= $contentField('contact_faq_invoice_a', 'پاسخ صورتحساب', 'به محض ثبت سفارش، صورتحساب نوع ۱ با شناسه یکتا صادر و در کارپوشه مؤدیان شما ثبت می‌شود.', 3, 800) ?>
        <?= $contentField('contact_faq_payment_q', 'سؤال پرداخت', 'چه روش‌های پرداختی برای سفارش فعال است؟', 1, 240) ?>
        <?= $contentField('contact_faq_payment_a', 'پاسخ پرداخت', 'روش‌های پرداخت فعال هنگام ثبت سفارش نمایش داده می‌شوند.', 2, 800) ?>
        <?= $contentField('contact_faq_shipping_q', 'سؤال ارسال', 'هزینه ارسال چگونه محاسبه می‌شود؟', 1, 240) ?>
        <?= $contentField('contact_faq_shipping_a', 'پاسخ ارسال', 'هزینه ارسال ثابت ' . money(settings('shipping_cost')) . ' است و برای سفارش‌های بالای ' . money(settings('free_shipping_min')) . ' رایگان می‌شود.', 2, 800) ?>
        <?= $contentField('contact_faq_warranty_q', 'سؤال گارانتی', 'آیا کالاها گارانتی دارند؟', 1, 240) ?>
        <?= $contentField('contact_faq_warranty_a', 'پاسخ گارانتی', 'شرایط گارانتی هر کالا در صفحهٔ مشخصات همان کالا درج می‌شود.', 2, 800) ?>
      </fieldset>

      <fieldset class="control-page-card">
        <legend>صفحهٔ استعلام قیمت</legend>
        <div class="input-group"><label for="content-page-title-rfq">عنوان مرورگر</label><input id="content-page-title-rfq" name="content_page_title_rfq" maxlength="180" value="<?= e(site_content('page_title_rfq', 'استعلام قیمت پروژه')) ?>"></div>
        <div class="input-group"><label for="content-rfq-heading">تیتر اصلی</label><textarea id="content-rfq-heading" name="content_rfq_heading" rows="2" maxlength="240"><?= e(site_content('rfq_heading', 'استعلام قیمت پروژه')) ?></textarea></div>
        <div class="input-group"><label for="content-rfq-intro">معرفی صفحه</label><textarea id="content-rfq-intro" name="content_rfq_intro" rows="3" maxlength="1000"><?= e(site_content('rfq_intro', 'اقلام پروژه و راه‌های تماس را ثبت کنید؛ کارشناسان فروش بر اساس درخواست شما پیش‌فاکتور را آماده می‌کنند.')) ?></textarea></div>
        <?= $contentField('rfq_done_heading', 'عنوان پیام ثبت استعلام', 'استعلام شما ثبت شد', 1, 180) ?>
        <?= $contentField('rfq_done_code_label', 'برچسب کد پیگیری', 'کد پیگیری استعلام:', 1, 180) ?>
        <?= $contentField('rfq_done_note', 'پیام پس از ثبت استعلام', 'کارشناسان فروش تا حداکثر ۲۴ ساعت کاری پیش‌فاکتور سازمانی را در پنل خریدار ثبت می‌کنند.', 3, 800) ?>
        <?= $contentField('rfq_submit_label', 'متن دکمه ارسال', '📤 ارسال استعلام رسمی', 1, 80) ?>
        <?= $contentField('rfq_response_note', 'یادداشت کنار دکمه ارسال', 'پاسخ‌دهی حداکثر ۲۴ ساعت کاری', 1, 180) ?>
        <?= $contentField('rfq_benefits_heading', 'عنوان راهنمای استعلام', 'اطلاعات خرید سازمانی', 1, 180) ?>
        <?= $contentField('rfq_benefits', 'موارد تدارکات (هر مورد در یک خط)', "✅ مشاور فنی تأمین کالا داریم\n✅ صدور صورتحساب رسمی با شناسه یکتای مؤدیان\n✅ ارسال مستقیم به کارگاه‌های پروژه در سراسر کشور", 4, 1200) ?>
      </fieldset>

      <fieldset class="control-page-card">
        <legend>صفحهٔ ثبت‌نام</legend>
        <div class="input-group"><label for="content-page-title-register">عنوان مرورگر</label><input id="content-page-title-register" name="content_page_title_register" maxlength="180" value="<?= e(site_content('page_title_register', 'ثبت‌نام خریدار حقیقی یا حقوقی')) ?>"></div>
        <div class="input-group"><label for="content-register-heading">تیتر اصلی</label><textarea id="content-register-heading" name="content_register_heading" rows="2" maxlength="240"><?= e(site_content('register_heading', 'ثبت‌نام خریدار حقیقی یا حقوقی')) ?></textarea></div>
        <div class="input-group"><label for="content-register-intro">معرفی صفحه</label><textarea id="content-register-intro" name="content_register_intro" rows="3" maxlength="1000"><?= e(site_content('register_intro', 'اطلاعات هویتی و نشانی لازم برای ثبت خریدار و صدور صورتحساب الکترونیکی را وارد کنید.')) ?></textarea></div>
        <?= $contentField('register_side_title', 'عنوان ستون معرفی', 'چرا از ' . settings('site_name') . ' خرید کنید؟', 1, 180) ?>
        <?= $contentField('register_benefits', 'موارد معرفی (هر مورد در یک خط)', "🧾 صورتحساب الکترونیکی معتبر و قابل استناد در ممیزی مالیاتی\n🚚 ارسال به کارگاه‌های پروژه در سراسر کشور\n🤝 مشاور فنی تأمین کالا داریم", 4, 1200) ?>
        <?= $contentField('register_privacy_note', 'یادداشت حریم خصوصی', 'اطلاعات کارپوشه شما فقط برای صدور اسناد رسمی استفاده می‌شود و در اختیار شخص ثالث قرار نمی‌گیرد.', 3, 800) ?>
      </fieldset>
    </div>
  </section>

  <section class="form-section" aria-labelledby="control-mellat-title">
    <h3 id="control-mellat-title" class="card-title">پرداخت آنلاین — به‌پرداخت ملت</h3>
    <div class="gateway-status <?= $gateway['ready'] ? 'is-ready' : 'is-not-ready' ?>">
      <strong><?= $gateway['ready'] ? 'تنظیمات لازم ثبت شده‌اند؛ اتصال واقعی با درگاه در زمان پرداخت بررسی می‌شود.' : 'درگاه هنوز آمادهٔ پرداخت نیست' ?></strong>
      <?php if (!$gateway['ready'] && $gateway['reasons']): ?>
        <ul><?php foreach ($gateway['reasons'] as $reason): ?><li><?= e($reason) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </div>
    <label class="check-row" for="ctl-mellat-enabled">
      <input id="ctl-mellat-enabled" type="checkbox" name="mellat_enabled" value="1" <?= ($s['mellat_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
      <span>فعال‌سازی گزینهٔ پرداخت آنلاین به‌پرداخت ملت در checkout</span>
    </label>
    <div class="grid-2">
      <div class="input-group">
        <label for="ctl-mellat-terminal">شماره ترمینال</label>
        <input id="ctl-mellat-terminal" type="text" name="mellat_terminal_id" inputmode="numeric" dir="ltr" maxlength="20" value="<?= e($s['mellat_terminal_id'] ?? '') ?>">
      </div>
      <div class="input-group">
        <label for="ctl-mellat-user">نام کاربری درگاه</label>
        <input id="ctl-mellat-user" type="text" name="mellat_username" dir="ltr" maxlength="120" autocomplete="off" value="<?= e($s['mellat_username'] ?? '') ?>">
      </div>
    </div>
    <div class="input-group">
      <label for="ctl-mellat-password">رمز درگاه (محرمانه)</label>
      <input id="ctl-mellat-password" type="password" name="mellat_password" dir="ltr" maxlength="240" autocomplete="new-password" value="" placeholder="<?= $gateway['password_saved'] ? '••••••••' : 'رمز درگاه' ?>">
      <small class="field-hint"><?= $gateway['password_saved'] ? 'رمز قبلی ذخیره و ماسک شده است؛ برای جایگزینی، رمز جدید وارد کنید.' : 'رمزی ذخیره نشده است؛ رمز را فقط در همین پنل امن وارد کنید.' ?></small>
    </div>
    <?php if ($gateway['password_saved']): ?>
      <label class="check-row check-row--danger" for="ctl-clear-mellat-password">
        <input id="ctl-clear-mellat-password" type="checkbox" name="clear_mellat_password" value="1">
        <span>حذف رمز ذخیره‌شده از تنظیمات درگاه</span>
      </label>
    <?php endif; ?>
    <div class="alert info">
      مبلغ‌های سایت به تومان هستند و هنگام ارسال به ملت به ریال تبدیل می‌شوند. برای نگهداری رمز از AES-256-GCM استفاده می‌شود؛ فایل <code>tmp/payment-gateway.key</code> را حذف نکنید و آن را همراه نسخهٔ پشتیبان پایگاه داده در محل امن نگه دارید. کلید در خروجی ZIP یا Git قرار نمی‌گیرد.
    </div>
  </section>

  <div class="form-actions">
    <button class="btn btn-primary btn-lg" type="submit">💾 ذخیره تغییرات مرکز مدیریت</button>
    <a class="btn btn-secondary" href="index.php?page=admin">بازگشت به داشبورد</a>
  </div>
</form>

<section class="card control-links" aria-labelledby="control-links-title">
  <div class="card-head"><h3 id="control-links-title" class="card-title">دسترسی سریع به بخش‌های دیگر مدیریت</h3></div>
  <div class="action-grid">
    <a class="action-card" href="index.php?page=admin_products"><strong>کالاها و موجودی</strong><span>اطلاعات کاتالوگ را ویرایش کنید</span></a>
    <a class="action-card" href="index.php?page=admin_categories"><strong>گروه‌های کالا</strong><span>گروه‌بندی کاتالوگ</span></a>
    <a class="action-card" href="index.php?page=admin_orders"><strong>سفارش‌ها و پرداخت</strong><span>پیگیری و بررسی پرداخت‌ها</span></a>
    <a class="action-card" href="index.php?page=admin_users"><strong>مشتریان</strong><span>پرونده و حساب خریداران</span></a>
    <a class="action-card" href="index.php?page=admin_rfqs"><strong>استعلام‌های قیمت</strong><span>بررسی و پاسخ به RFQها</span></a>
    <a class="action-card" href="index.php?page=admin_invoices"><strong>صورتحساب‌ها</strong><span>آرشیو و چاپ اسناد</span></a>
    <a class="action-card" href="index.php?page=admin_notifications"><strong>اعلان‌های مدیریت</strong><span>پیام‌های داخلی مدیران</span></a>
    <a class="action-card" href="index.php?page=admin_coupons"><strong>کدهای تخفیف</strong><span>مدیریت کدهای موجود</span></a>
    <a class="action-card" href="index.php?page=admin_reports"><strong>گزارش‌های فروش</strong><span>خلاصه و خروجی گزارش‌ها</span></a>
    <a class="action-card" href="index.php?page=admin_logs"><strong>گزارش رویدادها</strong><span>تاریخچه عملیات مدیریت</span></a>
    <a class="action-card" href="index.php?page=admin_profile"><strong>پروفایل مدیر</strong><span>اطلاعات حساب مدیریت</span></a>
    <a class="action-card" href="index.php?page=admin_settings"><strong>تنظیمات پیشرفته</strong><span>صفحهٔ تنظیمات فعلی و وضعیت سیستم</span></a>
    <a class="action-card" href="index.php?page=admin"><strong>داشبورد مدیریت</strong><span>آمار و موارد نیازمند اقدام</span></a>
  </div>
</section>

<section class="card" aria-labelledby="broadcast-title">
  <div class="card-head"><h3 id="broadcast-title" class="card-title">ارسال پیام همگانی به کاربران</h3></div>
  <p class="mini-note">این فرم یک اعلان داخلی برای همهٔ حساب‌های خریدار فعال می‌سازد. نمایش بنر عمومی سایت از بخش «اعلان همگانی سایت» بالاتر کنترل می‌شود.</p>
  <form method="POST" action="index.php" class="broadcast-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="admin_broadcast">
    <div class="grid-2">
      <div class="input-group"><label for="broadcast-title-field">عنوان پیام</label><input id="broadcast-title-field" type="text" name="title" maxlength="160" required></div>
      <div class="input-group"><label for="broadcast-link-field">پیوند داخلی (اختیاری)</label><input id="broadcast-link-field" type="text" name="link" dir="ltr" maxlength="240" placeholder="index.php?page=home"></div>
    </div>
    <div class="input-group"><label for="broadcast-body-field">متن پیام</label><textarea id="broadcast-body-field" name="body" rows="3" maxlength="1600" required></textarea></div>
    <button class="btn btn-secondary" type="submit">ارسال اعلان به همهٔ خریداران فعال</button>
  </form>
</section>
