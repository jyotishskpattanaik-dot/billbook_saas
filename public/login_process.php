<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;

// 🔑 Encryption key (must match what you used when inserting modules)
$secretKey = "MyStrongSecretKey";

function decryptData($data, $key) {
    $cipher = "AES-256-CBC";
    $decoded = base64_decode($data);
    $iv = substr($decoded, 0, 16);
    $encrypted = substr($decoded, 16);
    return openssl_decrypt($encrypted, $cipher, $key, 0, $iv);
}

// --- 1. Collect login form input ---
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($email) || empty($password)) {
    $_SESSION['error'] = "❌ Email and password are required!";
    header("Location: login.php");
    exit;
}

try {
    // --- 2. Connect to main DB ---
    $pdo = Database::getConnection();

    // --- 3. Check user ---
    $stmt = $pdo->prepare("SELECT * FROM login_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $_SESSION['error'] = "❌ No user found with that email!";
        header("Location: login.php");
        exit;
    }

    if (!password_verify($password, $user['password_hash'])) {
        $_SESSION['error'] = "❌ Incorrect password!";
        header("Location: login.php");
        exit;
    }

    // --- 4. Save user session ---
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    // --- 5. Fetch user modules ---
    $stmt = $pdo->prepare("
        SELECT m.*
        FROM user_modules um
        JOIN modules m ON um.module_id = m.id
        WHERE um.user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$modules) {
        $_SESSION['error'] = "⚠️ No modules assigned to this user!";
        header("Location: login.php");
        exit;
    }

    // --- If multiple modules, let user pick ---
    if (count($modules) > 1) {
        $_SESSION['pending_modules'] = $modules;
        $_SESSION['temp_user_id'] = $user['id'];
        $_SESSION['temp_user_name'] = $user['name'];
        header("Location: select_module.php");
        exit;
    }

    // --- Otherwise continue as before ---
    $selectedModule = $modules[0];
    $_SESSION['user_module'] = $selectedModule['module_name'];
    $_SESSION['module_db'] = [
        'host' => $selectedModule['db_host'] ?? 'localhost',
        'name' => $selectedModule['db_name'],
        'user' => $selectedModule['db_user'],
        'pass' => $selectedModule['db_pass'] // decryptData() if encrypted
    ];

    // --- 6. Check if user has a company ---
    $stmt = $pdo->prepare("SELECT company_id, role FROM users WHERE email = ?");
    $stmt->execute([$user['email']]);
    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRecord || empty($userRecord['company_id'])) {
    // 🚨 Force company setup for users with no company
    $_SESSION['pending_user_id'] = $user['id'];
    header("Location: /billbook.in/public/auth/create_company.php");
    exit;
}


    $_SESSION['company_id'] = $userRecord['company_id'];
    $_SESSION['user_role']  = $userRecord['role'];

    // --- 7. Check accounting year in module DB ---
    try {
        $db = $_SESSION['module_db'];
        $modulePDO = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']}",
            $db['user'],
            $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        $stmt = $modulePDO->prepare("SELECT * FROM accounting_years WHERE company_id = ?");
        $stmt->execute([$_SESSION['company_id']]);
        $years = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!$years) {
            // 🚨 No years found → create one
            header("Location: auth/create_year.php");
            exit;
        } elseif (count($years) > 1) {
            // 🚨 Multiple years → select one
            $_SESSION['available_years'] = $years;
            header("Location: auth/select_year.php");
            exit;
        } else {
            // ✅ One year found → set session
            $_SESSION['financial_year_id'] = $years[0]['id'];
        }

    } catch (PDOException $e) {
        die("Module DB connection failed: " . $e->getMessage());
    }

    // --- 8. Redirect to module dashboard ---
    $module = strtolower($_SESSION['user_module']);
    header("Location: ../$module/dashboard.php");
    exit;

} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
