<?php
session_start();
require __DIR__ . '/../includes/public_db_helper.php';
require __DIR__ . '/../vendor/autoload.php';

// Centralized log file for debugging
$debugFile = __DIR__ . '/../debug_checkout.log';
function debugLog($msg) {
    global $debugFile;
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

debugLog("=== Checkout started ===");

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    debugLog("Invalid request method.");
    header("Location: checkout.php");
    exit;
}

try {
    $pdo = getPublicPDO();
    debugLog("DB connection established");

    // Collect form data
    $first_name = sanitizeInput($_POST['first_name'] ?? '');
    $last_name  = sanitizeInput($_POST['last_name'] ?? '');
    $email      = sanitizeInput($_POST['email'] ?? '');
    $phone      = sanitizeInput($_POST['phone'] ?? '');
    $company    = sanitizeInput($_POST['company'] ?? '');
    $gstin      = sanitizeInput($_POST['gstin'] ?? '');
    $module     = $_POST['module'] ?? 'pharma_retail';
    $plan       = $_POST['plan'] ?? 'silver';
    $period     = $_POST['period'] ?? 'yearly';
    $duration   = intval($_POST['duration'] ?? 1);
    $price      = floatval($_POST['price'] ?? 0);
    $discount   = intval($_POST['discount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'upi';
    $is_free_trial = ($plan === 'free_trial' || $price === 0);

    debugLog("Collected data: plan=$plan, module=$module, price=$price");

    // Validate
    $errors = [];
    if (empty($first_name)) $errors[] = "First name is required";
    if (empty($last_name)) $errors[] = "Last name is required";
    if (!isValidEmail($email)) $errors[] = "Invalid email";
    if (!isValidPhone($phone)) $errors[] = "Invalid phone";
    if (!isValidGSTIN($gstin)) $errors[] = "Invalid GSTIN";
    if (!$is_free_trial && empty($payment_method)) $errors[] = "Payment method required";

    if (!empty($errors)) {
        debugLog("Validation failed: " . implode(', ', $errors));
        $_SESSION['checkout_errors'] = $errors;
        header("Location: checkout.php?plan=$plan");
        exit;
    }

    $expiry_date = $is_free_trial ? date('Y-m-d H:i:s', strtotime('+14 days')) : calculateExpiryDate($period, $duration);
    $order_number = generateOrderNumber();

    // Insert into orders
    $stmt = $pdo->prepare("
        INSERT INTO orders (
            order_number, first_name, last_name, email, phone, company, gstin, 
            plan_name, billing_period, duration, amount, discount_percent, payment_method, 
            status, payment_status, expiry_date, module, created_at
        ) VALUES (
            :order_number, :first_name, :last_name, :email, :phone, :company, :gstin,
            :plan_name, :billing_period, :duration, :amount, :discount_percent, :payment_method,
            :status, :payment_status, :expiry_date, :module, NOW()
        )
    ");
    $stmt->execute([
        ':order_number' => $order_number,
        ':first_name' => $first_name,
        ':last_name' => $last_name,
        ':email' => $email,
        ':phone' => $phone,
        ':company' => $company,
        ':gstin' => $gstin,
        ':plan_name' => $plan,
        ':billing_period' => $period,
        ':duration' => $duration,
        ':amount' => $price,
        ':discount_percent' => $discount,
        ':payment_method' => $payment_method,
        ':status' => $is_free_trial ? 'active' : 'pending',
        ':payment_status' => $is_free_trial ? 'completed' : 'pending',
        ':expiry_date' => $expiry_date,
        ':module' => $module
    ]);
    $order_id = $pdo->lastInsertId();
    debugLog("Order inserted with ID: $order_id");

    $_SESSION['order_id'] = $order_id;

    // Start transaction for user+module+subscription
    $pdo->beginTransaction();

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        $password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (username, first_name, last_name, email, phone, password_hash, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$first_name, $first_name, $last_name, $email, $phone, $hashed_password]);
        $user_id = $pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO login_users (username, email, module, password_hash, first_name, last_name, phone, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$first_name, $email, $module, $hashed_password, $first_name, $last_name, $phone]);

        debugLog("New user created with ID: $user_id");
    } else {
        $user_id = $user['id'];
        debugLog("Existing user found ID: $user_id");
    }

    // Assign module
    $stmt = $pdo->prepare("SELECT id FROM modules WHERE module_name = ?");
    $stmt->execute([$module]);
    $moduleRow = $stmt->fetch();
    if (!$moduleRow) throw new Exception("Module not found: $module");
    $moduleId = $moduleRow['id'];

    $stmt = $pdo->prepare("SELECT id FROM user_modules WHERE user_id = ? AND module_id = ?");
    $stmt->execute([$user_id, $moduleId]);
    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO user_modules (user_id, module_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$user_id, $moduleId]);
        debugLog("user_modules inserted: user=$user_id, module=$moduleId");
    } else {
        debugLog("user_modules already exists for user=$user_id, module=$moduleId");
    }

    // Subscription
    $status = $is_free_trial ? 'active' : 'pending';
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (user_id, order_id, plan_name, status, start_date, expiry_date, created_at)
        VALUES (?, ?, ?, ?, NOW(), ?, NOW())
    ");
    $stmt->execute([$user_id, $order_id, $plan, $status, $expiry_date]);
    debugLog("Subscription inserted for user=$user_id");

    $pdo->commit();

    // Send mail
    $emailResult = sendWelcomeEmail($email, $first_name, $password ?? 'existing-user', $order_number, $plan);
    debugLog("Mail send result: " . ($emailResult ? 'OK' : 'FAIL'));

    // Redirect
    if ($is_free_trial) {
        debugLog("Redirecting to success page (free trial)");
        header("Location: success.php?order=$order_number&type=trial");
    } else {
        debugLog("Redirecting to payment gateway");
        header("Location: payment_gateway.php?order_id=$order_id&order_number=$order_number");
    }
    exit;

} catch (Exception $e) {
    debugLog("EXCEPTION: " . $e->getMessage());
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['checkout_errors'] = [$e->getMessage()];
    header("Location: checkout.php?plan=" . ($_POST['plan'] ?? 'silver'));
    exit;
}
