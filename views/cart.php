<?php
/** سبد سفارش + فرم نهایی ثبت سفارش */
$totals = cart_totals($db, $_SESSION['coupon'] ?? null);
$items = $totals['items'];
$me = current_user();
$vatPercent = fa_num((float)settings('vat_rate', 10), 0);
?>

<h2 class="sec-title" style="margin-bottom:16px">سبد سفارش و صدور صورتحساب الکترونیکی</h2>

<?php if (!$items): ?>
  <div class="empty-state">
    <span>🛒</span>
    <h3>سبد سفارش شما خالی است</h3>
    <p>برای صدور پیش‌فاکتور رسمی، ابتدا کالاهای مورد نیاز پروژه را از کاتالوگ انتخاب کنید.</p>
    <div class="flex-center gap-10">
      <a class="btn btn-primary" href="index.php?page=home">مشاهده کاتالوگ</a>
      <a class="btn btn-orange" href="index.php?page=rfq">ثبت استعلام پروژه</a>
    </div>
  </div>
<?php else: ?>
  <div class="cart-wrap">
    <div class="cart-table">
      <?php foreach ($items as $it): ?>
        <div class="cart-row">
          <a class="cart-thumb" href="<?= product_url($it['id']) ?>"><?= e($it['icon']) ?></a>
          <div class="cart-info">
            <a class="cart-name" href="<?= product_url($it['id']) ?>"><?= e($it['name']) ?></a>
            <div class="cart-sub">
              <span><?= e($it['brand']) ?></span>
              <span class="mono">شناسه مالیاتی: <?= e($it['tax_id']) ?></span>
              <span>قیمت واحد: <?= money($it['price']) ?></span>
              <span class="<?= stock_badge($it)['class'] ?>">موجودی: <?= fa_num($it['stock']) ?> <?= e($it['unit']) ?></span>
            </div>
          </div>
          <form class="inline-form" method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cart_update">
            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-icon" name="op" value="dec" type="submit">−</button>
            <input type="number" class="qty-input" name="qty" value="<?= (int)$it['qty'] ?>" min="1" max="<?= max(1, (int)$it['stock']) ?>">
            <button class="btn btn-icon" name="op" value="inc" type="submit">+</button>
            <button class="btn btn-icon" name="op" value="set" type="submit" title="ثبت تعداد">↻</button>
          </form>
          <div class="cart-line-total"><?= money($it['line_total']) ?></div>
          <form class="inline-form" method="POST" action="index.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cart_update">
            <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
            <button class="btn btn-icon danger" name="op" value="del" type="submit" title="حذف از سبد">✕</button>
          </form>
        </div>
      <?php endforeach; ?>

      <div class="cart-foot">
        <form class="coupon-form" method="POST" action="index.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cart_coupon">
          <input type="text" name="coupon_code" value="<?= e($totals['coupon']['code'] ?? '') ?>" placeholder="کد تخفیف پروژه (مثلاً PROJECT10)">
          <button class="btn btn-secondary btn-sm" type="submit"><?= $totals['coupon'] ? 'تغییر کد' : 'اعمال کد تخفیف' ?></button>
        </form>
        <form method="POST" action="index.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="cart_clear">
          <button class="btn btn-icon danger" type="submit">🗑️ خالی کردن سبد</button>
        </form>
      </div>

      <?php if ($totals['coupon']): ?>
        <div class="alert success" style="margin-top:12px">
          کد تخفیف <strong><?= e($totals['coupon']['code']) ?></strong> فعال است
          (<?= $totals['coupon']['type'] === 'percent' ? fa_num($totals['coupon']['amount']) . '٪' : money($totals['coupon']['amount']) ?> تخفیف).
        </div>
      <?php endif; ?>
    </div>

    <aside class="cart-summary">
      <h3 class="card-title">جمع‌بندی و ثبت سفارش</h3>

      <div class="sum-line"><span>جمع خالص اقلام:</span><span><?= money($totals['subtotal']) ?></span></div>
      <?php if ($totals['discount'] > 0): ?>
        <div class="sum-line discount"><span>تخفیف پروژه‌ای:</span><span>− <?= money($totals['discount']) ?></span></div>
      <?php endif; ?>
      <div class="sum-line"><span>مالیات بر ارزش افزوده (<?= $vatPercent ?>٪):</span><span><?= money($totals['tax']) ?></span></div>
      <div class="sum-line">
        <span>هزینه ارسال:</span>
        <span><?= $totals['shipping'] > 0 ? money($totals['shipping']) : 'رایگان 🚚' ?></span>
      </div>
      <div class="sum-line total"><span>مبلغ قابل پرداخت:</span><span><?= money($totals['total']) ?></span></div>

      <form method="POST" action="index.php" class="checkout-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="checkout">

        <?php if (!$me): ?>
          <div class="alert info">برای پیگیری سفارش و مشاهده صورتحساب در پنل، پیشنهاد می‌کنیم ابتدا <a href="index.php?page=login">وارد حساب</a> شوید. ثبت سفارش مهمان نیز امکان‌پذیر است.</div>
        <?php endif; ?>

        <div class="input-group">
          <label>نام رابط خرید / تحویل‌گیرنده *</label>
          <input type="text" name="customer_name" required value="<?= e($me['name'] ?? '') ?>" placeholder="مهندس علوی">
        </div>
        <div class="input-group">
          <label>نام شرکت / شخصیت حقوقی</label>
          <input type="text" name="company" value="<?= e($me['company'] ?? '') ?>" placeholder="شرکت مهندسی بناسازان">
        </div>
        <div class="grid-2">
          <div class="input-group">
            <label>شماره تماس *</label>
            <input type="text" name="phone" required maxlength="11" value="<?= e($me['phone'] ?? '') ?>" placeholder="09121111111">
          </div>
          <div class="input-group">
            <label>شناسه ملی / کد اقتصادی</label>
            <input type="text" name="tax_id" value="<?= e($me['national_id'] ?? '') ?>">
          </div>
        </div>
        <div class="grid-2">
          <div class="input-group">
            <label>استان</label>
            <input type="text" name="province" value="<?= e($me['province'] ?? '') ?>">
          </div>
          <div class="input-group">
            <label>شهر</label>
            <input type="text" name="city" value="<?= e($me['city'] ?? '') ?>">
          </div>
        </div>
        <div class="input-group">
          <label>نشانی کامل تحویل کالا *</label>
          <textarea name="address" rows="2" required><?= e($me['address'] ?? '') ?></textarea>
        </div>
        <div class="input-group">
          <label>روش پرداخت</label>
          <select name="payment_method">
            <option value="transfer">کارت به کارت / انتقال بانکی</option>
            <option value="credit">تسویه اعتباری ۳۰ روزه (پیمانکاران)</option>
            <option value="wallet">کسر از اعتبار کارپوشه</option>
          </select>
        </div>
        <div class="input-group">
          <label>توضیحات سفارش (اختیاری)</label>
          <textarea name="note" rows="2" placeholder="مثلاً: فاکتور به نام دفتر مرکزی صادر شود."></textarea>
        </div>
        <button type="submit" class="btn btn-green btn-lg" style="width:100%">🧾 ثبت سفارش و صدور صورتحساب الکترونیکی</button>
        <p class="mini-note">با ثبت سفارش، صورتحساب الکترونیکی نوع ۱ با شناسه یکتای مالیاتی صادر و به کارپوشه مؤدیان خریدار منتقل می‌شود.</p>
      </form>
    </aside>
  </div>
<?php endif; ?>
