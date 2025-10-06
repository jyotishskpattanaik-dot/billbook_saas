<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    // ✅ Fetch distinct sales headers from bill_summary
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
  <title>Sales Bills</title>
  <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
<div class="container">
    <h3 class="mb-4">📋 Sales Bills</h3>

    <table class="table table-bordered table-striped table-sm">
        <thead class="thead-dark">
            <tr>
                <th>Bill No</th>
                <th>Date</th>
                <th>Customer</th>
                <th class="text-right">Amount (₹)</th>
                <th>Edit</th>
                <th>Delete</th>
                <th>Print</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($sales)): ?>
            <?php foreach($sales as $s): ?>
                <tr>
                    <td><?= htmlspecialchars($s['bill_no']) ?></td>
                    <td><?= htmlspecialchars($s['bill_date']) ?></td>
                    <td><?= htmlspecialchars($s['customer_name']) ?></td>
                    <td class="text-right"><?= number_format($s['bill_amount'], 2) ?></td>

                    <td>
                        <a href="edit_bill.php?bill_no=<?= urlencode($s['bill_no']) ?>" 
                           class="btn btn-sm btn-primary">✏ Edit</a>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-danger deleteBtn" data-id="<?= $s['bill_no'] ?>">🗑 Delete</button>
                    </td>
                    <td>
                        <a href="../print/bill_print.php?bill_no=<?= urlencode($s['bill_no']) ?>" 
                           target="_blank"
                           class="btn btn-sm btn-info">🖨 Print</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="7" class="text-center text-muted">No sales found.</td>
            </tr>
        <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-3">
        <a href="create_bill.php" class="btn btn-success">➕ New Bill</a>
        <a href="../dashboard.php" class="btn btn-secondary">🏠 Back</a>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script>
$(document).on("click", ".deleteBtn", function(){
    if(confirm("⚠ Are you sure you want to delete this bill? This will rollback stock.")){
        let pid = $(this).data("id");
        // Placeholder – will wire up later
        alert("Delete handler not yet implemented. Bill ID = " + pid);
    }
});
</script>
</body>
</html>
