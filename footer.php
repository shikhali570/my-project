</div> <!-- پایان page-wrapper -->

<!-- ============================================================
     مودال ورود / ثبت‌نام
============================================================= -->
<div class="modal-overlay no-print" id="authModal" role="dialog" aria-modal="true" aria-label="ورود به حساب">
  <div class="auth-card">
    <div class="auth-header">
      <div class="auth-head-text">
        <h3>ورود به پنل مالی و کارپوشه</h3>
        <p>فاکتورهای رسمی خود را بایگانی و چاپ کنید</p>
      </div>
      <button class="icon-btn close-btn" onclick="closeModal('authModal')" aria-label="بستن">✕</button>
    </div>
    <form method="POST" action="index.php" class="auth-form">
      <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
      <div class="input-group">
        <label>نام و نام‌خانوادگی رابط</label>
        <input type="text" name="name" placeholder="مهندس علوی">
      </div>
      <div class="input-group">
        <label>نام شرکت یا مؤسسه</label>
        <input type="text" name="company" placeholder="شرکت مهندسی بناسازان">
      </div>
      <div class="input-group">
        <label>شماره تلفن همراه *</label>
        <input type="tel" dir="ltr" class="ltr-input" name="phone" required maxlength="11" placeholder="09XXXXXXXXX">
      </div>
      <button type="submit" name="btn_login" class="btn btn-primary btn-lg">ورود به حساب</button>
      <p class="auth-note">🔐 با ثبت شماره، حساب سازمانی شما به صورت خودکار و رایگان ایجاد می‌شود.</p>
    </form>
  </div>
</div>

<!-- ============================================================
     فوتر
============================================================= -->
<footer class="site-footer no-print">
  <div class="container footer-grid">
    <div class="footer-col footer-about">
      <a href="index.php?page=home" class="footer-logo">
        <span class="logo-icon">🏗️</span>
        <strong><?= e(SITE['name']) ?></strong>
      </a>
      <p>تأمین تخصصی ابزار دقیق، ادوات نقشه‌برداری، ایمنی HSE و ملزومات دفاتر فنی پروژه‌های عمرانی — با صورتحساب رسمی و شناسه مالیاتی معتبر.</p>
      <span class="footer-badge">متصل به سامانه پایانه‌های فروشگاهی و مؤدیان دارایی</span>
    </div>
    <div class="footer-col">
      <h4>دسترسی سریع</h4>
      <ul>
        <li><a href="index.php?page=home">کاتالوگ تجهیزات</a></li>
        <li><a href="index.php?page=cart">سبد سفارش</a></li>
        <li><a href="index.php?page=rfq">استعلام پروژه (RFQ)</a></li>
        <li><a href="index.php?page=about">درباره شرکت</a></li>
      </ul>
    </div>
    <div class="footer-col">
      <h4>دسته‌بندی‌ها</h4>
      <ul>
        <?php foreach (CATEGORIES as $key => $c): ?>
          <li><a href="index.php?page=home&cat=<?= e($key) ?>"><?= $c['icon'] ?> <?= e($c['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="footer-col">
      <h4>تماس با ما</h4>
      <ul class="footer-contact">
        <li><span>☎️</span> <span dir="ltr"><?= e(SITE['phone']) ?></span></li>
        <li><span>📍</span> <?= e(SITE['address']) ?></li>
        <li><span>🕐</span> <?= e(SITE['hours']) ?></li>
      </ul>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container flex-between">
      <span>© ۱۴۰۵ تمامی حقوق مادی و معنوی برای «<?= e(SITE['name']) ?>» محفوظ است.</span>
      <span class="footer-tax">صدور صورتحساب الکترونیکی نوع ۱ — پذیرش قطعی در ممیزی مالیاتی</span>
    </div>
  </div>
</footer>

<button class="to-top no-print" id="toTop" aria-label="بازگشت به بالا">↑</button>

<script src="app.js"></script>
</body>
</html>
