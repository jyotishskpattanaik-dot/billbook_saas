<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$pdo = getModulePDO();
$mainPdo = getMainPDO();

$bill_no = $_GET['bill_no'] ?? '';
if (empty($bill_no)) {
    die("Bill number missing.");
}

// --- Fetch bill summary ---
$stmt = $pdo->prepare("SELECT * FROM bill_summary WHERE bill_no = ? AND company_id = ? AND accounting_year_id = ?");
$stmt->execute([$bill_no, $COMPANY_ID, $YEAR_ID]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$bill) {
    die("Bill not found.");
}

// --- Fetch products ---
$stmt = $pdo->prepare("SELECT * FROM sale_bill_products WHERE bill_no = ? AND sale_id = ?");
$stmt->execute([$bill_no, $bill['id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Fetch company details from MAIN DB ---
$stmt = $mainPdo->prepare("SELECT company_name, gst_number, dl_number, fssai_number, address, contact_no 
                           FROM companies WHERE id = ?");
$stmt->execute([$COMPANY_ID]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Build HTML ---
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-size: 11px; font-family: Arial, sans-serif; }
        .invoice-box { width: 100%; }
        .header, .footer { text-align: center; }
        .header h3 { margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #444; padding: 4px; text-align: left; font-size: 11px; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <div class="header">
        <h3><?= htmlspecialchars($company['company_name']) ?></h3>
        <div style="font-size:10px;"><?= htmlspecialchars($company['address']) ?><br>
        GSTIN: <?= htmlspecialchars($company['gst_number']) ?> | DL No: <?= htmlspecialchars($company['dl_number']) ?><br>
        FSSAI: <?= htmlspecialchars($company['fssai_number']) ?><br>
        Contact: <?= htmlspecialchars($company['contact_no']) ?></div>
    </div>

    <hr>

    <div class="text-center">
        <h4 style="text-decoration:underline;">TAX INVOICE</h4>
    </div>

    <table>
        <tr>
            <td><strong>Bill No:</strong> <?= htmlspecialchars($bill['bill_no']) ?></td>
            <td><strong>Date:</strong> <?= htmlspecialchars($bill['bill_date']) ?></td>
            <td><strong>Payment:</strong> <?= htmlspecialchars($bill['bill_type']) ?></td>
        </tr>
        <tr>
            <td><strong>Customer:</strong> <?= htmlspecialchars($bill['customer_name']) ?></td>
            <td><strong>Mobile:</strong> <?= htmlspecialchars($bill['mobile_number']) ?></td>
            <td><strong>Doctor:</strong> <?= htmlspecialchars($bill['doctor'] ?? '-') ?></td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>#</th><th>Product</th><th>Batch</th><th>Pack</th><th>HSN</th>
                <th>Exp</th><th>MRP</th><th>Qty</th><th>Rate</th><th>Disc %</th><th>GST %</th><th>Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php $i=1; foreach ($products as $p): ?>
            <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($p['product_name']) ?></td>
                <td><?= htmlspecialchars($p['batch_no']) ?></td>
                <td><?= htmlspecialchars($p['pack']) ?></td>
                <td><?= htmlspecialchars($p['hsn_code']) ?></td>
                <td><?= htmlspecialchars($p['expiry_date']) ?></td>
                <td><?= number_format($p['mrp'],2) ?></td>
                <td><?= $p['quantity'] ?></td>
                <td><?= number_format($p['taxable_amount'] / max(1,$p['quantity']),2) ?></td>
                <td><?= $p['discount'] ?>%</td>
                <td><?= $p['gst'] ?>%</td>
                <td><?= number_format($p['net_amount'],2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <table>
        <tr>
            <th>Total Taxable</th><td><?= number_format($bill['grand_total'] - $bill['gst_amount'],2) ?></td>
            <th>Total GST</th><td><?= number_format($bill['gst_amount'],2) ?></td>
            <th>Total Discount</th><td><?= number_format($bill['discount_amount'],2) ?></td>
            <th>Grand Total</th><td><strong><?= number_format($bill['grand_total'],2) ?></strong></td>
        </tr>
    </table>

    <div class="footer">
        <p>*** THANK YOU! VISIT AGAIN ***</p>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// --- Configure Dompdf ---
$options = new Options();
$options->set('isRemoteEnabled', true);
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// --- Save to folder ---
$output = $dompdf->output();
$savePath = __DIR__ . "/generated_bills/";
if (!is_dir($savePath)) {
    mkdir($savePath, 0777, true);
}
$filePath = $savePath . "Bill_" . $bill_no . ".pdf";
file_put_contents($filePath, $output);

// --- Stream to browser ---
$dompdf->stream("Bill_$bill_no.pdf", ["Attachment" => true]);
exit;
