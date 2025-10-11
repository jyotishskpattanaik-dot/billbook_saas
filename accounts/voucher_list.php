<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/navigation_helper.php';

// ✅ Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Check login/session
if (!isset($_SESSION['company_id'], $_SESSION['financial_year_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];
$userId    = $_SESSION['user_id'];

// ✅ Dynamic module-based back link helper if required to be used later
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

// ✅ Fetch filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');
$type      = $_GET['type'] ?? '';
$category  = $_GET['category'] ?? '';

// ✅ Get dropdown options
$categories = $pdo->query("SELECT name FROM expense_heads ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$types = ['expense', 'payment', 'receipt', 'journal'];

// ✅ Build query dynamically
$query = "
    SELECT 
        v.id,
        v.voucher_no,
        v.voucher_type,
        v.voucher_date,
        v.amount,
        v.paid_to,
        v.particulars,
        eh.name AS category_name,
        pm.method_name AS payment_method
    FROM vouchers v
    LEFT JOIN expense_heads eh ON v.category_id = eh.id
    LEFT JOIN payment_methods pm ON v.payment_mode_id = pm.id
    WHERE v.company_id = :company_id 
      AND v.accounting_year_id = :year_id
      AND v.voucher_date BETWEEN :start_date AND :end_date
";

$params = [
    ':company_id' => $companyId,
    ':year_id'    => $yearId,
    ':start_date' => $startDate,
    ':end_date'   => $endDate
];

if (!empty($type)) {
    $query .= " AND v.voucher_type = :type";
    $params[':type'] = $type;
}

if (!empty($category)) {
    $query .= " AND eh.name = :category";
    $params[':category'] = $category;
}

$query .= " ORDER BY v.voucher_date DESC, v.id DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$vouchers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Voucher List</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }
table { font-size: 0.9rem; }
a.view-link { text-decoration: none; color: #0d6efd; }
a.view-link:hover { text-decoration: underline; }
</style>
</head>
<body class="p-4">
<div class="container">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📋 Voucher List</h3>
        <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
            🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
        </a>
    </div>

    <!-- Filters -->
    <form method="get" class="row g-2 mb-3">
        <div class="col-md-2">
            <label class="form-label">From</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">To</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
        </div>
        <div class="col-md-2">
            <label class="form-label">Type</label>
            <select name="type" class="form-select">
                <option value="">All</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?= $t ?>" <?= ($t === $type) ? 'selected' : '' ?>>
                        <?= ucfirst($t) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label">Category</label>
            <select name="category" class="form-select">
                <option value="">All</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= htmlspecialchars($c) ?>" <?= ($c === $category) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary me-2">🔍 Filter</button>
            <a href="create_voucher.php" class="btn btn-success">➕ New Voucher</a>
        </div>
    </form>

    <!-- Voucher Table -->
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Date</th>
                <th>Voucher No</th>
                <th>Type</th>
                <th>Category</th>
                <th>Payment Mode</th>
                <th>Paid To / Received From</th>
                <th class="text-end">Amount (₹)</th>
                <th>View</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($vouchers): ?>
                <?php foreach ($vouchers as $v): ?>
                    <tr>
                        <td><?= htmlspecialchars($v['voucher_date']) ?></td>
                        <td><?= htmlspecialchars($v['voucher_no']) ?></td>
                        <td><?= ucfirst(htmlspecialchars($v['voucher_type'])) ?></td>
                        <td><?= htmlspecialchars($v['category_name'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['payment_method'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($v['paid_to'] ?? '-') ?></td>
                        <td class="text-end"><?= number_format($v['amount'], 2) ?></td>
                        <td>
                            <a href="voucher_view.php?voucher_id=<?= urlencode($v['id']) ?>" 
                               target="_blank" class="btn btn-sm btn-outline-primary">
                               👁 View
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center text-muted">No vouchers found for selected filters.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
