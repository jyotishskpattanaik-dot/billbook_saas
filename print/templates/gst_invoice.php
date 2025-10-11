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

<div style="width: 800px; margin: auto; font-family: Arial, sans-serif; border: 2px solid #000; padding: 15px;">
    
    <!-- Header -->
    <div style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px;">
        <h2 style="margin:0;">💊 Pharmacy Invoice</h2>
        <p style="margin:0;">GSTIN: 22AAAAA0000A1Z5</p>
        <small>123 Main Street, City - PIN, Contact: 99999-99999</small>
    </div>

    <!-- Bill & Customer Info -->
    <div style="margin-top: 10px; display: flex; justify-content: space-between;">
        <div>
            <p><strong>Invoice No:</strong> <?= htmlspecialchars($summary['bill_no']) ?></p>
            <p><strong>Date:</strong> <?= date("d-m-Y", strtotime($summary['bill_date'])) ?></p>
            <p><strong>Payment Mode:</strong> <?= htmlspecialchars($summary['bill_type']) ?></p>
        </div>
        <div>
            <p><strong>Customer:</strong> <?= htmlspecialchars($summary['customer_name']) ?></p>
            <p><strong>Mobile:</strong> <?= htmlspecialchars($summary['mobile_number']) ?></p>
        </div>
    </div>

    <!-- Products Table -->
    <table style="width:100%; border-collapse: collapse; margin-top: 10px; font-size: 13px;">
        <thead>
            <tr style="background: #f2f2f2;">
                <th style="border:1px solid #000;">#</th>
                <th style="border:1px solid #000;">Product</th>
                <th style="border:1px solid #000;">HSN</th>
                <th style="border:1px solid #000;">Pack</th>
                <th style="border:1px solid #000;">Batch</th>
                <th style="border:1px solid #000;">Expiry</th>
                <th style="border:1px solid #000;">MRP</th>
                <th style="border:1px solid #000;">Qty</th>
                <th style="border:1px solid #000;">Taxable</th>
                <th style="border:1px solid #000;">GST%</th>
                <th style="border:1px solid #000;">GST Amt</th>
                <th style="border:1px solid #000;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php $i=1; foreach ($products as $row): ?>
            <tr>
                <td style="border:1px solid #000; text-align:center;"><?= $i++ ?></td>
                <td style="border:1px solid #000;"><?= htmlspecialchars($row['product_name']) ?></td>
                <td style="border:1px solid #000; text-align:center;"><?= htmlspecialchars($row['hsn_code']) ?></td>
                <td style="border:1px solid #000; text-align:center;"><?= htmlspecialchars($row['pack']) ?></td>
                <td style="border:1px solid #000; text-align:center;"><?= htmlspecialchars($row['batch_no']) ?></td>
                <td style="border:1px solid #000; text-align:center;"><?= htmlspecialchars($row['expiry_date']) ?></td>
                <td style="border:1px solid #000; text-align:right;"><?= number_format($row['mrp'],2) ?></td>
                <td style="border:1px solid #000; text-align:center;"><?= $row['quantity'] ?></td>
                <td style="border:1px solid #000; text-align:right;"><?= number_format($row['taxable_amount'],2) ?></td>
                <td style="border:1px solid #000; text-align:center;"><?= $row['gst'] ?>%</td>
                <td style="border:1px solid #000; text-align:right;"><?= number_format($row['gst_amount'],2) ?></td>
                <td style="border:1px solid #000; text-align:right;"><?= number_format($row['net_amount'],2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div style="margin-top: 15px; display: flex; justify-content: space-between;">
        <div>
            <p><strong>Total Items:</strong> <?= count($products) ?></p>
            <p><strong>Discount:</strong> ₹<?= number_format($summary['discount_amount'],2) ?></p>
            <p><strong>GST:</strong> ₹<?= number_format($summary['gst_amount'],2) ?></p>
        </div>
        <div style="text-align:right;">
            <h3>Grand Total: ₹<?= number_format($summary['grand_total'],2) ?></h3>
        </div>
    </div>

    <!-- Footer -->
    <div style="margin-top: 20px; border-top: 2px solid #000; padding-top: 10px; display: flex; justify-content: space-between;">
        <div>
            <p><strong>Customer Signature</strong></p>
        </div>
        <div style="text-align: right;">
            <p><strong>For Pharmacy Name</strong></p>
            <p>Authorised Signatory</p>
        </div>
    </div>
</div>
