<?php /** تماس با واحد فروش و پشتیبانی */ ?>
<h1 class="sec-title" style="margin-bottom:6px"><?= e(site_content('contact_heading', 'تماس با واحد فروش و پشتیبانی فنی')) ?></h1>
<p class="sec-sub" style="margin-bottom:20px"><?= nl2br(e(site_content('contact_intro', 'برای استعلام قیمت، پیگیری اسناد مالیاتی و پشتیبانی فنی در ساعات کاری با ما در ارتباط باشید.'))) ?></p>

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
    <p><?= e(site_content('contact_email_note', 'برای پیگیری از طریق ایمیل با ما در تماس باشید.')) ?></p>
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
    <details class="faq"><summary><?= e(site_content('contact_faq_invoice_q', 'صورتحساب الکترونیکی چه زمانی صادر می‌شود؟')) ?></summary><p><?= nl2br(e(site_content('contact_faq_invoice_a', 'به محض ثبت سفارش، صورتحساب نوع ۱ با شناسه یکتا صادر و در کارپوشه مؤدیان شما ثبت می‌شود.'))) ?></p></details>
    <details class="faq"><summary><?= e(site_content('contact_faq_payment_q', 'چه روش‌های پرداختی برای سفارش فعال است؟')) ?></summary><p><?= nl2br(e(site_content('contact_faq_payment_a', 'روش‌های پرداخت فعال هنگام ثبت سفارش نمایش داده می‌شوند.'))) ?></p></details>
    <details class="faq"><summary><?= e(site_content('contact_faq_shipping_q', 'هزینه ارسال چگونه محاسبه می‌شود؟')) ?></summary><p><?= nl2br(e(site_content('contact_faq_shipping_a', 'هزینه ارسال ثابت ' . money(settings('shipping_cost')) . ' است و برای سفارش‌های بالای ' . money(settings('free_shipping_min')) . ' رایگان می‌شود.'))) ?></p></details>
    <details class="faq"><summary><?= e(site_content('contact_faq_warranty_q', 'آیا کالاها گارانتی دارند؟')) ?></summary><p><?= nl2br(e(site_content('contact_faq_warranty_a', 'شرایط گارانتی هر کالا در صفحهٔ مشخصات همان کالا درج می‌شود.'))) ?></p></details>
  </div>
</div>
