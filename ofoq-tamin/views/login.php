<?php
/** ورود به حساب (خریدار و مدیر) */
$next = get('next');

// پس از ورود ناموفق، شماره همراه نگه داشته می‌شود
$formState = take_form_state('login');
$phoneValue = array_key_exists('phone', $formState['old'])
    ? (string)$formState['old']['phone']
    : (string)get('phone');
?>
<div class="auth-layout">
  <div class="auth-card-full">
    <div class="auth-head">
      <div class="logo-icon" aria-hidden="true">🏗️</div>
      <div>
        <h1>ورود به کارپوشه خرید سازمانی</h1>
        <p>با شماره همراه و گذرواژه خود وارد شوید. مدیران سیستم به پنل مدیریت هدایت می‌شوند.</p>
      </div>
    </div>

    <form method="POST" action="index.php<?= $next ? '?next=' . e($next) : '' ?>" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="login">

      <div class="input-group">
        <label for="l-phone">شماره همراه <span class="req" aria-hidden="true">*</span></label>
        <input id="l-phone" type="tel" name="phone" required maxlength="11" inputmode="numeric" autocomplete="username" dir="ltr"
               placeholder="09121111111" value="<?= e($phoneValue) ?>"<?= $phoneValue === '' ? ' autofocus' : '' ?>>
      </div>

      <div class="input-group">
        <label for="l-pass">گذرواژه <span class="req" aria-hidden="true">*</span></label>
        <input id="l-pass" type="password" name="password" required autocomplete="current-password" placeholder="••••••••"<?= $phoneValue !== '' ? ' autofocus' : '' ?>>
      </div>

      <button type="submit" class="btn btn-primary btn-lg btn-block">ورود به حساب</button>

      <div class="auth-links">
        <span>حساب کاربری ندارید؟ <a href="index.php?page=register">ثبت‌نام خریدار حقیقی یا حقوقی</a></span>
        <a href="index.php?page=contact">فراموشی گذرواژه / پشتیبانی</a>
      </div>
    </form>

    <details class="demo-box">
      <summary><strong>🔐 حساب‌های نمایشی برای تست پنل‌ها</strong> <span class="muted">(برای آزمایش)</span></summary>
      <div class="demo-grid">
        <div class="demo-item">
          <span class="pill primary">مدیر سیستم</span>
          <div class="mono">۰۹۱۲۰۰۰۰۰۰۰</div>
          <div class="mono">admin1234</div>
          <button type="button" class="btn btn-secondary btn-sm" data-fill-phone="09120000000" data-fill-pass="admin1234">درج خودکار</button>
        </div>
        <div class="demo-item">
          <span class="pill success">خریدار</span>
          <div class="mono">۰۹۱۲۱۱۱۱۱۱۱</div>
          <div class="mono">buyer1234</div>
          <button type="button" class="btn btn-secondary btn-sm" data-fill-phone="09121111111" data-fill-pass="buyer1234">درج خودکار</button>
        </div>
      </div>
    </details>
  </div>

  <aside class="auth-side">
    <h2>پنل خریدار شامل چه امکاناتی است؟</h2>
    <ul class="feature-list">
      <li>📦 پیگیری مرحله‌به‌مرحله سفارش‌ها و کد رهگیری مرسوله</li>
      <li>🧾 مشاهده، چاپ و آرشیو صورتحساب‌های الکترونیکی</li>
      <li>📋 ثبت استعلام قیمت پروژه و مشاهده پیش‌فاکتور</li>
      <li>⭐ لیست علاقه‌مندی‌ها برای تأمین سریع مجدد اقلام</li>
      <li>👤 تکمیل کارپوشه: کد ملی یا شناسه ملی، کد اقتصادی و نشانی</li>
      <li>🔔 اعلان تغییر وضعیت سفارش و پاسخ استعلام‌ها</li>
    </ul>
  </aside>
</div>
