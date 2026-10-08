<?php
/** ثبت‌نام خریدار سازمانی */
$old = $_SESSION['old_register'] ?? [];
unset($_SESSION['old_register']);
$provinces = ['تهران', 'البرز', 'اصفهان', 'خراسان رضوی', 'فارس', 'آذربایجان شرقی', 'گیلان', 'مازندران', 'خوزستان', 'کرمان', 'یزد', 'قم', 'هرمزگان', 'سایر استان‌ها'];
?>
<div class="auth-layout">
  <div class="auth-card-full">
    <div class="auth-head">
      <div class="logo-icon">📝</div>
      <div>
        <h1>ثبت‌نام خریدار سازمانی</h1>
        <p>با تکمیل کارپوشه، امکان دریافت پیش‌فاکتور رسمی، تسویه اعتباری و پیگیری سفارش فعال می‌شود.</p>
      </div>
    </div>

    <form method="POST" action="index.php" class="auth-form">
      <?= csrf_field() ?>
      <input type="hidden" name="auth_action" value="register">
      <div class="grid-2">
        <div class="input-group">
          <label>نام و نام خانوادگی رابط *</label>
          <input type="text" name="name" required value="<?= e($old['name'] ?? '') ?>" placeholder="مهندس علوی">
        </div>
        <div class="input-group">
          <label>نام شرکت / مؤسسه</label>
          <input type="text" name="company" value="<?= e($old['company'] ?? '') ?>" placeholder="شرکت مهندسی بناسازان">
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>شماره تلفن همراه *</label>
          <input type="text" name="phone" required maxlength="11" inputmode="numeric" value="<?= e($old['phone'] ?? '') ?>" placeholder="09121111111">
        </div>
        <div class="input-group">
          <label>ایمیل سازمانی</label>
          <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" placeholder="procurement@company.ir">
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>گذرواژه * (حداقل ۶ کاراکتر)</label>
          <input type="password" name="password" required>
        </div>
        <div class="input-group">
          <label>تکرار گذرواژه *</label>
          <input type="password" name="password2" required>
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>شناسه ملی / کد اقتصادی</label>
          <input type="text" name="national_id" value="<?= e($old['nationalId'] ?? '') ?>" placeholder="10103456789">
        </div>
        <div class="input-group">
          <label>استان</label>
          <select name="province">
            <?php foreach ($provinces as $pv): ?>
              <option value="<?= e($pv) ?>" <?= ($old['province'] ?? '') === $pv ? 'selected' : '' ?>><?= e($pv) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid-2">
        <div class="input-group">
          <label>شهر</label>
          <input type="text" name="city" value="<?= e($old['city'] ?? '') ?>">
        </div>
        <div class="input-group">
          <label>نشانی دفتر / انبار</label>
          <input type="text" name="address" value="<?= e($old['address'] ?? '') ?>" placeholder="تهران، خیابان گاندی، پلاک ۴۲">
        </div>
      </div>
      <button type="submit" class="btn btn-primary btn-lg" style="width:100%">ایجاد حساب خریدار سازمانی</button>
      <div class="auth-links">
        <span>قبلاً ثبت‌نام کرده‌اید؟ <a href="index.php?page=login">ورود به حساب</a></span>
      </div>
    </form>
  </div>

  <aside class="auth-side">
    <h2>چرا خرید سازمانی از پارس سازه؟</h2>
    <ul class="feature-list">
      <li>🧾 صورتحساب الکترونیکی معتبر و قابل استناد در ممیزی مالیاتی</li>
      <li>💳 امکان تسویه اعتباری ۳۰ روزه پس از بررسی سابقه</li>
      <li>📉 تخفیف پلکانی و قیمت‌گذاری پروژه‌ای</li>
      <li>🚚 ارسال به کارگاه‌های پروژه در سراسر کشور</li>
      <li>🤝 کارشناس اختصاصی تدارکات برای هر پیمانکار</li>
    </ul>
    <div class="side-note">
      اطلاعات کارپوشه شما فقط برای صدور اسناد رسمی استفاده می‌شود و در اختیار شخص ثالث قرار نمی‌گیرد.
    </div>
  </aside>
</div>
