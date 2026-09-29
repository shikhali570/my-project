<?php
/** صفحه اصلی: کاتالوگ کالاها با جست‌وجو، فیلتر و صفحه‌بندی */

$q = get('q');
$cat = get('cat');
$brand = get('brand');
$sort = get('sort', 'newest');
$priceMax = (int)en_digits(get('price_max'));
$onlyAvailable = get('available') === '1';

$where = [];
$params = [];

if ($cat !== '') {
    $where[] = 'category = ?';
    $params[] = $cat;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR brand LIKE ? OR tax_id LIKE ? OR sku LIKE ? OR description LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like, $like);
}
if ($brand !== '') {
    $where[] = 'brand = ?';
    $params[] = $brand;
}
if ($priceMax > 0) {
    $where[] = 'price <= ?';
    $params[] = $priceMax;
}
if ($onlyAvailable) {
    $where[] = 'stock > 0';
}

$orderBy = 'id DESC';
if ($sort === 'price_asc') {
    $orderBy = 'price ASC';
} elseif ($sort === 'price_desc') {
    $orderBy = 'price DESC';
} elseif ($sort === 'popular') {
    $orderBy = 'sold DESC';
}

$sqlWhere = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $db->prepare('SELECT COUNT(*) FROM products' . $sqlWhere);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$pg = paginate($total, 9, (int)get('p', 1));

$stmt = $db->prepare('SELECT * FROM products' . $sqlWhere . ' ORDER BY ' . $orderBy . ' LIMIT ' . (int)$pg['per_page'] . ' OFFSET ' . (int)$pg['offset']);
$stmt->execute($params);
$products = $stmt->fetchAll();

$brands = $db->query('SELECT brand, COUNT(*) AS c FROM products WHERE is_active = 1 GROUP BY brand ORDER BY c DESC LIMIT 12')->fetchAll();
$featured = $db->query('SELECT * FROM products WHERE is_active = 1 ORDER BY sold DESC LIMIT 4')->fetchAll();
$activeCat = $cat ? category_title($cat) : null;
?>

<?php if (!$cat && !$q && !$brand): ?>
  <section class="hero">
    <div class="hero-grid">
      <div>
        <span class="hero-badge">🧾 صورتحساب الکترونیکی معتبر برای ممیزی مالیاتی</span>
        <h1>خرید بی‌واسطه تجهیزات کارگاهی، نقشه‌برداری و اداری پروژه</h1>
        <p>
          از متر لیزری و تراز ۳۶۰ درجه تا رول پلاتر و تجهیزات HSE؛ همه با شناسه یکتای کالای سامانه مؤدیان،
          اعتبار ارزش افزوده انتقال‌یافته به کارپوشه شما و ارسال به سراسر کشور.
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="#catalog">مشاهده کاتالوگ کالاها</a>
          <a class="btn btn-outline" href="index.php?page=rfq">درخواست پیش‌فاکتور سازمانی</a>
        </div>
      </div>
      <div class="hero-cards">
        <div class="hero-card">
          <strong><?= fa_num($db->query("SELECT COUNT(*) FROM products WHERE is_active = 1")->fetchColumn()) ?></strong>
          <span>قلم کالای فعال با شناسه مالیاتی</span>
        </div>
        <div class="hero-card">
          <strong><?= fa_num((int)$db->query("SELECT COUNT(*) FROM users WHERE role = 'buyer'")->fetchColumn()) ?></strong>
          <span>خریدار سازمانی فعال</span>
        </div>
        <div class="hero-card">
          <strong><?= fa_num((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn()) ?></strong>
          <span>سفارش ثبت‌شده با فاکتور رسمی</span>
        </div>
        <div class="hero-card">
          <strong>۲۴h</strong>
          <span>پاسخ‌دهی به استعلام قیمت پروژه</span>
        </div>
      </div>
    </div>
  </section>

  <div class="trust-row">
    <div class="trust-item"><span>🚚</span> ارسال رایگان سفارش‌های بالای <?= money_short(settings('free_shipping_min')) ?> تومان</div>
    <div class="trust-item"><span>🧾</span> صدور صورتحساب نوع ۱ در همان لحظه ثبت سفارش</div>
    <div class="trust-item"><span>🔁</span> امکان مرجوعی ۷ روزه اقلام سالم و بسته‌بندی‌نشده</div>
    <div class="trust-item"><span>💳</span> پرداخت اعتباری و تسویه ۳۰ روزه برای پیمانکاران</div>
  </div>
<?php endif; ?>

<div class="catalog-head" id="catalog">
  <div>
    <h2 class="sec-title"><?= $activeCat ? e($activeCat) : 'تجهیزات و ادوات مهندسی' ?></h2>
    <p class="sec-sub">
      <?= fa_num($total) ?> کالا یافت شد<?= $q ? ' برای «' . e($q) . '»' : '' ?>
      <?= $onlyAvailable ? ' | فقط کالاهای موجود' : '' ?>
    </p>
  </div>
  <form class="filter-bar" method="GET" action="index.php">
    <input type="hidden" name="page" value="home">
    <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
    <input type="text" name="q" value="<?= e($q) ?>" placeholder="جست‌وجو…" class="filter-search">
    <select name="brand">
      <option value="">همه برندها</option>
      <?php foreach ($brands as $b): ?>
        <option value="<?= e($b['brand']) ?>" <?= $brand === $b['brand'] ? 'selected' : '' ?>><?= e($b['brand']) ?> (<?= fa_num($b['c']) ?>)</option>
      <?php endforeach; ?>
    </select>
    <select name="price_max">
      <option value="">هر قیمتی</option>
      <?php foreach ([2000000, 5000000, 10000000, 20000000] as $cap): ?>
        <option value="<?= $cap ?>" <?= $priceMax === $cap ? 'selected' : '' ?>>تا <?= money_short($cap) ?> تومان</option>
      <?php endforeach; ?>
    </select>
    <select name="sort">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>جدیدترین</option>
      <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>پرفروش‌ترین</option>
      <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ارزان‌ترین</option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>گران‌ترین</option>
    </select>
    <label class="check-inline">
      <input type="checkbox" name="available" value="1" <?= $onlyAvailable ? 'checked' : '' ?>> فقط موجود
    </label>
    <button class="btn btn-primary btn-sm" type="submit">اعمال فیلتر</button>
    <?php if ($q || $brand || $priceMax || $onlyAvailable || $sort !== 'newest'): ?>
      <a class="btn btn-secondary btn-sm" href="index.php?page=home<?= $cat ? '&cat=' . e($cat) : '' ?>">حذف فیلترها</a>
    <?php endif; ?>
  </form>
</div>

<?php if (!$products): ?>
  <div class="empty-state">
    <span>🔍</span>
    <h3>کالایی با این مشخصات پیدا نشد</h3>
    <p>می‌توانید فیلترها را تغییر دهید یا نیاز پروژه خود را در قالب استعلام قیمت ثبت کنید تا کارشناسان ما تأمین کنند.</p>
    <a class="btn btn-orange" href="index.php?page=rfq">ثبت استعلام قیمت</a>
  </div>
<?php else: ?>
  <div class="pro-grid">
    <?php foreach ($products as $p) {
        require 'views/partials/product_card.php';
    } ?>
  </div>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination">
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('home', ['p' => $i])) ?>"><?= fa_num($i) ?></a>
      <?php endfor; ?>
    </nav>
  <?php endif; ?>
<?php endif; ?>

<?php if (!$cat && !$q): ?>
  <section class="sec-block">
    <div class="sec-head">
      <h2 class="sec-title">پرفروش‌ترین‌های سه ماه گذشته</h2>
      <a class="link-more" href="<?= e(page_link('home', ['sort' => 'popular', 'p' => 1])) ?>">مشاهده همه ←</a>
    </div>
    <div class="mini-grid">
      <?php foreach ($featured as $f): ?>
        <a class="mini-card" href="<?= product_url($f['id']) ?>">
          <span class="mini-ico"><?= e($f['icon']) ?></span>
          <div>
            <strong><?= e($f['name']) ?></strong>
            <small><?= e($f['brand']) ?> • فروش <?= fa_num($f['sold']) ?> <?= e($f['unit']) ?></small>
          </div>
          <span class="mini-price"><?= money($f['price']) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="sec-block">
    <div class="sec-head">
      <h2 class="sec-title">گروه‌های کالایی</h2>
    </div>
    <div class="cat-grid">
      <?php foreach ($navCategories as $c):
          $cnt = $db->prepare('SELECT COUNT(*) FROM products WHERE category = ? AND is_active = 1');
          $cnt->execute([$c['slug']]); ?>
        <a class="cat-card" href="index.php?page=home&cat=<?= e($c['slug']) ?>">
          <span><?= e($c['icon']) ?></span>
          <strong><?= e($c['title']) ?></strong>
          <small><?= fa_num($cnt->fetchColumn()) ?> کالا</small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="cta-band">
    <div>
      <h3>خریدار سازمانی هستید و نیاز به پیش‌فاکتور رسمی دارید؟</h3>
      <p>با ثبت استعلام پروژه، فهرست اقلام و مقادیر را ارسال کنید؛ کارشناسان ما با اعمال تخفیف سازمانی، پیش‌فاکتور رسمی صادر می‌کنند.</p>
    </div>
    <a class="btn btn-orange btn-lg" href="index.php?page=rfq">ثبت استعلام قیمت پروژه</a>
  </section>
<?php endif; ?>
