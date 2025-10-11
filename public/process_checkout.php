<?php
session_start();
require __DIR__ . '/../includes/public_db_helper.php';
require __DIR__ . '/../vendor/autoload.php';

// Centralized debug log
$debugFile = __DIR__ . '/../debug_checkout.log';
function debugLog($msg) {
    global $debugFile;
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

// Generate a unique user_id like USR-0001
function generateUserId($pdo) {
    $stmt = $pdo->query("SELECT user_id FROM users ORDER BY id DESC LIMIT 1");
    $last = $stmt->fetchColumn();
    if ($last) {
        $num = intval(str_replace('USR-', '', $last)) + 1;
    } else {
        $num = 1;
    }
    return 'USR-' . str_pad($num, 4, '0', STR_PAD_LEFT);
}

// Generate order number (you may already have this function)
function generateOrderNumber() {
    return 'ORD-' . date('YmdHis') . rand(100, 999);
}

// Sanitize helper
function sanitize($str) {
    return htmlspecialchars(trim($str));
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        header("Location: checkout.php");
        exit;
    }

    $pdo = getPublicPDO();
    debugLog("DB connected");

    // Collect form data
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name  = sanitize($_POST['last_name'] ?? '');
    $email      = sanitize($_POST['email'] ?? '');
    $phone      = sanitize($_POST['phone'] ?? '');
    // $company    = sanitize($_POST['company'] ?? '');
    // $gstin      = sanitize($_POST['gstin'] ?? '');
    $module     = $_POST['module'] ?? 'pharma_retail';
    $plan       = $_POST['plan'] ?? 'silver';
    $period     = $_POST['period'] ?? 'yearly';
    $duration   = intval($_POST['duration'] ?? 1);
    $price      = floatval($_POST['price'] ?? 0);
    $discount   = intval($_POST['discount'] ?? 0);
    $payment_method = $_POST['payment_method'] ?? 'upi';
    $is_free_trial = ($plan === 'free_trial' || $price === 0);

    // Validation
    $errors = [];
    if (!$first_name) $errors[] = "First name required";
    if (!$last_name)  $errors[] = "Last name required";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Invalid email";
    if (!$phone) $errors[] = "Phone required";
    if (!$is_free_trial && !$payment_method) $errors[] = "Payment method required";

    if ($errors) {
        $_SESSION['checkout_errors'] = $errors;
        header("Location: checkout.php?plan=$plan");
        exit;
    }

    // Check if user exists by email
    $stmt = $pdo->prepare("SELECT user_id, id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        // New user
        $user_id = generateUserId($pdo);
        $password = bin2hex(random_bytes(8));
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert into users
        $stmt = $pdo->prepare("
            INSERT INTO users (user_id, username, first_name, last_name, email, phone, password_hash, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$user_id, $first_name, $first_name, $last_name, $email, $phone, $hashed_password]);

        // Insert into login_users
        $stmt = $pdo->prepare("
            INSERT INTO login_users (user_id, username, email, module, password_hash, first_name, last_name, phone, status, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
        ");
        $stmt->execute([$user_id, $first_name, $email, $module, $hashed_password, $first_name, $last_name, $phone]);

        $user_db_id = $pdo->lastInsertId();
        debugLog("New user created: $user_id");
    } else {
        // Existing user
        $user_id = $user['user_id'];
        $user_db_id = $user['id'];
        debugLog("Existing user: $user_id");
    }

    // Calculate expiry
    $expiry_date = $is_free_trial ? date('Y-m-d H:i:s', strtotime('+14 days')) : date('Y-m-d H:i:s', strtotime("+$duration $period"));

    // Insert order
    $order_number = generateOrderNumber();
    $stmt = $pdo->prepare("
        INSERT INTO orders 
            (order_number, user_id, first_name, last_name, email, phone, plan_name, billing_period, duration, amount, discount_percent, payment_method, status, payment_status, expiry_date, module, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $order_number, $user_id, $first_name, $last_name, $email, $phone, 
        $plan, $period, $duration, $price, $discount, $payment_method,
        $is_free_trial ? 'active' : 'pending',
        $is_free_trial ? 'completed' : 'pending',
        $expiry_date, $module
    ]);
    $order_id = $pdo->lastInsertId();
    $_SESSION['order_id'] = $order_id;

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
        debugLog("user_modules inserted: $user_id - $moduleId");
    }

    // Subscription
    $status = $is_free_trial ? 'active' : 'pending';
    $stmt = $pdo->prepare("
        INSERT INTO subscriptions (module_id,user_id, order_number, plan_name, status, start_date, expiry_date, created_at)
        VALUES (?,?, ?, ?, ?, NOW(), ?, NOW())
    ");
    $stmt->execute([$moduleId,$user_id, $order_number, $plan, $status, $expiry_date]);
    debugLog("Subscription inserted for $user_id");

    // Redirect
    if ($is_free_trial) {
        header("Location: success.php?order=$order_number&type=trial");
    } else {
        header("Location: payment_gateway.php?order_id=$order_id&order_number=$order_number");
    }
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['checkout_errors'] = [$e->getMessage()];
    header("Location: checkout.php");
    exit;
}
