<?php
require_once __DIR__ . '/../includes/init.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getModulePDO(); // Connect to module database (pharma_retail, etc.)

// --- helper for debug ---
function debugLog($msg) {
    file_put_contents(__DIR__ . "/debug_customer_ledger.log", "\n" . date("Y-m-d H:i:s") . " - " . $msg, FILE_APPEND);
}

// --- Input filters ---
$company_id = $_SESSION['company_id'] ?? 1;
$accounting_year_id = $_SESSION['accounting_year_id'] ?? 1;
$customer_name = $_GET['customer_name'] ?? '';
$from_date = $_GET['from_date'] ?? date('Y-m-01');
$to_date = $_GET['to_date'] ?? date('Y-m-t');

$rows = [];
$opening_balance = 0.00;
$opening_type = 'Dr'; // default
$total_debit = 0.00;
$total_credit = 0.00;

try {

    if ($customer_name) {

        // --- Step 1: Get opening balance ---
        $stmt = $pdo->prepare("SELECT opening_balance, balance_type FROM ledger_accounts 
                               WHERE company_id = ? AND accounting_year_id = ? AND account_name = ?");
        $stmt->execute([$company_id, $accounting_year_id, $customer_name]);
        if ($ob = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $opening_balance = (float)$ob['opening_balance'];
            $opening_type = $ob['balance_type']; // Dr or Cr
        }

        // --- Step 2: Get sale and receipt transactions ---
        $sql = "
            SELECT 
                bs.bill_date AS entry_date,
                'Sale' AS transaction_type,
                bs.bill_no AS reference_no,
                bs.grand_total AS debit,
                0.00 AS credit,
                'bill_summary' AS source_table
            FROM bill_summary bs
            WHERE bs.company_id = :company_id
              AND bs.accounting_year_id = :year_id
              AND bs.customer_name = :customer_name
              AND bs.bill_date BETWEEN :from_date AND :to_date

            UNION ALL

            SELECT 
                cr.receipt_date AS entry_date,
                'Receipt' AS transaction_type,
                cr.bill_no AS reference_no,
                0.00 AS debit,
                cr.amount_received AS credit,
                'customer_receipts' AS source_table
            FROM customer_receipts cr
            WHERE cr.company_id = :company_id
              AND cr.accounting_year_id = :year_id
              AND cr.customer_name = :customer_name
              AND cr.receipt_date BETWEEN :from_date AND :to_date

            ORDER BY entry_date ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'company_id' => $company_id,
            'year_id' => $accounting_year_id,
            'customer_name' => $customer_name,
            'from_date' => $from_date,
            'to_date' => $to_date
        ]);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Step 3: Running Balance ---
        $balance = ($opening_type === 'Cr') ? -$opening_balance : $opening_balance;

        foreach ($rows as &$row) {
            $balance += ($row['debit'] - $row['credit']);
            $row['running_balance'] = $balance;
            $total_debit += $row['debit'];
            $total_credit += $row['credit'];
        }

        unset($row);
    }

} catch (Exception $e) {
    debugLog("Ledger Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
   <?php require_once __DIR__ . '/../includes/navbar.php'; ?>
    <title>Customer Ledger</title>
    <link rel="stylesheet" href="../assets/css/module.css">
    <!-- <link rel="stylesheet" href="../assets/css/bootstrap.min.css"> -->
    <style>
        body { background: #f9fafb; padding: 20px; }
        table { font-size: 14px; }
        th { background: #eee; }
        .balance-dr { color: #d9534f; font-weight: bold; }
        .balance-cr { color: #28a745; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h3 class="mb-4">Customer Ledger</h3>

        <form method="GET" class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label">Customer Name</label>
                <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($customer_name) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" value="<?= $from_date ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" value="<?= $to_date ?>">
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">Show</button>
            </div>
        </form>

        <?php if ($customer_name): ?>
            <div class="card p-3 mb-3">
                <strong>Customer:</strong> <?= htmlspecialchars($customer_name) ?><br>
                <strong>Opening Balance:</strong> <?= number_format($opening_balance, 2) ?> <?= $opening_type ?><br>
                <strong>Period:</strong> <?= $from_date ?> to <?= $to_date ?>
            </div>

            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Reference</th>
                        <th>Debit</th>
                        <th>Credit</th>
                        <th>Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5"><strong>Opening Balance</strong></td>
                        <td class="<?= $opening_type === 'Dr' ? 'balance-dr' : 'balance-cr' ?>">
                            <?= number_format($opening_balance, 2) ?> <?= $opening_type ?>
                        </td>
                    </tr>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?= $r['entry_date'] ?></td>
                            <td><?= $r['transaction_type'] ?></td>
                            <td><?= $r['reference_no'] ?></td>
                            <td><?= $r['debit'] > 0 ? number_format($r['debit'], 2) : '' ?></td>
                            <td><?= $r['credit'] > 0 ? number_format($r['credit'], 2) : '' ?></td>
                            <td class="<?= $r['running_balance'] >= 0 ? 'balance-dr' : 'balance-cr' ?>">
                                <?= number_format(abs($r['running_balance']), 2) ?> <?= $r['running_balance'] >= 0 ? 'Dr' : 'Cr' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <th colspan="3">Totals</th>
                        <th><?= number_format($total_debit, 2) ?></th>
                        <th><?= number_format($total_credit, 2) ?></th>
                        <th></th>
                    </tr>
                </tfoot>
            </table>
        <?php endif; ?>
    </div>
</body>
</html>
