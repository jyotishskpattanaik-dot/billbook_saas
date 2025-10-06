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

<div style="width: 320px; margin: auto; font-family: monospace; border: 1px dashed #000; padding: 10px;">
    <div style="text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px;">
        <h4 style="margin:0;">🧾 Pharmacy Bill</h4>
        <small>Bill No: <?= htmlspecialchars($summary['bill_no']) ?> | Date: <?= date("d-m-Y", strtotime($summary['bill_date'])) ?></small>
    </div>

    <!-- Customer -->
    <p style="margin:5px 0;">
        <strong>CUSTOMER:</strong> <?= htmlspecialchars($summary['customer_name']) ?><br>
        <strong>MOB:</strong> <?= htmlspecialchars($summary['mobile_number']) ?>
    </p>

    <!-- Product List -->
    <table style="width:100%; border-collapse: collapse; font-size: 12px;">
        <thead>
            <tr style="border-bottom:1px solid #000;">
                <th align="left">Item</th>
                <th align="center">Qty</th>
                <th align="right">Amt</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td align="center"><?= $row['quantity'] ?></td>
                <td align="right"><?= number_format($row['net_amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div style="border-top:1px solid #000; margin-top:5px; padding-top:5px; font-size: 13px;">
        <p style="margin:0;">
            Discount: ₹<?= number_format($summary['discount_amount'], 2) ?><br>
            GST: ₹<?= number_format($summary['gst_amount'], 2) ?><br>
            <strong>GRAND TOTAL: ₹<?= number_format($summary['grand_total'], 2) ?></strong>
        </p>
    </div>

    <!-- Footer -->
    <div style="text-align: center; margin-top:10px; border-top:1px dashed #000; padding-top:5px;">
        <small>Thank you! Visit Again 🙏</small>
    </div>
</div>
