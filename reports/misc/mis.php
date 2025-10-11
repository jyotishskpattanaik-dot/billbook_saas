<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

$pdo = getModulePDO();
$error = "";
$results = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fromDate = $_POST['from_date'] ?? '';
    $toDate   = $_POST['to_date'] ?? '';

    if (!$fromDate || !$toDate) {
        $error = "Please select both start and end dates.";
    } else {
        try {
            // --- Sales ---
            $stmt = $pdo->prepare("SELECT SUM(net_amount) FROM sale_bill_master WHERE bill_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $sales = $stmt->fetchColumn() ?? 0;

            // --- Purchase ---
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM purchase_details WHERE purchase_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $purchase = $stmt->fetchColumn() ?? 0;

            // --- GST Input (on Purchases) ---
            $stmt = $pdo->prepare("SELECT SUM(gst_amount) FROM stock_details WHERE purchase_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $gstInput = $stmt->fetchColumn() ?? 0;

            // --- GST Output (on Sales) ---
            $stmt = $pdo->prepare("SELECT SUM(gst_amount) FROM bill_summary WHERE bill_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $gstOutput = $stmt->fetchColumn() ?? 0;

            // --- GST to be Paid ---
            $gstToPay = $gstOutput - $gstInput;

            // --- Profit / Loss ---
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(sp.grand_total - sp.purchase_price * sp.quantity) AS profit
                FROM bill_summary sp
                JOIN bill_summary sm ON sm.bill_no = sp.bill_no
                WHERE sm.bill_date BETWEEN ? AND ?
            ");
            $stmt->execute([$fromDate, $toDate]);
            $profitLoss = $stmt->fetchColumn() ?? 0;

            // --- Receipts ---
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM receipts WHERE receipt_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $receipts = $stmt->fetchColumn() ?? 0;

            // --- Payments ---
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE payment_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $payments = $stmt->fetchColumn() ?? 0;

            // --- Expenses ---
            $stmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE expense_date BETWEEN ? AND ?");
            $stmt->execute([$fromDate, $toDate]);
            $expenses = $stmt->fetchColumn() ?? 0;

            // --- Stock in Hand ---
            $stmt = $pdo->query("
                SELECT 
                    SUM(total_quantity * purchase_price) AS stock_value_purchase,
                    SUM(total_quantity * mrp) AS stock_value_mrp
                FROM current_stock
            ");
            $stock = $stmt->fetch(PDO::FETCH_ASSOC);
            $stockPurchase = $stock['stock_value_purchase'] ?? 0;
            $stockMRP = $stock['stock_value_mrp'] ?? 0;

            // --- Outstanding Receivables ---
            $stmt = $pdo->query("SELECT SUM(balance_amount) FROM customers WHERE balance_amount > 0");
            $toReceive = $stmt->fetchColumn() ?? 0;

            // --- Outstanding Payables ---
            $stmt = $pdo->query("SELECT SUM(balance_amount) FROM suppliers WHERE balance_amount > 0");
            $toPay = $stmt->fetchColumn() ?? 0;

            // Collect results
            $results = [
                'sales' => $sales,
                'purchase' => $purchase,
                'gst_input' => $gstInput,
                'gst_output' => $gstOutput,
                'gst_to_pay' => $gstToPay,
                'profit_loss' => $profitLoss,
                'receipts' => $receipts,
                'payments' => $payments,
                'expenses' => $expenses,
                'stock_purchase' => $stockPurchase,
                'stock_mrp' => $stockMRP,
                'to_receive' => $toReceive,
                'to_pay' => $toPay,
            ];

        } catch (Exception $e) {
            $error = "Database Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MIS Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .clickable-row:hover {
            background: #f1f9ff;
            cursor: pointer;
        }
        .card-title {
            color: #0d6efd;
        }
    </style>
</head>
<body class="bg-light p-4">

<div class="container">
    <h2 class="mb-4 text-center">📊 Management Information System (MIS)</h2>

    <form method="POST" class="card p-3 shadow-sm mb-4">
        <div class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">From Date</label>
                <input type="date" name="from_date" class="form-control" required value="<?= htmlspecialchars($_POST['from_date'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">To Date</label>
                <input type="date" name="to_date" class="form-control" required value="<?= htmlspecialchars($_POST['to_date'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-chart-line"></i> Generate Report
                </button>
            </div>
        </div>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($results): ?>
        <div class="card shadow-sm">
            <div class="card-body">
                <h5 class="card-title">Summary for <?= htmlspecialchars($fromDate) ?> to <?= htmlspecialchars($toDate) ?></h5>
                <hr>
                <table class="table table-bordered align-middle">
                    <tbody>
                        <tr class="clickable-row" data-href="../../sales/list_sales.php"><th>Sales</th><td>₹<?= number_format($results['sales'], 2) ?></td></tr>
                        <tr class="clickable-row" data-href="../../purchasenstock/list_purchase.php"><th>Purchases</th><td>₹<?= number_format($results['purchase'], 2) ?></td></tr>
                        <tr class="clickable-row" data-href="../../gst_reports/gst_summary.php"><th>GST Input</th><td>₹<?= number_format($results['gst_input'], 2) ?></td></tr>
                        <tr class="clickable-row" data-href="../../gst_reports/gst_summary.php"><th>GST Output</th><td>₹<?= number_format($results['gst_output'], 2) ?></td></tr>
                        <tr class="table-warning"><th>GST to Pay</th><td>₹<?= number_format($results['gst_to_pay'], 2) ?></td></tr>
                        <tr class="clickable-row" data-href="../../sales/monthly_sales_report.php"><th>Profit / Loss</th><td>₹<?= number_format($results['profit_loss'], 2) ?></td></tr>
                        <tr><th>Receipts</th><td>₹<?= number_format($results['receipts'], 2) ?></td></tr>
                        <tr><th>Payments</th><td>₹<?= number_format($results['payments'], 2) ?></td></tr>
                        <tr><th>Expenses</th><td>₹<?= number_format($results['expenses'], 2) ?></td></tr>
                        <tr class="clickable-row" data-href="../../misc_reports/stock_summary.php"><th>Stock (Purchase Price)</th><td>₹<?= number_format($results['stock_purchase'], 2) ?></td></tr>
                        <tr class="clickable-row" data-href="../../misc_reports/stock_summary.php"><th>Stock (MRP)</th><td>₹<?= number_format($results['stock_mrp'], 2) ?></td></tr>
                        <tr><th>Outstanding (Receivable)</th><td>₹<?= number_format($results['to_receive'], 2) ?></td></tr>
                        <tr><th>Outstanding (Payable)</th><td>₹<?= number_format($results['to_pay'], 2) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="mt-3">
        <a href="../../../pharma_retail/dashboard.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
<script>
document.querySelectorAll(".clickable-row").forEach(row => {
    row.addEventListener("click", () => {
        const target = row.dataset.href;
        if (target) window.location.href = target;
    });
});
</script>
</body>
</html>
