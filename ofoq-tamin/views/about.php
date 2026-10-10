<?php /** درباره ما و استانداردهای مالیاتی */ ?>
<div class="about-hero">
  <div>
    <span class="pill info">درباره <?= e(settings('site_name')) ?></span>
    <h1>تأمین تخصصی تجهیزات پروژه، با اسناد مالیاتی بی‌نقص</h1>
    <p>
      ما از سال ۱۳۹۲ در حوزه تأمین ابزار دقیق نقشه‌برداری، تجهیزات ایمنی کارگاهی و ملزومات دفاتر فنی
      پروژه‌های عمرانی فعالیت می‌کنیم. تمرکز ما بر دو موضوع است: تأمین کالای اصل با قیمت رقابتی،
      و صدور اسناد مالیاتی معتبر که در ممیزی دارایی خریدار را دچار چالش نکند.
    </p>
  </div>
  <div class="about-stats">
    <div><strong>۱۲+</strong><span>سال سابقه تأمین پروژه‌ای</span></div>
    <div><strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn()) ?>+</strong><span>قلم کالای فعال</span></div>
    <div><strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()) ?></strong><span>سفارش رسمی ثبت‌شده</span></div>
    <div><strong>۱۰۰٪</strong><span>انطباق با سامانه مؤدیان</span></div>
  </div>
</div>

<div class="grid-2">
  <div class="card">
    <h3 class="card-title">استاندارد مالیاتی ما</h3>
    <ul class="feature-list">
      <li>🧾 صدور صورتحساب الکترونیکی نوع ۱ برای همه سفارش‌ها با شناسه یکتای مالیاتی</li>
      <li>🔢 ثبت شناسه کالا (IRK) برای تمام اقلام کاتالوگ مطابق فهرست سازمان امور مالیاتی</li>
      <li>🔁 انتقال اعتبار ارزش افزوده به کارپوشه مؤدیان خریدار در همان لحظه صدور</li>
      <li>🛡️ نگهداری سابقه صورتحساب‌ها و امکان دریافت رونوشت رسمی در هر زمان</li>
    </ul>
  </div>
  <div class="card">
    <h3 class="card-title">خدمات تدارکات پروژه</h3>
    <ul class="feature-list">
      <li>🤝 مشاور فنی تأمین کالا داریم</li>
      <li>🚚 ارسال مستقیم به کارگاه، انبار پروژه یا دفتر فنی</li>
      <li>🧰 بسته‌بندی‌های آماده کارگاهی (پک ایمنی، پک دفتر فنی)</li>
      <li>🛠️ خدمات کالیبراسیون و سرویس دوره‌ای ابزار دقیق</li>
    </ul>
  </div>
</div>

<section class="sec-block">
  <div class="sec-head"><h2 class="sec-title">مسیر ثبت و صدور سند در سه گام</h2></div>
  <div class="steps-grid">
    <div class="step-card">
      <span class="step-no">۱</span>
      <h4>انتخاب کالا یا ثبت استعلام</h4>
      <p>از کاتالوگ کالاها را به سبد اضافه کنید یا فهرست اقلام پروژه را در فرم RFQ ثبت کنید.</p>
    </div>
    <div class="step-card">
      <span class="step-no">۲</span>
      <h4>تأیید پیش‌فاکتور و پرداخت</h4>
      <p>پیش‌فاکتور رسمی با مبالغ شفاف در پنل خریدار نمایش داده می‌شود؛ پس از پرداخت، سفارش وارد جریان آماده‌سازی می‌شود.</p>
    </div>
    <div class="step-card">
      <span class="step-no">۳</span>
      <h4>صدور صورتحساب و ارسال</h4>
      <p>صورتحساب الکترونیکی با شناسه یکتا صادر و کد رهگیری مرسوله در پنل شما ثبت می‌گردد.</p>
    </div>
  </div>
</section>

<section class="cta-band">
  <div>
    <h3>سؤالی درباره خرید سازمانی یا اسناد مالیاتی دارید؟</h3>
    <p>کارشناسان ما در ساعات کاری پاسخگوی شما هستند.</p>
  </div>
  <a class="btn btn-orange btn-lg" href="index.php?page=contact">تماس با واحد فروش</a>
</section>
