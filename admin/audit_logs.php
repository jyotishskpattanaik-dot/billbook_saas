<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php';

// ✅ Only admin can view
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied. Admins only.");
}

$pdo = getMainPDO();

// --- Filters ---
$companyId = $_GET['company_id'] ?? null;
$userId    = $_GET['user_id'] ?? null;
$module    = $_GET['module'] ?? null;
$dateFrom  = $_GET['date_from'] ?? null;
$dateTo    = $_GET['date_to'] ?? null;

$where = [];
$params = [];

if ($companyId) { $where[] = "a.company_id = ?"; $params[] = $companyId; }
if ($userId)    { $where[] = "a.user_id = ?";    $params[] = $userId; }
if ($module)    { $where[] = "a.module = ?";     $params[] = $module; }
if ($dateFrom)  { $where[] = "a.created_at >= ?"; $params[] = $dateFrom . " 00:00:00"; }
if ($dateTo)    { $where[] = "a.created_at <= ?"; $params[] = $dateTo . " 23:59:59"; }

$whereSQL = $where ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT a.*, u.username AS user_name, c.company_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN companies c ON a.company_id = c.id
    $whereSQL
    ORDER BY a.created_at DESC
    LIMIT 200
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; font-family: 'Inter', sans-serif; }
        .container { margin-top: 30px; }
        table { font-size: 0.9rem; }
        pre { background: #f1f1f1; padding: 5px; border-radius: 5px; max-height: 150px; overflow:auto; }
    </style>
</head>
<body>
<div class="container">
    <h2 class="mb-4"><i class="fas fa-clipboard-list"></i> Audit Logs</h2>
    <a href="control_panel.php" class="btn btn-outline-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back to Control Panel
    </a>

    <!-- Filter Form -->
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-2">
            <input type="text" name="company_id" class="form-control" placeholder="Company ID" value="<?= htmlspecialchars($companyId ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="text" name="user_id" class="form-control" placeholder="User ID" value="<?= htmlspecialchars($userId ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="text" name="module" class="form-control" placeholder="Module" value="<?= htmlspecialchars($module ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_from" class="form-control" value="<?= htmlspecialchars($dateFrom ?? '') ?>">
        </div>
        <div class="col-md-2">
            <input type="date" name="date_to" class="form-control" value="<?= htmlspecialchars($dateTo ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary w-100">Filter</button>
        </div>
    </form>

    <!-- Logs Table -->
    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th>ID</th>
                    <th>Time</th>
                    <th>User</th>
                    <th>Company</th>
                    <th>Module</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record</th>
                    <th>Old Values</th>
                    <th>New Values</th>
                    <th>IP</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= $log['id'] ?></td>
                    <td><?= $log['created_at'] ?></td>
                    <td><?= htmlspecialchars($log['user_name'] ?? "User#".$log['user_id']) ?></td>
                    <td><?= htmlspecialchars($log['company_name'] ?? "Company#".$log['company_id']) ?></td>
                    <td><?= htmlspecialchars($log['module']) ?></td>
                    <td><span class="badge bg-info"><?= htmlspecialchars($log['action']) ?></span></td>
                    <td><?= htmlspecialchars($log['table_name']) ?></td>
                    <td><?= htmlspecialchars($log['record_id']) ?></td>
                    <td><pre><?= htmlspecialchars($log['old_values']) ?></pre></td>
                    <td><pre><?= htmlspecialchars($log['new_values']) ?></pre></td>
                    <td><?= htmlspecialchars($log['ip_address']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
