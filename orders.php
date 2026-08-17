<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product-repository.php';

function orders_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function orders_currency(int $amount): string
{
    return '₦' . number_format($amount);
}

function orders_text(array $order, string $key, string $fallback = ''): string
{
    $value = $order[$key] ?? $fallback;
    return trim((string) $value);
}

function orders_date(string $value): string
{
    if ($value === '') {
        return 'N/A';
    }

    try {
        return (new DateTimeImmutable($value))->format('d M Y, H:i');
    } catch (Throwable $error) {
        return $value;
    }
}

try {
    $pdo = extra_store_pdo();
    extra_store_bootstrap_catalog($pdo);
    $orders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC, id DESC')->fetchAll();
} catch (Throwable $error) {
    http_response_code(500);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Orders error</title></head><body style="font-family:Arial,sans-serif;padding:24px;background:#f5f7fb;color:#111827;">';
    echo '<h1>Unable to load orders</h1>';
    echo '<p>The database connection failed. Check MAMP, the database name, and the MySQL port.</p>';
    echo '<pre style="white-space:pre-wrap;background:#fff;border:1px solid #e5e7eb;border-radius:12px;padding:16px;">' . orders_escape($error->getMessage()) . '</pre>';
    echo '</body></html>';
    exit;
}

$statusCounts = [
    'pending' => 0,
    'processing' => 0,
    'paid' => 0,
    'cancelled' => 0,
];
$totalRevenue = 0;
foreach ($orders as $order) {
    $status = strtolower((string) ($order['status'] ?? 'pending'));
    if (isset($statusCounts[$status])) {
        $statusCounts[$status]++;
    }
    $totalRevenue += (int) ($order['total_price'] ?? 0);
}

$orderCount = count($orders);
$latestOrderAt = orders_date((string) ($orders[0]['created_at'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Extra Store Orders</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #eff3fb;
      --panel: #ffffff;
      --line: #dfe7f3;
      --ink: #1f2a37;
      --muted: #6b7280;
      --accent: #6b4eff;
      --accent-soft: #ece7ff;
      --danger: #ef4444;
      --success: #16a34a;
      --warning: #f59e0b;
      --shadow: 0 18px 45px rgba(29, 41, 57, 0.08);
      --sidebar-width: 292px;
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
      font-weight: 400;
      letter-spacing: 0.01em;
      overflow-x: hidden;
    }
    strong, b { font-weight: 500; }
    a { color: inherit; text-decoration: none; }
    button, input { font: inherit; font-weight: inherit; }
    .layout {
      display: grid;
      grid-template-columns: var(--sidebar-width) minmax(0, 1fr);
      min-height: 100vh;
    }
    .sidebar {
      position: sticky;
      top: 0;
      align-self: start;
      height: 100vh;
      padding: 18px;
      background:
        radial-gradient(circle at top right, rgba(107, 78, 255, 0.06), transparent 30%),
        rgba(255, 255, 255, 0.94);
      border-right: 1px solid var(--line);
      backdrop-filter: blur(12px);
      overflow: auto;
      z-index: 40;
    }
    .sidebar-shell {
      min-height: 100%;
      display: flex;
      flex-direction: column;
      gap: 16px;
    }
    .sidebar-head {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
    }
    .sidebar-close {
      display: none;
      width: 40px;
      height: 40px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #fff;
      color: var(--ink);
      font-size: 1.2rem;
      font-weight: 500;
      box-shadow: var(--shadow);
      cursor: pointer;
    }
    .brand {
      display: flex;
      align-items: center;
      gap: 12px;
      font-weight: 500;
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
    .side-group {
      padding: 14px;
      border-radius: 20px;
      border: 1px solid var(--line);
      background: rgba(248, 250, 255, 0.92);
    }
    .side-group + .side-group { margin-top: 0; }
    .side-title {
      color: var(--muted);
      font-size: 0.72rem;
      font-weight: 500;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin: 0 0 10px;
    }
    .side-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 12px 14px;
      border-radius: 14px;
      color: #334155;
      margin-bottom: 8px;
      transition: background 120ms ease, transform 120ms ease, color 120ms ease;
    }
    .side-link:hover {
      background: #f2f6ff;
      transform: translateX(1px);
    }
    .side-link.active {
      background: var(--accent-soft);
      color: var(--accent);
      font-weight: 500;
    }
    .sidebar-note {
      margin-top: auto;
      padding: 16px;
      border-radius: 18px;
      background: linear-gradient(135deg, #f6f0ff, #eff8ff);
      border: 1px solid #e6e8ff;
      color: #4c1d95;
      font-size: 0.9rem;
      line-height: 1.6;
    }
    .content {
      padding: clamp(14px, 1.8vw, 24px);
      min-width: 0;
    }
    .topbar {
      position: sticky;
      top: 18px;
      z-index: 35;
      display: flex;
      align-items: center;
      gap: 16px;
      background: rgba(255, 255, 255, 0.94);
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
      font-weight: 500;
      cursor: pointer;
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
      background: #fff;
    }
    .searchbar input {
      width: 100%;
      border: 0;
      outline: 0;
      background: transparent;
      color: var(--ink);
    }
    .top-actions {
      display: flex;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap;
      margin-left: auto;
    }
    .action-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      min-height: 40px;
      padding: 0 14px;
      border-radius: 14px;
      border: 1px solid var(--line);
      background: #f8faff;
      color: #475569;
      font-size: 0.85rem;
      font-weight: 500;
    }
    .action-pill.primary {
      background: var(--accent);
      border-color: transparent;
      color: white;
      box-shadow: 0 10px 24px rgba(107, 78, 255, 0.22);
    }
    .hero {
      margin-top: 18px;
      padding: 22px;
      border-radius: 28px;
      background:
        radial-gradient(circle at top right, rgba(107, 78, 255, 0.12), transparent 34%),
        radial-gradient(circle at bottom left, rgba(56, 189, 248, 0.10), transparent 28%),
        rgba(255, 255, 255, 0.92);
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
    }
    .hero-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }
    .hero h1 {
      margin: 0;
      font-size: clamp(1.65rem, 2vw, 2.3rem);
      line-height: 1.1;
      font-weight: 500;
    }
    .hero p {
      margin: 10px 0 0;
      color: var(--muted);
      max-width: 56rem;
      line-height: 1.65;
    }
    .stats-grid {
      margin-top: 18px;
      display: grid;
      grid-template-columns: repeat(5, minmax(0, 1fr));
      gap: 12px;
    }
    .stat-card {
      padding: 16px;
      border-radius: 20px;
      background: linear-gradient(180deg, #ffffff, #f3f6ff);
      border: 1px solid var(--line);
    }
    .stat-card span {
      display: block;
      color: var(--muted);
      font-size: 0.8rem;
    }
    .stat-card strong {
      display: block;
      margin-top: 6px;
      font-size: 1.25rem;
      font-weight: 500;
    }
    .panel {
      margin-top: 18px;
      padding: 18px;
      border-radius: 28px;
      background: rgba(255, 255, 255, 0.92);
      border: 1px solid var(--line);
      box-shadow: var(--shadow);
    }
    .panel-head {
      display: flex;
      align-items: flex-end;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 16px;
    }
    .panel-head h2 {
      margin: 0;
      font-size: 1.15rem;
      font-weight: 500;
    }
    .panel-head p {
      margin: 6px 0 0;
      color: var(--muted);
      font-size: 0.92rem;
    }
    .orders-list {
      display: grid;
      gap: 14px;
    }
    .order-card {
      overflow: hidden;
      border-radius: 24px;
      border: 1px solid var(--line);
      background: linear-gradient(180deg, #ffffff, #f7f9ff);
      box-shadow: 0 16px 38px rgba(31, 42, 55, 0.07);
    }
    .order-card[open] {
      border-color: rgba(107, 78, 255, 0.22);
      box-shadow: 0 20px 46px rgba(107, 78, 255, 0.12);
    }
    .order-summary {
      list-style: none;
      cursor: pointer;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 18px;
      padding: 18px 20px;
      outline: none;
    }
    .order-summary::-webkit-details-marker { display: none; }
    .order-summary-main {
      min-width: 0;
    }
    .order-kicker {
      color: var(--muted);
      font-size: 0.74rem;
      letter-spacing: 0.12em;
      text-transform: uppercase;
    }
    .order-title {
      margin: 8px 0 6px;
      font-size: 1.02rem;
      font-weight: 500;
      line-height: 1.35;
    }
    .order-copy {
      margin: 0;
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.5;
    }
    .order-summary-side {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      gap: 8px;
      text-align: right;
      flex-shrink: 0;
    }
    .order-badge {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-height: 30px;
      padding: 0 12px;
      border-radius: 999px;
      font-size: 0.72rem;
      font-weight: 500;
      letter-spacing: 0.08em;
      text-transform: uppercase;
    }
    .status-pending { background: #fef3c7; color: #92400e; }
    .status-processing { background: #e0f2fe; color: #075985; }
    .status-paid { background: #dcfce7; color: #166534; }
    .status-cancelled { background: #fee2e2; color: #b91c1c; }
    .order-summary-side strong {
      font-size: 1.05rem;
      font-weight: 500;
    }
    .order-summary-side span {
      color: var(--muted);
      font-size: 0.82rem;
    }
    .order-details {
      padding: 0 20px 20px;
      display: grid;
      gap: 16px;
    }
    .order-preview {
      display: grid;
      grid-template-columns: 104px minmax(0, 1fr);
      gap: 16px;
      padding: 16px;
      border-radius: 22px;
      border: 1px solid var(--line);
      background: #f8faff;
    }
    .order-preview img {
      width: 104px;
      height: 104px;
      object-fit: cover;
      border-radius: 18px;
      border: 1px solid var(--line);
      background: #eef2ff;
    }
    .order-preview h3 {
      margin: 4px 0 6px;
      font-size: 1.02rem;
      font-weight: 500;
    }
    .order-preview p {
      margin: 0;
      color: var(--muted);
      font-size: 0.9rem;
      line-height: 1.6;
    }
    .detail-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap: 12px;
    }
    .detail-card {
      padding: 14px;
      border-radius: 18px;
      background: linear-gradient(180deg, #f8faff, #f2f6ff);
      border: 1px solid var(--line);
      min-width: 0;
    }
    .detail-card span {
      display: block;
      color: var(--muted);
      font-size: 0.75rem;
      letter-spacing: 0.1em;
      text-transform: uppercase;
    }
    .detail-card strong {
      display: block;
      margin-top: 6px;
      font-size: 0.94rem;
      font-weight: 500;
      line-height: 1.5;
      overflow-wrap: anywhere;
    }
    .empty-state {
      padding: 22px;
      border-radius: 22px;
      border: 1px dashed var(--line);
      background: #f8faff;
      color: var(--muted);
      line-height: 1.7;
    }
    .sidebar-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.42);
      backdrop-filter: blur(2px);
      border: 0;
      padding: 0;
      margin: 0;
      opacity: 0;
      pointer-events: none;
      transition: opacity 180ms ease;
      z-index: 30;
    }
    .mobile-only { display: none; }
    @media (max-width: 1180px) {
      .stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
      .topbar { top: 14px; }
    }
    @media (max-width: 900px) {
      .layout { grid-template-columns: 1fr; }
      .sidebar {
        position: fixed;
        inset: 0 auto 0 0;
        width: min(88vw, 320px);
        height: 100dvh;
        transform: translateX(-105%);
        transition: transform 220ms ease;
        box-shadow: 32px 0 70px rgba(15, 23, 42, 0.18);
        border-right: 1px solid var(--line);
        padding: 16px;
      }
      body.sidebar-open .sidebar { transform: translateX(0); }
      body.sidebar-open .sidebar-backdrop {
        opacity: 1;
        pointer-events: auto;
      }
      .content { padding: 14px; }
      .topbar {
        top: 14px;
        flex-wrap: wrap;
        align-items: flex-start;
        padding: 12px 14px;
      }
      .searchbar {
        order: 3;
        width: 100%;
        min-width: 0;
      }
      .top-actions {
        margin-left: 0;
      }
      .sidebar-close { display: inline-grid; place-items: center; }
      .side-group { padding: 12px; }
      .detail-grid,
      .order-preview {
        grid-template-columns: 1fr;
      }
      .order-preview img {
        width: 100%;
        height: 220px;
      }
      .order-summary {
        flex-direction: column;
      }
      .order-summary-side {
        align-items: flex-start;
        text-align: left;
      }
    }
    @media (max-width: 640px) {
      .stats-grid { grid-template-columns: 1fr; }
      .mobile-only { display: inline-grid; }
      .hero,
      .panel {
        padding: 16px;
        border-radius: 22px;
      }
      .hero h1 { font-size: clamp(1.45rem, 6vw, 1.95rem); }
      .order-summary,
      .order-details {
        padding-left: 16px;
        padding-right: 16px;
      }
      .order-summary {
        padding-top: 16px;
        padding-bottom: 16px;
      }
    }
  </style>
</head>
<body>
  <div class="layout">
    <button class="sidebar-backdrop" data-sidebar-overlay type="button" aria-label="Close sidebar" hidden></button>
    <aside class="sidebar" data-sidebar>
      <div class="sidebar-shell">
        <div class="sidebar-head">
          <div class="brand">
            <div class="brand-mark">E</div>
            <div>
              <div style="font-size: 1rem;">EXTRA</div>
              <div class="muted" style="font-size: 0.85rem; font-weight: 400;">Store Admin</div>
            </div>
          </div>
          <button class="sidebar-close mobile-only" type="button" aria-label="Close menu" data-sidebar-close>×</button>
        </div>

        <nav class="sidebar-nav">
          <div class="side-group">
            <p class="side-title">Main</p>
            <a class="side-link" href="admin.php">Products</a>
            <a class="side-link active" href="orders.php">Orders</a>
          </div>

          <div class="side-group">
            <p class="side-title">Storefronts</p>
            <a class="side-link" href="index.html" target="_blank" rel="noreferrer">Storefront</a>
            <a class="side-link" href="light-index.html" target="_blank" rel="noreferrer">Light Storefront</a>
            <a class="side-link" href="iron-index.html" target="_blank" rel="noreferrer">Iron Storefront</a>
          </div>
        </nav>

        <div class="sidebar-note">
          Click any order card to expand the full customer and delivery details.
        </div>
      </div>
    </aside>

    <main class="content">
      <div class="topbar">
        <button class="hamburger mobile-only" type="button" aria-label="Menu" data-sidebar-toggle>☰</button>
        <div class="searchbar">
          <span aria-hidden="true">⌕</span>
          <input id="orderSearch" type="text" placeholder="Search order number, customer, product, state">
        </div>
        <div class="top-actions">
          <a class="action-pill" href="admin.php">Back to Products</a>
          <a class="action-pill primary" href="#ordersList">View Orders</a>
        </div>
      </div>

      <section class="hero">
        <div class="hero-top">
          <div>
            <p class="order-kicker">Orders Dashboard</p>
            <h1>All purchases in one place</h1>
            <p>Open any order to see the full customer details, shipping address, product summary, and payment note. This page keeps purchase management inside admin instead of sending you back to the storefront checkout screens.</p>
          </div>
        </div>

        <div class="stats-grid">
          <div class="stat-card"><span>Total orders</span><strong><?php echo (int) $orderCount; ?></strong></div>
          <div class="stat-card"><span>Pending</span><strong><?php echo (int) $statusCounts['pending']; ?></strong></div>
          <div class="stat-card"><span>Processing</span><strong><?php echo (int) $statusCounts['processing']; ?></strong></div>
          <div class="stat-card"><span>Paid</span><strong><?php echo (int) $statusCounts['paid']; ?></strong></div>
          <div class="stat-card"><span>Total value</span><strong><?php echo orders_currency($totalRevenue); ?></strong></div>
        </div>
      </section>

      <section class="panel" id="ordersList">
        <div class="panel-head">
          <div>
            <h2>Order records</h2>
            <p>Click an order to expand all of the details.</p>
          </div>
          <div class="muted" style="font-size: 0.86rem;">Latest order: <?php echo orders_escape($latestOrderAt); ?></div>
        </div>

        <?php if ($orders): ?>
          <div class="orders-list">
            <?php foreach ($orders as $order): ?>
              <?php
                $orderNumber = orders_text($order, 'order_number', 'N/A');
                $productName = orders_text($order, 'product_name', 'Unknown product');
                $productImage = orders_text($order, 'product_image', '');
                $productDescription = orders_text($order, 'product_description', '');
                $productCategory = orders_text($order, 'product_category', '');
                $qty = (int) ($order['qty'] ?? 1);
                $unitPrice = (int) ($order['unit_price'] ?? 0);
                $totalPrice = (int) ($order['total_price'] ?? 0);
                $customerName = orders_text($order, 'customer_name', 'No customer name');
                $firstName = orders_text($order, 'first_name', '');
                $lastName = orders_text($order, 'last_name', '');
                $email = orders_text($order, 'email', '');
                $phone = orders_text($order, 'phone', '');
                $address = orders_text($order, 'address', '');
                $city = orders_text($order, 'city', '');
                $state = orders_text($order, 'state', '');
                $orderNote = orders_text($order, 'order_note', '');
                $status = strtolower(orders_text($order, 'status', 'pending'));
                if (!in_array($status, ['pending', 'processing', 'paid', 'cancelled'], true)) {
                    $status = 'pending';
                }
                $createdAt = orders_date(orders_text($order, 'created_at', ''));
                $updatedAt = orders_date(orders_text($order, 'updated_at', ''));
                $receiptName = orders_text($order, 'receipt_original_name', '');
                $receiptPath = orders_text($order, 'receipt_path', '');
                $productImageSrc = $productImage !== '' ? $productImage : 'assets/red-product-clean.png';
              ?>
              <details class="order-card" data-order-card>
                <summary class="order-summary">
                  <div class="order-summary-main">
                    <div class="order-kicker">#<?php echo orders_escape($orderNumber); ?> · <?php echo orders_escape($createdAt); ?></div>
                    <div class="order-title"><?php echo orders_escape($customerName); ?></div>
                    <p class="order-copy"><?php echo orders_escape($productName); ?><?php echo $state !== '' ? ' · ' . orders_escape($state) : ''; ?></p>
                  </div>
                  <div class="order-summary-side">
                    <span class="order-badge status-<?php echo orders_escape($status); ?>"><?php echo orders_escape($status); ?></span>
                    <strong><?php echo orders_currency($totalPrice); ?></strong>
                    <span><?php echo orders_escape($productCategory !== '' ? $productCategory : 'No category'); ?></span>
                  </div>
                </summary>

                <div class="order-details">
                  <div class="order-preview">
                    <img src="<?php echo orders_escape($productImageSrc); ?>" alt="<?php echo orders_escape($productName); ?>">
                    <div>
                      <div class="order-kicker">Product snapshot</div>
                      <h3><?php echo orders_escape($productName); ?></h3>
                      <p><?php echo orders_escape($productDescription !== '' ? $productDescription : 'No description provided.'); ?></p>
                    </div>
                  </div>

                  <div class="detail-grid">
                    <div class="detail-card"><span>Order number</span><strong><?php echo orders_escape($orderNumber); ?></strong></div>
                    <div class="detail-card"><span>Status</span><strong><?php echo orders_escape($status); ?></strong></div>
                    <div class="detail-card"><span>Quantity</span><strong><?php echo (int) $qty; ?></strong></div>
                    <div class="detail-card"><span>Unit price</span><strong><?php echo orders_currency($unitPrice); ?></strong></div>
                    <div class="detail-card"><span>Total</span><strong><?php echo orders_currency($totalPrice); ?></strong></div>
                    <div class="detail-card"><span>Category</span><strong><?php echo orders_escape($productCategory !== '' ? $productCategory : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>Customer</span><strong><?php echo orders_escape($customerName); ?></strong></div>
                    <div class="detail-card"><span>Phone</span><strong><?php echo orders_escape($phone !== '' ? $phone : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>Email</span><strong><?php echo orders_escape($email !== '' ? $email : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>Address</span><strong><?php echo orders_escape($address !== '' ? $address : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>State</span><strong><?php echo orders_escape($state !== '' ? $state : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>City</span><strong><?php echo orders_escape($city !== '' ? $city : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>First name</span><strong><?php echo orders_escape($firstName !== '' ? $firstName : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>Last name</span><strong><?php echo orders_escape($lastName !== '' ? $lastName : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>Created</span><strong><?php echo orders_escape($createdAt); ?></strong></div>
                    <div class="detail-card"><span>Updated</span><strong><?php echo orders_escape($updatedAt); ?></strong></div>
                    <div class="detail-card"><span>Receipt file</span><strong><?php echo orders_escape($receiptName !== '' ? $receiptName : 'N/A'); ?></strong></div>
                    <div class="detail-card"><span>Receipt path</span><strong><?php echo orders_escape($receiptPath !== '' ? $receiptPath : 'N/A'); ?></strong></div>
                    <div class="detail-card" style="grid-column: 1 / -1;"><span>Order note</span><strong><?php echo orders_escape($orderNote !== '' ? $orderNote : 'No note provided.'); ?></strong></div>
                  </div>
                </div>
              </details>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">No purchases have been placed yet. Once someone checks out, the full order record will show here.</div>
        <?php endif; ?>
      </section>
    </main>
  </div>

  <script>
    (function () {
      const sidebar = document.querySelector('[data-sidebar]');
      const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
      const sidebarClose = document.querySelector('[data-sidebar-close]');
      const sidebarOverlay = document.querySelector('[data-sidebar-overlay]');
      const searchInput = document.getElementById('orderSearch');
      const orderCards = Array.from(document.querySelectorAll('[data-order-card]'));

      function setSidebarOpen(open) {
        document.body.classList.toggle('sidebar-open', open);
        if (sidebarOverlay) {
          sidebarOverlay.hidden = !open;
        }
        if (sidebar) {
          if (window.innerWidth <= 900) {
            sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
          } else {
            sidebar.removeAttribute('aria-hidden');
          }
        }
      }

      function openSidebar() {
        setSidebarOpen(true);
      }

      function closeSidebar() {
        setSidebarOpen(false);
      }

      sidebarToggle?.addEventListener('click', openSidebar);
      sidebarClose?.addEventListener('click', closeSidebar);
      sidebarOverlay?.addEventListener('click', closeSidebar);

      sidebar?.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => {
          if (window.innerWidth <= 900) {
            closeSidebar();
          }
        });
      });

      window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
          closeSidebar();
        }
      });

      window.addEventListener('resize', () => {
        if (window.innerWidth > 900) {
          closeSidebar();
        }
      });

      function applySearch() {
        const query = String(searchInput?.value || '').trim().toLowerCase();
        orderCards.forEach((card) => {
          const text = card.textContent || '';
          card.hidden = query ? !text.toLowerCase().includes(query) : false;
        });
      }

      searchInput?.addEventListener('input', applySearch);
      applySearch();
    })();
  </script>
</body>
</html>
