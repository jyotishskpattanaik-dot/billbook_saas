<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/navigation_helper.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// ✅ Dynamic Dashboard URL helper
// if (!function_exists('getModuleDashboardUrl')) {
//     function getModuleDashboardUrl(): string {
//         $module = $_SESSION['user_module'] ?? 'main';
//         $base = "/billbook.in";
//         switch ($module) {
//             case 'pharma_retail': return "$base/pharma_retail/dashboard.php";
//             case 'pharma_wholesale': return "$base/pharma_wholesale/dashboard.php";
//             case 'retail_other': return "$base/retail_other/dashboard.php";
//             case 'wholesale_others': return "$base/wholesale_others/dashboard.php";
//             default: return "$base/public/home.php";
//         }
//     }
// }

$pdo = getModulePDO();
$message = "";

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);

    if ($name === '') {
        $message = "<div class='alert alert-danger'>Please enter a category name.</div>";
    } else {
        // Prevent duplicate entry
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM expense_heads WHERE name = :name");
        $checkStmt->execute([':name' => $name]);
        if ($checkStmt->fetchColumn() > 0) {
            $message = "<div class='alert alert-warning'>This category already exists.</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO expense_heads (name, description) VALUES (:name, :description)");
            $stmt->execute([':name' => $name, ':description' => $description]);
            $message = "<div class='alert alert-success'>Expense category added successfully.</div>";
        }
    }
}

// ✅ Fetch all expense categories
$stmt = $pdo->query("SELECT id, name, description FROM expense_heads ORDER BY name ASC");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Expense Categories</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }
.card { box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
.form-label { font-weight: 600; }
</style>
</head>
<body class="p-4">
<div class="container">
    <div class="card p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3>💼 Expense Categories</h3>
            <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">🏠 Back to Dashboard</a>
        </div>

        <?= $message ?>

        <!-- Add Category Form -->
        <form method="POST" class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Category Name</label>
                <input type="text" name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">➕ Add</button>
            </div>
        </form>

        <!-- Category List -->
        <table class="table table-bordered table-striped table-sm">
            <thead class="table-dark">
                <tr>
                    <th width="5%">#</th>
                    <th>Category Name</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($categories) > 0): ?>
                    <?php foreach ($categories as $i => $cat): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['description'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center text-muted">No categories added yet.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
