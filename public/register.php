<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
use App\Core\Database;

try {
    $pdo = Database::getConnection();

    // Fetch all available modules
    $stmt = $pdo->query("SELECT id, module_name FROM modules ORDER BY module_name ASC");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("❌ Error loading modules: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Registration</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: linear-gradient(135deg, #ae00ffff, #ae00ffff);
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      font-family: "Segoe UI", sans-serif;
    }
    .card {
      border-radius: 12px;
      box-shadow: 0 6px 18px rgba(0, 0, 0, 0.2);
    }
    .card-header {
      background: #ae00ffff;
      color: #fff;
      border-radius: 12px 12px 0 0;
      text-align: center;
      padding: 20px;
    }
    .card-header h4 { margin: 0; font-weight: 600; }
    .form-label { font-weight: 500; color: #333; }
    .btn-primary {
      background: #ae00ffff;
      border: none;
      border-radius: 6px;
      padding: 10px;
      font-size: 16px;
    }
    .btn-primary:hover { background: #ae00ffff; }
    .extra-links p { margin: 5px 0; font-size: 0.9rem; }
    .extra-links a { color: #0c0c0bff; text-decoration: none; }
    .extra-links a:hover { text-decoration: underline; }
  </style>
</head>
<body>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
      <div class="card">
        <div class="card-header">
          <h4>📝 Create Your Account</h4>
        </div>
        <div class="card-body p-4">
          <form method="post" action="register_process.php">

            <!-- Full Name -->
            <div class="mb-3">
              <label for="name" class="form-label">Full Name</label>
              <input type="text" id="name" name="name" class="form-control" required>
            </div>

            <!-- Email -->
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" name="email" class="form-control" required>
            </div>

            <!-- Password -->
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" id="password" name="password" class="form-control" required>
            </div>

            <!-- Module Selection -->
            <div class="mb-3">
              <label for="module" class="form-label">Select Module</label>
              <select id="module" name="module" class="form-control" required>
                <option value="">-- Select Module --</option>
                <?php foreach ($modules as $m): ?>
                  <option value="<?= htmlspecialchars($m['module_name']) ?>">
                    <?= htmlspecialchars($m['module_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <!-- Plan / Tier Selection -->
            <div class="mb-3">
              <label for="plan" class="form-label">Select Plan</label>
              <select id="plan" name="plan" class="form-control" required>
                <option value="">-- Select Plan --</option>
                <option value="bronze">Bronze (1 User)</option>
                <option value="silver">Silver (3 Users)</option>
                <option value="gold">Gold (5 Users)</option>
                <option value="diamond">Diamond (Custom)</option>
              </select>
            </div>

            <!-- Submit Button -->
            <button type="submit" class="btn btn-primary w-100">🚀 Register</button>
          </form>

          <div class="extra-links text-center mt-4">
            <a href="login.php" class="btn btn-outline-primary w-100 mb-2">🔑 Already have an Account? Login</a>
            <a href="../index.php" class="btn btn-secondary w-100">🏠 Back to Home</a>
          </div>

        </div>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
