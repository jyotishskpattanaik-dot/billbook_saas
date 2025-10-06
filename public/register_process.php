<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo = Database::getConnection();

        $name     = trim($_POST['name']);
        $email    = trim($_POST['email']);
        $password = $_POST['password'];
        $module   = $_POST['module'];

        // 1️⃣ Check if user already exists
        $stmt = $pdo->prepare("SELECT id, password_hash FROM login_users WHERE email = ?");
        $stmt->execute([$email]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$userRow) {
            // New user → insert into login_users
            $passwordHash = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("INSERT INTO login_users (name, email, password_hash, module, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$name, $email, $passwordHash, $module]);
            $loginUserId = $pdo->lastInsertId();

            // Insert into users
            $stmt = $pdo->prepare("INSERT INTO users (name, email, status, created_at) VALUES (?, ?, 'active', NOW())");
            $stmt->execute([$name, $email]);
            $userId = $pdo->lastInsertId();
        } else {
            // Existing user → reuse user id
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                // Safety: create missing users record
                $stmt = $pdo->prepare("INSERT INTO users (name, email, status, created_at) VALUES (?, ?, 'active', NOW())");
                $stmt->execute([$name, $email]);
                $userId = $pdo->lastInsertId();
            } else {
                $userId = $user['id'];
            }
        }

        // 2️⃣ Find module id
        $stmt = $pdo->prepare("SELECT id FROM modules WHERE module_name = ?");
        $stmt->execute([$module]);
        $moduleRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$moduleRow) {
            die("❌ Error: Module not found!");
        }
        $moduleId = $moduleRow['id'];

        // 3️⃣ Check if mapping already exists
        $stmt = $pdo->prepare("SELECT id FROM user_modules WHERE user_id = ? AND module_id = ?");
        $stmt->execute([$userId, $moduleId]);
        if ($stmt->fetch()) {
            echo "<script>
                    alert('⚠️ You are already registered for this module!');
                    window.location.href = 'login.php';
                  </script>";
            exit;
        }

        // 4️⃣ Insert into user_modules
        $stmt = $pdo->prepare("INSERT INTO user_modules (user_id, module_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$userId, $moduleId]);

        echo "<script>
                alert('✅ Registration successful! Please log in.');
                window.location.href = 'login.php?registered=1';
              </script>";

    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
    }
} else {
    echo "⚠️ Invalid request!";
}
