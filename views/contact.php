<?php /** تماس با واحد فروش و پشتیبانی */ ?>
<h1 class="sec-title" style="margin-bottom:6px">تماس با واحد فروش و پشتیبانی فنی</h1>
<p class="sec-sub" style="margin-bottom:20px">برای استعلام قیمت پروژه‌ای، پیگیری اسناد مالیاتی و پشتیبانی فنی در ساعات کاری با ما در ارتباط باشید.</p>

<div class="grid-3">
  <div class="card contact-card">
    <span class="ico">☎</span>
    <h4>تلفن واحد فروش</h4>
    <strong><?= e(settings('phone')) ?></strong>
    <p><?= e(settings('work_hours')) ?></p>
  </div>
  <div class="card contact-card">
    <span class="ico">✉</span>
    <h4>ایمیل سازمانی</h4>
    <strong><?= e(settings('email')) ?></strong>
    <p>پاسخ‌دهی حداکثر تا یک روز کاری</p>
  </div>
  <div class="card contact-card">
    <span class="ico">📍</span>
    <h4>نشانی دفتر مرکزی و انبار</h4>
    <strong><?= e(settings('address')) ?></strong>
    <p>بازدید حضوری با هماهنگی قبلی</p>
  </div>
</div>

<div class="grid-2" style="margin-top:20px">
  <div class="card">
    <h3 class="card-title">اطلاعات پرداخت و تسویه</h3>
    <div class="bank-box">
      <strong>حساب رسمی شرکت</strong>
      <p class="mono"><?= e(settings('bank_info')) ?></p>
      <small>پس از واریز، تصویر فیش را به همراه شماره سفارش برای پشتیبانی ارسال کنید تا وضعیت پرداخت در پنل به‌روزرسانی شود.</small>
    </div>
    <div class="kv"><span>شناسه ملی شرکت:</span><strong class="mono"><?= e(settings('company_national_id')) ?></strong></div>
    <div class="kv"><span>کد اقتصادی:</span><strong class="mono"><?= e(settings('company_economic_code')) ?></strong></div>
  </div>

  <div class="card">
    <h3 class="card-title">پرسش‌های پرتکرار خریداران</h3>
    <details class="faq"><summary>صورتحساب الکترونیکی چه زمانی صادر می‌شود؟</summary><p>به محض ثبت سفارش، صورتحساب نوع ۱ با شناسه یکتا صادر و در کارپوشه مؤدیان شما ثبت می‌شود.</p></details>
    <details class="faq"><summary>امکان خرید اعتباری وجود دارد؟</summary><p>بله، برای پیمانکاران دارای سابقه خرید، تسویه اعتباری ۳۰ روزه قابل تنظیم است؛ درخواست خود را در استعلام قیمت ذکر کنید.</p></details>
    <details class="faq"><summary>هزینه ارسال چگونه محاسبه می‌شود؟</summary><p>هزینه ارسال ثابت <?= money(settings('shipping_cost')) ?> است و برای سفارش‌های بالای <?= money(settings('free_shipping_min')) ?> رایگان می‌شود.</p></details>
    <details class="faq"><summary>آیا کالاها گارانتی دارند؟</summary><p>تمامی ابزارهای دقیق و برقی دارای گارانتی رسمی شرکت واردکننده و خدمات پس از فروش هستند.</p></details>
  </div>
</div>
