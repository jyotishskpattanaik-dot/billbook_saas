<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/navigation_helper.php';
require __DIR__ . '/../accounts/helpers/accounts_functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Verify session context
if (!isset($_SESSION['company_id'], $_SESSION['financial_year_id'])) {
    header("Location: ../public/login.php");
    exit;
}

$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];

// --- Get customer list ---
$stmt = $pdo->prepare("
    SELECT DISTINCT customer_name 
    FROM bill_summary 
    WHERE company_id = ? AND accounting_year_id = ?
    ORDER BY customer_name
");
$stmt->execute([$companyId, $yearId]);
$customers = $stmt->fetchAll(PDO::FETCH_COLUMN);

// --- Detect current module for dashboard link ---
$currentPath = basename(dirname(__DIR__)); // e.g. pharma_retail
$moduleName  = strtolower($currentPath);
$dashboardUrl = "../{$moduleName}/dashboard.php";

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Receive Payment</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f8f9fa; }
    .container { max-width: 700px; }
    .card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
    .form-label { font-weight: 500; }
    .btn-primary { border-radius: 8px; }
  </style>
</head>
<body class="p-4">

<div class="container mt-4">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h5 class="mb-0">💰 Receive Payment</h5>
     <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
            🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
        </a>
    </div>

    <div class="card-body">
      <?php if (!empty($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
      <?php elseif (!empty($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
      <?php endif; ?>

      <form action="process_payment.php" method="POST" class="row g-3">
        <!-- Customer -->
        <div class="col-md-12">
          <label for="customer_name" class="form-label">Customer Name</label>
          <select id="customer_name" name="customer_name" class="form-select" required>
            <option value="">-- Select Customer --</option>
            <?php foreach ($customers as $cust): ?>
              <option value="<?= htmlspecialchars($cust) ?>"><?= htmlspecialchars($cust) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- Bill No (loaded dynamically with JS) -->
        <div class="col-md-12">
          <label for="bill_no" class="form-label">Bill No</label>
          <select id="bill_no" name="bill_no" class="form-select" required>
            <option value="">-- Select Bill --</option>
          </select>
        </div>

        <!-- Amount -->
        <div class="col-md-6">
          <label for="amount" class="form-label">Amount Received (₹)</label>
          <input type="number" step="0.01" id="amount" name="amount" class="form-control" required>
        </div>

        <!-- Mode -->
        <div class="col-md-6">
          <label for="mode" class="form-label">Payment Mode</label>
          <select id="mode" name="mode" class="form-select">
            <option value="cash">Cash</option>
            <option value="bank">Bank</option>
            <option value="upi">UPI</option>
          </select>
        </div>

        <!-- Remarks -->
        <div class="col-md-12">
          <label for="remarks" class="form-label">Remarks</label>
          <textarea id="remarks" name="remarks" rows="2" class="form-control" placeholder="Optional"></textarea>
        </div>

        <div class="col-md-12 text-end">
          <button type="submit" class="btn btn-primary px-4">Record Payment</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// --- Dynamically fetch unpaid bills for the selected customer ---
document.getElementById('customer_name').addEventListener('change', function() {
  const customer = this.value;
  const billSelect = document.getElementById('bill_no');
  billSelect.innerHTML = '<option>Loading...</option>';
  
  fetch(`fetch_unpaid_bills.php?customer=${encodeURIComponent(customer)}`)
    .then(res => res.json())
    .then(data => {
      billSelect.innerHTML = '<option value="">-- Select Bill --</option>';
      data.forEach(bill => {
        const opt = document.createElement('option');
        opt.value = bill.bill_no;
        opt.textContent = `${bill.bill_no} (Balance ₹${bill.balance_amount})`;
        billSelect.appendChild(opt);
      });
    })
    .catch(() => {
      billSelect.innerHTML = '<option>Error loading bills</option>';
    });
});
</script>

</body>
</html>
