<?php
require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../includes/init.php';

$pdo = getModulePDO();
$bill_no = $_GET['bill_no'] ?? null;

if (!$bill_no) {
    die("Invalid Bill Number");
}

// 🔹 Fetch Bill Summary
$sql_summary = "SELECT * FROM bill_summery WHERE bill_no = :bill_no";
$stmt_summary = $pdo->prepare($sql_summary);
$stmt_summary->execute([':bill_no' => $bill_no]);
$summary = $stmt_summary->fetch(PDO::FETCH_ASSOC);

if (!$summary) {
    die("Bill not found");
}

// 🔹 Fetch Products
$sql_products = "SELECT * FROM sale_bill_products WHERE bill_no = :bill_no";
$stmt_products = $pdo->prepare($sql_products);
$stmt_products->execute([':bill_no' => $bill_no]);
$products = $stmt_products->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card shadow">
    <div class="card-header bg-primary text-white">
        <h4 class="mb-0">Invoice #<?= htmlspecialchars($summary['bill_no']) ?></h4>
    </div>
    <div class="card-body">

        <!-- Customer + Bill Info -->
        <div class="row mb-3">
            <div class="col-md-6">
                <h5>Customer Details</h5>
                <p>
                    <strong>Name:</strong> <?= htmlspecialchars($summary['customer_name']) ?><br>
                    <strong>Mobile:</strong> <?= htmlspecialchars($summary['mobile_number']) ?><br>
                </p>
            </div>
            <div class="col-md-6 text-right">
                <h5>Bill Info</h5>
                <p>
                    <strong>Date:</strong> <?= date("d-m-Y", strtotime($summary['bill_date'])) ?><br>
                    <strong>Bill Type:</strong> <?= htmlspecialchars($summary['bill_type']) ?><br>
                </p>
            </div>
        </div>

        <!-- Products Table -->
        <table class="table table-bordered">
            <thead class="thead-dark">
                <tr>
                    <th>#</th>
                    <th>Product</th>
                    <th>Batch</th>
                    <th>Expiry</th>
                    <th>Pack</th>
                    <th>Qty</th>
                    <th>MRP</th>
                    <th>Discount</th>
                    <th>Taxable</th>
                    <th>GST</th>
                    <th>Net</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $i => $row): ?>
                <tr>
                    <td><?= $i+1 ?></td>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td><?= htmlspecialchars($row['batch_no']) ?></td>
                    <td><?= htmlspecialchars($row['expiry_date']) ?></td>
                    <td><?= htmlspecialchars($row['pack']) ?></td>
                    <td><?= $row['quantity'] ?></td>
                    <td><?= number_format($row['mrp'], 2) ?></td>
                    <td><?= number_format($row['discount_amount'], 2) ?></td>
                    <td><?= number_format($row['taxable_amount'], 2) ?></td>
                    <td><?= number_format($row['gst_amount'], 2) ?></td>
                    <td><?= number_format($row['net_amount'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="row mt-3">
            <div class="col-md-6"></div>
            <div class="col-md-6 text-right">
                <h5>Summary</h5>
                <p>
                    <strong>Discount:</strong> ₹<?= number_format($summary['discount_amount'], 2) ?><br>
                    <strong>GST:</strong> ₹<?= number_format($summary['gst_amount'], 2) ?><br>
                    <strong>Grand Total:</strong> ₹<?= number_format($summary['grand_total'], 2) ?><br>
                </p>
            </div>
        </div>
    </div>
    <div class="card-footer text-center bg-light">
        <small>Thank you for your purchase!</small>
    </div>
</div>
