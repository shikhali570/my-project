</div> <!-- پایان page-wrapper -->

<footer class="site-footer no-print">
  <div class="container footer-grid">
    <div>
      <div class="footer-logo">🏗️ <?= e(settings('site_name', 'پارس سازه و آفیس')) ?></div>
      <p class="footer-text">
        تأمین تخصصی ابزار دقیق، ادوات نقشه‌برداری، تجهیزات ایمنی کارگاهی و ملزومات دفاتر فنی پروژه‌های عمرانی؛
        همراه با صدور صورتحساب الکترونیکی مطابق قانون پایانه‌های فروشگاهی و سامانه مؤدیان.
      </p>
      <div class="footer-badges">
        <span class="pill success">✅ عضو سامانه مؤدیان</span>
        <span class="pill info">🧾 صورتحساب نوع ۱</span>
      </div>
    </div>
    <div>
      <h4>دسترسی سریع</h4>
      <a href="index.php?page=home">کاتالوگ کالاها</a>
      <a href="index.php?page=rfq">ثبت استعلام قیمت</a>
      <a href="index.php?page=track">پیگیری سفارش</a>
      <a href="index.php?page=about">استانداردهای ممیزی</a>
      <a href="index.php?page=contact">تماس با واحد فروش</a>
    </div>
    <div>
      <h4>حساب کاربری</h4>
      <a href="index.php?page=login">ورود به پنل خریدار</a>
      <a href="index.php?page=register">ثبت‌نام خریدار سازمانی</a>
      <a href="index.php?page=panel">پیشخوان خریدار</a>
      <a href="index.php?page=cart">سبد سفارش</a>
      <a href="index.php?page=login">ورود مدیر سیستم</a>
    </div>
    <div>
      <h4>اطلاعات تماس</h4>
      <div class="footer-contact">☎ <?= e(settings('phone')) ?></div>
      <div class="footer-contact">✉ <?= e(settings('email')) ?></div>
      <div class="footer-contact">📍 <?= e(settings('address')) ?></div>
      <div class="footer-contact">🕘 <?= e(settings('work_hours')) ?></div>
      <div class="footer-contact mono"><?= e(settings('bank_info')) ?></div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container flex-between">
      <span>© <?= fa_num(jyear()) ?> تمامی حقوق مادی و معنوی برای «<?= e(settings('site_name')) ?>» محفوظ است.</span>
      <span>نسخه <?= fa_num(APP_VERSION) ?> | متصل به وب‌سرویس پایانه فروشگاهی و سامانه مؤدیان</span>
    </div>
  </div>
</footer>

<script src="app.js?v=<?= APP_VERSION ?>"></script>
</body>
</html>
