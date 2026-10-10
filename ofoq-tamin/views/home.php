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

// کالای غیرفعال فقط برای مدیران دیده می‌شود (برای خریدار، لینکِ بن‌بست ساخته نمی‌شود)
if (!is_admin()) {
    $where[] = 'is_active = 1';
}
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
$filtersActive = $q || $brand || $priceMax || $onlyAvailable || $sort !== 'newest';
?>

<div class="storefront-page">
<?php if (!$cat && !$q && !$brand): ?>
  <section class="hero hero--catalog" aria-labelledby="catalog-hero-title">
    <div class="hero-grid">
      <div class="hero-copy">
        <span class="hero-badge"><span aria-hidden="true">▤</span> هر سفارش با صورتحساب الکترونیکی رسمی</span>
        <h1 id="catalog-hero-title">تجهیزات مهندسی و دفتر فنی پروژه، با فاکتور رسمی</h1>
        <p>
          متر و تراز لیزری، رول پلاتر، تجهیزات HSE و لوازم اداری؛ هر کالا با شناسه مالیاتی،
          و هر سفارش با صورتحساب الکترونیکی در کارپوشه شما. ارسال به سراسر کشور.
        </p>
        <div class="hero-actions">
          <a class="btn btn-primary" href="#catalog">مشاهده کالاها <span aria-hidden="true">←</span></a>
          <a class="btn btn-outline" href="index.php?page=rfq">استعلام قیمت پروژه</a>
        </div>
      </div>
      <div class="hero-cards" aria-label="اطلاعات فروشگاه">
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
          <strong>۲۴ ساعت</strong>
          <span>پاسخ‌دهی کارشناسان به استعلام قیمت</span>
        </div>
      </div>
    </div>
  </section>

<?php endif; ?>

<div class="catalog-head" id="catalog">
  <div class="catalog-title">
    <div>
      <?php if ($cat): ?>
        <a class="catalog-back" href="index.php?page=home">→ همه تجهیزات</a>
      <?php endif; ?>
      <h2 class="sec-title"><?= $activeCat ? e($activeCat) : 'تجهیزات و ادوات مهندسی' ?></h2>
      <p class="sec-sub" aria-live="polite">
        <?= fa_num($total) ?> کالا<?= $q ? ' برای «' . e($q) . '»' : '' ?><?= $onlyAvailable ? ' · فقط کالاهای موجود' : '' ?>
      </p>
    </div>
    <?php if ($filtersActive): ?>
      <a class="btn btn-secondary btn-sm" href="index.php?page=home<?= $cat ? '&cat=' . e($cat) : '' ?>">✕ پاک‌کردن فیلترها</a>
    <?php endif; ?>
  </div>

  <form class="filter-bar catalog-filters" method="GET" action="index.php" role="search">
    <input type="hidden" name="page" value="home">
    <?php if ($cat): ?><input type="hidden" name="cat" value="<?= e($cat) ?>"><?php endif; ?>
    <div class="catalog-search">
      <input type="search" name="q" value="<?= e($q) ?>" placeholder="نام کالا، برند یا شناسه…" class="filter-search" aria-label="جست‌وجو در کاتالوگ">
      <button class="btn btn-primary btn-sm" type="submit">جست‌وجو</button>
    </div>
    <div class="catalog-options">
      <select name="brand" aria-label="برند">
        <option value="">همه برندها</option>
        <?php foreach ($brands as $b): ?>
          <option value="<?= e($b['brand']) ?>" <?= $brand === $b['brand'] ? 'selected' : '' ?>><?= e($b['brand']) ?> (<?= fa_num($b['c']) ?>)</option>
        <?php endforeach; ?>
      </select>
      <select name="price_max" aria-label="محدوده قیمت">
        <option value="">هر قیمتی</option>
        <?php foreach ([2000000, 5000000, 10000000, 20000000] as $cap): ?>
          <option value="<?= $cap ?>" <?= $priceMax === $cap ? 'selected' : '' ?>>تا <?= money_short($cap) ?> تومان</option>
        <?php endforeach; ?>
      </select>
      <select name="sort" aria-label="مرتب‌سازی">
        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>جدیدترین</option>
        <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>پرفروش‌ترین</option>
        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>ارزان‌ترین</option>
        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>گران‌ترین</option>
      </select>
      <label class="check-inline">
        <input type="checkbox" name="available" value="1" <?= $onlyAvailable ? 'checked' : '' ?>> فقط موجود
      </label>
      <noscript><button class="btn btn-secondary btn-sm" type="submit">اعمال</button></noscript>
    </div>
  </form>
</div>

<?php if (!$products): ?>
  <div class="empty-state">
    <span aria-hidden="true">🔍</span>
    <h3>کالایی با این مشخصات پیدا نشد</h3>
    <p>عبارت جست‌وجو یا فیلترها را تغییر دهید، یا نیاز پروژه‌تان را به‌صورت استعلام قیمت ثبت کنید تا کارشناسان ما تأمین کنند.</p>
    <div class="flex-center gap-10 wrap">
      <?php if ($filtersActive): ?>
        <a class="btn btn-secondary" href="index.php?page=home<?= $cat ? '&cat=' . e($cat) : '' ?>">پاک‌کردن فیلترها</a>
      <?php endif; ?>
      <a class="btn btn-orange" href="index.php?page=rfq">ثبت استعلام قیمت</a>
    </div>
  </div>
<?php else: ?>
  <div class="pro-grid">
    <?php foreach ($products as $p) {
        require APP_ROOT . '/views/partials/product_card.php';
    } ?>
  </div>

  <?php if ($pg['pages'] > 1): ?>
    <nav class="pagination" aria-label="صفحات کاتالوگ">
      <?php if ($pg['current'] > 1): ?>
        <a class="page-item page-nav" rel="prev" href="<?= e(page_link('home', ['p' => $pg['current'] - 1])) ?>">قبلی</a>
      <?php endif; ?>
      <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
        <a class="page-item <?= $i === $pg['current'] ? 'active' : '' ?>" href="<?= e(page_link('home', ['p' => $i])) ?>"<?= $i === $pg['current'] ? ' aria-current="page"' : '' ?>><?= fa_num($i) ?></a>
      <?php endfor; ?>
      <?php if ($pg['current'] < $pg['pages']): ?>
        <a class="page-item page-nav" rel="next" href="<?= e(page_link('home', ['p' => $pg['current'] + 1])) ?>">بعدی</a>
      <?php endif; ?>
    </nav>
  <?php endif; ?>
  <p class="pg-summary">نمایش <?= fa_num($pg['from']) ?> تا <?= fa_num($pg['to']) ?> از <?= fa_num($pg['total']) ?> کالا</p>
<?php endif; ?>

<?php if (!$cat && !$q): ?>
  <section class="sec-block">
    <div class="sec-head">
      <h2 class="sec-title">پرفروش‌ترین کالاها</h2>
      <a class="link-more" href="<?= e(page_link('home', ['sort' => 'popular', 'p' => 1])) ?>">مشاهده همه ←</a>
    </div>
    <div class="mini-grid">
      <?php foreach ($featured as $f): ?>
        <a class="mini-card" href="<?= product_url($f['id']) ?>">
          <span class="mini-ico" aria-hidden="true"><?= e($f['icon']) ?></span>
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
          <span aria-hidden="true"><?= e($c['icon']) ?></span>
          <strong><?= e($c['title']) ?></strong>
          <small><?= fa_num($cnt->fetchColumn()) ?> کالا</small>
        </a>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="cta-band">
    <div>
      <h3>برای پروژه‌تان پیش‌فاکتور رسمی لازم دارید؟</h3>
      <p>فهرست اقلام و مقادیر را ثبت کنید؛ کارشناسان تدارکات با اعمال تخفیف سازمانی، پیش‌فاکتور رسمی با شناسه مالیاتی صادر می‌کنند.</p>
    </div>
    <a class="btn btn-orange btn-lg" href="index.php?page=rfq">ثبت استعلام قیمت پروژه</a>
  </section>
<?php endif; ?>
</div><!-- storefront-page -->
