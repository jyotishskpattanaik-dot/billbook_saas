<?php
require_once __DIR__ . '/../../includes/init.php'; // adjust path if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

    $company_id = $COMPANY_ID ?? ($_SESSION['company_id'] ?? null);
    $year_id    = $YEAR_ID ?? ($_SESSION['financial_year_id'] ?? null);
    $created_by = $CURRENT_USER ?? ($_SESSION['user_id'] ?? 'SYSTEM');

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company/year in session");
    }

try {
    $pdo = getModulePDO(); // get your module db connection

    $stmt = $pdo->prepare("
        SELECT 
            product_id,
            product_name,
            avg_monthly_sales,
            current_stock,
            reorder_level,
            reorder_quantity,
            DATE_FORMAT(generated_at, '%d-%m-%Y %H:%i') AS generated_at
        FROM reorder_suggestions
        WHERE company_id = ?
        ORDER BY reorder_quantity DESC
    ");
    $stmt->execute([$company_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("⚠️ Error loading suggestions: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reorder Suggestions</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        body { background: #f9f9f9; }
        .card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .table th { background: #f1f3f4; }
        .badge-low { background-color: #ffc107; }
        .badge-critical { background-color: #dc3545; }
        .badge-ok { background-color: #28a745; }
    </style>
</head>
<body class="p-4">
<div class="container">
    <div class="card">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Reorder Suggestions</h5>
            <button onclick="window.location.reload()" class="btn btn-light btn-sm">🔄 Refresh</button>
        </div>
        <div class="card-body">
            <?php if (empty($rows)): ?>
                <div class="alert alert-info">No reorder suggestions found. Generate them first.</div>
            <?php else: ?>
                <table id="reorderTable" class="table table-striped table-hover align-middle">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Product Name</th>
                            <th>Avg. Monthly Sales</th>
                            <th>Current Stock</th>
                            <th>Reorder Level</th>
                            <th>Suggested Qty</th>
                            <th>Generated At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $i => $r): 
                            $statusClass = ($r['reorder_quantity'] >= 10) ? 'badge-critical' : 
                                           (($r['reorder_quantity'] >= 3) ? 'badge-low' : 'badge-ok');
                            $statusText = ($r['reorder_quantity'] > 0) ? 'Reorder Needed' : 'Sufficient';
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= htmlspecialchars($r['product_name']) ?></td>
                            <td><?= round($r['avg_monthly_sales']) ?></td>
                            <td><?= round($r['current_stock']) ?></td>
                            <td><?= round($r['reorder_level']) ?></td>
                            <td><strong><?= round($r['reorder_quantity']) ?></strong></td>
                            <td><?= $r['generated_at'] ?></td>
                            <td><span class="badge <?= $statusClass ?>"><?= $statusText ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#reorderTable').DataTable({
            pageLength: 10,
            order: [[5, 'desc']],
            responsive: true
        });
    });
</script>
</body>
</html>
