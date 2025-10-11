<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/helpers/accounts_functions.php';

// ✅ Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Verify session
if (!isset($_SESSION['company_id'], $_SESSION['financial_year_id'])) {
    header("Location: ../public/login.php");
    exit;
}

// ✅ Database connection
$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId = $_SESSION['financial_year_id'];
$userId = $_SESSION['user_id'];

// ✅ Dynamic Dashboard URL
if (!function_exists('getModuleDashboardUrl')) {
    function getModuleDashboardUrl(): string {
        $module = $_SESSION['user_module'] ?? 'main';
        $base = "/billbook.in";
        switch ($module) {
            case 'pharma_retail': return "$base/pharma_retail/dashboard.php";
            case 'pharma_wholesale': return "$base/pharma_wholesale/dashboard.php";
            case 'retail_other': return "$base/retail_other/dashboard.php";
            case 'wholesale_others': return "$base/wholesale_others/dashboard.php";
            default: return "$base/public/home.php";
        }
    }
}

// ✅ Get voucher ID
$voucherId = $_GET['voucher_id'] ?? null;
if (!$voucherId) {
    die("Invalid voucher reference.");
}

// ✅ Fetch existing voucher
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
    die("Voucher not found or unauthorized access.");
}

// ✅ Dropdown data
$categories = $pdo->query("SELECT name FROM expense_heads ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$paymentModes = $pdo->query("SELECT method_name FROM payment_methods ORDER BY method_name")->fetchAll(PDO::FETCH_COLUMN);
$types = ['expense', 'payment', 'receipt', 'journal'];

// ✅ Process update form
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['voucher_type'];
    $category = $_POST['category'];
    $amount = (float) $_POST['amount'];
    $paymentMode = $_POST['payment_mode'];
    $paidTo = $_POST['paid_to'];
    $narration = $_POST['narration'];
    $voucherDate = $_POST['voucher_date'];

    if ($amount <= 0) {
        $message = "<div class='alert alert-danger'>Amount must be greater than 0.</div>";
    } else {
        // ✅ Update voucher
        $updateStmt = $pdo->prepare("
            UPDATE vouchers
            SET voucher_type = :type,
                voucher_date = :voucher_date,
                amount = :amount,
                paid_to = :paid_to,
                particulars = :narration,
                updated_by = :user_id,
                updated_at = NOW()
            WHERE id = :id AND company_id = :company_id AND accounting_year_id = :year_id
        ");
        $success = $updateStmt->execute([
            ':type' => $type,
            ':voucher_date' => $voucherDate,
            ':amount' => $amount,
            ':paid_to' => $paidTo,
            ':narration' => $narration,
            ':user_id' => $userId,
            ':id' => $voucherId,
            ':company_id' => $companyId,
            ':year_id' => $yearId
        ]);

        if ($success) {
            // ✅ Optional: Update ledger entries for this voucher
            updateVoucherLedger($pdo, $voucherId, $companyId, $yearId, $type, $amount, $userId);

            $message = "<div class='alert alert-success'>Voucher updated successfully!</div>";
            // Refresh voucher data
            $stmt->execute([
                ':id' => $voucherId,
                ':company_id' => $companyId,
                ':year_id' => $yearId
            ]);
            $voucher = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $message = "<div class='alert alert-danger'>Failed to update voucher. Please try again.</div>";
        }
    }
}

// ✅ Helper: Update linked ledger entries
function updateVoucherLedger($pdo, $voucherId, $companyId, $yearId, $type, $amount, $userId) {
    // Delete old entries and reinsert (simplest sync logic)
    $pdo->prepare("DELETE FROM ledger_entries WHERE voucher_id = :vid AND company_id = :cid AND accounting_year_id = :yid")
        ->execute([':vid' => $voucherId, ':cid' => $companyId, ':yid' => $yearId]);

    // Create new entry based on type
    $desc = ucfirst($type) . " updated voucher entry";
    $pdo->prepare("
        INSERT INTO ledger_entries (voucher_id, company_id, accounting_year_id, entry_date, description, debit_account_id, credit_account_id, amount, created_by)
        VALUES (:vid, :cid, :yid, CURDATE(), :desc, 1, 2, :amount, :uid)
    ")->execute([
        ':vid' => $voucherId,
        ':cid' => $companyId,
        ':yid' => $yearId,
        ':desc' => $desc,
        ':amount' => $amount,
        ':uid' => $userId
    ]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Voucher</title>
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
            <h3>✏️ Edit Voucher</h3>
            <a href="voucher_list.php" class="btn btn-outline-secondary btn-sm">📋 Voucher List</a>
        </div>

        <?= $message ?>

        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Voucher Type</label>
                <select name="voucher_type" class="form-select" required>
                    <?php foreach ($types as $t): ?>
                        <option value="<?= $t ?>" <?= ($voucher['voucher_type'] === $t) ? 'selected' : '' ?>>
                            <?= ucfirst($t) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Voucher Date</label>
                <input type="date" name="voucher_date" value="<?= htmlspecialchars($voucher['voucher_date']) ?>" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Category</label>
                <select name="category" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= htmlspecialchars($c) ?>" <?= ($voucher['category_name'] === $c) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($c) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Payment Mode</label>
                <select name="payment_mode" class="form-select" required>
                    <option value="">Select Mode</option>
                    <?php foreach ($paymentModes as $m): ?>
                        <option value="<?= htmlspecialchars($m) ?>" <?= ($voucher['payment_method'] === $m) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Paid To / Received From</label>
                <input type="text" name="paid_to" value="<?= htmlspecialchars($voucher['paid_to']) ?>" class="form-control" required>
            </div>

            <div class="col-md-4">
                <label class="form-label">Amount (₹)</label>
                <input type="number" step="0.01" name="amount" value="<?= htmlspecialchars($voucher['amount']) ?>" class="form-control" required>
            </div>

            <div class="col-12">
                <label class="form-label">Narration / Remarks</label>
                <textarea name="narration" class="form-control" rows="2"><?= htmlspecialchars($voucher['particulars'] ?? '') ?></textarea>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary">💾 Update Voucher</button>
                <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-secondary">🏠 Back to Dashboard</a>
            </div>
        </form>
    </div>
</div>
</body>
</html>
