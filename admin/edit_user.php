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

// 🚨 Safety check
if (!$companyId) {
    die("⚠️ No company assigned to this admin.");
}

$userId = $_GET['id'] ?? null;
if (!$userId) {
    die("⚠️ No user selected.");
}

// --- Fetch user ---
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND company_id = ?");
$stmt->execute([$userId, $companyId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("❌ User not found or not in your company.");
}

// --- Fetch subscribed modules for company ---
$sql = "
    SELECT m.id, m.module_name 
    FROM subscriptions s
    JOIN modules m ON s.module_id = m.id
    WHERE s.company_id = ? AND s.status = 'active'
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$companyId]);
$modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch user’s current modules ---
$stmt = $pdo->prepare("SELECT module_id FROM user_modules WHERE user_id = ?");
$stmt->execute([$userId]);
$userModules = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'module_id');

$errors = [];
$success = null;

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name   = trim($_POST['name']);
    $role   = $_POST['role'];
    $status = $_POST['status'] ?? 'active';
    $assignedModules = $_POST['modules'] ?? [];

    if (!$name) {
        $errors[] = "Name is required.";
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // Update user info
            $stmt = $pdo->prepare("UPDATE users SET name = ?, role = ?, status = ? WHERE id = ? AND company_id = ?");
            $stmt->execute([$name, $role, $status, $userId, $companyId]);

            // Update modules (clear old and insert new)
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
            // Refresh $user and $userModules
            $user['name'] = $name;
            $user['role'] = $role;
            $user['status'] = $status;
            $userModules = $assignedModules;
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
    <title>Edit User</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2><i class="fas fa-user-edit"></i> Edit User</h2>
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
            <input type="text" name="name" value="<?= htmlspecialchars($user['name']) ?>" class="form-control" required>
        </div>

        <div class="mb-3">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                <option value="manager" <?= $user['role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
                <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Assigned Modules</label><br>
            <?php foreach ($modules as $m): ?>
                <div class="form-check form-check-inline">
                    <input type="checkbox" class="form-check-input" name="modules[]" value="<?= $m['id'] ?>"
                        <?= in_array($m['id'], $userModules) ? 'checked' : '' ?>>
                    <label class="form-check-label"><?= htmlspecialchars($m['module_name']) ?></label>
                </div>
            <?php endforeach; ?>
        </div>

        <button type="submit" class="btn btn-success"><i class="fas fa-save"></i> Save Changes</button>
    </form>
</div>
</body>
</html>
