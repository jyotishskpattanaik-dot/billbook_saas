<?php
session_start();
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../includes/sanitize.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Add Customer</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Bootstrap 5 -->
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
      ➕ Add New Customer
    </div>
    <div class="card-body">
      <form id="customerForm" method="POST" action="../save/save_customer.php">
        <div class="row g-3">
          <!-- Supplier Name -->
          <div class="col-md-6">
            <label for="customer_name" class="form-label">Customer Name</label>
            <input type="text" class="form-control" id="customer_name" name="customer_name" required>
            <span id="customer_error" class="error-text">⚠️ Customer already exists.</span>
          </div>
          <!-- Contact -->
          <div class="col-md-6">
            <label for="mobile_number" class="form-label">Mobile  Number</label>
            <input type="text" class="form-control" id="mobile_number" name="mobile_number" required pattern="\d{10}">
            <span id="contact_error" class="error-text">⚠️ Contact already exists.</span>
          </div>
          <!-- Address -->
          
        <!-- Buttons -->
        <div class="mt-4 text-center">
          <button type="submit" class="btn btn-primary" id="submitBtn">💾 Save Customer</button>
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
        let name = $("#customer_name").val().trim();
        let contact = $("#mobile_number").val().trim();

        if (name.length < 2 && contact.length < 10) return;

        $.post("../ajax/check_customer.php", {customer_name: name, mobile_number: contact}, function(resp) {
            if (resp.exists_name) {
                $("#customer_error").show();
            } else {
                $("#customer_error").hide();
            }

            if (resp.exists_contact) {
                $("#mobile_error").show();
            } else {
                $("#mobile_error").hide();
            }

            // Disable submit if duplicates exist
            if (resp.exists_name || resp.exists_contact) {
                $("#submitBtn").prop("disabled", true);
            } else {
                $("#submitBtn").prop("disabled", false);
            }
        }, "json");
    }

    $("#customer_name, #mobile_number").on("blur keyup", checkDuplicate);
});
</script>
</body>
</html>
