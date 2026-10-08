<?php
/** تنظیمات فروشگاه */
require_admin();
$s = settings();
$vatSum = (int)$db->query('SELECT COALESCE(SUM(tax_amount),0) FROM invoices')->fetchColumn();
?>

<div class="detail-head">
  <div>
    <h2 class="sec-title">تنظیمات فروشگاه و اسناد مالی</h2>
    <p class="sec-sub">این مقادیر در تمام صفحات فروشگاه، صورتحساب‌های الکترونیکی و محاسبات مالی استفاده می‌شوند.</p>
  </div>
  <div class="flex-gap">
    <span class="pill info">ارزش افزوده صادرشده: <?= money_short($vatSum) ?> تومان</span>
  </div>
</div>

<form method="POST" action="index.php" class="card">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="settings_save">

  <div class="form-section">
    <h3 class="card-title">هویت فروشگاه</h3>
    <div class="grid-2">
      <div class="input-group">
        <label>نام فروشگاه / شرکت</label>
        <input type="text" name="site_name" value="<?= e($s['site_name']) ?>">
      </div>
      <div class="input-group">
        <label>شعار یا شرح کوتاه</label>
        <input type="text" name="site_slogan" value="<?= e($s['site_slogan']) ?>">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label>تلفن پشتیبانی و تدارکات</label>
        <input type="text" name="phone" value="<?= e($s['phone']) ?>">
      </div>
      <div class="input-group">
        <label>ایمیل سازمانی</label>
        <input type="text" name="email" value="<?= e($s['email']) ?>">
      </div>
    </div>
    <div class="input-group">
      <label>نشانی دفتر مرکزی / انبار</label>
      <input type="text" name="address" value="<?= e($s['address']) ?>">
    </div>
    <div class="input-group">
      <label>ساعات کاری</label>
      <input type="text" name="work_hours" value="<?= e($s['work_hours']) ?>">
    </div>
  </div>

  <div class="form-section">
    <h3 class="card-title">محاسبات مالی و ارسال</h3>
    <div class="grid-3">
      <div class="input-group">
        <label>نرخ مالیات بر ارزش افزوده (٪)</label>
        <input type="number" name="vat_rate" value="<?= e($s['vat_rate']) ?>" min="0" max="30" step="0.5">
        <small class="mini-note">مطابق نرخ قانونی جاری (۱۰٪).</small>
      </div>
      <div class="input-group">
        <label>هزینه ثابت ارسال (تومان)</label>
        <input type="number" name="shipping_cost" value="<?= (int)$s['shipping_cost'] ?>" min="0" step="50000">
      </div>
      <div class="input-group">
        <label>حد سفارش رایگان ارسال (تومان)</label>
        <input type="number" name="free_shipping_min" value="<?= (int)$s['free_shipping_min'] ?>" min="0" step="1000000">
      </div>
    </div>
    <div class="grid-2">
      <div class="input-group">
        <label>شناسه ملی شرکت (درج در صورتحساب)</label>
        <input type="text" name="company_national_id" value="<?= e($s['company_national_id']) ?>" class="mono">
      </div>
      <div class="input-group">
        <label>کد اقتصادی شرکت</label>
        <input type="text" name="company_economic_code" value="<?= e($s['company_economic_code']) ?>" class="mono">
      </div>
    </div>
    <div class="input-group">
      <label>اطلاعات حساب بانکی (درج در صفحه ثبت سفارش و تماس)</label>
      <textarea name="bank_info" rows="2"><?= e($s['bank_info']) ?></textarea>
    </div>
    <div class="input-group">
      <label>پیش‌شماره شماره‌گذاری فاکتور</label>
      <input type="text" name="invoice_prefix" value="<?= e($s['invoice_prefix']) ?>" class="mono">
    </div>
  </div>

  <div class="form-section">
    <h3 class="card-title">وضعیت سیستم</h3>
    <div class="table-note">
      نسخه سیستم: <strong><?= fa_text(APP_VERSION) ?></strong> |
      تعداد کالا: <strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn()) ?></strong> |
      تعداد کاربران: <strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()) ?></strong> |
      تعداد سفارش: <strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()) ?></strong> |
      تعداد صورتحساب: <strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM invoices')->fetchColumn()) ?></strong>
    </div>
    <div class="alert info">
      پایگاه داده SQLite در مسیر <code class="mono">parssaze.db</code> نگهداری می‌شود. برای پشتیبان‌گیری، این فایل را کپی کنید.
      جداول و ستون‌های جدید به صورت خودکار در اولین اجرا ساخته/به‌روزرسانی می‌شوند.
    </div>
  </div>

  <div class="form-actions">
    <button class="btn btn-primary btn-lg" type="submit">💾 ذخیره تنظیمات</button>
    <a class="btn btn-secondary" href="index.php?page=admin">انصراف</a>
  </div>
</form>
