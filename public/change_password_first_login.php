<?php
// ==============================================
// FORCED PASSWORD CHANGE ON FIRST LOGIN
// ==============================================
// change_password_first_login.php

session_start();
require_once 'config.php';

if (!isset($_SESSION['force_password_change']) || !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $errors = [];
    
    // Validate password
    if (strlen($newPassword) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    if (empty($errors)) {
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        
        // Update password and mark first_login as complete
        $stmt = $conn->prepare("UPDATE users SET password = ?, first_login = 0 WHERE id = ?");
        $stmt->bind_param("si", $hashedPassword, $_SESSION['user_id']);
        
        if ($stmt->execute()) {
            // Delete temp password record
            $deleteTemp = $conn->prepare("DELETE FROM temp_passwords WHERE user_id = ?");
            $deleteTemp->bind_param("i", $_SESSION['user_id']);
            $deleteTemp->execute();
            
            unset($_SESSION['force_password_change']);
            $_SESSION['password_changed'] = true;
            header("Location: dashboard.php");
            exit();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Change Your Password</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        input[type="password"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            background: #007bff;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
        }
        .alert {
            background: #f8d7da;
            color: #721c24;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .info-box {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="info-box">
        <strong>⚠️ First Time Login</strong>
        <p>For security reasons, you must change your temporary password before continuing.</p>
    </div>
    
    <?php if (!empty($errors)): ?>
        <div class="alert">
            <?php foreach ($errors as $error): ?>
                <p><?php echo $error; ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <h2>Set Your New Password</h2>
    
    <form method="POST" action="">
        <div class="form-group">
            <label for="new_password">New Password:</label>
            <input type="password" id="new_password" name="new_password" required>
            <div class="requirements">
                Minimum 8 characters
            </div>
        </div>
        
        <div class="form-group">
            <label for="confirm_password">Confirm New Password:</label>
            <input type="password" id="confirm_password" name="confirm_password" required>
        </div>
        
        <button type="submit" class="btn">Change Password & Continue</button>
    </form>
    
    <script>
    // Prevent back button after logout
    window.history.pushState(null, null, window.location.href);
    window.onpopstate = function () {
        window.history.go(1);
    };
    </script>
</body>
</html>

