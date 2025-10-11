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

if (!isset($_GET['voucher_id'])) {
    die("Voucher ID not provided.");
}

$voucherId = (int) $_GET['voucher_id'];

// ✅ Get both database connections
$pdo = getModulePDO();   // module DB (e.g., pharma_retail_db)
$mainPDO = getMainPDO(); // main DB (user & company details)

$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];

// ✅ Fetch voucher details (no JOIN with users)
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        eh.name AS category_name,
        pm.method_name AS payment_method
    FROM vouchers v
    LEFT JOIN expense_heads eh ON v.category_id = eh.id
    LEFT JOIN payment_methods pm ON v.payment_mode_id = pm.id
    WHERE v.id = :id AND v.company_id = :company_id AND v.accounting_year_id = :year_id
");
$stmt->execute([
    ':id' => $voucherId,
    ':company_id' => $companyId,
    ':year_id' => $yearId
]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    die("Voucher not found.");
}

// ✅ Fetch created_by username from main_db.users
$voucher['created_by_user'] = '-';
if (!empty($voucher['created_by'])) {
    $stmt = $mainPDO->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$voucher['created_by']]);
    $voucher['created_by_user'] = $stmt->fetchColumn() ?: '-';
}

// ✅ Fetch corresponding ledger entries from module DB
$stmt = $pdo->prepare("
    SELECT 
        le.entry_date,
        la1.account_name AS debit_account,
        la2.account_name AS credit_account,
        le.amount,
        le.narration
    FROM ledger_entries le
    JOIN ledger_accounts_master la1 ON le.debit_account_id = la1.id
    JOIN ledger_accounts_master la2 ON le.credit_account_id = la2.id
    WHERE le.ref_table = 'vouchers' 
      AND le.ref_id = :voucher_id
      AND le.company_id = :company_id 
      AND le.accounting_year_id = :year_id
");
$stmt->execute([
    ':voucher_id' => $voucherId,
    ':company_id' => $companyId,
    ':year_id' => $yearId
]);
$ledgerEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Voucher View - <?= htmlspecialchars($voucher['voucher_no']) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; font-size: 0.95rem; }
.voucher-box {
    background: white;
    border: 1px solid #ccc;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 3px 8px rgba(0,0,0,0.1);
}
.header { border-bottom: 2px solid #000; padding-bottom: 8px; margin-bottom: 15px; }
h3 { font-size: 1.3rem; }
.table th, .table td { vertical-align: middle !important; }
.print-btn { float: right; }
</style>
</head>
<body class="p-4">
<div class="container">
    <div class="voucher-box">
        <div class="header d-flex justify-content-between align-items-center">
            <h3>🧾 Voucher Details</h3>
            <div>
                <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
                    🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
                </a>
                <button class="btn btn-outline-secondary btn-sm print-btn" onclick="window.print()">
                    🖨 Print
                </button>
            </div>
        </div>

        <div class="row mb-3">
            <div class="col-md-6">
                <p><strong>Voucher No:</strong> <?= htmlspecialchars($voucher['voucher_no']) ?></p>
                <p><strong>Date:</strong> <?= htmlspecialchars($voucher['voucher_date']) ?></p>
                <p><strong>Type:</strong> <?= ucfirst(htmlspecialchars($voucher['voucher_type'])) ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Category:</strong> <?= htmlspecialchars($voucher['category_name'] ?? '-') ?></p>
                <p><strong>Payment Mode:</strong> <?= htmlspecialchars($voucher['payment_method'] ?? '-') ?></p>
                <p><strong>Paid To:</strong> <?= htmlspecialchars($voucher['paid_to'] ?? '-') ?></p>
            </div>
        </div>

        <div class="mb-3">
            <strong>Narration / Remarks:</strong>
            <p><?= nl2br(htmlspecialchars($voucher['particulars'] ?? $voucher['remarks'])) ?></p>
        </div>

        <table class="table table-bordered">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Debit Account</th>
                    <th>Credit Account</th>
                    <th class="text-end">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ledgerEntries as $le): ?>
                    <tr>
                        <td><?= htmlspecialchars($le['entry_date']) ?></td>
                        <td><?= htmlspecialchars($le['debit_account']) ?></td>
                        <td><?= htmlspecialchars($le['credit_account']) ?></td>
                        <td class="text-end"><?= number_format($le['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="table-secondary">
                <tr>
                    <th colspan="3" class="text-end">Total:</th>
                    <th class="text-end"><?= number_format($voucher['amount'], 2) ?></th>
                </tr>
            </tfoot>
        </table>

        <div class="mt-3 text-muted small">
            <p><strong>Created By:</strong> <?= htmlspecialchars($voucher['created_by_user'] ?? '-') ?></p>
            <p><strong>Created At:</strong> <?= htmlspecialchars($voucher['created_at'] ?? '-') ?></p>
        </div>

        <div class="mt-3">
            <a href="voucher_list.php" class="btn btn-secondary btn-sm">⬅ Back to Voucher List</a>
        </div>
    </div>
</div>
</body>
</html>
