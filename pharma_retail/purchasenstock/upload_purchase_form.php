<?php
session_start();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Upload Purchase Bill</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
<div class="container">
  <h3>Upload Purchase Bill</h3>
  <?php if (!empty($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
  <?php endif; ?>
  <form method="post" action="upload_purchase.php" enctype="multipart/form-data">
    <div class="mb-3">
      <label>File (CSV / XLSX / PDF)</label>
      <input type="file" name="bill_file" accept=".csv,.xlsx,.xls,.pdf" class="form-control" required>
    </div>

    <div class="mb-3">
      <label>Supplier (optional, will attempt to auto-detect)</label>
      <input type="text" name="supplier_name" class="form-control">
    </div>

    <div class="mb-3">
      <label>Invoice Number (optional)</label>
      <input type="text" name="invoice_number" class="form-control">
    </div>

    <div class="mb-3">
      <label>Purchase Date (optional)</label>
      <input type="date" name="purchase_date" class="form-control">
    </div>

    <div class="d-flex justify-content-between mt-3">
      <a href="../dashboard.php" class="btn btn-outline-secondary">🏠 Back</a>
      <div>
        
           <button class="btn btn-primary">Upload & Parse</button>
        
      </div>
    </div>
  </form>
</div>
</body>
</html>
