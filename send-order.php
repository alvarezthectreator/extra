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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    fail('Method not allowed.', 405, $wantsJson);
}

$autoload = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    fail('PHPMailer is not installed yet. Run composer install before sending orders.', 500, $wantsJson);
}

require $autoload;

$products = [
    'umbrella-red' => [
        'name' => 'Classic Red Auto Umbrella',
        'price' => 24000,
        'category' => 'Travel Essential'
    ],
    'umbrella-green' => [
        'name' => 'Green Windproof Umbrella',
        'price' => 24000,
        'category' => 'Travel Essential'
    ],
    'umbrella-blue' => [
        'name' => 'Blue Compact Auto Umbrella',
        'price' => 24000,
        'category' => 'Travel Essential'
    ],
    'umbrella-pink' => [
        'name' => 'Pink Compact Umbrella',
        'price' => 24000,
        'category' => 'Travel Essential'
    ]
];

$productId = trim((string) ($_POST['product'] ?? ''));
$qty = (int) ($_POST['qty'] ?? 1);
$qty = max(1, min(99, $qty));

if (!isset($products[$productId])) {
    fail('Please choose a valid product.', 422, $wantsJson);
}

$product = $products[$productId];
$productName = trim((string) ($_POST['product_name'] ?? $product['name']));
$productPrice = (int) ($_POST['product_price'] ?? $product['price']);
$productTotal = (int) ($_POST['product_total'] ?? ($productPrice * $qty));
$productCategory = trim((string) ($_POST['product_category'] ?? $product['category']));
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$customerName = trim((string) ($_POST['customer_name'] ?? trim($firstName . ' ' . $lastName)));
$email = trim((string) ($_POST['email'] ?? ''));
$address = trim((string) ($_POST['address'] ?? ''));
$city = trim((string) ($_POST['city'] ?? ''));
$state = trim((string) ($_POST['state'] ?? ''));
$orderNote = trim((string) ($_POST['order_note'] ?? ''));
$orderNumber = preg_replace('/\D+/', '', (string) ($_POST['order_number'] ?? ''));
$orderNumber = $orderNumber !== '' ? $orderNumber : (string) random_int(100000, 999999);

if ($firstName === '' || $lastName === '' || $email === '' || $address === '' || $city === '' || $state === '') {
    fail('Please complete all required fields.', 422, $wantsJson);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fail('Please enter a valid email address.', 422, $wantsJson);
}

if (!isset($_FILES['payment_receipt']) || !is_array($_FILES['payment_receipt'])) {
    fail('Please upload your payment receipt.', 422, $wantsJson);
}

$receipt = $_FILES['payment_receipt'];
if (($receipt['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    fail('We could not read the uploaded receipt. Please try again.', 422, $wantsJson);
}

$maxReceiptSize = 8 * 1024 * 1024;
if ((int) ($receipt['size'] ?? 0) > $maxReceiptSize) {
    fail('Receipt file is too large. Please upload a file smaller than 8 MB.', 422, $wantsJson);
}

$receiptName = basename((string) ($receipt['name'] ?? 'receipt'));
$receiptTmp = (string) ($receipt['tmp_name'] ?? '');
$receiptExtension = strtolower(pathinfo($receiptName, PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
if (!in_array($receiptExtension, $allowedExtensions, true)) {
    fail('Receipt must be an image or PDF file.', 422, $wantsJson);
}

if ($receiptTmp === '' || !is_uploaded_file($receiptTmp)) {
    fail('We could not verify the uploaded receipt file.', 422, $wantsJson);
}

$recipientEmail = 'hello@extrastore.com';

$mail = new \PHPMailer\PHPMailer\PHPMailer(true);

try {
    $mail->CharSet = 'UTF-8';
    $mail->isMail();
    $mail->setFrom('hello@extrastore.com', 'Extra Store');
    $mail->addAddress($recipientEmail, 'Extra Store Orders');
    if ($email !== '') {
        $mail->addReplyTo($email, $customerName !== '' ? $customerName : 'Customer');
    }
    $mail->addAttachment($receiptTmp, $receiptName);

    $mail->isHTML(true);
    $mail->Subject = "New Order #{$orderNumber} - {$productName}";

    $mail->Body = '
      <html>
        <body style="margin:0;padding:0;background:#f7f2ea;font-family:Arial,sans-serif;color:#2a1f1c;">
          <div style="max-width:680px;margin:0 auto;padding:24px;">
            <div style="background:#ffffff;border:1px solid #e8dcc4;border-radius:20px;padding:24px 28px;">
              <h1 style="margin:0 0 12px;font-size:24px;line-height:1.2;color:#7a1f2b;">New Extra Store Order</h1>
              <p style="margin:0 0 18px;font-size:14px;line-height:1.6;">A customer just placed an order and uploaded their payment receipt.</p>
              <table style="width:100%;border-collapse:collapse;font-size:14px;line-height:1.6;">
                <tr><td style="padding:6px 0;color:#8c7a66;width:180px;">Order number</td><td style="padding:6px 0;font-weight:700;">#' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Product</td><td style="padding:6px 0;font-weight:700;">' . htmlspecialchars($productName, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Category</td><td style="padding:6px 0;">' . htmlspecialchars($productCategory, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Quantity</td><td style="padding:6px 0;">' . number_format($qty) . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Unit price</td><td style="padding:6px 0;">₦' . number_format($productPrice) . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Total</td><td style="padding:6px 0;font-weight:700;">₦' . number_format($productTotal) . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Customer name</td><td style="padding:6px 0;">' . htmlspecialchars($customerName !== '' ? $customerName : trim($firstName . ' ' . $lastName), ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Email</td><td style="padding:6px 0;">' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Address</td><td style="padding:6px 0;">' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">City</td><td style="padding:6px 0;">' . htmlspecialchars($city, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">State</td><td style="padding:6px 0;">' . htmlspecialchars($state, ENT_QUOTES, 'UTF-8') . '</td></tr>
                <tr><td style="padding:6px 0;color:#8c7a66;">Note</td><td style="padding:6px 0;">' . htmlspecialchars($orderNote !== '' ? $orderNote : 'No note provided.', ENT_QUOTES, 'UTF-8') . '</td></tr>
              </table>
              <p style="margin:18px 0 0;font-size:13px;line-height:1.6;color:#8c7a66;">Receipt attached: ' . htmlspecialchars($receiptName, ENT_QUOTES, 'UTF-8') . '</p>
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
        'Quantity: ' . $qty,
        'Unit price: ₦' . number_format($productPrice),
        'Total: ₦' . number_format($productTotal),
        'Customer name: ' . ($customerName !== '' ? $customerName : trim($firstName . ' ' . $lastName)),
        'Email: ' . $email,
        'Address: ' . $address,
        'City: ' . $city,
        'State: ' . $state,
        'Note: ' . ($orderNote !== '' ? $orderNote : 'No note provided.'),
        'Receipt attached: ' . $receiptName
    ]);

    $mail->send();

    respond([
        'ok' => true,
        'message' => 'Order email sent successfully.',
        'orderNumber' => $orderNumber,
        'product' => $productName
    ], 200, $wantsJson);
} catch (\Throwable $error) {
    fail('PHPMailer could not send the order email: ' . $error->getMessage(), 500, $wantsJson);
}
