<?php
/** سبد سفارش + فرم نهایی ثبت سفارش */
$totals = cart_totals($db, $_SESSION['coupon'] ?? null);
$items = $totals['items'];
$me = current_user();
$vatPercent = fa_num((float)settings('vat_rate', 10), 0);
$entityType = in_array(($me['entity_type'] ?? ''), ['individual', 'legal'], true)
    ? $me['entity_type']
    : (!empty($me['company']) ? 'legal' : '');
$defaultTaxId = $me['national_id'] ?? '';
if ($entityType === 'legal' && !empty($me['economic_code'])) {
    $defaultTaxId = $me['economic_code'];
}
$taxIdLabel = $entityType === 'individual' ? 'کد ملی' : 'شناسه ملی / کد اقتصادی';
$postalCodeRequired = in_array($entityType, ['individual', 'legal'], true);

// ورودی‌های فرم پس از خطا (یک‌بار مصرف)
$formState = take_form_state('checkout');
$old = $formState['old'];
$errs = $formState['errors'];
$fieldVal = function ($key, $default = '') use ($old) {
    return array_key_exists($key, $old) ? (string)$old[$key] : (string)$default;
};

$freeMin = (int)$totals['freeShippingMin'];
$subtotal = (int)$totals['subtotal'];
$remaining = ($freeMin > 0) ? max(0, $freeMin - $subtotal) : 0;
$progress = ($freeMin > 0 && $subtotal > 0) ? min(100, (int)round($subtotal * 100 / $freeMin)) : 0;

$walletOk = $me && (int)$me['credit'] >= (int)$totals['total'];
$mellatStatus = mellat_gateway_status();
$selectedPay = $fieldVal('payment_method', 'transfer');
if (!in_array($selectedPay, ['transfer', 'wallet', 'mellat'], true)) {
    $selectedPay = 'transfer';
}
if ($selectedPay === 'wallet' && !$walletOk) {
    $selectedPay = 'transfer';
}
if ($selectedPay === 'mellat' && !$mellatStatus['ready']) {
    $selectedPay = 'transfer';
}

$couponCode = (string)($_SESSION['coupon'] ?? '');
$stockErrors = [];
foreach ($errs as $key => $msg) {
    if (strpos((string)$key, 'stock_') === 0) {
        $stockErrors[(int)substr($key, 6)] = $msg;
    }
}
?>

<div class="page-head">
  <h1 class="sec-title">سبد سفارش و صدور صورتحساب الکترونیکی</h1>
  <p class="sec-sub">کالاهای پروژه را بررسی کنید، سپس اطلاعات تحویل و پرداخت را تکمیل کنید.</p>
</div>

<?php if (!$items): ?>
  <div class="empty-state">
    <span aria-hidden="true">🛒</span>
    <h3>سبد سفارش شما خالی است</h3>
    <p>برای صدور پیش‌فاکتور رسمی، ابتدا کالاهای مورد نیاز پروژه را از کاتالوگ انتخاب کنید.</p>
    <div class="flex-center gap-10 wrap">
      <a class="btn btn-primary" href="index.php?page=home">مشاهده کاتالوگ</a>
      <a class="btn btn-orange" href="index.php?page=rfq">ثبت استعلام پروژه</a>
    </div>
  </div>
<?php else: ?>
  <div class="cart-wrap">
    <div class="cart-main">

      <section class="cart-table" aria-labelledby="cart-items-title">
        <div class="cart-table-head">
          <h2 id="cart-items-title" class="card-title">اقلام سفارش (<?= fa_num(count($items)) ?>)</h2>
          <form method="POST" action="index.php" id="clear-cart-form" class="clear-cart-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cart_clear">
            <button class="btn btn-icon danger btn-sm" type="button" data-confirm="همه اقلام سبد سفارش حذف شود؟" data-form="clear-cart-form">🗑️ خالی کردن سبد</button>
          </form>
        </div>

        <?php foreach ($items as $it):
            $badge = stock_badge($it);
            $itemName = $it['name'];
            $qtyId = 'cq-' . (int)$it['id']; ?>
          <div class="cart-row">
            <a class="cart-thumb" href="<?= product_url($it['id']) ?>" tabindex="-1" aria-hidden="true"><?php $thumbSrc = product_image_src($it['image'] ?? ''); ?><?php if ($thumbSrc !== ''): ?><img src="<?= e($thumbSrc) ?>" alt="" loading="lazy"><?php else: ?><?= e($it['icon']) ?><?php endif; ?></a>

            <div class="cart-info">
              <a class="cart-name" href="<?= product_url($it['id']) ?>"><?= e($itemName) ?></a>
              <div class="cart-sub">
                <span><?= e($it['brand']) ?></span>
                <span class="mono">شناسه مالیاتی: <?= e($it['tax_id']) ?></span>
                <span>قیمت واحد: <?= money($it['price']) ?> <small>(بدون ارزش افزوده)</small></span>
                <span class="<?= $badge['class'] ?>"><?= e($badge['text']) ?></span>
              </div>
              <?php if (isset($stockErrors[(int)$it['id']])): ?>
                <div class="field-error" role="alert"><?= e($stockErrors[(int)$it['id']]) ?></div>
              <?php endif; ?>
            </div>

            <form class="qty-form" method="POST" action="index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cart_update">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <?php /* دکمه پیش‌فرض (Enter در فیلد تعداد) باید «ثبت تعداد» باشد، نه کاهش */ ?>
              <button type="submit" name="op" value="set" class="visually-hidden" tabindex="-1">ثبت تعداد</button>
              <button type="submit" name="op" value="dec" class="btn btn-icon" formnovalidate aria-label="کاهش یک عدد از «<?= e($itemName) ?>»">−</button>
              <label class="visually-hidden" for="<?= $qtyId ?>">تعداد «<?= e($itemName) ?>»</label>
              <input id="<?= $qtyId ?>" class="qty-input" type="number" name="qty" value="<?= (int)$it['qty'] ?>" min="1" max="<?= max(1, (int)$it['stock']) ?>" inputmode="numeric">
              <button type="submit" name="op" value="inc" class="btn btn-icon" formnovalidate aria-label="افزایش یک عدد از «<?= e($itemName) ?>»">+</button>
              <button type="submit" name="op" value="set" class="btn btn-sm btn-secondary">ثبت</button>
            </form>

            <div class="cart-line-total"><?= money($it['line_total']) ?></div>

            <form class="cart-remove" method="POST" action="index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cart_update">
              <input type="hidden" name="id" value="<?= (int)$it['id'] ?>">
              <button class="btn btn-icon danger" name="op" value="del" type="submit" formnovalidate aria-label="حذف «<?= e($itemName) ?>» از سبد" title="حذف از سبد">✕</button>
            </form>
          </div>
        <?php endforeach; ?>

        <div class="cart-foot">
          <div class="coupon-box">
            <form class="coupon-form" method="POST" action="index.php">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="cart_coupon">
              <label for="coupon-code">کد تخفیف پروژه‌ای</label>
              <div class="coupon-row">
                <input id="coupon-code" type="text" name="coupon_code" value="<?= e($couponCode) ?>" placeholder="مثلاً PROJECT10" autocomplete="off" autocapitalize="characters" spellcheck="false"<?= $totals['couponError'] ? ' aria-invalid="true" aria-describedby="coupon-msg"' : '' ?>>
                <button class="btn btn-secondary btn-sm" type="submit"><?= $couponCode !== '' ? 'اعمال مجدد' : 'اعمال کد' ?></button>
              </div>
            </form>

            <?php if ($totals['coupon']): ?>
              <p id="coupon-msg" class="coupon-status ok">
                ✓ کد <strong><?= e($totals['coupon']['code']) ?></strong> اعمال شد:
                <?= $totals['coupon']['type'] === 'percent' ? fa_num($totals['coupon']['amount']) . '٪' : money($totals['coupon']['amount']) ?> تخفیف.
              </p>
            <?php elseif ($couponCode !== '' && $totals['couponError']): ?>
              <p id="coupon-msg" class="coupon-status err" role="alert">
                کد «<?= e($couponCode) ?>» در حال حاضر اعمال نشده است: <?= e($totals['couponError']) ?>
              </p>
            <?php endif; ?>

            <?php if ($couponCode !== ''): ?>
              <form class="coupon-clear" method="POST" action="index.php">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cart_coupon">
                <input type="hidden" name="coupon_code" value="">
                <button class="link-btn" type="submit">حذف کد تخفیف</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </section>

      <section class="checkout-card" id="checkout" tabindex="-1" aria-labelledby="checkout-title">
        <h2 id="checkout-title" class="card-title">اطلاعات تحویل و پرداخت</h2>

        <?php if (!$me): ?>
          <div class="alert info">
            ثبت سفارش به‌صورت مهمان ممکن است. برای پیگیری سفارش‌ها و صورتحساب‌ها در کارپوشه، <a href="index.php?page=login">وارد حساب</a> شوید.
          </div>
        <?php endif; ?>

        <?php if ($errs): ?>
          <div class="form-errors" role="alert">
            <strong>ثبت سفارش انجام نشد. موارد زیر را اصلاح کنید:</strong>
            <ul>
              <?php foreach ($errs as $msg): ?>
                <li><?= e($msg) ?></li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form id="checkout-form" method="POST" action="index.php">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="checkout">
          <p class="form-legend"><span class="req" aria-hidden="true">*</span> فیلدهای الزامی</p>

          <div class="form-grid">
            <div class="input-group span-2">
              <label for="f-name">نام رابط خرید / تحویل‌گیرنده <span class="req" aria-hidden="true">*</span></label>
              <input id="f-name" type="text" name="customer_name" required autocomplete="name" placeholder="مثلاً مهندس علوی"
                     value="<?= e($fieldVal('customer_name', $me['name'] ?? '')) ?>"<?= field_invalid_attr($errs, 'customer_name') ?>>
              <?= field_error($errs, 'customer_name') ?>
            </div>

            <?php if (!$me || $entityType !== 'individual'): ?>
              <div class="input-group span-2">
                <label for="f-company">نام شرکت / شخصیت حقوقی<?= $entityType === 'legal' ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="muted">(اختیاری)</span>' ?></label>
                <input id="f-company" type="text" name="company" autocomplete="organization" placeholder="مثلاً شرکت مهندسی بناسازان"<?= $entityType === 'legal' ? ' required' : '' ?>
                       value="<?= e($fieldVal('company', $me['company'] ?? '')) ?>"<?= field_invalid_attr($errs, 'company') ?>>
                <?= field_error($errs, 'company') ?>
              </div>
            <?php endif; ?>

            <div class="input-group">
              <label for="f-phone">شماره همراه <span class="req" aria-hidden="true">*</span></label>
              <input id="f-phone" type="tel" name="phone" required maxlength="11" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09121111111"
                     value="<?= e($fieldVal('phone', $me['phone'] ?? '')) ?>"<?= field_invalid_attr($errs, 'phone') ?>>
              <?= field_error($errs, 'phone') ?>
            </div>

            <div class="input-group">
              <label for="f-tax"><?= e($taxIdLabel) ?> <span class="muted">(اختیاری)</span></label>
              <input id="f-tax" type="text" name="tax_id" inputmode="numeric" autocomplete="off"
                     value="<?= e($fieldVal('tax_id', $defaultTaxId)) ?>">
            </div>

            <div class="input-group">
              <label for="f-postal">کد پستی<?= $postalCodeRequired ? ' <span class="req" aria-hidden="true">*</span>' : ' <span class="muted">(اختیاری)</span>' ?></label>
              <input id="f-postal" type="text" name="postal_code" maxlength="10" inputmode="numeric" autocomplete="postal-code" dir="ltr"<?= $postalCodeRequired ? ' required' : '' ?>
                     value="<?= e($fieldVal('postal_code', $me['postal_code'] ?? '')) ?>"<?= field_invalid_attr($errs, 'postal_code') ?>>
              <?= field_error($errs, 'postal_code') ?>
            </div>

            <div class="input-group">
              <label for="f-province">استان</label>
              <input id="f-province" type="text" name="province" autocomplete="address-level1"
                     value="<?= e($fieldVal('province', $me['province'] ?? '')) ?>">
            </div>

            <div class="input-group">
              <label for="f-city">شهر</label>
              <input id="f-city" type="text" name="city" autocomplete="address-level2"
                     value="<?= e($fieldVal('city', $me['city'] ?? '')) ?>">
            </div>

            <div class="input-group span-2">
              <label for="f-address">نشانی کامل تحویل کالا <span class="req" aria-hidden="true">*</span></label>
              <textarea id="f-address" name="address" rows="2" required autocomplete="street-address" placeholder="خیابان، کوچه، پلاک و کد پستی"<?= field_invalid_attr($errs, 'address') ?>><?= e($fieldVal('address', $me['address'] ?? '')) ?></textarea>
              <?= field_error($errs, 'address') ?>
            </div>
          </div>

          <fieldset class="pay-group<?= isset($errs['payment_method']) ? ' is-invalid' : '' ?>">
            <legend>روش پرداخت</legend>

            <label class="pay-option<?= $selectedPay === 'transfer' ? ' selected' : '' ?>" for="pay-transfer">
              <input id="pay-transfer" type="radio" name="payment_method" value="transfer"<?= $selectedPay === 'transfer' ? ' checked' : '' ?>>
              <span>
                <strong>کارت به کارت / انتقال بانکی</strong>
                <small>پس از ثبت سفارش، از طریق کارت به کارت یا پایا پرداخت کنید.</small>
              </span>
            </label>

            <?php if ($mellatStatus['ready']): ?>
              <label class="pay-option<?= $selectedPay === 'mellat' ? ' selected' : '' ?>" for="pay-mellat">
                <input id="pay-mellat" type="radio" name="payment_method" value="mellat"<?= $selectedPay === 'mellat' ? ' checked' : '' ?>>
                <span>
                  <strong>پرداخت آنلاین به‌پرداخت ملت</strong>
                  <small>پرداخت در درگاه بانکی؛ وضعیت سفارش پس از تأیید نهایی بانک به‌روزرسانی می‌شود.</small>
                </span>
              </label>
            <?php endif; ?>

            <label class="pay-option<?= $selectedPay === 'wallet' ? ' selected' : '' ?><?= $walletOk ? '' : ' is-disabled' ?>" for="pay-wallet">
              <input id="pay-wallet" type="radio" name="payment_method" value="wallet"<?= $selectedPay === 'wallet' ? ' checked' : '' ?><?= $walletOk ? '' : ' disabled' ?>>
              <span>
                <strong>کسر از اعتبار کارپوشه</strong>
                <small>
                  <?php if (!$me): ?>
                    برای استفاده، ابتدا وارد حساب خریدار شوید.
                  <?php elseif ($walletOk): ?>
                    موجودی اعتبار شما: <?= money($me['credit']) ?>
                  <?php else: ?>
                    اعتبار کافی نیست (موجودی: <?= money($me['credit']) ?>)
                  <?php endif; ?>
                </small>
              </span>
            </label>
            <?= field_error($errs, 'payment_method') ?>
          </fieldset>

          <div class="input-group">
            <label for="f-note">توضیحات سفارش <span class="muted">(اختیاری)</span></label>
            <textarea id="f-note" name="note" rows="2" autocomplete="off" placeholder="مثلاً: فاکتور به نام دفتر مرکزی صادر شود."><?= e($fieldVal('note', '')) ?></textarea>
          </div>
        </form>
      </section>
    </div>

    <aside class="cart-summary" aria-labelledby="summary-title">
      <h2 id="summary-title" class="card-title">جمع‌بندی سفارش</h2>

      <div class="sum-line"><span>جمع اقلام (بدون ارزش افزوده)</span><span><?= money($subtotal) ?></span></div>
      <?php if ($totals['discount'] > 0): ?>
        <div class="sum-line discount"><span>تخفیف کد «<?= e($totals['coupon']['code']) ?>»</span><span>− <?= money($totals['discount']) ?></span></div>
      <?php endif; ?>
      <div class="sum-line"><span>ارزش افزوده (<?= $vatPercent ?>٪)</span><span><?= money($totals['tax']) ?></span></div>
      <div class="sum-line">
        <span>هزینه ارسال</span>
        <?php if ($totals['shipping'] > 0): ?>
          <span><?= money($totals['shipping']) ?></span>
        <?php else: ?>
          <span class="free-tag">رایگان 🚚</span>
        <?php endif; ?>
      </div>

      <?php if ($freeMin > 0): ?>
        <div class="ship-hint<?= $totals['shipping'] === 0 ? ' done' : '' ?>">
          <?php if ($totals['shipping'] === 0): ?>
            🎉 ارسال رایگان برای این سفارش فعال است.
          <?php else: ?>
            تا ارسال رایگان، <strong><?= money($remaining) ?></strong> دیگر خرید کنید.
          <?php endif; ?>
          <div class="progress" role="progressbar" aria-label="پیشرفت تا ارسال رایگان" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $progress ?>">
            <span style="width:<?= $progress ?>%"></span>
          </div>
        </div>
      <?php endif; ?>

      <div class="sum-line total"><span>مبلغ قابل پرداخت</span><span><?= money($totals['total']) ?></span></div>

      <button type="submit" form="checkout-form" class="btn btn-green btn-lg btn-block">🧾 ثبت سفارش و صدور صورتحساب</button>
      <p class="mini-note">با ثبت سفارش، صورتحساب الکترونیکی نوع ۱ با شناسه یکتای مالیاتی صادر و به کارپوشه مؤدیان خریدار منتقل می‌شود.</p>
      <p class="mini-note"><a href="index.php?page=home">ادامه خرید از کاتالوگ</a></p>
    </aside>
  </div>
<?php endif; ?>
