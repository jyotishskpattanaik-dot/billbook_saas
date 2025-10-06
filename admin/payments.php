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

// Filters
$startDate = $_GET['start_date'] ?? null;
$endDate   = $_GET['end_date'] ?? null;
$status    = $_GET['status'] ?? null;

try {
    $pdo = ModuleDatabase::getConnection();

    $query = "SELECT * FROM payments WHERE company_id = :company_id";
    $params = [':company_id' => $companyId];

    if ($startDate) {
        $query .= " AND DATE(created_at) >= :start_date";
        $params[':start_date'] = $startDate;
    }
    if ($endDate) {
        $query .= " AND DATE(created_at) <= :end_date";
        $params[':end_date'] = $endDate;
    }
    if ($status) {
        $query .= " AND status = :status";
        $params[':status'] = $status;
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payments History</title>
<meta name="viewport" content="width=device-width, initial-scale=1">

<!-- Bootstrap + Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

<style>
body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
.card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
</style>
</head>
<body>
<div class="container my-4">
    <h2><i class="fas fa-credit-card"></i> Payment History</h2>
    <p>Welcome, <strong><?= $username ?></strong>. View all payments below.</p>

    <!-- Filter Form -->
    <div class="card p-3 mb-4">
        <form class="row g-2">
            <div class="col-md-3">
                <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control" placeholder="Start Date">
            </div>
            <div class="col-md-3">
                <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control" placeholder="End Date">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="success" <?= $status === 'success' ? 'selected' : '' ?>>Success</option>
                    <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            </div>
        </form>
    </div>

    <!-- Payment Table -->
    <div class="card p-3">
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Subscription ID</th>
                        <th>User ID</th>
                        <th>Amount</th>
                        <th>Provider</th>
                        <th>Payment ID</th>
                        <th>Status</th>
                        <th>Payload</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($payments): ?>
                    <?php foreach ($payments as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($p['subscription_id']) ?></td>
                            <td><?= htmlspecialchars($p['user_id']) ?></td>
                            <td>₹<?= number_format($p['amount'],2) ?></td>
                            <td><?= htmlspecialchars($p['provider']) ?></td>
                            <td><?= htmlspecialchars($p['provider_payment_id']) ?></td>
                            <td><?= htmlspecialchars($p['status']) ?></td>
                            <td><?= htmlspecialchars($p['payload']) ?></td>
                            <td><?= date('d-m-Y H:i', strtotime($p['created_at'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="9" class="text-center">No payments found.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</body>
</html>
