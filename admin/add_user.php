<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php';

// ✅ Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied.");
}

$pdo = getMainPDO();
$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) die("⚠️ No company assigned to this admin.");

// --- Company info ---
$stmt = $pdo->prepare("SELECT plan, user_limit FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$userLimit = intval($company['user_limit'] ?? 1);

// --- Count existing users ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
$stmt->execute([$companyId]);
$usersUsed = intval($stmt->fetchColumn());

$errors = [];
$success = null;

// --- Handle form ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($usersUsed >= $userLimit) {
        $errors[] = "User limit reached. Cannot add more users.";
    } else {
        $name  = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $role  = $_POST['role'] ?? 'user';
        $status= $_POST['status'] ?? 'active';

        if (!$name || !$email) $errors[] = "Name and Email are required.";

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO users (company_id, name, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$companyId, $name, $email, $role, $status]);
            $success = "✅ User added successfully!";
            $usersUsed++;
        }
    }
}

// --- Fetch available modules for the company ---
$stmt = $pdo->prepare("SELECT id, module_name FROM modules WHERE id IN (SELECT module_id FROM subscriptions WHERE company_id = ? AND status='active')");
$stmt->execute([$companyId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2>Add New User</h2>
    <a href="control_panel.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back to Control Panel
    </a>
    <p>User Limit: <?= $usersUsed ?>/<?= $userLimit ?></p>

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
                <option value="active">Active</option>
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
        

        <button type="submit" class="btn btn-success">Add User</button>
    </form>
</div>
</body>
</html>
