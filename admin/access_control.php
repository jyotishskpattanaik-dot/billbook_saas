<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';
use App\Core\ModuleDatabase;

// ✅ Admin access only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied. Admins only.");
}

$companyId = $_SESSION['company_id'] ?? null;
$username  = htmlspecialchars($_SESSION['user_name']);

// Define available modules
$availableModules = ['sales', 'purchase', 'reports', 'customers', 'suppliers'];

try {
    $pdo = ModuleDatabase::getConnection();

    // Fetch all users in this company
    $stmt = $pdo->prepare("SELECT id, user_name, email, user_role, user_module FROM users WHERE company_id = :company_id ORDER BY user_name ASC");
    $stmt->execute([':company_id' => $companyId]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Handle POST updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['user_role'])) {
        $userId = $_POST['user_id'];
        $role   = $_POST['user_role'];
        $modules = $_POST['modules'] ?? []; // array of module names

        $modulesJson = json_encode($modules);

        $updateStmt = $pdo->prepare("
            UPDATE users SET user_role = :role, user_module = :modules 
            WHERE id = :id AND company_id = :company_id
        ");
        $updateStmt->execute([
            ':role' => $role,
            ':modules' => $modulesJson,
            ':id' => $userId,
            ':company_id' => $companyId
        ]);

        header("Location: access_control.php");
        exit;
    }

} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Access Control</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
.card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.checkbox-group { display: flex; gap: 10px; flex-wrap: wrap; }
</style>
</head>
<body>
<div class="container my-4">
    <h2><i class="fas fa-lock"></i> Access Control</h2>
    <p>Welcome, <strong><?= $username ?></strong>. Manage user roles and module permissions below.</p>

    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Modules Access</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($users): ?>
                    <?php foreach ($users as $i => $user): ?>
                        <?php
                            $userModules = json_decode($user['user_module'] ?? '[]', true);
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($user['user_name']) ?></td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                    <select name="user_role" class="form-select form-select-sm mb-2">
                                        <option value="user" <?= $user['user_role'] === 'user' ? 'selected' : '' ?>>User</option>
                                        <option value="admin" <?= $user['user_role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                        <option value="manager" <?= $user['user_role'] === 'manager' ? 'selected' : '' ?>>Manager</option>
                                    </select>

                                    <div class="checkbox-group mb-2">
                                        <?php foreach ($availableModules as $module): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="modules[]" value="<?= $module ?>" 
                                                       id="<?= $module . '_' . $user['id'] ?>"
                                                       <?= in_array($module, $userModules) ? 'checked' : '' ?>>
                                                <label class="form-check-label" for="<?= $module . '_' . $user['id'] ?>">
                                                    <?= ucfirst($module) ?>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="btn btn-sm btn-primary">Update</button>
                                </form>
                            </td>
                            <td></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center">No users found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
