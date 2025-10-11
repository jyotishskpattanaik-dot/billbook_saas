<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;

// Utility redirect function
function redirect($url) {
    header("Location: $url");
    exit;
}

// --- 1️⃣ Collect input ---
$email    = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if (!$email || !$password) {
    $_SESSION['error'] = "❌ Email and password are required!";
    redirect("login.php");
}

try {
    // --- 2️⃣ Connect to main DB ---
    $pdo = Database::getConnection();

    // --- 3️⃣ Fetch user by user_id/email ---
    $stmt = $pdo->prepare("SELECT * FROM login_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        $_SESSION['error'] = "❌ Invalid email or password!";
        redirect("login.php");
    }

    // --- 4️⃣ Save session using user_id ---
    $_SESSION['user_id']    = $user['user_id'];      // USR-0001 style
    $_SESSION['user_name']  = $user['username'];
    $_SESSION['user_email'] = $user['email'];

    // --- 5️⃣ Fetch assigned modules ---
    $stmt = $pdo->prepare("
        SELECT m.*
        FROM user_modules um
        JOIN modules m ON um.module_id = m.id
        WHERE um.user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$modules) {
        $_SESSION['error'] = "⚠️ No modules assigned to this user!";
        redirect("login.php");
    }

    // --- 6️⃣ Multiple modules selection ---
    if (count($modules) > 1) {
        $_SESSION['pending_modules'] = $modules;
        redirect("select_module.php");
    }

    $selectedModule = $modules[0];
    $_SESSION['user_module'] = $selectedModule['module_name'];
    $_SESSION['module_db'] = [
        'host' => $selectedModule['db_host'] ?? 'localhost',
        'name' => $selectedModule['db_name'],
        'user' => $selectedModule['db_user'],
        'pass' => $selectedModule['db_pass'] // decrypt if encrypted
    ];

    // --- 7️⃣ Check if user has a company ---
    $stmt = $pdo->prepare("SELECT company_id, role FROM users WHERE user_id = ?");
    $stmt->execute([$user['user_id']]);
    $userRecord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userRecord || empty($userRecord['company_id'])) {
        $_SESSION['pending_user_id'] = $user['user_id'];
        redirect("auth/create_company.php");
    }

    $_SESSION['company_id'] = $userRecord['company_id'];
    $_SESSION['user_role']  = $userRecord['role'];

    // --- 8️⃣ Connect to module DB and check accounting years ---
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
        redirect("auth/create_year.php");
    } elseif (count($years) > 1) {
        $_SESSION['available_years'] = $years;
        redirect("auth/select_year.php");
    } else {
        $_SESSION['financial_year_id'] = $years[0]['id'];
    }

    // --- 9️⃣ Redirect to module dashboard ---
    $module = strtolower($_SESSION['user_module']);
    redirect("../$module/dashboard.php");

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
