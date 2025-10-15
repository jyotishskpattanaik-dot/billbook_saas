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

// --- Date filters ---
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$selectedMode = $_GET['mode'] ?? 'all';

// --- Mode condition ---
$modeCondition = ($selectedMode !== 'all') ? "AND payment_mode = :payment_mode" : "";

// --- Fetch entries ---
$query = "
    SELECT 
        entry_date, 
        description, 
        cash_in, 
        cash_out, 
        payment_mode, 
        closing_balance
    FROM cash_book
    WHERE company_id = :company_id 
      AND accounting_year_id = :year_id
      AND entry_date BETWEEN :start_date AND :end_date
      $modeCondition
    ORDER BY entry_date DESC, id DESC
";

$stmt = $pdo->prepare($query);

$params = [
    ':company_id' => $companyId,
    ':year_id'    => $yearId,
    ':start_date' => $startDate,
    ':end_date'   => $endDate
];

if ($selectedMode !== 'all') {
    $params[':payment_mode'] = $selectedMode;
}

$stmt->execute($params);
$entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch latest balances per payment_mode ---
$stmt = $pdo->prepare("
    SELECT payment_mode, MAX(closing_balance) AS last_balance
    FROM cash_book
    WHERE company_id = :company_id AND accounting_year_id = :year_id
    GROUP BY payment_mode
");
$stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
$balances = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cash Book Report</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background-color: #f8f9fa; }
.table th, .table td { vertical-align: middle !important; }
.card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
.balance-box { font-size: 1rem; font-weight: 500; }
</style>
</head>
<body class="p-3">

<div class="container-fluid mt-3">
  <div class="card p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="mb-0">📘 Cash Book Report</h4>
      <a href="<?= getModuleDashboardUrl() ?>" class="btn btn-outline-secondary btn-sm">
          🏠 Back to <?= ucfirst(str_replace('_', ' ', $_SESSION['user_module'] ?? 'Main')) ?> Dashboard
      </a>
    </div>

    <!-- Filters -->
    <form method="GET" class="row g-2 mb-3 align-items-end">
      <div class="col-md-2">
        <label class="form-label">From</label>
        <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">To</label>
        <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label">Mode</label>
        <select name="mode" class="form-select form-select-sm">
          <option value="all" <?= $selectedMode === 'all' ? 'selected' : '' ?>>All</option>
          <option value="cash" <?= $selectedMode === 'cash' ? 'selected' : '' ?>>Cash</option>
          <option value="bank" <?= $selectedMode === 'bank' ? 'selected' : '' ?>>Bank</option>
          <option value="upi" <?= $selectedMode === 'upi' ? 'selected' : '' ?>>UPI</option>
        </select>
      </div>
      <div class="col-md-2">
        <button type="submit" class="btn btn-primary btn-sm">🔍 Filter</button>
      </div>
      <div class="col-md-4 text-end">
        <button type="button" class="btn btn-success btn-sm" onclick="exportTableToCSV()">⬇ Export CSV</button>
        <button type="button" class="btn btn-danger btn-sm" onclick="window.print()">🖨 Print / PDF</button>
      </div>
    </form>

    <!-- Balances -->
    <div class="row mb-3">
      <?php foreach ($balances as $mode => $bal): ?>
        <div class="col-md-3 col-6 mb-2">
          <div class="alert alert-info text-center p-2 balance-box">
            <?= htmlspecialchars(ucfirst($mode)) ?> Balance: ₹<?= number_format($bal, 2) ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Table -->
    <div class="table-responsive">
      <table id="cashBookTable" class="table table-striped table-bordered">
        <thead class="table-dark">
          <tr>
            <th>Date</th>
            <th>Description</th>
            <th class="text-end">Cash In (₹)</th>
            <th class="text-end">Cash Out (₹)</th>
            <th>Payment Mode</th>
            <th class="text-end">Closing Balance (₹)</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($entries) === 0): ?>
            <tr><td colspan="6" class="text-center text-muted">No entries found for selected filters.</td></tr>
          <?php else: ?>
            <?php foreach ($entries as $row): ?>
              <tr>
                <td><?= htmlspecialchars($row['entry_date']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td class="text-end text-success"><?= $row['cash_in'] > 0 ? number_format($row['cash_in'], 2) : '' ?></td>
                <td class="text-end text-danger"><?= $row['cash_out'] > 0 ? number_format($row['cash_out'], 2) : '' ?></td>
                <td><?= htmlspecialchars($row['payment_mode']) ?></td>
                <td class="text-end fw-bold"><?= number_format($row['closing_balance'], 2) ?></td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Export to CSV -->
<script>
function exportTableToCSV() {
  const rows = document.querySelectorAll("table tr");
  let csv = [];
  rows.forEach(row => {
    const cols = row.querySelectorAll("th, td");
    const rowData = Array.from(cols).map(col => `"${col.innerText.replace(/"/g, '""')}"`);
    csv.push(rowData.join(","));
  });
  const csvString = csv.join("\n");
  const blob = new Blob([csvString], { type: "text/csv" });
  const link = document.createElement("a");
  link.href = URL.createObjectURL(blob);
  link.download = "cash_book_report.csv";
  link.click();
}
</script>

</body>
</html>
