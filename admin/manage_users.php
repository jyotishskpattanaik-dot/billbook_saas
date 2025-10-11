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

// --- Fetch company info to get plan & user_limit ---
$stmt = $pdo->prepare("SELECT plan, user_limit FROM companies WHERE id = ?");
$stmt->execute([$companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);
$plan      = $company['plan'] ?? 'free_trial';
$userLimit = intval($company['user_limit'] ?? 1);

// --- Count existing users ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE company_id = ?");
$stmt->execute([$companyId]);
$usersUsed = intval($stmt->fetchColumn());

// --- Handle delete ---
if (isset($_GET['delete'])) {
    $userId = intval($_GET['delete']);
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND company_id = ?");
    $stmt->execute([$userId, $companyId]);
    header("Location: manage_users.php");
    exit;
}

// --- Fetch all users of this company ---
$sql = "
    SELECT u.id, u.username, u.email, u.role, u.status, u.last_login,
           GROUP_CONCAT(m.module_name) AS modules
    FROM users u
    LEFT JOIN user_modules um ON u.id = um.user_id
    LEFT JOIN modules m ON um.module_id = m.id
    WHERE u.company_id = ?
    GROUP BY u.id
    ORDER BY u.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$companyId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2>Manage Users</h2>
     <a href="control_panel.php" class="btn btn-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back to Control Panel
    </a>
    <p>Company Plan: <strong><?= htmlspecialchars($plan) ?></strong> | User Limit: <strong><?= $userLimit ?></strong> | Users Used: <strong><?= $usersUsed ?></strong></p>
    <a href="add_user.php" class="btn btn-primary mb-3">Add New User</a>

    <?php if ($usersUsed >= $userLimit): ?>
        <div class="alert alert-warning">User limit reached (<?= $usersUsed ?>/<?= $userLimit ?>)</div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Modules</th>
                    <th>Last Login</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><?= $u['id'] ?></td>
                    <td><?= htmlspecialchars($u['username']) ?></td>
                    <td><?= htmlspecialchars($u['email']) ?></td>
                    <td><?= htmlspecialchars($u['role'] ?? 'user') ?></td>
                    <td><?= $u['status'] === 'active' ? 'Active' : 'Inactive' ?></td>
                    <td><?= htmlspecialchars($u['modules'] ?? '-') ?></td>
                    <td><?= $u['last_login'] ?: 'Never' ?></td>
                    <td>
                        <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                        <a href="manage_users.php?delete=<?= $u['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this user?')">Delete</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
