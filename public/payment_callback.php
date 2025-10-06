<?php
// payment_callback.php
// This file handles payment gateway callbacks and verifies payments

require __DIR__ . '../includes/public_db_helper.php';
require __DIR__ . '../vendor/autoload.php';

$pdo = getPublicPDO();

// Set JSON response header
header('Content-Type: application/json');

// Gateway configuration
$razorpay_key_secret = "XXXXXXXXXXXXXXXXXXXXXXXX"; // Replace with your actual secret

try {
    // Get POST data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        throw new Exception('Invalid request data');
    }
    
    // Extract payment details
    $razorpay_payment_id = $data['razorpay_payment_id'] ?? null;
    $razorpay_order_id = $data['razorpay_order_id'] ?? null;
    $razorpay_signature = $data['razorpay_signature'] ?? null;
    $order_id = $data['order_id'] ?? null;
    $order_number = $data['order_number'] ?? null;
    $amount = $data['amount'] ?? 0;
    
    if (!$razorpay_payment_id || !$order_id) {
        throw new Exception('Missing required payment parameters');
    }
    
    // Verify Razorpay signature
    $generated_signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $razorpay_key_secret);
    
    if ($generated_signature !== $razorpay_signature) {
        // Signature verification failed
        logPaymentAttempt($pdo, $order_id, $razorpay_payment_id, 'failed', 'Signature verification failed');
        
        echo json_encode([
            'success' => false,
            'message' => 'Payment verification failed'
        ]);
        exit;
    }
    
    // Signature verified - Payment is genuine
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Update order status
    $stmt = $pdo->prepare("
        UPDATE orders 
        SET payment_status = 'completed',
            status = 'active',
            transaction_id = :transaction_id,
            updated_at = NOW()
        WHERE id = :order_id
    ");
    $stmt->execute([
        ':transaction_id' => $razorpay_payment_id,
        ':order_id' => $order_id
    ]);
    
    // 2. Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    // 3. Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email");
    $stmt->execute([':email' => $order['email']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $is_new_user = false;
    $user_password = null;
    
    if (!$user) {
        // Create new user account
        $password = bin2hex(random_bytes(8)); // Generate random password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert into users table
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
        
        // In payment_callback.php, find this section and update:

$module_name = 'mod_' . strtolower(substr($order['first_name'], 0, 3)) . '_' . uniqid();

// Insert into login_users table
$stmt = $pdo->prepare("
    INSERT INTO login_users (username, email, module, password, first_name, last_name, phone, status, created_at)
    VALUES (:email, :email, :module, :password, :first_name, :last_name, :phone, 'active', NOW())
");
$stmt->execute([
    ':email' => $order['email'],
    ':module' => $module_name,  // ✅ ADD THIS
    ':password' => $hashed_password,
    ':first_name' => $order['first_name'],
    ':last_name' => $order['last_name'],
    ':phone' => $order['phone']
]);
        
        $is_new_user = true;
        $user_password = $password;
    } else {
        $user_id = $user['id'];
    }
    
    // 4. Calculate subscription expiry
    $expiry_date = null;
    if ($order['billing_period'] === 'monthly') {
        $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $order['duration'] . ' months'));
    } elseif ($order['billing_period'] === 'yearly') {
        $expiry_date = date('Y-m-d H:i:s', strtotime('+' . $order['duration'] . ' years'));
    }
    
    // 5. Check if subscription already exists (in case of duplicate callback)
    $stmt = $pdo->prepare("SELECT id FROM subscriptions WHERE order_id = :order_id");
    $stmt->execute([':order_id' => $order_id]);
    $existing_sub = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existing_sub) {
        // Create new subscription
        $stmt = $pdo->prepare("
            INSERT INTO subscriptions (user_id, order_id, plan_name, status, start_date, expiry_date, created_at)
            VALUES (:user_id, :order_id, :plan_name, 'active', NOW(), :expiry_date, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':order_id' => $order_id,
            ':plan_name' => $order['plan_name'],
            ':expiry_date' => $expiry_date
        ]);
        $subscription_id = $pdo->lastInsertId();
    } else {
        $subscription_id = $existing_sub['id'];
    }
    
    // 6. Log payment
    logPaymentAttempt($pdo, $order_id, $razorpay_payment_id, 'success', json_encode($data));
    
    // 7. Log activity
    logActivity($pdo, $user_id, $order_id, 'payment_completed', 'Payment of ₹' . $amount . ' completed successfully');
    
    // Commit transaction
    $pdo->commit();
    
    // 8. Send emails
    // Calculate duration display
    $duration_text = $order['duration'] . ' ' . ucfirst($order['billing_period']);
    if ($order['duration'] > 1) {
        $duration_text .= 's';
    }
    
    // Send welcome email if new user
    if ($is_new_user && $user_password) {
        sendWelcomeEmail(
            $order['email'], 
            $order['first_name'], 
            $user_password, 
            $order['order_number'], 
            $order['plan_name']
        );
    }
    
    // Send payment confirmation
    sendPaymentConfirmationEmail(
        $order['email'],
        $order['first_name'],
        $order['order_number'],
        $amount,
        $order['plan_name'],
        $duration_text
    );
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Payment verified successfully',
        'subscription_id' => $subscription_id
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Payment callback error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Helper function to log payment attempts
function logPaymentAttempt($pdo, $order_id, $transaction_id, $status, $response) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO payment_logs (order_id, transaction_id, amount, status, gateway_response, ip_address, created_at)
            SELECT :order_id, :transaction_id, amount, :status, :response, :ip, NOW()
            FROM orders WHERE id = :order_id2
        ");
        $stmt->execute([
            ':order_id' => $order_id,
            ':order_id2' => $order_id,
            ':transaction_id' => $transaction_id,
            ':status' => $status,
            ':response' => $response,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log payment: " . $e->getMessage());
    }
}

// Helper function to log activity
function logActivity($pdo, $user_id, $order_id, $action, $description) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, order_id, action, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :order_id, :action, :description, :ip, :user_agent, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':order_id' => $order_id,
            ':action' => $action,
            ':description' => $description,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log activity: " . $e->getMessage());
    }
}

// Helper function to send payment confirmation email
function sendPaymentConfirmationEmail($order, $user_id) {
    // Implement your email sending logic here
    // You can use PHPMailer, SendGrid, AWS SES, etc.
    
    $to = $order['email'];
    $subject = "Payment Confirmed - Order #" . $order['order_number'];
    $message = "
        <h2>Payment Successful!</h2>
        <p>Dear {$order['first_name']},</p>
        <p>Your payment of ₹{$order['amount']} has been received successfully.</p>
        <p><strong>Order Number:</strong> {$order['order_number']}</p>
        <p><strong>Plan:</strong> " . ucfirst($order['plan_name']) . " Plan</p>
        <p>You can now access your account and start using all the features.</p>
        <p>Thank you for your purchase!</p>
    ";
    
    // Send email (implement based on your email service)
    // mail($to, $subject, $message, $headers);
}