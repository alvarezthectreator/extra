<?php
declare(strict_types=1);

header('X-Content-Type-Options: nosniff');

$wantsJson = false;
if (isset($_SERVER['HTTP_ACCEPT']) && strpos((string) $_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    $wantsJson = true;
}
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    $wantsJson = true;
}

function respond(array $payload, int $statusCode, bool $wantsJson): void
{
    http_response_code($statusCode);
    if ($wantsJson) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo htmlspecialchars($payload['message'] ?? 'Request processed.', ENT_QUOTES, 'UTF-8');
    exit;
}

function fail(string $message, int $statusCode, bool $wantsJson): void
{
    respond([
        'ok' => false,
        'message' => $message
    ], $statusCode, $wantsJson);
}

function extra_store_generate_order_number(PDO $pdo): string
{
    $check = $pdo->prepare('SELECT 1 FROM orders WHERE order_number = :order_number LIMIT 1');

    for ($attempt = 0; $attempt < 12; $attempt++) {
        $candidate = 'ES' . date('ymdHis') . random_int(1000, 9999);
        $check->execute(['order_number' => $candidate]);
        if (!$check->fetchColumn()) {
            return $candidate;
        }
    }

    return 'ES' . date('ymdHis') . random_int(100000, 999999);
}

function extra_store_table_columns(PDO $pdo, string $table): array
{
    static $cache = [];

    if (isset($cache[$table])) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->query(sprintf('SHOW COLUMNS FROM `%s`', str_replace('`', '``', $table)));
        $cache[$table] = $stmt ? array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN, 0)) : [];
    } catch (\Throwable $error) {
        $cache[$table] = [];
    }

    return $cache[$table];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail('Method not allowed.', 405, $wantsJson);
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require $autoload;
}
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/product-repository.php';

$productId = trim((string) ($_POST['product'] ?? ''));
$qty = (int) ($_POST['qty'] ?? 1);
$qty = max(1, min(99, $qty));
$fullName = trim((string) ($_POST['full_name'] ?? ($_POST['customer_name'] ?? '')));
$phone = trim((string) ($_POST['phone'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$state = trim((string) ($_POST['state'] ?? ''));
$orderNote = trim((string) ($_POST['order_note'] ?? ''));
if ($fullName === '' || $phone === '' || $address === '' || $state === '') {
    fail('Please complete all required fields.', 422, $wantsJson);
}

$nameParts = preg_split('/\s+/', $fullName, -1, PREG_SPLIT_NO_EMPTY) ?: [];
$firstName = $nameParts[0] ?? '';
$lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';
$customerName = $fullName;
$email = '';

$pdo = null;
try {
    $pdo = extra_store_pdo();
} catch (\Throwable $error) {
    fail('We could not connect to the database. Check your MAMP MySQL settings and database name.', 500, $wantsJson);
}

$product = extra_store_fetch_product($pdo, $productId);
if (!$product) {
    fail('Please choose a valid product.', 422, $wantsJson);
}

$productName = $product['name'];
$productPrice = (int) $product['price'];
$productTotal = $productPrice * $qty;
$productCategory = $product['category'];
$productImage = $product['image_primary'] ?: ($product['images'][0] ?? '');
$productDescription = $product['description'];
$adminEmail = extra_store_admin_email();
$mailerConfig = extra_store_mailer_config();
$orderNumber = extra_store_generate_order_number($pdo);
$orderColumns = array_fill_keys(extra_store_table_columns($pdo, 'orders'), true);

try {
    $pdo->beginTransaction();

    $orderData = [
        'order_number' => $orderNumber,
        'product_id' => $productId,
        'product_name' => $productName,
        'product_image' => $productImage !== '' ? $productImage : ($product['images'][0] ?? ''),
        'product_description' => $productDescription !== '' ? $productDescription : ($product['description'] ?? ''),
        'product_category' => $productCategory,
        'qty' => $qty,
        'unit_price' => $productPrice,
        'total_price' => $productTotal,
        'customer_name' => $customerName !== '' ? $customerName : trim($firstName . ' ' . $lastName),
        'first_name' => $firstName,
        'last_name' => $lastName,
        'email' => $email,
        'phone' => $phone,
        'address' => $address,
        'city' => '',
        'state' => $state,
        'order_note' => $orderNote,
        'receipt_original_name' => '',
        'receipt_path' => '',
        'status' => 'pending'
    ];

    $insertColumns = [];
    $insertPlaceholders = [];
    $insertValues = [];
    foreach ($orderData as $column => $value) {
        if (!isset($orderColumns[$column])) {
            continue;
        }

        $insertColumns[] = $column;
        $insertPlaceholders[] = ':' . $column;
        $insertValues[$column] = $value;
    }

    if (!$insertColumns) {
        fail('We could not save your order because the orders table is not available.', 500, $wantsJson);
    }

    $insert = $pdo->prepare(
        'INSERT INTO orders (' . implode(', ', $insertColumns) . ') VALUES (' . implode(', ', $insertPlaceholders) . ')'
    );

    $insert->execute($insertValues);

    $pdo->commit();
} catch (\Throwable $error) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $databaseError = $error->getMessage();
    error_log('Extra Store order save failed: ' . $databaseError);
    fail('We could not save your order in the database. Please try again. ' . $databaseError, 500, $wantsJson);
}

$recipientEmail = $adminEmail;
$mailerAvailable = class_exists(\PHPMailer\PHPMailer\PHPMailer::class);

$emailSent = false;
$emailErrorMessage = '';

if ($mailerAvailable) {
    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        $mail->CharSet = 'UTF-8';
        if (($mailerConfig['transport'] ?? 'mail') === 'smtp' && !empty($mailerConfig['host'])) {
            $mail->isSMTP();
            $mail->Host = (string) $mailerConfig['host'];
            $mail->Port = (int) $mailerConfig['port'];
            $mail->SMTPAuth = (string) ($mailerConfig['username'] ?? '') !== '';
            $mail->Username = (string) ($mailerConfig['username'] ?? '');
            $mail->Password = (string) ($mailerConfig['password'] ?? '');
            $encryption = (string) ($mailerConfig['encryption'] ?? '');
            if ($encryption !== '') {
                $mail->SMTPSecure = $encryption;
            }
        } else {
            $mail->isMail();
        }

        $mail->setFrom((string) $mailerConfig['from_email'], (string) $mailerConfig['from_name']);
        $mail->addAddress($recipientEmail, 'Extra Store Admin');

        $mail->isHTML(true);
        $mail->Subject = "New Order #{$orderNumber} - {$productName}";

        $mail->Body = '
      <html>
        <body style="margin:0;padding:0;background:#f7f2ea;font-family:Arial,sans-serif;color:#2a1f1c;">
          <div style="max-width:680px;margin:0 auto;padding:24px;">
            <div style="background:#ffffff;border:1px solid #e8dcc4;border-radius:20px;padding:24px 28px;">
              <h1 style="margin:0 0 12px;font-size:24px;line-height:1.2;color:#7a1f2b;">New Extra Store Order</h1>
              <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">A customer just placed an order through the checkout form.</p>
              <table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">
                <tr><td style="padding:6px 0;color:#8c7a66;width:180px;">Order number</td><td style="padding:6px 0;font-weight:700;">#' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Product</td><td style="padding:6px 0;font-weight:700;">' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Category</td><td style="padding:6px 0;">' . htmlspecialchars($productCategory, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Image</td><td style="padding:6px 0;">' . htmlspecialchars($productImage !== '' ? $productImage : 'Not provided', ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Description</td><td style="padding:6px 0;">' . htmlspecialchars($productDescription !== '' ? $productDescription : 'No description provided.', ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Quantity</td><td style="padding:6px 0;">' . number_format($qty) . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Unit price</td><td style="padding:6px 0;">₦' . number_format($productPrice) . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Total</td><td style="padding:6px 0;font-weight:700;">₦' . number_format($productTotal) . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Customer name</td><td style="padding:6px 0;">' . htmlspecialchars($customerName !== '' ? $customerName : trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Phone</td><td style="padding:6px 0;">' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Address</td><td style="padding:6px 0;">' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">State</td><td style="padding:6px 0;">' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Note</td><td style="padding:6px 0;">' . htmlspecialchars($orderNote !== '' ? $orderNote : 'No note provided.', ENT_QUOTES, 'UTF-8') . '</td></tr>
              </table>
            </div>
          </div>
        </body>
      </html>';

        $mail->AltBody = implode("\n", [
            'New Extra Store Order',
            '',
            'Order number: #' . $orderNumber,
            'Product: ' . $productName,
            'Category: ' . $productCategory,
            'Image: ' . ($productImage !== '' ? $productImage : 'Not provided'),
            'Description: ' . ($productDescription !== '' ? $productDescription : 'No description provided.'),
            'Quantity: ' . $qty,
            'Unit price: ₦' . number_format($productPrice),
            'Total: ₦' . number_format($productTotal),
            'Customer name: ' . ($customerName !== '' ? $customerName : trim($firstName . ' ' . $lastName)),
            'Phone: ' . $phone,
            'Address: ' . $address,
            'State: ' . $state,
            'Note: ' . ($orderNote !== '' ? $orderNote : 'No note provided.')
        ]);

        $mail->send();
        $emailSent = true;
    } catch (\Throwable $error) {
        $emailErrorMessage = $error->getMessage();
    }
} else {
    $emailErrorMessage = 'PHPMailer is not installed yet. Run composer install to enable automatic email notifications.';
}

$responseMessage = $emailSent
    ? 'Order saved successfully and the email was sent.'
    : 'Order saved successfully, but the email notification could not be sent automatically. We still received your order in the database.';

respond([
    'ok' => true,
    'message' => $responseMessage,
    'orderNumber' => $orderNumber,
    'product' => $productName,
    'emailSent' => $emailSent,
    'emailError' => $emailSent ? null : $emailErrorMessage
], 200, $wantsJson);
