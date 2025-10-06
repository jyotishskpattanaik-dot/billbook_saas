<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php';

// ✅ Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied.");
}

$pdo = getMainPDO();
$companyId = $_SESSION['id'] ?? null;

// 🚨 Safety check
if (!$companyId) {
    die("⚠️ No company assigned to this admin.");
}

// --- Fetch subscribed modules for company ---
$sql = "
    SELECT m.id, m.module_name 
    FROM company_subscription s
    JOIN modules m ON s.id = m.id
    WHERE s.company_name = ? AND s.status = 'active'
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$companyId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

$errors = [];
$success = null;

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $role     = $_POST['role'];
    $status   = $_POST['status'] ?? 'active';
    $assignedModules = $_POST['modules'] ?? [];

    if (!$name || !$email || !$password) {
        $errors[] = "Name, Email, and Password are required.";
    }

    if (empty($errors)) {
        // ✅ Hash password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        try {
            $pdo->beginTransaction();

            // Insert user
            $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$companyId, $name, $email, $hashedPassword, $role, $status]);
            $userId = $pdo->lastInsertId();

            // Assign modules
            if (!empty($assignedModules)) {
                $stmt = $pdo->prepare("INSERT INTO user_modules (user_id, module_id) VALUES (?, ?)");
                foreach ($assignedModules as $mid) {
                    $stmt->execute([$userId, $mid]);
                }
            }

            $pdo->commit();
            $success = "✅ User created successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2><i class="fas fa-user-plus"></i> Add New User</h2>
    <a href="manage_users.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back</a>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= implode("<br>", $errors) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm bg-white">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="user">User</option>
                <option value="manager">Manager</option>
                <option value="admin">Admin</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" selected>Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Assign Modules</label><br>
            <?php foreach ($modules as $m): ?>
                <div class="form-check form-check-inline">
                    <input type="checkbox" class="form-check-input" name="modules[]" value="<?= $m['id'] ?>">
                    <label class="form-check-label"><?= htmlspecialchars($m['module_name']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save User</button>
    </form>
</div>
</body>
</html>
