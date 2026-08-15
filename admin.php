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

try {
    $pdo = extra_store_pdo();
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

$editorValues = $selectedProduct ?? [
    'id' => '',
    'name' => '',
    'price' => '',
    'color' => '',
    'category' => '',
    'image_primary' => '',
    'images' => [],
    'description' => '',
    'updated_at' => '',
];

$editorGallery = implode("\n", (array) ($editorValues['images'] ?? []));
$editorPrimary = (string) ($editorValues['image_primary'] ?? '');
if ($editorPrimary === '' && isset($editorValues['images'][0])) {
    $editorPrimary = (string) $editorValues['images'][0];
}

function admin_input_value($value): string
{
    return admin_escape((string) $value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Extra Store Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #eff3fb;
      --panel: #ffffff;
      --panel-soft: #f7f9fd;
      --line: #dfe7f3;
      --ink: #1f2a37;
      --muted: #6b7280;
      --accent: #6b4eff;
      --accent-soft: #ece7ff;
      --accent-2: #38bdf8;
      --danger: #ef4444;
      --success: #16a34a;
      --shadow: 0 18px 45px rgba(29, 41, 57, 0.08);
    }
    * { box-sizing: border-box; }
    html, body { min-height: 100%; }
    body {
      margin: 0;
      font-family: 'Poppins', sans-serif;
      background:
        radial-gradient(circle at top left, rgba(107, 78, 255, 0.08), transparent 28%),
        linear-gradient(180deg, #f9fbff 0%, #eef3fb 100%);
      color: var(--ink);
    }
    a { color: inherit; }
    button, input, textarea, select { font: inherit; }
    .layout {
      display: grid;
      grid-template-columns: 260px minmax(0, 1fr);
      min-height: 100vh;
    }
    .sidebar {
      position: sticky;
      top: 0;
      align-self: start;
      height: 100vh;
      padding: 22px 18px;
      background: rgba(255, 255, 255, 0.9);
      border-right: 1px solid var(--line);
      backdrop-filter: blur(12px);
      overflow: auto;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      margin-bottom: 24px;
      font-weight: 800;
      letter-spacing: 0.04em;
    }
    .brand-mark {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      background: linear-gradient(135deg, #d9d1ff, #bde6ff);
      display: grid;
      place-items: center;
      color: var(--accent);
      box-shadow: inset 0 1px 0 rgba(255,255,255,0.7);
    }
    .side-group { margin-top: 22px; }
    .side-title {
      color: var(--muted);
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin: 0 0 10px;
    }
    .side-link,
    .side-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 14px;
      color: #334155;
      text-decoration: none;
      margin-bottom: 8px;
    }
    .side-link.active,
    .side-item.active {
      background: var(--accent-soft);
      color: var(--accent);
      font-weight: 700;
    }
    .side-sublist {
      margin: 8px 0 0 18px;
      padding-left: 14px;
      border-left: 1px solid var(--line);
    }
    .sidebar-note {
      margin-top: 26px;
      padding: 16px;
      border-radius: 18px;
      background: linear-gradient(135deg, #f6f0ff, #eff8ff);
      border: 1px solid #e6e8ff;
      color: #4c1d95;
      font-size: 0.9rem;
      line-height: 1.6;
    }
    .content {
      padding: 18px;
    }
    .topbar {
      display: flex;
      align-items: center;
      gap: 16px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid var(--line);
      border-radius: 22px;
      padding: 14px 16px;
      box-shadow: var(--shadow);
      backdrop-filter: blur(12px);
    }
    .hamburger {
      width: 40px;
      height: 40px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: var(--accent-soft);
      color: var(--accent);
      display: grid;
      place-items: center;
      font-weight: 800;
    }
    .searchbar {
      flex: 1;
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 0 14px;
      min-height: 44px;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: #f8faff;
    }
    .searchbar input {
      width: 100%;
      border: 0;
      outline: 0;
      background: transparent;
      color: var(--ink);
    }
    .top-icons {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
    }
    .icon-pill {
      width: 40px;
      height: 40px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #f8faff;
      display: grid;
      place-items: center;
      color: var(--accent);
      font-weight: 700;
    }
    .avatar {
      width: 40px;
      height: 40px;
      border-radius: 999px;
      background: linear-gradient(135deg, #ffd39a, #fff4d8);
      display: grid;
      place-items: center;
      color: #9f580a;
      font-weight: 800;
    }
    .page-shell {
      margin-top: 18px;
      display: grid;
      grid-template-columns: minmax(0, 1fr) 340px;
      gap: 18px;
      align-items: start;
    }
    .main-panel,
    .side-panel,
    .editor-card,
    .stats-card,
    .filters-card,
    .section-head {
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid var(--line);
      border-radius: 24px;
      box-shadow: var(--shadow);
    }
    .main-panel { padding: 20px; }
    .section-head {
      padding: 16px 18px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-bottom: 18px;
    }
    .breadcrumb {
      display: flex;
      align-items: center;
      gap: 8px;
      color: var(--muted);
      font-size: 0.85rem;
    }
    .breadcrumbs strong {
      color: var(--accent);
    }
    .headline h1 {
      margin: 0;
      font-size: clamp(1.7rem, 2vw, 2.2rem);
      line-height: 1.1;
    }
    .headline p {
      margin: 8px 0 0;
      color: var(--muted);
      font-size: 0.95rem;
    }
    .toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 14px;
      margin-bottom: 18px;
    }
    .toolbar-left,
    .toolbar-right {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-wrap: wrap;
    }
    .toolbar-chip {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 14px;
      border-radius: 14px;
      background: #f6f7ff;
      border: 1px solid var(--line);
      color: #4338ca;
      font-weight: 600;
      cursor: pointer;
    }
    .toolbar-select,
    .toolbar-input {
      min-height: 44px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #f8faff;
      padding: 0 14px;
      outline: none;
    }
    .toolbar-input { min-width: 220px; }
    .product-grid {
      display: grid;
      gap: 16px;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    }
    .product-card {
      overflow: hidden;
      border-radius: 22px;
      border: 1px solid var(--line);
      background: #fff;
      box-shadow: 0 16px 38px rgba(31, 42, 55, 0.07);
    }
    .product-card.highlight {
      border-color: rgba(107, 78, 255, 0.35);
      box-shadow: 0 18px 44px rgba(107, 78, 255, 0.12);
    }
    .product-thumb {
      aspect-ratio: 1 / 1;
      width: 100%;
      object-fit: cover;
      display: block;
      background: #eef2ff;
    }
    .product-body {
      padding: 14px 14px 16px;
    }
    .product-name {
      margin: 0 0 8px;
      font-size: 1rem;
      line-height: 1.35;
    }
    .product-desc {
      margin: 0;
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.6;
      min-height: 3.2em;
    }
    .product-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      margin: 12px 0 0;
      color: #475569;
      font-size: 0.85rem;
    }
    .product-actions {
      display: flex;
      gap: 10px;
      margin-top: 14px;
    }
    .btn {
      border: 0;
      border-radius: 14px;
      min-height: 42px;
      padding: 0 14px;
      font-weight: 700;
      cursor: pointer;
      transition: transform 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }
    .btn:hover { transform: translateY(-1px); }
    .btn-primary {
      background: var(--accent);
      color: white;
      box-shadow: 0 10px 24px rgba(107, 78, 255, 0.22);
    }
    .btn-soft {
      background: #eef2ff;
      color: #4338ca;
    }
    .btn-danger {
      background: #fff1f2;
      color: var(--danger);
    }
    .right-stack {
      display: grid;
      gap: 18px;
      position: sticky;
      top: 18px;
    }
    .stats-card,
    .filters-card,
    .editor-card {
      padding: 18px;
    }
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 12px;
      margin-top: 14px;
    }
    .stat {
      padding: 14px;
      border-radius: 18px;
      background: linear-gradient(180deg, #f8faff, #f2f6ff);
      border: 1px solid var(--line);
    }
    .stat strong {
      display: block;
      font-size: 1.15rem;
      margin-bottom: 4px;
    }
    .stat span {
      color: var(--muted);
      font-size: 0.8rem;
    }
    .panel-title {
      margin: 0 0 12px;
      font-size: 1rem;
    }
    .filter-group {
      margin-top: 14px;
      padding-top: 14px;
      border-top: 1px solid var(--line);
    }
    .filter-label {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 10px;
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 600;
    }
    .filter-list {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }
    .filter-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 12px;
      border-radius: 999px;
      border: 1px solid var(--line);
      background: #f8faff;
      color: #334155;
      font-size: 0.9rem;
    }
    .editor-card form { display: grid; gap: 14px; }
    .editor-grid {
      display: grid;
      gap: 12px;
      grid-template-columns: repeat(2, minmax(0, 1fr));
    }
    .field {
      display: grid;
      gap: 7px;
    }
    .field.full { grid-column: 1 / -1; }
    label {
      color: #475569;
      font-size: 0.8rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }
    input[type="text"],
    input[type="number"],
    textarea,
    select {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 14px;
      padding: 12px 14px;
      background: #f9fbff;
      color: var(--ink);
      outline: none;
    }
    textarea {
      min-height: 120px;
      resize: vertical;
    }
    .editor-preview {
      width: 100%;
      height: 180px;
      border-radius: 18px;
      object-fit: cover;
      border: 1px solid var(--line);
      background: #eef2ff;
      margin-bottom: 12px;
    }
    .editor-actions {
      display: flex;
      flex-wrap: wrap;
      justify-content: space-between;
      gap: 10px;
      align-items: center;
      margin-top: 4px;
    }
    .flash {
      margin-bottom: 14px;
      padding: 14px 16px;
      border-radius: 16px;
      border: 1px solid var(--line);
      background: #fff;
      box-shadow: var(--shadow);
    }
    .flash.success { border-color: rgba(22, 163, 74, 0.2); background: #f0fdf4; color: #166534; }
    .flash.error { border-color: rgba(239, 68, 68, 0.2); background: #fef2f2; color: #b91c1c; }
    .muted { color: var(--muted); }
    .small { font-size: 0.85rem; }
    .status-line {
      margin-top: 8px;
      color: var(--muted);
      font-size: 0.85rem;
    }
    .mobile-only {
      display: none;
    }
    @media (max-width: 1180px) {
      .page-shell { grid-template-columns: 1fr; }
      .right-stack { position: static; }
    }
    @media (max-width: 900px) {
      .layout { grid-template-columns: 1fr; }
      .sidebar {
        position: relative;
        height: auto;
      }
      .content { padding: 14px; }
      .topbar { flex-wrap: wrap; }
      .toolbar-input { min-width: 100%; }
    }
    @media (max-width: 640px) {
      .editor-grid { grid-template-columns: 1fr; }
      .stats-grid { grid-template-columns: 1fr; }
      .mobile-only { display: inline-flex; }
      .desktop-only { display: none; }
    }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-mark">E</div>
        <div>
          <div style="font-size: 1rem;">EXTRA</div>
          <div class="muted small" style="font-weight:600;">Store Admin</div>
        </div>
      </div>

      <nav>
        <div class="side-group">
          <p class="side-title">Main</p>
          <a class="side-link active" href="admin.php">Products</a>
          <a class="side-link" href="index.html" target="_blank" rel="noreferrer">Storefront</a>
          <a class="side-link" href="checkout.html" target="_blank" rel="noreferrer">Checkout</a>
        </div>

        <div class="side-group">
          <p class="side-title">E-commerce</p>
          <div class="side-item active">Products</div>
          <div class="side-sublist">
            <div class="side-item">Product Details</div>
            <div class="side-item">Product List</div>
            <div class="side-item">Checkout</div>
          </div>
        </div>

        <div class="side-group">
          <p class="side-title">Forms</p>
          <div class="side-item">Components</div>
          <div class="side-item">Plugins</div>
        </div>
      </nav>

      <div class="sidebar-note">
        Use the form to create a new product or edit an existing one. Deleting a product removes it from the storefront too.
      </div>
    </aside>

    <main class="content">
      <div class="topbar">
        <button class="hamburger mobile-only" type="button" aria-label="Menu">☰</button>
        <div class="searchbar">
          <span aria-hidden="true">⌕</span>
          <input id="globalSearch" type="text" placeholder="Search products, colors, categories">
        </div>
        <div class="top-icons">
          <div class="icon-pill" aria-hidden="true">◔</div>
          <div class="icon-pill" aria-hidden="true">A</div>
          <div class="icon-pill" aria-hidden="true">!</div>
          <div class="avatar" aria-label="Admin">EX</div>
          <div class="icon-pill" aria-hidden="true">⚙</div>
        </div>
      </div>

      <?php if ($status === 'created'): ?>
        <div class="flash success">Created product <strong><?php echo admin_escape($message !== '' ? $message : ''); ?></strong>.</div>
      <?php elseif ($status === 'updated'): ?>
        <div class="flash success">Saved changes for <strong><?php echo admin_escape($message !== '' ? $message : ''); ?></strong>.</div>
      <?php elseif ($status === 'deleted'): ?>
        <div class="flash success">Deleted product <strong><?php echo admin_escape($message !== '' ? $message : ''); ?></strong>.</div>
      <?php elseif ($status === 'error'): ?>
        <div class="flash error"><?php echo admin_escape($message !== '' ? $message : 'Something went wrong.'); ?></div>
      <?php endif; ?>

      <div class="page-shell">
        <section class="main-panel">
          <div class="section-head">
            <div class="headline">
              <h1>Products</h1>
              <p>Manage product records, images, and storefront details from one place.</p>
            </div>
            <div class="breadcrumb">
              <span>Home</span>
              <span>&gt;</span>
              <strong>E-commerce</strong>
              <span>&gt;</span>
              <span>Products</span>
            </div>
          </div>

          <div class="toolbar">
            <div class="toolbar-left">
              <div class="small muted" style="font-weight:700;">Shop</div>
              <div class="small muted">&gt;</div>
            </div>
            <div class="toolbar-right">
              <input id="productSearch" class="toolbar-input" type="text" placeholder="Search product">
              <select id="productSort" class="toolbar-select">
                <option value="featured">Sort by featured</option>
                <option value="price-low">Price: Low to High</option>
                <option value="price-high">Price: High to Low</option>
                <option value="name">Name: A to Z</option>
              </select>
              <button id="clearFilters" type="button" class="toolbar-chip">Clear filters</button>
            </div>
          </div>

          <div class="product-grid" id="productGrid">
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
                $isSelected = $productId !== '' && $productId === $editId;
              ?>
              <article
                class="product-card<?php echo $isSelected ? ' highlight' : ''; ?>"
                data-product-card
                data-product-id="<?php echo admin_escape($productId); ?>"
                data-product-name="<?php echo admin_escape($productName); ?>"
                data-product-color="<?php echo admin_escape($productColor); ?>"
                data-product-category="<?php echo admin_escape($productCategory); ?>"
                data-product-price="<?php echo (int) $productPrice; ?>"
                data-product-updated="<?php echo admin_escape((string) ($product['updated_at'] ?? '')); ?>"
              >
                <img class="product-thumb" src="<?php echo admin_escape($productImage !== '' ? $productImage : 'assets/red-product-clean.png'); ?>" alt="<?php echo admin_escape($productName); ?>">
                <div class="product-body">
                  <h3 class="product-name"><?php echo admin_escape($productName); ?></h3>
                  <p class="product-desc"><?php echo admin_escape($productDescription); ?></p>
                  <div class="product-meta">
                    <span><?php echo admin_escape($productCategory); ?></span>
                    <span><?php echo admin_escape($productColor); ?></span>
                  </div>
                  <div class="product-meta" style="margin-top:10px;">
                    <strong><?php echo admin_escape('₦' . number_format($productPrice)); ?></strong>
                    <span><?php echo admin_escape($productId); ?></span>
                  </div>
                  <div class="product-actions">
                    <button
                      type="button"
                      class="btn btn-soft"
                      data-edit-product
                      data-product-id="<?php echo admin_escape($productId); ?>"
                      data-product-name="<?php echo admin_escape($productName); ?>"
                      data-product-price="<?php echo (int) $productPrice; ?>"
                      data-product-color="<?php echo admin_escape($productColor); ?>"
                      data-product-category="<?php echo admin_escape($productCategory); ?>"
                      data-product-image="<?php echo admin_escape($productImage); ?>"
                      data-product-gallery="<?php echo admin_escape($productGallery); ?>"
                      data-product-description="<?php echo admin_escape($productDescription); ?>"
                    >Edit</button>
                    <form method="post" class="delete-form" data-delete-form data-product-name="<?php echo admin_escape($productName); ?>">
                      <input type="hidden" name="action_type" value="delete">
                      <input type="hidden" name="id" value="<?php echo admin_escape($productId); ?>">
                      <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                  </div>
                </div>
              </article>
            <?php endforeach; ?>
          </div>
        </section>

        <div class="right-stack">
          <section class="stats-card">
            <h2 class="panel-title">Overview</h2>
            <div class="stats-grid">
              <div class="stat">
                <strong id="productCount"><?php echo count($products); ?></strong>
                <span>Products</span>
              </div>
              <div class="stat">
                <strong><?php echo admin_escape('₦' . number_format($totalValue)); ?></strong>
                <span>Total value</span>
              </div>
              <div class="stat">
                <strong><?php echo admin_escape($latestUpdate !== '' ? $latestUpdate : 'N/A'); ?></strong>
                <span>Latest update</span>
              </div>
              <div class="stat">
                <strong><?php echo admin_escape($selectedProduct['id'] ?? 'N/A'); ?></strong>
                <span>Selected</span>
              </div>
            </div>
          </section>

          <section class="filters-card">
            <h2 class="panel-title">Filters</h2>
            <div class="filter-group" style="border-top: 0; padding-top: 0;">
              <div class="filter-label">Categories</div>
              <div class="filter-list">
                <label class="filter-pill"><input type="checkbox" data-filter-category value="all" checked> All</label>
                <?php foreach ($allCategories as $category): ?>
                  <label class="filter-pill"><input type="checkbox" data-filter-category value="<?php echo admin_escape($category); ?>"> <?php echo admin_escape($category); ?></label>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="filter-group">
              <div class="filter-label">Colors</div>
              <div class="filter-list">
                <label class="filter-pill"><input type="checkbox" data-filter-color value="all" checked> All</label>
                <?php foreach ($allColors as $color): ?>
                  <label class="filter-pill"><input type="checkbox" data-filter-color value="<?php echo admin_escape($color); ?>"> <?php echo admin_escape($color); ?></label>
                <?php endforeach; ?>
              </div>
            </div>
          </section>

          <section class="editor-card" id="editorCard">
            <h2 class="panel-title">Create or edit product</h2>
            <img
              id="editorPreview"
              class="editor-preview"
              src="<?php echo admin_escape($editorPrimary !== '' ? $editorPrimary : 'assets/red-product-clean.png'); ?>"
              alt="Product preview"
            >

            <form method="post" id="productForm" enctype="multipart/form-data">
              <input type="hidden" name="action_type" id="actionType" value="<?php echo $selectedProduct ? 'update' : 'create'; ?>">
              <div class="editor-grid">
                <div class="field">
                  <label for="productId">Product ID</label>
                  <input
                    id="productId"
                    name="id"
                    type="text"
                    value="<?php echo admin_input_value($editorValues['id'] ?? ''); ?>"
                    placeholder="Leave blank for auto ID"
                  >
                </div>
                <div class="field">
                  <label for="productName">Product name</label>
                  <input id="productName" name="name" type="text" value="<?php echo admin_input_value($editorValues['name'] ?? ''); ?>" required>
                </div>
                <div class="field">
                  <label for="productPrice">Price</label>
                  <input id="productPrice" name="price" type="number" min="0" step="1" value="<?php echo admin_input_value($editorValues['price'] ?? ''); ?>" required>
                </div>
                <div class="field">
                  <label for="productColor">Color</label>
                  <input id="productColor" name="color" type="text" value="<?php echo admin_input_value($editorValues['color'] ?? ''); ?>" required>
                </div>
                <div class="field">
                  <label for="productCategory">Category</label>
                  <input id="productCategory" name="category" type="text" value="<?php echo admin_input_value($editorValues['category'] ?? ''); ?>" required>
                </div>
                <div class="field">
                  <label for="productImage">Primary image</label>
                  <input id="productImage" name="image_primary" type="text" value="<?php echo admin_input_value($editorPrimary); ?>" placeholder="Paste an image path or use upload below">
                </div>
                <div class="field full">
                  <label for="productImageUpload">Upload primary image from gallery</label>
                  <input id="productImageUpload" name="image_primary_upload" type="file" accept="image/*">
                  <div class="status-line">Choose an image from your device to replace the primary product image.</div>
                </div>
                <div class="field full">
                  <label for="productGallery">Gallery images</label>
                  <textarea id="productGallery" name="images_text" placeholder="One image path per line"><?php echo admin_escape($editorGallery); ?></textarea>
                </div>
                <div class="field full">
                  <label for="productGalleryUpload">Upload gallery images</label>
                  <input id="productGalleryUpload" name="gallery_image_uploads[]" type="file" accept="image/*" multiple>
                  <div class="status-line">Any files you choose here will be added to the product gallery.</div>
                </div>
                <div class="field full">
                  <label for="productDescription">Description</label>
                  <textarea id="productDescription" name="description" required><?php echo admin_escape((string) ($editorValues['description'] ?? '')); ?></textarea>
                </div>
              </div>

              <div class="editor-actions">
                <div>
                  <div id="editorMode" style="font-weight:700; color: var(--accent);">
                    <?php echo $selectedProduct ? 'Editing ' . admin_escape((string) ($selectedProduct['id'] ?? 'product')) : 'Creating new product'; ?>
                  </div>
                  <div class="status-line">Save to update the storefront, checkout, and order emails.</div>
                </div>
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                  <button type="button" id="newProductBtn" class="btn btn-soft">New Product</button>
                  <button type="submit" class="btn btn-primary" id="submitButton">Save Product</button>
                </div>
              </div>
            </form>
          </section>
        </div>
      </div>
    </main>
  </div>

  <script>
    (function () {
      const searchInput = document.getElementById('productSearch');
      const globalSearch = document.getElementById('globalSearch');
      const sortSelect = document.getElementById('productSort');
      const clearButton = document.getElementById('clearFilters');
      const grid = document.getElementById('productGrid');
      const cards = Array.from(document.querySelectorAll('[data-product-card]'));
      const categoryFilters = Array.from(document.querySelectorAll('[data-filter-category]'));
      const colorFilters = Array.from(document.querySelectorAll('[data-filter-color]'));
      const form = document.getElementById('productForm');
      const actionType = document.getElementById('actionType');
      const editorMode = document.getElementById('editorMode');
      const submitButton = document.getElementById('submitButton');
      const newProductBtn = document.getElementById('newProductBtn');
      const preview = document.getElementById('editorPreview');
      const idField = document.getElementById('productId');
      const nameField = document.getElementById('productName');
      const priceField = document.getElementById('productPrice');
      const colorField = document.getElementById('productColor');
      const categoryField = document.getElementById('productCategory');
      const imageField = document.getElementById('productImage');
      const imageUploadField = document.getElementById('productImageUpload');
      const galleryField = document.getElementById('productGallery');
      const galleryUploadField = document.getElementById('productGalleryUpload');
      const descriptionField = document.getElementById('productDescription');
      const productCount = document.getElementById('productCount');
      let previewBlobUrl = '';

      function normalize(value) {
        return String(value || '').trim().toLowerCase();
      }

      function setPreviewSource(src) {
        if (previewBlobUrl) {
          URL.revokeObjectURL(previewBlobUrl);
          previewBlobUrl = '';
        }
        preview.src = src;
      }

      function previewUploadedImage(file) {
        if (!file) return;
        if (previewBlobUrl) {
          URL.revokeObjectURL(previewBlobUrl);
        }
        previewBlobUrl = URL.createObjectURL(file);
        preview.src = previewBlobUrl;
      }

      function setEditorMode(mode, product = null) {
        actionType.value = mode;
        submitButton.textContent = mode === 'create' ? 'Create Product' : 'Save Product';
        editorMode.textContent = mode === 'create'
          ? 'Creating new product'
          : `Editing ${product?.id || 'product'}`;
        idField.readOnly = mode === 'update';
        idField.placeholder = mode === 'create' ? 'Leave blank for auto ID' : '';
        if (mode === 'create') {
          idField.value = '';
        }
      }

      function fillEditor(data) {
        if (!data) return;
        idField.value = data.id || '';
        nameField.value = data.name || '';
        priceField.value = data.price || '';
        colorField.value = data.color || '';
        categoryField.value = data.category || '';
        imageField.value = data.image || '';
        galleryField.value = data.gallery || '';
        descriptionField.value = data.description || '';
        if (imageUploadField) imageUploadField.value = '';
        if (galleryUploadField) galleryUploadField.value = '';
        setPreviewSource(data.image || preview.src);
        setEditorMode('update', data);
        document.getElementById('editorCard')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      function clearEditor() {
        form.reset();
        idField.value = '';
        nameField.value = '';
        priceField.value = '';
        colorField.value = '';
        categoryField.value = '';
        imageField.value = '';
        galleryField.value = '';
        descriptionField.value = '';
        if (imageUploadField) imageUploadField.value = '';
        if (galleryUploadField) galleryUploadField.value = '';
        setPreviewSource('assets/red-product-clean.png');
        setEditorMode('create');
        nameField.focus();
      }

      function applyFilters() {
        const query = normalize(searchInput?.value);
        const selectedCategories = categoryFilters.filter((checkbox) => checkbox.checked && checkbox.value !== 'all').map((checkbox) => normalize(checkbox.value));
        const selectedColors = colorFilters.filter((checkbox) => checkbox.checked && checkbox.value !== 'all').map((checkbox) => normalize(checkbox.value));
        const sortMode = sortSelect?.value || 'featured';

        const visible = cards.filter((card) => {
          const haystack = [
            card.dataset.productName,
            card.dataset.productCategory,
            card.dataset.productColor,
            card.dataset.productId
          ].join(' ').toLowerCase();
          const matchesQuery = !query || haystack.includes(query);
          const matchesCategory = !selectedCategories.length || selectedCategories.includes(normalize(card.dataset.productCategory));
          const matchesColor = !selectedColors.length || selectedColors.includes(normalize(card.dataset.productColor));
          const show = matchesQuery && matchesCategory && matchesColor;
          card.hidden = !show;
          return show;
        });

        const sorted = [...visible].sort((left, right) => {
          if (sortMode === 'price-low') return Number(left.dataset.productPrice || 0) - Number(right.dataset.productPrice || 0);
          if (sortMode === 'price-high') return Number(right.dataset.productPrice || 0) - Number(left.dataset.productPrice || 0);
          if (sortMode === 'name') return (left.dataset.productName || '').localeCompare(right.dataset.productName || '');
          return (right.dataset.productUpdated || '').localeCompare(left.dataset.productUpdated || '');
        });

        sorted.forEach((card) => grid.appendChild(card));

        if (productCount) {
          productCount.textContent = String(sorted.length);
        }
      }

      document.addEventListener('click', (event) => {
        const editButton = event.target.closest('[data-edit-product]');
        if (editButton) {
          fillEditor({
            id: editButton.dataset.productId,
            name: editButton.dataset.productName,
            price: editButton.dataset.productPrice,
            color: editButton.dataset.productColor,
            category: editButton.dataset.productCategory,
            image: editButton.dataset.productImage,
            gallery: editButton.dataset.productGallery,
            description: editButton.dataset.productDescription
          });
        }
      });

      document.addEventListener('submit', (event) => {
        const deleteForm = event.target.closest('[data-delete-form]');
        if (!deleteForm) return;

        const productName = deleteForm.dataset.productName || 'this product';
        if (!window.confirm(`Delete ${productName}?`)) {
          event.preventDefault();
        }
      });

      categoryFilters.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          if (checkbox.value === 'all' && checkbox.checked) {
            categoryFilters.forEach((item) => {
              if (item !== checkbox) item.checked = false;
            });
          } else if (checkbox.checked) {
            const all = categoryFilters.find((item) => item.value === 'all');
            if (all) all.checked = false;
          }
          applyFilters();
        });
      });

      colorFilters.forEach((checkbox) => {
        checkbox.addEventListener('change', () => {
          if (checkbox.value === 'all' && checkbox.checked) {
            colorFilters.forEach((item) => {
              if (item !== checkbox) item.checked = false;
            });
          } else if (checkbox.checked) {
            const all = colorFilters.find((item) => item.value === 'all');
            if (all) all.checked = false;
          }
          applyFilters();
        });
      });

      globalSearch?.addEventListener('input', () => {
        if (searchInput) {
          searchInput.value = globalSearch.value;
        }
        applyFilters();
      });
      searchInput?.addEventListener('input', () => {
        if (globalSearch && globalSearch.value !== searchInput.value) {
          globalSearch.value = searchInput.value;
        }
        applyFilters();
      });
      sortSelect?.addEventListener('change', applyFilters);

      clearButton?.addEventListener('click', () => {
        if (searchInput) searchInput.value = '';
        if (sortSelect) sortSelect.value = 'featured';
        categoryFilters.forEach((checkbox) => {
          checkbox.checked = checkbox.value === 'all';
        });
        colorFilters.forEach((checkbox) => {
          checkbox.checked = checkbox.value === 'all';
        });
        applyFilters();
      });

      newProductBtn?.addEventListener('click', clearEditor);

      imageField?.addEventListener('input', () => {
        if (imageField.value.trim()) {
          setPreviewSource(imageField.value.trim());
        }
      });

      imageUploadField?.addEventListener('change', () => {
        const file = imageUploadField.files && imageUploadField.files[0] ? imageUploadField.files[0] : null;
        if (file) {
          previewUploadedImage(file);
        }
      });

      applyFilters();

      <?php if ($selectedProduct): ?>
      setEditorMode('update', { id: <?php echo json_encode((string) $selectedProduct['id']); ?> });
      <?php else: ?>
      setEditorMode('create');
      <?php endif; ?>
    })();
  </script>
</body>
</html>
