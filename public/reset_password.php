    <?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init_main.php';

$pdo = getMainPDO();
$message = '';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // Find user by token
    $stmt = $pdo->prepare("SELECT id, reset_expires FROM login_users WHERE reset_token = :token");
    $stmt->execute([':token' => $token]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        $message = "❌ Invalid or expired reset link.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $message = "⏳ Reset link has expired.";
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password     = $_POST['password'];
        $confirm_pass = $_POST['confirm_password'];

        if ($password !== $confirm_pass) {
            $message = "❌ Passwords do not match.";
        } else {
            $newPassword = password_hash($password, PASSWORD_DEFAULT);

            // Update password and clear token
            $update = $pdo->prepare("
                UPDATE login_users 
                SET password = :password, reset_token = NULL, reset_expires = NULL 
                WHERE id = :id
            ");
            $update->execute([
                ':password' => $newPassword,
                ':id' => $user['id']
            ]);

            header("Location: login.php?reset_success=1");
            exit;
        }
    }
} else {
    $message = "❌ No token provided.";
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        .match-status {
            font-size: 1.2rem;
            margin-left: 8px;
        }
    </style>
</head>
<body class="p-3">
<div class="container">
    <h3>🔑 Reset Password</h3>
    <?php if (!empty($message)): ?>
        <div class="alert alert-danger"><?= $message ?></div>
    <?php endif; ?>

    <?php if (isset($user) && empty($message)): ?>
        <form method="post">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <div class="input-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                    <span id="matchStatus" class="input-group-text match-status"></span>
                </div>
            </div>
            <button type="submit" class="btn btn-success">Update Password</button>
            <a href="login.php" class="btn btn-secondary">Cancel</a>
        </form>
    <?php endif; ?>
</div>

<script>
const password = document.getElementById('password');
const confirmPassword = document.getElementById('confirm_password');
const matchStatus = document.getElementById('matchStatus');

function checkMatch() {
    if (confirmPassword.value.length === 0) {
        matchStatus.textContent = '';
        return;
    }

    if (password.value === confirmPassword.value) {
        matchStatus.textContent = '✅';
        matchStatus.style.color = 'green';
    } else {
        matchStatus.textContent = '❌';
        matchStatus.style.color = 'red';
    }
}

password.addEventListener('input', checkMatch);
confirmPassword.addEventListener('input', checkMatch);
</script>
</body>
</html>
