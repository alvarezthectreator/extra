<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product-repository.php';

function admin_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function admin_redirect(string $status, string $message = '', string $edit = ''): void
{
    $query = ['status' => $status];
    if ($message !== '') {
        $query['message'] = $message;
    }
    if ($edit !== '') {
        $query['edit'] = $edit;
    }

    header('Location: admin.php?' . http_build_query($query));
    exit;
}

function admin_normalize_upload_array(array $file): array
{
    if (!isset($file['name']) || !is_array($file['name'])) {
        return [];
    }

    $normalized = [];
    foreach ($file['name'] as $index => $name) {
        $normalized[] = [
            'name' => $name,
            'type' => $file['type'][$index] ?? '',
            'tmp_name' => $file['tmp_name'][$index] ?? '',
            'error' => $file['error'][$index] ?? UPLOAD_ERR_NO_FILE,
            'size' => $file['size'][$index] ?? 0,
        ];
    }

    return $normalized;
}

function admin_store_uploaded_image(array $file, string $prefix = 'product'): string
{
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($error === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if ($error !== UPLOAD_ERR_OK) {
        throw new RuntimeException('One of the uploaded images could not be processed.');
    }

    $maxSize = 8 * 1024 * 1024;
    if ((int) ($file['size'] ?? 0) > $maxSize) {
        throw new RuntimeException('Uploaded image is too large. Please use an image smaller than 8 MB.');
    }

    $tmp = (string) ($file['tmp_name'] ?? '');
    $name = (string) ($file['name'] ?? 'image');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'];

    if (!in_array($ext, $allowed, true)) {
        throw new RuntimeException('Please upload a JPG, PNG, WebP, GIF, or AVIF image.');
    }

    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('The uploaded image could not be verified.');
    }

    $uploadDir = __DIR__ . '/uploads/products';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Could not create the product upload folder.');
    }

    $safePrefix = preg_replace('/[^a-zA-Z0-9_-]+/', '-', $prefix) ?: 'product';
    $storedName = sprintf('%s-%s.%s', $safePrefix, bin2hex(random_bytes(6)), $ext);
    $storedPath = $uploadDir . '/' . $storedName;

    if (!move_uploaded_file($tmp, $storedPath)) {
        throw new RuntimeException('Could not save the uploaded image.');
    }

    return 'uploads/products/' . $storedName;
}

function admin_input_value($value): string
{
    return admin_escape((string) $value);
}

function admin_storefront_label(string $storefront): string
{
    $storefront = extra_store_normalize_storefront($storefront);
    return $storefront === 'light' ? 'Light Store' : ($storefront === 'iron' ? 'Iron Store' : 'Extra Store');
}

try {
    $pdo = extra_store_pdo();
    extra_store_bootstrap_catalog($pdo);
    $products = extra_store_fetch_products($pdo);
} catch (Throwable $error) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Admin error</title></head><body style="font-family:Arial,sans-serif;padding:24px;background:#f5f7fb;color:#111827;">';
    echo '<h1>Unable to load product data</h1>';
    echo '<p>The database connection failed. Check MAMP, the database name, and the MySQL port.</p>';
    echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">' . admin_escape($error->getMessage()) . '</pre>';
    echo '</body></html>';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = trim((string) ($_POST['action_type'] ?? 'update'));
    $productId = trim((string) ($_POST['id'] ?? ''));
    $existingProduct = $productId !== '' ? extra_store_fetch_product($pdo, $productId) : null;

    try {
        if ($action === 'delete') {
            extra_store_delete_product($pdo, $productId);
            admin_redirect('deleted', $productId);
        }

        $uploadedPrimaryImage = '';
        $uploadedGalleryImages = [];
        $uploadPrefix = $productId !== '' ? $productId : trim((string) ($_POST['name'] ?? 'product'));

        if (isset($_FILES['image_primary_upload']) && is_array($_FILES['image_primary_upload'])) {
            $uploadedPrimaryImage = admin_store_uploaded_image($_FILES['image_primary_upload'], $uploadPrefix);
        }

        if (isset($_FILES['gallery_image_uploads']) && is_array($_FILES['gallery_image_uploads'])) {
            foreach (admin_normalize_upload_array($_FILES['gallery_image_uploads']) as $upload) {
                $stored = admin_store_uploaded_image($upload, $uploadPrefix);
                if ($stored !== '') {
                    $uploadedGalleryImages[] = $stored;
                }
            }
        }

        $imagePrimary = trim((string) ($_POST['image_primary'] ?? ''));
        if ($uploadedPrimaryImage !== '') {
            $imagePrimary = $uploadedPrimaryImage;
        }

        $galleryText = trim((string) ($_POST['images_text'] ?? ''));
        $galleryImages = $galleryText !== ''
            ? (preg_split('/[\r\n,]+/', $galleryText) ?: [])
            : (array) ($existingProduct['images'] ?? []);
        foreach ($uploadedGalleryImages as $galleryImage) {
            $galleryImages[] = $galleryImage;
        }
        $galleryImages = array_values(array_filter(array_map('trim', $galleryImages)));
        $imagesText = implode("\n", $galleryImages);

        if ($action === 'create') {
            $created = extra_store_create_product($pdo, [
                'id' => $_POST['id'] ?? '',
                'name' => $_POST['name'] ?? '',
                'price' => $_POST['price'] ?? 0,
                'color' => $_POST['color'] ?? '',
                'category' => $_POST['category'] ?? '',
                'storefront' => $_POST['storefront'] ?? 'extra',
                'image_primary' => $imagePrimary,
                'images_text' => $imagesText,
                'description' => $_POST['description'] ?? '',
            ]);

            admin_redirect('created', (string) ($created['id'] ?? ''), (string) ($created['id'] ?? ''));
        }

        $updated = extra_store_update_product($pdo, [
            'id' => $productId,
            'name' => $_POST['name'] ?? '',
            'price' => $_POST['price'] ?? 0,
            'color' => $_POST['color'] ?? '',
            'category' => $_POST['category'] ?? '',
            'storefront' => $_POST['storefront'] ?? ($existingProduct['storefront'] ?? 'extra'),
            'image_primary' => $imagePrimary !== '' ? $imagePrimary : (string) ($existingProduct['image_primary'] ?? ''),
            'images_text' => $imagesText,
            'description' => $_POST['description'] ?? '',
        ]);

        admin_redirect('updated', (string) ($updated['id'] ?? $productId), (string) ($updated['id'] ?? $productId));
    } catch (Throwable $error) {
        admin_redirect('error', $error->getMessage(), $productId);
    }
}

$status = trim((string) ($_GET['status'] ?? ''));
$message = trim((string) ($_GET['message'] ?? ''));
$editId = trim((string) ($_GET['edit'] ?? ''));
$selectedProduct = null;

foreach ($products as $product) {
    if ($editId !== '' && ($product['id'] ?? '') === $editId) {
        $selectedProduct = $product;
        break;
    }
}

if (!$selectedProduct && $products) {
    $selectedProduct = $products[0];
    $editId = (string) ($selectedProduct['id'] ?? '');
}

$allCategories = array_values(array_unique(array_filter(array_map(static fn ($product) => (string) ($product['category'] ?? ''), $products))));
$allColors = array_values(array_unique(array_filter(array_map(static fn ($product) => (string) ($product['color'] ?? ''), $products))));
$totalValue = array_reduce($products, static function (int $sum, array $product): int {
    return $sum + (int) ($product['price'] ?? 0);
}, 0);
$latestUpdate = '';
foreach ($products as $product) {
    $updatedAt = (string) ($product['updated_at'] ?? '');
    if ($updatedAt !== '' && $updatedAt > $latestUpdate) {
        $latestUpdate = $updatedAt;
    }
}

$recentOrders = [];
$orderCount = 0;
$pendingOrderCount = 0;

try {
    $recentOrdersStmt = $pdo->query(
        'SELECT order_number, product_name, customer_name, total_price, state, status, created_at
         FROM orders
         ORDER BY created_at DESC, id DESC'
    );
    $recentOrders = $recentOrdersStmt ? $recentOrdersStmt->fetchAll() : [];
    $orderCount = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
    $pendingOrderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetchColumn();
} catch (Throwable $error) {
    $recentOrders = [];
}

$recentOrders = array_slice($recentOrders, 0, 8);

$editorValues = $selectedProduct ?? [
    'id' => '',
    'name' => '',
    'price' => '',
    'color' => '',
    'category' => '',
    'image_primary' => '',
    'images' => [],
    'description' => '',
    'storefront' => 'extra',
    'updated_at' => '',
];

$editorGallery = implode("\n", (array) ($editorValues['images'] ?? []));
$editorPrimary = (string) ($editorValues['image_primary'] ?? '');
if ($editorPrimary === '' && isset($editorValues['images'][0])) {
    $editorPrimary = (string) $editorValues['images'][0];
}
$editorStorefront = extra_store_normalize_storefront((string) ($editorValues['storefront'] ?? 'extra'));
$extraStorefrontCount = count(array_filter($products, static fn ($product) => extra_store_normalize_storefront((string) ($product['storefront'] ?? '')) === 'extra'));
$lightStorefrontCount = count(array_filter($products, static fn ($product) => extra_store_normalize_storefront((string) ($product['storefront'] ?? '')) === 'light'));
$ironStorefrontCount = count(array_filter($products, static fn ($product) => extra_store_normalize_storefront((string) ($product['storefront'] ?? '')) === 'iron'));
$productAveragePrice = $products ? (int) round($totalValue / max(1, count($products))) : 0;

$categoryCounts = [];
foreach ($products as $product) {
    $category = trim((string) ($product['category'] ?? ''));
    if ($category === '') {
        $category = 'Uncategorized';
    }
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}
arsort($categoryCounts);
$topCategories = array_slice($categoryCounts, 0, 4, true);

$storefrontCounts = [
    'extra' => $extraStorefrontCount,
    'light' => $lightStorefrontCount,
    'iron' => $ironStorefrontCount,
];
$maxStorefrontCount = max(1, max($storefrontCounts));
$maxCategoryCount = max(1, max($categoryCounts ?: [1]));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Extra Store Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/admin.css">
</head>
<body class="admin-page">
  <div class="admin-shell">
    <button class="admin-backdrop" type="button" data-admin-backdrop hidden aria-label="Close sidebar"></button>

    <aside class="admin-sidebar" data-admin-sidebar aria-hidden="true">
      <div class="sidebar-brand">
        <div class="brand-lockup">
          <div class="brand-mark">E</div>
          <div>
            <div class="brand-title">Extra Admin</div>
            <div class="brand-subtitle">Catalog and orders</div>
          </div>
        </div>
        <button class="sidebar-toggle" type="button" data-admin-sidebar-close aria-label="Close menu">×</button>
      </div>

      <div class="sidebar-stats">
        <div class="sidebar-stat">
          <strong><?php echo (int) count($products); ?></strong>
          <span>Products</span>
        </div>
        <div class="sidebar-stat">
          <strong><?php echo (int) $orderCount; ?></strong>
          <span>Orders</span>
        </div>
        <div class="sidebar-stat">
          <strong><?php echo (int) $pendingOrderCount; ?></strong>
          <span>Pending</span>
        </div>
      </div>

      <div class="sidebar-group">
        <p class="sidebar-group-title">Dashboard</p>
        <nav class="sidebar-nav">
          <a href="#overview" data-admin-nav-link class="active">Overview</a>
          <a href="#products" data-admin-nav-link>Products</a>
          <a href="#orders" data-admin-nav-link>Orders</a>
          <a href="#editor" data-admin-nav-link>Editor</a>
        </nav>
      </div>

      <div class="sidebar-group">
        <p class="sidebar-group-title">Storefronts</p>
        <div class="sidebar-links">
          <a href="index.html" target="_blank" rel="noreferrer">Extra Store <span>↗</span></a>
          <a href="light-index.html" target="_blank" rel="noreferrer">Light Store <span>↗</span></a>
          <a href="iron-index.html" target="_blank" rel="noreferrer">Iron Store <span>↗</span></a>
        </div>
      </div>

      <div class="sidebar-group">
        <p class="sidebar-group-title">Quick Actions</p>
        <div class="sidebar-links">
          <a href="#editor" data-new-product data-storefront="<?php echo admin_escape($editorStorefront); ?>">New product <span>+</span></a>
          <a href="orders.php">Open orders page <span>↗</span></a>
        </div>
      </div>

      <div class="sidebar-note">
        Product updates sync to the storefronts and checkout flow. Orders stay in the admin workspace so you can manage everything in one place.
      </div>
    </aside>

    <main class="admin-main">
      <header class="admin-topbar">
        <button class="sidebar-toggle" type="button" data-admin-sidebar-toggle aria-expanded="false" aria-label="Open menu">☰</button>
        <div class="topbar-search">
          <span aria-hidden="true">⌕</span>
          <input type="search" placeholder="Search products, categories, colors" data-admin-search>
        </div>
        <div class="topbar-actions">
          <div class="topbar-icon" aria-hidden="true">◔</div>
          <div class="topbar-icon" aria-hidden="true">⌁</div>
          <div class="profile-chip">
            <div class="profile-avatar" aria-hidden="true">EX</div>
            <div class="profile-name">Store Admin</div>
            <div class="profile-caret" aria-hidden="true">⌄</div>
          </div>
        </div>
      </header>

      <?php if ($status === 'created'): ?>
        <div class="flash success">Created product <strong><?php echo admin_escape($message !== '' ? $message : ''); ?></strong>.</div>
      <?php elseif ($status === 'updated'): ?>
        <div class="flash success">Saved changes for <strong><?php echo admin_escape($message !== '' ? $message : ''); ?></strong>.</div>
      <?php elseif ($status === 'deleted'): ?>
        <div class="flash success">Deleted product <strong><?php echo admin_escape($message !== '' ? $message : ''); ?></strong>.</div>
      <?php elseif ($status === 'error'): ?>
        <div class="flash error"><?php echo admin_escape($message !== '' ? $message : 'Something went wrong.'); ?></div>
      <?php endif; ?>

      <section class="admin-banner" id="overview">
        <div class="banner-copy">
          <h1>Products Dashboard</h1>
          <p>Manage product records, storefront placement, gallery images, and incoming orders from one polished workspace. The layout is built to feel closer to a modern SaaS admin while keeping the same functional backend.</p>
        </div>
        <div class="banner-actions">
          <button type="button" class="btn btn-primary" data-new-product data-storefront="<?php echo admin_escape($editorStorefront); ?>">+ Add Product</button>
          <a class="btn btn-ghost" href="#orders">View Orders</a>
        </div>
      </section>

      <section class="metrics-grid" aria-label="Dashboard summary">
        <article class="metric-card">
          <div class="metric-top">
            <span class="metric-label">Catalog size</span>
            <span class="metric-chip primary">Live</span>
          </div>
          <div class="metric-value"><?php echo (int) count($products); ?></div>
          <div class="metric-note">Products currently in the database</div>
        </article>
        <article class="metric-card">
          <div class="metric-top">
            <span class="metric-label">Orders</span>
            <span class="metric-chip success">Incoming</span>
          </div>
          <div class="metric-value"><?php echo (int) $orderCount; ?></div>
          <div class="metric-note"><?php echo (int) $pendingOrderCount; ?> pending for review</div>
        </article>
        <article class="metric-card">
          <div class="metric-top">
            <span class="metric-label">Total value</span>
            <span class="metric-chip warning">Inventory</span>
          </div>
          <div class="metric-value"><?php echo admin_escape('₦' . number_format($totalValue)); ?></div>
          <div class="metric-note">Combined price of every product</div>
        </article>
        <article class="metric-card">
          <div class="metric-top">
            <span class="metric-label">Average price</span>
            <span class="metric-chip danger">Catalog</span>
          </div>
          <div class="metric-value"><?php echo admin_escape('₦' . number_format($productAveragePrice)); ?></div>
          <div class="metric-note">Based on the current product mix</div>
        </article>
      </section>

      <section class="insight-grid" aria-label="Catalog insights">
        <article class="panel chart-card">
          <div class="chart-card-head">
            <div>
              <h2 class="panel-title">Storefront mix</h2>
              <div class="panel-subtitle">How products are split across the three storefronts</div>
            </div>
            <div class="chart-legend">
              <span class="legend-item"><span class="legend-dot primary"></span> Extra</span>
              <span class="legend-item"><span class="legend-dot accent"></span> Light</span>
              <span class="legend-item"><span class="legend-dot success"></span> Iron</span>
            </div>
          </div>
          <div class="chart-stack">
            <?php foreach ($storefrontCounts as $storefrontKey => $count): ?>
              <?php
                $label = admin_storefront_label($storefrontKey);
                $width = (int) round(($count / $maxStorefrontCount) * 100);
                $chipClass = $storefrontKey === 'extra' ? 'primary' : ($storefrontKey === 'light' ? 'warning' : 'success');
              ?>
              <div class="chart-row">
                <div class="chart-row-label">
                  <span><?php echo admin_escape($label); ?></span>
                  <strong><?php echo (int) $count; ?></strong>
                </div>
                <div class="chart-track">
                  <span class="chart-fill <?php echo admin_escape($chipClass); ?>" style="width: <?php echo (int) $width; ?>%;"></span>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </article>

        <article class="panel chart-card">
          <div class="chart-card-head">
            <div>
              <h2 class="panel-title">Category reach</h2>
              <div class="panel-subtitle">Your most active product categories right now</div>
            </div>
            <span class="metric-chip primary"><?php echo count($categoryCounts); ?> categories</span>
          </div>
          <div class="chart-stack">
            <?php if ($topCategories): ?>
              <?php foreach ($topCategories as $category => $count): ?>
                <?php $width = (int) round(($count / $maxCategoryCount) * 100); ?>
                <div class="chart-row">
                  <div class="chart-row-label">
                    <span><?php echo admin_escape($category); ?></span>
                    <strong><?php echo (int) $count; ?></strong>
                  </div>
                  <div class="chart-track">
                    <span class="chart-fill" style="width: <?php echo (int) $width; ?>%;"></span>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div class="help-text">No categories found yet.</div>
            <?php endif; ?>
          </div>
        </article>
      </section>

      <div class="content-grid">
        <section class="content-column">
          <section class="panel" id="products">
            <div class="panel-header">
              <div>
                <h2 class="panel-title">Products</h2>
                <div class="panel-subtitle">Filter, sort, edit, and remove products from one table</div>
              </div>
              <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot primary"></span> <?php echo (int) count($products); ?> total</span>
                <span class="legend-item"><span class="legend-dot success"></span> <span data-product-visible-count><?php echo (int) count($products); ?></span> visible</span>
              </div>
            </div>

            <div class="catalog-toolbar">
              <div class="catalog-toolbar-left">
                <button type="button" class="catalog-toggle" aria-label="Table view">☰</button>
                <button type="button" class="catalog-toggle" aria-label="Grid view">▦</button>
              </div>
              <div class="catalog-toolbar-right">
                <input type="search" class="catalog-input" placeholder="Search products..." data-admin-search>
                <select class="catalog-select" data-admin-filter="sort">
                  <option value="featured">Sort: Featured</option>
                  <option value="recent">Sort: Recent</option>
                  <option value="price-low">Price: Low to High</option>
                  <option value="price-high">Price: High to Low</option>
                  <option value="name">Name: A to Z</option>
                </select>
                <button type="button" class="btn btn-ghost" data-admin-clear-filters>Clear filters</button>
                <button type="button" class="btn btn-primary" data-new-product data-storefront="<?php echo admin_escape($editorStorefront); ?>">+ Add Product</button>
              </div>
            </div>

            <div class="catalog-tools" id="filters">
              <div class="filter-field">
                <label for="catalogCategoryFilter">Category</label>
                <select id="catalogCategoryFilter" data-admin-filter="category">
                  <option value="all">All categories</option>
                  <?php foreach ($allCategories as $category): ?>
                    <option value="<?php echo admin_escape($category); ?>"><?php echo admin_escape($category); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="filter-field">
                <label for="catalogStoreFilter">Store</label>
                <select id="catalogStoreFilter" data-admin-filter="store">
                  <option value="all">All stores</option>
                  <option value="extra">Extra</option>
                  <option value="light">Light</option>
                  <option value="iron">Iron</option>
                </select>
              </div>
              <div class="filter-field">
                <label for="catalogColorFilter">Color</label>
                <select id="catalogColorFilter" data-admin-filter="color">
                  <option value="all">All colors</option>
                  <?php foreach ($allColors as $color): ?>
                    <option value="<?php echo admin_escape($color); ?>"><?php echo admin_escape($color); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="filter-field">
                <label for="catalogPriceFilter">Price band</label>
                <select id="catalogPriceFilter" data-admin-filter="price">
                  <option value="all">All prices</option>
                  <option value="under-20000">Under ₦20k</option>
                  <option value="20000-24999">₦20k - ₦24,999</option>
                  <option value="25000-plus">₦25k and above</option>
                </select>
              </div>
            </div>

            <div class="table-shell">
              <table class="product-table">
                <thead>
                  <tr>
                    <th>Product</th>
                    <th>Purchase Unit Price</th>
                    <th>Gallery</th>
                    <th>Store</th>
                    <th>Status</th>
                    <th>Updated</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody data-product-table-body>
                  <?php foreach ($products as $index => $product): ?>
                    <?php
                      $productId = (string) ($product['id'] ?? '');
                      $productName = (string) ($product['name'] ?? '');
                      $productColor = (string) ($product['color'] ?? '');
                      $productCategory = (string) ($product['category'] ?? '');
                      $productDescription = (string) ($product['description'] ?? '');
                      $productPrice = (int) ($product['price'] ?? 0);
                      $productImage = (string) ($product['image_primary'] ?? '');
                      $productGallery = implode("\n", (array) ($product['images'] ?? []));
                      $productStorefront = extra_store_normalize_storefront((string) ($product['storefront'] ?? ''));
                      $productStorefrontLabel = admin_storefront_label($productStorefront);
                      $productUpdated = (string) ($product['updated_at'] ?? '');
                      $galleryCount = is_array($product['images'] ?? null) ? count((array) $product['images']) : 0;
                    ?>
                    <tr
                      data-product-row
                      data-product-sort="<?php echo (int) ($index + 1); ?>"
                      data-product-id="<?php echo admin_escape($productId); ?>"
                      data-product-name="<?php echo admin_escape($productName); ?>"
                      data-product-color="<?php echo admin_escape($productColor); ?>"
                      data-product-category="<?php echo admin_escape($productCategory); ?>"
                      data-product-price="<?php echo (int) $productPrice; ?>"
                      data-product-store="<?php echo admin_escape($productStorefront); ?>"
                      data-product-gallery="<?php echo admin_escape($productGallery); ?>"
                      data-product-description="<?php echo admin_escape($productDescription); ?>"
                      data-product-image="<?php echo admin_escape($productImage); ?>"
                      data-product-updated="<?php echo admin_escape($productUpdated); ?>"
                    >
                      <td>
                        <div class="product-cell">
                          <img class="product-thumb" src="<?php echo admin_escape($productImage !== '' ? $productImage : 'assets/red-product-clean.png'); ?>" alt="<?php echo admin_escape($productName); ?>">
                          <div>
                            <p class="product-name"><?php echo admin_escape($productName); ?></p>
                            <div class="product-meta">SKU: <?php echo admin_escape($productId !== '' ? $productId : 'auto'); ?> · <?php echo admin_escape($productCategory !== '' ? $productCategory : 'Uncategorized'); ?></div>
                          </div>
                        </div>
                      </td>
                      <td><?php echo admin_escape('₦' . number_format($productPrice)); ?></td>
                      <td><span class="pill neutral"><?php echo (int) $galleryCount; ?> image<?php echo $galleryCount === 1 ? '' : 's'; ?></span></td>
                      <td><span class="pill primary"><?php echo admin_escape($productStorefrontLabel); ?></span></td>
                      <td><span class="pill success">Active</span></td>
                      <td><?php echo admin_escape($productUpdated !== '' ? $productUpdated : 'N/A'); ?></td>
                      <td>
                        <div class="action-group">
                          <button
                            type="button"
                            class="action-button primary"
                            data-edit-product
                            data-product-id="<?php echo admin_escape($productId); ?>"
                            data-product-name="<?php echo admin_escape($productName); ?>"
                            data-product-price="<?php echo (int) $productPrice; ?>"
                            data-product-color="<?php echo admin_escape($productColor); ?>"
                            data-product-category="<?php echo admin_escape($productCategory); ?>"
                            data-product-image="<?php echo admin_escape($productImage); ?>"
                            data-product-gallery="<?php echo admin_escape($productGallery); ?>"
                            data-product-description="<?php echo admin_escape($productDescription); ?>"
                            data-product-store="<?php echo admin_escape($productStorefront); ?>"
                          >Edit</button>
                          <form method="post" data-delete-form data-product-name="<?php echo admin_escape($productName); ?>">
                            <input type="hidden" name="action_type" value="delete">
                            <input type="hidden" name="id" value="<?php echo admin_escape($productId); ?>">
                            <button type="submit" class="action-button" aria-label="Delete product <?php echo admin_escape($productName); ?>">🗑</button>
                          </form>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>

            <div class="empty-state" data-product-empty hidden>No products match the current filters.</div>

            <div class="catalog-footer">
              <div class="catalog-footer-count">Showing <span data-product-visible-count><?php echo (int) count($products); ?></span> of <?php echo (int) count($products); ?> products</div>
              <div class="chart-legend">
                <span class="legend-item"><span class="legend-dot primary"></span> Last update: <?php echo admin_escape($latestUpdate !== '' ? $latestUpdate : 'N/A'); ?></span>
              </div>
            </div>
          </section>
        </section>

        <aside class="side-column stack">
          <section class="panel" id="orders">
            <div class="panel-header">
              <div>
                <h2 class="panel-title">Recent Orders</h2>
                <div class="panel-subtitle">Latest purchases stored in the admin database</div>
              </div>
              <span class="metric-chip primary"><?php echo (int) count($recentOrders); ?> shown</span>
            </div>
            <div class="table-shell">
              <?php if ($recentOrders): ?>
                <table class="orders-table">
                  <thead>
                    <tr>
                      <th>Order</th>
                      <th>Customer</th>
                      <th>Total</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($recentOrders as $order): ?>
                      <?php
                        $orderNumber = (string) ($order['order_number'] ?? '');
                        $productName = (string) ($order['product_name'] ?? '');
                        $customerName = (string) ($order['customer_name'] ?? '');
                        $state = (string) ($order['state'] ?? '');
                        $status = strtolower((string) ($order['status'] ?? 'pending'));
                        $totalPrice = (int) ($order['total_price'] ?? 0);
                        $createdAt = (string) ($order['created_at'] ?? '');
                        $statusClass = 'neutral';
                        if ($status === 'pending') {
                            $statusClass = 'warning';
                        } elseif ($status === 'processing') {
                            $statusClass = 'primary';
                        } elseif ($status === 'paid') {
                            $statusClass = 'success';
                        } elseif ($status === 'cancelled') {
                            $statusClass = 'danger';
                        }
                      ?>
                      <tr>
                        <td>
                          <div class="orders-cell-title">#<?php echo admin_escape($orderNumber !== '' ? $orderNumber : '—'); ?></div>
                          <div class="orders-cell-subtitle"><?php echo admin_escape($productName !== '' ? $productName : 'Unknown product'); ?></div>
                        </td>
                        <td>
                          <div class="orders-cell-title"><?php echo admin_escape($customerName !== '' ? $customerName : 'No customer'); ?></div>
                          <div class="orders-cell-subtitle"><?php echo admin_escape($state !== '' ? $state : 'No state'); ?></div>
                        </td>
                        <td><?php echo admin_escape('₦' . number_format($totalPrice)); ?></td>
                        <td>
                          <div class="stack" style="gap: 6px;">
                            <span class="pill <?php echo admin_escape($statusClass); ?>"><?php echo admin_escape($status !== '' ? $status : 'pending'); ?></span>
                            <span class="orders-cell-subtitle"><?php echo admin_escape($createdAt !== '' ? $createdAt : 'No date'); ?></span>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?>
                <div class="empty-state" style="border-top: 0;">No purchases have been placed yet.</div>
              <?php endif; ?>
            </div>
          </section>

          <section class="panel" id="editor">
            <div class="panel-header">
              <div>
                <h2 class="panel-title">Product editor</h2>
                <div class="panel-subtitle" data-editor-mode><?php echo $selectedProduct ? 'Editing ' . admin_escape((string) ($selectedProduct['id'] ?? 'product')) : 'Creating a new product'; ?></div>
              </div>
              <button type="button" class="btn btn-soft" data-new-product data-storefront="<?php echo admin_escape($editorStorefront); ?>">New Product</button>
            </div>

            <img
              src="<?php echo admin_escape($editorPrimary !== '' ? $editorPrimary : 'assets/red-product-clean.png'); ?>"
              alt="Product preview"
              class="editor-preview"
              data-product-preview
            >

            <form method="post" enctype="multipart/form-data" class="editor-form" data-product-form>
              <input type="hidden" name="action_type" value="<?php echo $selectedProduct ? 'update' : 'create'; ?>" data-product-action-type>
              <div class="editor-grid">
                <div class="field">
                  <label for="productId">Product ID</label>
                  <input id="productId" type="text" name="id" value="<?php echo admin_input_value($editorValues['id'] ?? ''); ?>" placeholder="Leave blank for auto ID" data-product-id>
                </div>
                <div class="field">
                  <label for="productName">Product name</label>
                  <input id="productName" type="text" name="name" value="<?php echo admin_input_value($editorValues['name'] ?? ''); ?>" required data-product-name-input>
                </div>
                <div class="field">
                  <label for="productPrice">Price</label>
                  <input id="productPrice" type="number" name="price" min="0" step="1" value="<?php echo admin_input_value($editorValues['price'] ?? ''); ?>" required data-product-price-input>
                </div>
                <div class="field">
                  <label for="productColor">Color</label>
                  <input id="productColor" type="text" name="color" value="<?php echo admin_input_value($editorValues['color'] ?? ''); ?>" required data-product-color-input>
                </div>
                <div class="field">
                  <label for="productCategory">Category</label>
                  <input id="productCategory" type="text" name="category" value="<?php echo admin_input_value($editorValues['category'] ?? ''); ?>" required data-product-category-input>
                </div>
                <div class="field">
                  <label for="productStorefront">Storefront</label>
                  <select id="productStorefront" name="storefront" required data-product-storefront-input>
                    <option value="extra"<?php echo $editorStorefront === 'extra' ? ' selected' : ''; ?>>Extra</option>
                    <option value="light"<?php echo $editorStorefront === 'light' ? ' selected' : ''; ?>>Light</option>
                    <option value="iron"<?php echo $editorStorefront === 'iron' ? ' selected' : ''; ?>>Iron</option>
                  </select>
                </div>
                <div class="field full">
                  <label for="productImage">Primary image path</label>
                  <input id="productImage" type="text" name="image_primary" value="<?php echo admin_input_value($editorPrimary); ?>" placeholder="Paste an image path" data-product-image-input>
                </div>
                <div class="field full">
                  <label for="productImageUpload">Upload primary image</label>
                  <input id="productImageUpload" type="file" accept="image/*" data-product-image-upload>
                  <div class="help-text">Upload a local file to replace the primary image without changing the stored path manually.</div>
                </div>
                <div class="field full">
                  <label for="productGallery">Gallery images</label>
                  <textarea id="productGallery" name="images_text" placeholder="One image path per line" data-product-gallery-input><?php echo admin_escape($editorGallery); ?></textarea>
                </div>
                <div class="field full">
                  <label for="productGalleryUpload">Upload gallery images</label>
                  <input id="productGalleryUpload" type="file" accept="image/*" multiple data-product-gallery-upload>
                  <div class="help-text">Any uploaded files are appended to the gallery list when the form is saved.</div>
                </div>
                <div class="field full">
                  <label for="productDescription">Description</label>
                  <textarea id="productDescription" name="description" required data-product-description-input><?php echo admin_escape((string) ($editorValues['description'] ?? '')); ?></textarea>
                </div>
              </div>

              <div class="editor-footer">
                <div>
                  <div class="status-note">Save to update the storefront, checkout, and order flows.</div>
                  <div class="help-text">The product ID stays locked when editing an existing item so the record remains stable.</div>
                </div>
                <div class="editor-actions">
                  <button type="button" class="btn btn-ghost" data-new-product data-storefront="<?php echo admin_escape($editorStorefront); ?>">Reset</button>
                  <button type="submit" class="btn btn-primary" data-product-submit>Save Product</button>
                </div>
              </div>
            </form>
          </section>
        </aside>
      </div>
    </main>
  </div>

  <script src="assets/js/admin.js" defer></script>
</body>
</html>
