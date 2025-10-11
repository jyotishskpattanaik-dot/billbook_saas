<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    // ✅ Fetch purchase headers with totals
    $sql = "
        SELECT 
            p.purchase_id,
            p.purchase_date,
            p.invoice_no,
            p.supplier_name,
            COALESCE(SUM(sd.net_amount), 0) AS bill_amount
        FROM purchase_details p
        LEFT JOIN stock_details sd 
               ON p.purchase_id = sd.purchase_id
              AND p.company_id = sd.company_id
              AND p.accounting_year_id = sd.accounting_year_id
        WHERE p.company_id = :company_id
          AND p.accounting_year_id = :year_id
        GROUP BY p.purchase_id, p.purchase_date, p.invoice_no, p.supplier_name
        ORDER BY p.purchase_date DESC, p.purchase_id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $COMPANY_ID,
        ':year_id'    => $YEAR_ID
    ]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Purchase Bills</title>
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
<div class="container">
    <h3 class="mb-4">📋 Purchase Bills</h3>

    <table class="table table-bordered table-striped table-sm">
        <thead class="thead-dark">
            <tr>
                <th>Purchase ID</th>
                <th>Date</th>
                <th>Invoice Number</th>
                <th>Supplier</th>
                <th class="text-right">Amount (₹)</th>
                <th>Edit</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($purchases)): ?>
            <?php foreach($purchases as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['purchase_id']) ?></td>
                    <td><?= htmlspecialchars($p['purchase_date']) ?></td>
                    <td><?= htmlspecialchars($p['invoice_no']) ?></td>
                    <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                    <td class="text-right"><?= number_format($p['bill_amount'], 2) ?></td>
                    <td>
                        <a href="../../pharma_retail/purchasenstock/edit_purchase.php?purchase_id=<?= urlencode($p['purchase_id']) ?>" 
                           class="btn btn-sm btn-primary">✏ Edit</a>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger deleteBtn" data-id="<?= $p['purchase_id'] ?>">🗑 Delete</button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center text-muted">No purchases found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-3">
        <a href="../../pharma_retail/purchasenstock/add_new_purchase.php" class="btn btn-success">➕ New Purchase</a>
        <a href="../../pharma_retail/dashboard.php" class="btn btn-secondary">🏠 Back</a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).on("click", ".deleteBtn", function(){
    if(confirm("⚠ Are you sure you want to delete this bill? This will rollback stock.")){
        let pid = $(this).data("id");
        // Placeholder – will wire up later
        alert("Delete handler not yet implemented. Purchase ID = " + pid);
    }
});
</script>
</body>
</html>
