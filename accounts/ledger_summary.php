<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/navigation_helper.php';
// ✅ Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Get PDO Connection
$pdo = getModulePDO(); // Connect to module DB

// ✅ Basic session checks
if (!isset($_SESSION['company_id'], $_SESSION['financial_year_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];
$userId    = $_SESSION['user_id'];

// ✅ Handle date filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');

// ✅ Fetch ledger summary
$stmt = $pdo->prepare("
    SELECT 
        am.account_name,
        am.account_group,
        SUM(CASE WHEN le.debit_account_id = am.id THEN le.amount ELSE 0 END) AS total_debit,
        SUM(CASE WHEN le.credit_account_id = am.id THEN le.amount ELSE 0 END) AS total_credit
    FROM ledger_accounts_master am
    LEFT JOIN ledger_entries le 
      ON le.company_id = am.company_id 
      AND le.accounting_year_id = am.accounting_year_id 
      AND le.entry_date BETWEEN :start_date AND :end_date
      AND (le.debit_account_id = am.id OR le.credit_account_id = am.id)
    WHERE am.company_id = :company_id 
      AND am.accounting_year_id = :year_id
    GROUP BY am.id
    ORDER BY am.account_group, am.account_name
");
$stmt->execute([
    ':company_id' => $companyId,
    ':year_id'    => $yearId,
    ':start_date' => $startDate,
    ':end_date'   => $endDate
]);
$ledgers = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * ✅ Dynamic module-aware dashboard redirect
 * This assumes you already defined `getModuleDashboardUrl()` in includes/init.php
 */
// if (!function_exists('getModuleDashboardUrl')) {
//     function getModuleDashboardUrl(): string {
//         $module = $_SESSION['user_module'] ?? 'main';
//         $base = "/billbook.in";
//         switch ($module) {
//             case 'pharma_retail': return "$base/pharma_retail/dashboard.php";
//             case 'pharma_wholesale': return "$base/pharma_wholesale/dashboard.php";
//             case 'retail_other': return "$base/retail_other/dashboard.php";
//             case 'wholesale_others': return "$base/wholesale_others/dashboard.php";
//             default: return "$base/public/login.php";
//         }
//     }
// }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ledger Summary</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body { background: #f8f9fa; }
    table { font-size: 0.9rem; }
    a.account-link { text-decoration: none; color: #0d6efd; }
    a.account-link:hover { text-decoration: underline; }
</style>
</head>
<body class="p-4">
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📘 Ledger Summary</h3>
        <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
            🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
        </a>
    </div>

    <!-- Filter -->
    <form method="get" class="row g-2 my-3">
        <div class="col-auto">
            <label class="form-label">From:</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
        </div>
        <div class="col-auto">
            <label class="form-label">To:</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
        </div>
        <div class="col-auto align-self-end">
            <button class="btn btn-primary">Filter</button>
        </div>
    </form>

    <!-- Ledger Table -->
    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Account</th>
                <th>Group</th>
                <th class="text-end">Total Debit (₹)</th>
                <th class="text-end">Total Credit (₹)</th>
                <th class="text-end">Balance (₹)</th>
                <th class="text-center">Type</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $totalDebit = $totalCredit = 0;
            foreach ($ledgers as $l): 
                $balance = abs($l['total_debit'] - $l['total_credit']);
                $type = ($l['total_debit'] > $l['total_credit']) ? 'Dr' : 'Cr';
                $totalDebit += $l['total_debit'];
                $totalCredit += $l['total_credit'];
            ?>
            <tr>
                <td>
                    <a href="ledger_detail.php?account=<?= urlencode($l['account_name']) ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" 
                       class="account-link">
                       <?= htmlspecialchars($l['account_name']) ?>
                    </a>
                </td>
                <td><?= htmlspecialchars($l['account_group']) ?></td>
                <td class="text-end"><?= number_format($l['total_debit'], 2) ?></td>
                <td class="text-end"><?= number_format($l['total_credit'], 2) ?></td>
                <td class="text-end fw-bold"><?= number_format($balance, 2) ?></td>
                <td class="text-center"><?= $type ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
            <tr>
                <th colspan="2">Total</th>
                <th class="text-end"><?= number_format($totalDebit, 2) ?></th>
                <th class="text-end"><?= number_format($totalCredit, 2) ?></th>
                <th class="text-end"><?= number_format(abs($totalDebit - $totalCredit), 2) ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>
</div>
</body>
</html>
