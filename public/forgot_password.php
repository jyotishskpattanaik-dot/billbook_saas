<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init_main.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);

    $pdo = getMainPDO();

    // ✅ Check user exists
    $stmt = $pdo->prepare("SELECT id FROM login_users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // Generate token
        $token = bin2hex(random_bytes(16));
        $expires = date("Y-m-d H:i:s", strtotime("+1 hour"));

        // Save in DB
        $update = $pdo->prepare("UPDATE login_users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
        $update->execute([
            ':token' => $token,
            ':expires' => $expires,
            ':id' => $user['id']
        ]);

        // For now, just display reset link
        $resetLink = "http://localhost/billbook.in/public/reset_password.php?token=$token";
        $message = "Password reset link (valid for 1 hour): <a href='$resetLink'>$resetLink</a>";
    } else {
        $message = "❌ Email not found.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    body, html {
      height: 100%;
      margin: 0;
      font-family: "Segoe UI", sans-serif;
    }
    .split {
      display: flex;
      height: 100vh;
    }
    .split .image-side {
      flex: 1;
      background: url('../assets/login-bg.jpg') no-repeat center center;
      background-size: cover;
    }
    .split .form-side {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
      padding: 20px;
    }
    .card {
      width: 100%;
      max-width: 420px;
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0,0,0,0.2);
    }
    .card-header {
      background: #007bff;
      color: #fff;
      border-radius: 12px 12px 0 0;
      text-align: center;
      padding: 20px;
      font-size: 1.2rem;
      font-weight: 600;
    }
    .btn-primary {
      background: #007bff;
      border: none;
    }
    .btn-primary:hover {
      background: #0056b3;
    }
  </style>
</head>
<body>
<div class="split">
  <!-- Left Side: Image -->
  <div class="image-side"></div>

  <!-- Right Side: Form -->
  <div class="form-side">
    <div class="card">
      <div class="card-header">🔑 Forgot Password</div>
      <div class="card-body p-4">
        <?php if (!empty($message)): ?>
          <div class="alert alert-info"><?= $message ?></div>
        <?php endif; ?>

        <form method="post">
          <!-- Email -->
          <div class="mb-3">
            <label for="email" class="form-label">📧 Email</label>
            <input type="email" id="email" name="email" class="form-control" required>
          </div>

          <!-- Submit -->
          <button type="submit" class="btn btn-primary w-100">📩 Send Reset Link</button>

          <div class="text-center mt-3">
            <a href="login.php" class="btn btn-outline-primary w-100 mb-2">🔑 Back to Login</a>
            <a href="../index.php" class="btn btn-secondary w-100">🏠 Back to Home</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
