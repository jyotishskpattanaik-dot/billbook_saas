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

if (!$companyId) {
    die("⚠️ No company assigned to this admin.");
}

$userId = $_GET['id'] ?? null;
if (!$userId) {
    die("⚠️ No user selected.");
}

// --- Get company plan & expiry (for control) ---
$stmt = $pdo->prepare("SELECT plan, active_till FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['plan' => 'free_trial', 'active_till' => null];
$isExpired = ($company['active_till'] && strtotime($company['active_till']) < strtotime(date('Y-m-d')));

// --- Detect the correct name column ---
$columns = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
$nameColumn = in_array('name', $columns) ? 'name' : (in_array('username', $columns) ? 'username' : 'full_name');

// --- Fetch user details ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$userId, $companyId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("❌ User not found or not part of your company.");
}

// --- Fetch active modules for this company ---
$stmt = $pdo->prepare("
    SELECT m.id, m.module_name 
    FROM subscriptions s
    JOIN modules m ON s.module_id = m.id
    WHERE s.company_id = ? AND s.status = 'active'
");
$stmt->execute([$companyId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch modules already assigned to this user ---
$stmt = $pdo->prepare("SELECT module_id FROM user_modules WHERE user_id = ?");
$stmt->execute([$userId]);
$userModules = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'module_id');

$errors = [];
$success = null;

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($isExpired) {
        $errors[] = "Your plan has expired. You cannot update users.";
    } else {
        $name   = trim($_POST['name'] ?? '');
        $role   = $_POST['role'] ?? 'user';
        $status = $_POST['status'] ?? 'active';
        $assignedModules = $_POST['modules'] ?? [];

        if (!$name) {
            $errors[] = "Name is required.";
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // ✅ Update user details
                $stmt = $pdo->prepare("UPDATE users SET `$nameColumn` = ?, role = ?, status = ? WHERE id = ? AND company_id = ?");
                $stmt->execute([$name, $role, $status, $userId, $companyId]);

                // ✅ Clear and reassign modules
                $stmt = $pdo->prepare("DELETE FROM user_modules WHERE user_id = ?");
                $stmt->execute([$userId]);

                if (!empty($assignedModules)) {
                    $stmt = $pdo->prepare("INSERT INTO user_modules (user_id, module_id) VALUES (?, ?)");
                    foreach ($assignedModules as $mid) {
                        $stmt->execute([$userId, $mid]);
                    }
                }

                $pdo->commit();

                $success = "✅ User updated successfully!";
                $user[$nameColumn] = $name;
                $user['role'] = $role;
                $user['status'] = $status;
                $userModules = $assignedModules;
            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Database error: " . $e->getMessage();
            }
        }
    }
}

function safe($v) { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2><i class="fas fa-user-edit"></i> Edit User</h2>
        <a href="manage_users.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Back</a>
    </div>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= implode("<br>", array_map('safe', $errors)) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= safe($success) ?></div>
    <?php endif; ?>

    <?php if ($isExpired): ?>
        <div class="alert alert-warning">⚠️ Your subscription has expired. You cannot modify users.</div>
    <?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm bg-white">
        <div class="mb-3">
            <label class="form-label">Name</label>
            <input type="text" name="name" value="<?= safe($user[$nameColumn]) ?>" class="form-control" required <?= $isExpired ? 'readonly' : '' ?>>
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select" <?= $isExpired ? 'disabled' : '' ?>>
                <option value="user" <?= ($user['role'] ?? '') === 'user' ? 'selected' : '' ?>>User</option>
                <option value="manager" <?= ($user['role'] ?? '') === 'manager' ? 'selected' : '' ?>>Manager</option>
                <option value="admin" <?= ($user['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select" <?= $isExpired ? 'disabled' : '' ?>>
                <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= ($user['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Assigned Modules</label><br>
            <?php if (empty($modules)): ?>
                <p class="text-muted">No active modules for this company.</p>
            <?php else: ?>
                <?php foreach ($modules as $m): ?>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" class="form-check-input" name="modules[]" value="<?= safe($m['id']) ?>"
                            <?= in_array($m['id'], $userModules) ? 'checked' : '' ?> <?= $isExpired ? 'disabled' : '' ?>>
                        <label class="form-check-label"><?= safe($m['module_name']) ?></label>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-success" <?= $isExpired ? 'disabled' : '' ?>>
            <i class="fas fa-save"></i> Save Changes
        </button>
    </form>
</div>
</body>
</html>
