</div> <!-- پایان page-wrapper -->

<div class="modal-overlay" id="authModal">
  <div class="auth-card">
    <div class="auth-header">
      <button onclick="closeModal('authModal')" style="position:absolute;left:18px;top:18px;background:none;border:none;color:#fff;font-size:20px;cursor:pointer">✕</button>
      <h3 style="font-size:16px;font-weight:800">ورود به پنل مالی و کارپوشه</h3>
    </div>
    <form method="POST" action="index.php" style="padding:22px">
      <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
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
        <input type="text" name="phone" required maxlength="11" placeholder="۰۹۱۲XXXXXXX">
      </div>
      <button type="submit" name="btn_login" class="btn btn-primary" style="width:100%;margin-top:10px">ورود به حساب</button>
    </form>
  </div>
</div>

<footer class="site-footer no-print">
  <div class="container flex-between">
    <div>© ۱۴۰۳ تمامی حقوق مادی و معنوی برای «پارس سازه و آفیس» محفوظ است.</div>
    <div>متصل به وب‌سرویس سامانه پایانه‌های فروشگاهی و مؤدیان دارایی</div>
  </div>
</footer>

<script src="app.js"></script>
</body>
</html>