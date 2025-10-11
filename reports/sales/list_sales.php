<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    $sql = "
        SELECT 
            sd.bill_no,
            sd.bill_date,
            sd.customer_name,
            sd.grand_total AS bill_amount
        FROM bill_summary sd
        WHERE sd.company_id = :company_id 
          AND sd.accounting_year_id = :year_id
        ORDER BY sd.bill_date DESC, sd.bill_no DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $COMPANY_ID,
        ':year_id'    => $YEAR_ID
    ]);
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sales Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        h3 { display: inline-block; }
        .export-btn { margin-left: 10px; }
    </style>
</head>
<body class="p-3">
<div class="container">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📋 Sales Bills</h3>

        <!-- Export Dropdown -->
        <div class="dropdown">
            <button class="btn btn-info dropdown-toggle" type="button" id="exportDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-file-export"></i> Export
            </button>
            <ul class="dropdown-menu" aria-labelledby="exportDropdown">
                <li>
                    <a class="dropdown-item" target="_blank" href="../../includes/export_report.php?report=sales&format=excel">
                        <i class="fas fa-file-excel"></i> Excel
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" target="_blank" href="../../includes/export_report.php?report=sales&format=csv">
                        <i class="fas fa-file-csv"></i> CSV
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" target="_blank" href="../../includes/export_report.php?report=sales&format=pdf">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                </li>
                <li>
                    <a class="dropdown-item" target="_blank" href="../../includes/export_report.php?report=sales&format=word">
                        <i class="fas fa-file-word"></i> Word
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <table class="table table-bordered table-striped table-sm">
        <thead class="table-dark">
            <tr>
                <th>Bill No</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-end">Amount (₹)</th>
                <th>Edit</th>
                <th>Delete</th>
                <th>Print</th>
                <th>View</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($sales)): ?>
            <?php foreach ($sales as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['bill_no']) ?></td>
                    <td><?= htmlspecialchars($s['bill_date']) ?></td>
                    <td><?= htmlspecialchars($s['customer_name']) ?></td>
                    <td class="text-end"><?= number_format($s['bill_amount'], 2) ?></td>
                    <td>
                        <a href="../../pharma_retail/salesnbilling/edit_bill.php?bill_no=<?= urlencode($s['bill_no']) ?>" class="btn btn-sm btn-primary">✏ Edit</a>
                    </td>
                    <td>
                        <a href="../../pharma_retail/salesnbilling/delete_bill.php?bill_no=<?= urlencode($s['bill_no']) ?>" 
                           class="btn btn-sm btn-danger"
                           onclick="return confirm('⚠ Are you sure you want to delete this bill? This will rollback stock.')">
                           🗑 Delete
                        </a>
                    </td>
                    <td>
                        <a href="../../print/bill_print.php?bill_no=<?= urlencode($s['bill_no']) ?>" target="_blank" class="btn btn-sm btn-info">🖨 Print</a>
                    </td>
                    <td>
                        <a href="../../pharma_retail/salesnbilling/view_sale_bill.php?bill_no=<?= urlencode($s['bill_no']) ?>" target="_blank" class="btn btn-sm btn-success">👁 View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="8" class="text-center text-muted">No sales found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-3">
        <a href="../../pharma_retail/salesnbilling/create_bill.php" class="btn btn-success">➕ New Bill</a>
        <a href="../../pharma_retail/dashboard.php" class="btn btn-secondary">🏠 Back</a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
