<?php
session_start();
require __DIR__ . '../vendor/autoload.php';
require __DIR__ . '../includes/init.php';

$pdo = getMainPDO();

// Include Razorpay PHP SDK
require_once __DIR__ . '../vendor/razorpay/razorpay-php/Razorpay.php';
use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

// Razorpay API keys
$keyId = "YOUR_RAZORPAY_KEY_ID";
$keySecret = "YOUR_RAZORPAY_KEY_SECRET";

$api = new Api($keyId, $keySecret);

// Get POST data from Razorpay
$razorpay_payment_id = $_POST['razorpay_payment_id'] ?? '';
$razorpay_order_id = $_POST['razorpay_order_id'] ?? '';
$razorpay_signature = $_POST['razorpay_signature'] ?? '';

if (!$razorpay_payment_id || !$razorpay_order_id || !$razorpay_signature) {
    die("Payment failed. Missing parameters.");
}

// Fetch order from DB
$stmt = $pdo->prepare("SELECT * FROM orders WHERE razorpay_order_id = :rpid");
$stmt->execute([':rpid' => $razorpay_order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Verify signature
$attributes = [
    'razorpay_order_id' => $razorpay_order_id,
    'razorpay_payment_id' => $razorpay_payment_id,
    'razorpay_signature' => $razorpay_signature
];

try {
    $api->utility->verifyPaymentSignature($attributes);

    // Signature valid, update order as paid
    $update = $pdo->prepare("UPDATE orders SET status = 'paid', payment_id = :pid, paid_on = NOW() WHERE id = :id");
    $update->execute([
        ':pid' => $razorpay_payment_id,
        ':id' => $order['id']
    ]);

    // Clear session order_id
    unset($_SESSION['order_id']);

    // Redirect to thank you page
    header("Location: thank_you.php?order_id=" . $order['id']);
    exit;

} catch (SignatureVerificationError $e) {
    // Payment verification failed
    die("Payment verification failed: " . $e->getMessage());
}
