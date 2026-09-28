</div> <!-- پایان page-wrapper -->

<!-- مودال ورود / ثبت‌نام -->
<div class="modal-overlay" id="authModal" role="dialog" aria-modal="true" aria-labelledby="authTitle">
  <div class="auth-card">
    <div class="auth-header">
      <button class="modal-close" onclick="closeModal('authModal')" aria-label="بستن">✕</button>
      <h3 id="authTitle" style="font-size:16px;font-weight:800">ورود به پنل مالی و کارپوشه</h3>
      <p style="font-size:11.5px;opacity:.75;margin-top:4px">با شمارهٔ همراه خود وارد شوید؛ در صورت نخستین ورود، حساب به‌صورت خودکار ساخته می‌شود.</p>
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
        <input type="text" name="phone" required maxlength="11" inputmode="numeric" placeholder="۰۹۱۲XXXXXXX">
      </div>
      <button type="submit" name="btn_login" class="btn btn-primary" style="width:100%;margin-top:10px">ورود به حساب</button>
    </form>
  </div>
</div>

<!-- مودال جزئیات کالا (نمایش سریع) -->
<div class="modal-overlay" id="productModal" role="dialog" aria-modal="true" aria-labelledby="pmTitle">
  <div class="auth-card product-card-modal">
    <div class="auth-header product-header">
      <button class="modal-close" onclick="closeModal('productModal')" aria-label="بستن">✕</button>
      <div class="pm-icon" id="pmIcon">📦</div>
      <div style="min-width:0">
        <div class="pm-brand" id="pmBrand"></div>
        <h3 id="pmTitle" style="font-size:15px;font-weight:800;line-height:1.7"></h3>
      </div>
    </div>
    <div class="pm-body">
      <p class="pm-desc" id="pmDesc"></p>
      <div class="pm-meta">
        <span class="pro-taxcode" id="pmTax"></span>
        <span id="pmStock"></span>
      </div>
      <div class="pm-price" id="pmPrice"></div>
      <div class="pm-qty" id="pmQtyWrap">
        <span style="font-size:12px;font-weight:700;color:var(--g500)">تعداد:</span>
        <button type="button" class="btn btn-sm btn-secondary" id="pmMinus" aria-label="کاهش تعداد">−</button>
        <input type="number" id="pmQty" value="1" min="1" readonly aria-label="تعداد">
        <button type="button" class="btn btn-sm btn-secondary" id="pmPlus" aria-label="افزایش تعداد">+</button>
      </div>
      <a class="btn btn-primary" id="pmAdd" href="index.php?page=cart" style="width:100%">افزودن به سبد خرید</a>
    </div>
  </div>
</div>

<footer class="site-footer no-print">
  <div class="container flex-between">
    <div>© <?= fa_digits(jalali_year()) ?> تمامی حقوق مادی و معنوی برای «پارس سازه و آفیس» محفوظ است.</div>
    <div>متصل به وب‌سرویس سامانه پایانه‌های فروشگاهی و مؤدیان دارایی</div>
  </div>
</footer>

<script src="app.js"></script>
</body>
</html>
