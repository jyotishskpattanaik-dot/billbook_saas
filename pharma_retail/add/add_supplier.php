<?php
session_start();
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../includes/sanitize.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Supplier</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="/assets/css/page_style.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background-color: #f4f8fb;
      font-family: "Segoe UI", sans-serif;
    }
    .card {
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .card-header {
      background: #007bff;
      color: #fff;
      font-size: 18px;
      font-weight: 600;
      text-align: center;
      border-radius: 12px 12px 0 0;
    }
    label {
      font-weight: 500;
      color: #333;
    }
    .btn-primary {
      background: #007bff;
      border: none;
      border-radius: 6px;
      padding: 10px 20px;
    }
    .btn-primary:hover {
      background: #0056b3;
    }
    .btn-secondary {
      border-radius: 6px;
    }
    .error-text {
      font-size: 0.9rem;
      color: red;
      display: none;
    }
  </style>
</head>
<body>
<div class="container mt-0">
     <div class="card">
    <div class="card-header">
      ➕ Add New Supplier
    </div>
    <div class="card-body">
      <form id="supplierForm" method="POST" action="../save/save_supplier.php">
        <div class="row g-3">
          <!-- Supplier Name -->
          <div class="col-md-6">
            <label for="supplier_name" class="form-label">Supplier Name</label>
            <input type="text" class="form-control" id="supplier_name" name="supplier_name" required>
            <span id="supplier_error" class="error-text">⚠️ Supplier already exists.</span>
          </div>
          <!-- Contact -->
          <div class="col-md-6">
            <label for="contact_number" class="form-label">Contact Number</label>
            <input type="text" class="form-control" id="contact_number" name="contact_number" required pattern="\d{10}">
            <span id="contact_error" class="error-text">⚠️ Contact already exists.</span>
          </div>
          <!-- Address -->
          <div class="col-md-6">
            <label for="address" class="form-label">Address</label>
            <input type="text" class="form-control" id="address" name="address">
          </div>
          <!-- District -->
          <div class="col-md-6">
            <label for="district" class="form-label">District</label>
            <input type="text" class="form-control" id="district" name="district">
          </div>
          <!-- State -->
          <div class="col-md-6">
            <label for="state" class="form-label">State</label>
            <input type="text" class="form-control" id="state" name="state">
          </div>
          <!-- DL Number -->
          <div class="col-md-6">
            <label for="dl_no" class="form-label">DL Number</label>
            <input type="text" class="form-control" id="dl_no" name="dl_no">
          </div>
          <!-- GSTIN -->
          <div class="col-md-6">
            <label for="gstin_no" class="form-label">GSTIN</label>
            <input type="text" class="form-control" id="gstin_no" name="gstin_no">
          </div>
          <!-- FSSAI -->
          <div class="col-md-6">
            <label for="fssai_no" class="form-label">FSSAI No.</label>
            <input type="text" class="form-control" id="fssai_no" name="fssai_no">
          </div>
          <!-- Email -->
          <div class="col-md-6">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email">
          </div>
        </div>

        <!-- Buttons -->
        <div class="mt-4 text-center">
          <button type="submit" class="btn btn-primary" id="submitBtn">💾 Save Supplier</button>
          <a href="../dashboard.php" class="btn btn-secondary">🏠 Back to Dashboard</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(function() {
    // Live duplicate check for supplier name + contact number
    function checkDuplicate() {
        let name = $("#supplier_name").val().trim();
        let contact = $("#contact_number").val().trim();

        if (name.length < 2 && contact.length < 10) return;

        $.post("../ajax/check_supplier.php", {supplier_name: name, contact_number: contact}, function(resp) {
            if (resp.exists_name) {
                $("#supplier_error").show();
            } else {
                $("#supplier_error").hide();
            }

            if (resp.exists_contact) {
                $("#contact_error").show();
            } else {
                $("#contact_error").hide();
            }

            // Disable submit if duplicates exist
            if (resp.exists_name || resp.exists_contact) {
                $("#submitBtn").prop("disabled", true);
            } else {
                $("#submitBtn").prop("disabled", false);
            }
        }, "json");
    }

    $("#supplier_name, #contact_number").on("blur keyup", checkDuplicate);
});
</script>
 </body>
</html>
