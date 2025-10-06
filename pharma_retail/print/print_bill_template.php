<?php

// print_bill_template.php

function renderInvoiceTemplate($company, $bill, $products, $bill_no, $forPdf = false) {
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Invoice - <?= htmlspecialchars($bill_no) ?></title>
  <?php if (!$forPdf): ?>
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
  <?php endif; ?>
  <style>
    body { font-size: 12px; }
    .invoice-box { max-width: 800px; margin: auto; padding: 20px; border: 1px solid #ddd; }
    .company-header { text-align: center; margin-bottom: 10px; }
    .company-header h2 { margin: 0; font-weight: bold; text-transform: uppercase; }
    .table th, .table td { padding: 4px; font-size: 12px; }
    .footer-note { margin-top: 20px; font-size: 10px; text-align: center; }
    @media print {
        .no-print { display: none; }
        body { margin: 0; }
    }
  </style>
</head>
<body>
<div class="invoice-box">

  <!-- TAX INVOICE HEADER -->
  <div class="company-header">
    <h2>TAX INVOICE</h2>
  </div>

  <!-- Company + Customer Header -->
  <div class="row mb-2">
    <div class="col-6 text-left">
      <h4 class="font-weight-bold mb-1"><?= htmlspecialchars($company['company_name']) ?></h4>
      <div style="font-size:11px; line-height:1.4;">
        GSTIN: <?= htmlspecialchars($company['gst_number']) ?> | DL No: <?= htmlspecialchars($company['dl_number']) ?><br>
        FSSAI: <?= htmlspecialchars($company['fssai_number']) ?><br>
        <?= htmlspecialchars($company['address']) ?><br>
        Contact: <?= htmlspecialchars($company['contact_no']) ?>
      </div>
    </div>

    <div class="col-6 text-right">
      <div style="font-size:12px; line-height:1.6;">
        <strong>Bill No:</strong> <?= htmlspecialchars($bill['bill_no']) ?><br>
        <strong>Date:</strong> <?= htmlspecialchars($bill['bill_date']) ?><br>
        <strong>Payment:</strong> <?= htmlspecialchars($bill['bill_type']) ?><br>
        <strong>Customer:</strong> <?= htmlspecialchars($bill['customer_name']) ?><br>
        <strong>Mobile:</strong> <?= htmlspecialchars($bill['mobile_number']) ?><br>
        <strong>Doctor:</strong> <?= htmlspecialchars($bill['doctor'] ?? '-') ?>
      </div>
    </div>
  </div>

  <hr style="border-top:1px solid #333; margin:8px 0;">

  <!-- Product Table -->
  <table class="table table-bordered">
    <thead class="thead-light">
      <tr>
        <th>#</th>
        <th>Product</th>
        <th>Batch</th>
        <th>Pack</th>
        <th>HSN</th>
        <th>Exp</th>
        <th>MRP</th>
        <th>Qty</th>
        <th>Rate</th>
        <th>Disc %</th>
        <th>GST %</th>
        <th>Amount</th>
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

  <!-- Totals -->
  <table class="table table-bordered">
    <tr>
      <th>Total Taxable</th><td>₹ <?= number_format($bill['grand_total'] - $bill['gst_amount'],2) ?></td>
      <th>Total GST</th><td>₹ <?= number_format($bill['gst_amount'],2) ?></td>
      <th>Total Discount</th><td>₹ <?= number_format($bill['discount_amount'],2) ?></td>
      <th>Grand Total</th><td><strong>₹ <?= number_format($bill['grand_total'],2) ?></strong></td>
    </tr>
  </table>

  <div class="footer-note">
    *** THANK YOU! VISIT AGAIN ***
  </div>

  <?php if (!$forPdf): ?>
  <div class="text-center no-print mt-3">
    <button class="btn btn-primary" onclick="window.print()">🖨 Print</button>
    <a href="../salesnbilling/create_bill.php" class="btn btn-secondary">🔙 Back</a>
    <a href="generate_pdf.php?bill_no=<?= urlencode($bill_no) ?>" class="btn btn-success">📄 Generate PDF</a>
  </div>
  <?php endif; ?>
</div>
</body>
</html>
<?php
}
