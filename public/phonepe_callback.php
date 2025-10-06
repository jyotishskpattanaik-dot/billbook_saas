<?php
// phonepe_callback.php
// Handles PhonePe redirect after payment

require __DIR__ . '../includes/public_db_helper.php';
require __DIR__ . '../vendor/autoload.php';

$pdo = getPublicPDO();

// PhonePe Configuration
$merchant_id = "MERCHANTUAT";
$salt_key = "099eb0cd-02cf-4e2a-8aca-3e6c6aff0399";
$salt_index = "1";
$api_url = "https://api-preprod.phonepe.com/apis/pg-sandbox";

try {
    // Get transaction ID from session or POST
    $merchant_transaction_id = $_SESSION['phonepe_transaction_id'] ?? null;
    $order_id = $_SESSION['phonepe_order_id'] ?? null;
    
    if (!$merchant_transaction_id) {
        throw new Exception('Transaction ID not found');
    }
    
    // Check payment status
    $status_endpoint = "/pg/v1/status/{$merchant_id}/{$merchant_transaction_id}";
    
    // Generate X-VERIFY for status check
    $string_to_hash = $status_endpoint . $salt_key;
    $sha256_hash = hash('sha256', $string_to_hash);
    $x_verify = $sha256_hash . "###" . $salt_index;
    
    // Make status check API call
    $ch = curl_init($api_url . $status_endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'X-VERIFY: ' . $x_verify,
        'X-MERCHANT-ID: ' . $merchant_id
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        throw new Exception('Failed to check payment status');
    }
    
    $response_data = json_decode($response, true);
    
    // Check if payment was successful
    if ($response_data['success'] && $response_data['code'] === 'PAYMENT_SUCCESS') {
        
        // Payment successful - Update database
        $pdo->beginTransaction();
        
        // Get transaction ID
        $transaction_id = $response_data['data']['transactionId'];
        
        // Update order
        $stmt = $pdo->prepare("
            UPDATE orders 
            SET payment_status = 'completed',
                status = 'active',
                transaction_id = :transaction_id,
                updated_at = NOW()
            WHERE id = :order_id
        ");
        $stmt->execute([
            ':transaction_id' => $transaction_id,
            ':order_id' => $order_id
        ]);
        
        // Get order details
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Create or get user
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
        $stmt->execute([':email' => $order['email']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $password = bin2hex(random_bytes(8));
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("
                INSERT INTO users (first_name, last_name, email, phone, password, status, created_at)
                VALUES (:first_name, :last_name, :email, :phone, :password, 'active', NOW())
            ");
            $stmt->execute([
                ':first_name' => $order['first_name'],
                ':last_name' => $order['last_name'],
                ':email' => $order['email'],
                ':phone' => $order['phone'],
                ':password' => $hashed_password
            ]);
            $user_id = $pdo->lastInsertId();
        } else {
            $user_id = $user['id'];
        }
        
        // Calculate expiry
        $expiry_date = null;
        if ($order['billing_period'] === 'monthly') {
            $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $order['duration'] . ' months'));
        } elseif ($order['billing_period'] === 'yearly') {
            $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $order['duration'] . ' years'));
        }
        
        // Create subscription
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions (user_id, order_id, plan_name, status, start_date, expiry_date, created_at)
            VALUES (:user_id, :order_id, :plan_name, 'active', NOW(), :expiry_date, NOW())
            ON DUPLICATE KEY UPDATE status = 'active'
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':order_id' => $order_id,
            ':plan_name' => $order['plan_name'],
            ':expiry_date' => $expiry_date
        ]);
        
        // Log payment
        $stmt = $pdo->prepare("
            INSERT INTO payment_logs (order_id, transaction_id, payment_method, amount, status, gateway_response, ip_address, created_at)
            VALUES (:order_id, :transaction_id, 'phonepe', :amount, 'success', :response, :ip, NOW())
        ");
        $stmt->execute([
            ':order_id' => $order_id,
            ':transaction_id' => $transaction_id,
            ':amount' => $order['amount'],
            ':response' => json_encode($response_data),
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
        $pdo->commit();
        
        // Clear session
        unset($_SESSION['phonepe_transaction_id']);
        unset($_SESSION['phonepe_order_id']);
        
        // Redirect to success page
        header("Location: success.php?order=" . $order['order_number'] . "&type=paid");
        exit;
        
    } else {
        // Payment failed or pending
        throw new Exception($response_data['message'] ?? 'Payment was not successful');
    }
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("PhonePe callback error: " . $e->getMessage());
    
    // Redirect to failure page
    header("Location: payment_failure.php?error=" . urlencode($e->getMessage()));
    exit;
}