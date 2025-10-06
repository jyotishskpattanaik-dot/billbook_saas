<?php
// phonepe_initiate.php
// Initiates PhonePe payment

require __DIR__ . '../includes/public_db_helper.php';
require __DIR__ . '../vendor/autoload.php';

header('Content-Type: application/json');

// PhonePe Configuration
$merchant_id = "MERCHANTUAT"; // Replace with your Merchant ID
$salt_key = "099eb0cd-02cf-4e2a-8aca-3e6c6aff0399"; // Replace with your Salt Key
$salt_index = "1"; // Replace with your Salt Index
$api_url = "https://api-preprod.phonepe.com/apis/pg-sandbox"; // Use production URL for live

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    $order_id = $data['order_id'];
    $order_number = $data['order_number'];
    $amount = $data['amount'] * 100; // Convert to paise
    $customer_name = $data['customer_name'];
    $customer_email = $data['customer_email'];
    $customer_phone = $data['customer_phone'];
    
    // Generate unique transaction ID
    $merchant_transaction_id = 'TXN_' . $order_number . '_' . time();
    
    // Callback URLs
    $redirect_url = "https://" . $_SERVER['HTTP_HOST'] . "/phonepe_callback.php";
    $callback_url = "https://" . $_SERVER['HTTP_HOST'] . "/phonepe_webhook.php";
    
    // Prepare payment request payload
    $payload = [
        "merchantId" => $merchant_id,
        "merchantTransactionId" => $merchant_transaction_id,
        "merchantUserId" => "USER_" . $order_id,
        "amount" => $amount,
        "redirectUrl" => $redirect_url,
        "redirectMode" => "POST",
        "callbackUrl" => $callback_url,
        "mobileNumber" => $customer_phone,
        "paymentInstrument" => [
            "type" => "PAY_PAGE"
        ]
    ];
    
    // Encode payload to base64
    $json_payload = json_encode($payload);
    $base64_payload = base64_encode($json_payload);
    
    // Generate X-VERIFY header
    $string_to_hash = $base64_payload . "/pg/v1/pay" . $salt_key;
    $sha256_hash = hash('sha256', $string_to_hash);
    $x_verify = $sha256_hash . "###" . $salt_index;
    
    // Make API call to PhonePe
    $ch = curl_init($api_url . "/pg/v1/pay");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['request' => $base64_payload]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-VERIFY: ' . $x_verify
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('PhonePe API request failed');
    }
    
    $response_data = json_decode($response, true);
    
    if (!$response_data['success']) {
        throw new Exception($response_data['message'] ?? 'Payment initiation failed');
    }
    
    // Get payment URL
    $payment_url = $response_data['data']['instrumentResponse']['redirectInfo']['url'];
    
    // Store transaction details in session
    $_SESSION['phonepe_transaction_id'] = $merchant_transaction_id;
    $_SESSION['phonepe_order_id'] = $order_id;
    
    // Return success with redirect URL
    echo json_encode([
        'success' => true,
        'redirect_url' => $payment_url,
        'transaction_id' => $merchant_transaction_id
    ]);
    
} catch (Exception $e) {
    error_log("PhonePe initiation error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}