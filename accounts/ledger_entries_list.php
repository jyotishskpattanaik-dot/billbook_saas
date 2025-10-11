<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/navigation_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['company_id'], $_SESSION['financial_year_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];

// --- Filters ---
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');
$refTable  = $_GET['ref_table'] ?? '';

// --- Fetch entries ---
$sql = "
    SELECT 
        le.id,
        le.entry_date,
        la1.account_name AS debit_account,
        la2.account_name AS credit_account,
        le.amount,
        le.narration,
        le.ref_table,
        le.ref_id
    FROM ledger_entries le
    JOIN ledger_accounts_master la1 ON le.debit_account_id = la1.id
    JOIN ledger_accounts_master la2 ON le.credit_account_id = la2.id
    WHERE le.company_id = :company_id
      AND le.accounting_year_id = :year_id
      AND le.entry_date BETWEEN :start AND :end
";

$params = [
    ':company_id' => $companyId,
    ':year_id'    => $yearId,
    ':start'      => $startDate,
    ':end'        => $endDate
];

if (!empty($refTable)) {
    $sql .= " AND le.ref_table = :ref_table";
    $params[':ref_table'] = $refTable;
}

$sql .= " ORDER BY le.entry_date DESC, le.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ledger Entries</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }
.table th, .table td { font-size: 0.9rem; }
</style>
</head>
<body class="p-4">
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📘 Ledger Entries</h3>
        <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
            🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
        </a>
    </div>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-3">
            <label class="form-label">Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" class="form-control">
        </div>
        <div class="col-md-3">
            <label class="form-label">Reference Type</label>
            <select name="ref_table" class="form-select">
                <option value="">All</option>
                <option value="vouchers" <?= $refTable === 'vouchers' ? 'selected' : '' ?>>Vouchers</option>
                <option value="sales" <?= $refTable === 'sales' ? 'selected' : '' ?>>Sales</option>
                <option value="purchase" <?= $refTable === 'purchase' ? 'selected' : '' ?>>Purchase</option>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">🔍 Filter</button>
        </div>
    </form>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Date</th>
                <th>Debit Account</th>
                <th>Credit Account</th>
                <th class="text-end">Amount (₹)</th>
                <th>Narration</th>
                <th>Reference</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($entries)): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted">No entries found for the selected filters.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($entries as $e): ?>
                    <tr>
                        <td><?= htmlspecialchars($e['entry_date']) ?></td>
                        <td><?= htmlspecialchars($e['debit_account']) ?></td>
                        <td><?= htmlspecialchars($e['credit_account']) ?></td>
                        <td class="text-end"><?= number_format($e['amount'], 2) ?></td>
                        <td><?= htmlspecialchars($e['narration']) ?></td>
                        <td>
                            <?php if ($e['ref_table'] === 'vouchers'): ?>
                                <a href="voucher_view.php?voucher_id=<?= urlencode($e['ref_id']) ?>" class="btn btn-sm btn-outline-info">
                                    View Voucher
                                </a>
                            <?php else: ?>
                                <?= htmlspecialchars($e['ref_table'] ?? '-') ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
</body>
</html>
