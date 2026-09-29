<?php
/** ورود به حساب (خریدار و مدیر) */
$next = get('next');
?>
<div class="auth-layout">
  <div class="auth-card-full">
    <div class="auth-head">
      <div class="logo-icon">🏗️</div>
      <div>
        <h1>ورود به کارپوشه خرید سازمانی</h1>
        <p>با شماره همراه و گذرواژه خود وارد شوید. مدیران سیستم به پنل مدیریت هدایت می‌شوند.</p>
      </div>
    </div>

    <form method="POST" action="index.php<?= $next ? '?next=' . e($next) : '' ?>" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="login">
      <div class="input-group">
        <label>شماره تلفن همراه *</label>
        <input type="text" name="phone" required maxlength="11" inputmode="numeric" placeholder="۰۹۱۲۱۱۱۱۱۱۱" value="<?= e(get('phone')) ?>">
      </div>
      <div class="input-group">
        <label>گذرواژه *</label>
        <input type="password" name="password" required placeholder="••••••••">
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%">ورود به حساب</button>
      <div class="auth-links">
        <span>حساب کاربری ندارید؟ <a href="index.php?page=register">ثبت‌نام خریدار سازمانی</a></span>
        <a href="index.php?page=contact">فراموشی گذرواژه / پشتیبانی</a>
      </div>
    </form>

    <div class="demo-box">
      <strong>🔐 حساب‌های نمایشی برای تست پنل‌ها</strong>
      <div class="demo-grid">
        <div class="demo-item">
          <span class="pill primary">مدیر سیستم</span>
          <div class="mono">۰۹۱۲۰۰۰۰۰۰۰</div>
          <div class="mono">admin1234</div>
          <button type="button" class="btn btn-secondary btn-sm" data-fill-phone="09120000000" data-fill-pass="admin1234">درج خودکار</button>
        </div>
        <div class="demo-item">
          <span class="pill success">خریدار سازمانی</span>
          <div class="mono">۰۹۱۲۱۱۱۱۱۱۱</div>
          <div class="mono">buyer1234</div>
          <button type="button" class="btn btn-secondary btn-sm" data-fill-phone="09121111111" data-fill-pass="buyer1234">درج خودکار</button>
        </div>
      </div>
    </div>
  </div>

  <aside class="auth-side">
    <h2>پنل خریدار سازمانی شامل چه امکاناتی است؟</h2>
    <ul class="feature-list">
      <li>📦 پیگیری مرحله‌به‌مرحله سفارش‌ها و کد رهگیری مرسوله</li>
      <li>🧾 مشاهده، چاپ و آرشیو صورتحساب‌های الکترونیکی</li>
      <li>📋 ثبت استعلام قیمت پروژه و مشاهده پیش‌فاکتور سازمانی</li>
      <li>⭐ لیست علاقه‌مندی‌ها برای تأمین سریع مجدد اقلام</li>
      <li>👤 تکمیل کارپوشه: شناسه ملی، کد اقتصادی، نشانی و کد پستی</li>
      <li>🔔 اعلان تغییر وضعیت سفارش و پاسخ استعلام‌ها</li>
    </ul>
  </aside>
</div>
