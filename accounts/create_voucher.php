<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/helpers/accounts_functions.php';
require __DIR__ . '/../includes/navigation_helper.php';

// ✅ Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getModulePDO(); // Connect to module DB

// ✅ Basic session checks
if (!isset($_SESSION['company_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];
$userId    = $_SESSION['user_id'];

// Fetch categories & payment modes for dropdowns
$categories = $pdo->query("SELECT name FROM expense_heads ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$paymentModes = $pdo->query("SELECT method_name FROM payment_methods ORDER BY method_name")->fetchAll(PDO::FETCH_COLUMN);

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['voucher_type'];
    $category = $_POST['category'];
    $amount = (float) $_POST['amount'];
    $paymentMode = $_POST['payment_mode'];
    $paidTo = $_POST['paid_to'];
    $narration = $_POST['narration'];

    if ($amount <= 0) {
        $message = "<div class='alert alert-danger'>Amount must be greater than 0.</div>";
    } else {
        $success = addVoucher(
            $pdo,
            $companyId,
            $yearId,
            $type,
            $category,
            $amount,
            $paymentMode,
            $paidTo,
            $narration,
            $userId
        );

        if ($success) {
            $message = "<div class='alert alert-success'>Voucher created successfully and ledger updated!</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error creating voucher. Please check logs.</div>";
        }
    }
}
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
<title>Create Voucher</title>
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
            <h3>🧾 Create New Voucher</h3>
            <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
            🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
        </a>
            <a href="voucher_list.php" class="btn btn-outline-secondary btn-sm">📋 View All Vouchers</a>
        </div>

        <?= $message ?>

        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Voucher Type</label>
                <select name="voucher_type" class="form-select" required>
                    <option value="expense">Expense</option>
                    <option value="payment">Payment</option>
                    <option value="receipt">Receipt</option>
                    <option value="journal">Journal</option>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Category / Head</label>
                <select name="category" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>"><?= htmlspecialchars($c) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Payment Mode</label>
                <select name="payment_mode" class="form-select" required>
                    <option value="">Select Mode</option>
                    <?php foreach ($paymentModes as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars($m) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label">Paid To / Received From</label>
                <input type="text" name="paid_to" class="form-control" placeholder="Name or entity" required>
            </div>

            <div class="col-md-6">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">Narration / Remarks</label>
                <textarea name="narration" class="form-control" rows="2" placeholder="Optional notes about this voucher"></textarea>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">💾 Save Voucher</button>
                <a href="../pharma_retail/dashboard.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
