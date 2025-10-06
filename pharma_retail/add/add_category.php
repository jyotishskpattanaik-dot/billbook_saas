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
      ➕ Add New Category
    </div>
    <div class="card-body">
      <form id="categoryForm" method="POST" action="../save/save_category.php">
        <div class="row g-3">
          <!-- Supplier Name -->
          <div class="col-md-6">
            <label for="category" class="form-label">Category Name</label>
            <input type="text" class="form-control" id="category" name="category" required>
            <span id="supplier_error" class="error-text">⚠️ category already exists.</span>
          </div>
          
        <!-- Buttons -->
        <div class="mt-4 text-center">
          <button type="submit" class="btn btn-primary" id="submitBtn">💾 Save Category</button>
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
        let name = $("#category").val().trim();
        let contact = $("#category").val().trim();

        if (name.length < 2 && contact.length < 10) return;

        $.post("../ajax/check_category.php", {category}, function(resp) {
            if (resp.exists_name) {
                $("#category_error").show();
            } else {
                $("#category_error").hide();
            }

            // Disable submit if duplicates exist
            if (resp.exists_name || resp.exists_contact) {
                $("#submitBtn").prop("disabled", true);
            } else {
                $("#submitBtn").prop("disabled", false);
            }
        }, "json");
    }

    $("#category").on("blur keyup", checkDuplicate);
});
</script>
 </body>
</html>
