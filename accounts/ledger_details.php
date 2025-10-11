<?php
require __DIR__ . '/../includes/init.php';
session_start();

$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId = $_SESSION['financial_year_id'];

$accountName = $_GET['account'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-t');

if (!$accountName) die("Invalid request.");

// Get account info
$stmt = $pdo->prepare("
    SELECT id, account_group, account_type 
    FROM ledger_accounts_master 
    WHERE company_id = ? AND accounting_year_id = ? AND account_name = ?
    LIMIT 1
");
$stmt->execute([$companyId, $yearId, $accountName]);
$account = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$account) die("Account not found.");

$accountId = $account['id'];

// Fetch ledger transactions
$stmt = $pdo->prepare("
    SELECT 
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
      AND le.entry_date BETWEEN :start_date AND :end_date
      AND (le.debit_account_id = :account_id OR le.credit_account_id = :account_id)
    ORDER BY le.entry_date, le.id
");
$stmt->execute([
    ':company_id' => $companyId,
    ':year_id' => $yearId,
    ':account_id' => $accountId,
    ':start_date' => $startDate,
    ':end_date' => $endDate
]);

$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

/**
 * Build reference URL dynamically
 */
function getRefLink($refTable, $refId)
{
    switch (strtolower($refTable)) {
        case 'sale_bill_master':
        case 'bill_summary':
            return "../pharma_retail/salesnbilling/view_sale_bill.php?bill_no={$refId}";
        case 'purchase_details':
            return "../pharma_retail/purchasenstock/view_purchase.php?id={$refId}";
        case 'vouchers':
            return "../accounts/voucher_view.php?voucher_id={$refId}";
        case 'payments':
            return "../accounts/payment_view.php?id={$refId}";
        case 'receipts':
            return "../accounts/receipt_view.php?id={$refId}";
        default:
            return "";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Ledger Detail - <?= htmlspecialchars($accountName) ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f8f9fa; }
table { font-size: 0.9rem; }
tr.credit { background: #ffecec; }
tr.debit { background: #eaffea; }
a.view-link { text-decoration: none; color: #0d6efd; }
a.view-link:hover { text-decoration: underline; }
</style>
</head>
<body class="p-4">
<div class="container">
    <h3>📗 Ledger Detail - <?= htmlspecialchars($accountName) ?></h3>
    <p><strong>Period:</strong> <?= htmlspecialchars($startDate) ?> to <?= htmlspecialchars($endDate) ?></p>

    <table class="table table-bordered table-striped">
        <thead class="table-dark">
            <tr>
                <th>Date</th>
                <th>Particulars</th>
                <th>Reference</th>
                <th class="text-end">Debit (₹)</th>
                <th class="text-end">Credit (₹)</th>
                <th class="text-end">Running Balance (₹)</th>
                <th>Type</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $balance = 0;
        foreach ($entries as $e):
            if ($e['debit_account'] === $accountName) {
                $balance += $e['amount'];
                $dr = $e['amount']; $cr = 0; $type = 'Dr';
            } else {
                $balance -= $e['amount'];
                $dr = 0; $cr = $e['amount']; $type = 'Cr';
            }

            $refLink = getRefLink($e['ref_table'], $e['ref_id']);
            $refName = $e['ref_table'] ? ucfirst(str_replace('_', ' ', $e['ref_table'])) : '-';
        ?>
            <tr class="<?= $type === 'Dr' ? 'debit' : 'credit' ?>">
                <td><?= htmlspecialchars($e['entry_date']) ?></td>
                <td><?= htmlspecialchars($e['narration']) ?></td>
                <td>
                    <?php if ($refLink): ?>
                        <a href="<?= $refLink ?>" target="_blank" class="view-link">
                            <?= htmlspecialchars($refName) ?> #<?= htmlspecialchars($e['ref_id']) ?>
                        </a>
                    <?php else: ?>
                        <?= htmlspecialchars($refName) ?>
                    <?php endif; ?>
                </td>
                <td class="text-end"><?= $dr ? number_format($dr, 2) : '' ?></td>
                <td class="text-end"><?= $cr ? number_format($cr, 2) : '' ?></td>
                <td class="text-end fw-bold"><?= number_format(abs($balance), 2) ?></td>
                <td><?= $type ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot class="table-secondary">
            <tr>
                <th colspan="5" class="text-end">Closing Balance:</th>
                <th><?= ($balance >= 0 ? 'Dr ' : 'Cr ') . number_format(abs($balance), 2) ?></th>
                <th></th>
            </tr>
        </tfoot>
    </table>

    <a href="ledger_summary.php?start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-secondary">⬅ Back to Summary</a>
</div>
</body>
</html>
