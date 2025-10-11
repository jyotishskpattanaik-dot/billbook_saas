<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../includes/sanitize.php';
include __DIR__ . "/../../includes/page_wrapper.php"; 

$pdo = getModulePDO();

$purchase_id = $_GET['purchase_id'] ?? null;
if (!$purchase_id) {
    die("Invalid Purchase ID");
}

// --- Fetch purchase header ---
$stmt = $pdo->prepare("SELECT * FROM purchase_details WHERE purchase_id = ? LIMIT 1");
$stmt->execute([$purchase_id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$purchase) {
    die("Purchase not found.");
}

// --- Fetch purchase items ---
$stmt = $pdo->prepare("SELECT * FROM stock_details WHERE purchase_id = ?");
$stmt->execute([$purchase_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Supplier list ---
$sql = "SELECT id, supplier_name 
        FROM supplier_details 
        WHERE company_id = :company_id 
        AND accounting_year_id = :year_id
        ORDER BY supplier_name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':company_id' => $COMPANY_ID,
    ':year_id'    => $YEAR_ID
]);
$parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Purchase</title>

<link rel="stylesheet" href="/assets/css/page_style.css">
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<style>
  body { background-color: #f8f9fa; font-size: 0.9rem; }
  .container-fluid { padding: 10px 20px; }

  .product-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 5px;
    font-size: 0.8rem;
    white-space: nowrap;
  }
  .product-table th, .product-table td {
    padding: 4px;
    border: 1px solid #ddd;
    text-align: center;
    vertical-align: middle;
  }
  .table-responsive { margin-top: 10px; }

  input, select, textarea {
    text-transform: uppercase;
    font-size: 0.5rem;
  }
  textarea { resize: none; height: 10px; }
  .modal-lg { max-width: 900px; }
  /* Make product table inputs compact */
.product-table input {
    font-size: 0.75rem;      /* smaller text */
    padding: 2px 4px;        /* less padding */
    height: 26px;            /* compact height */
}

/* Optional: align text in center */
.product-table input[type="text"],
.product-table input[type="date"],
.product-table input[type="number"] {
    text-align: center;
}

</style>
</head>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
    ✅ Purchase saved successfully!
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
  <div class="alert alert-danger alert-dismissible fade show" role="alert" id="errorAlert">
    ⚠️ <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    <button type="button" class="close" data-dismiss="alert" aria-label="Close">
      <span aria-hidden="true">&times;</span>
    </button>
  </div>
<?php endif; ?>

<body>
<div class="container-fluid">
   <?php pageHeader("🧾 Edit Purchase", "primary"); ?>
<h3 class="mb-3">✏️ Edit Purchase</h3>

<form id="purchaseForm" method="POST" action="update_purchase.php">
<!-- Purchase Edit Header -->
<div class="form-row mb-3">    
  <div class="form-group col-md-2">
    <label for="purchase_id">Purchase ID:</label>
    <input type="text" class="form-control" id="purchase_id" name="purchase_id" readonly
           value="<?= htmlspecialchars($purchase['purchase_id'] ?? '') ?>">
  </div>

  <div class="form-group col-md-2">
    <label>Purchase Date:</label>
    <input type="date" class="form-control" name="purchase_date" 
           value="<?= htmlspecialchars(date('Y-m-d', strtotime($purchase['purchase_date'] ?? date('Y-m-d')))) ?>" required>
  </div>

 <div class="form-group col-md-3">
  <label for="supplier_id">Supplier:</label>
  <select class="form-control" id="supplier_id" name="supplier_id" required>
    <option value="">-- Select Supplier --</option>
    <?php foreach($parties as $p): ?>
      <option value="<?= $p['id']; ?>" <?= ($purchase['supplier_id'] ?? '') == $p['id'] ? 'selected' : '' ?>
              data-name="<?= htmlspecialchars($p['supplier_name'] ?? '') ?>">
        <?= htmlspecialchars($p['supplier_name'] ?? '') ?>
      </option>
    <?php endforeach; ?>
  </select>
  <!-- hidden supplier_name field -->
  <input type="hidden" id="supplier_name" name="supplier_name" 
         value="<?= htmlspecialchars($purchase['supplier_name'] ?? '') ?>">
</div>


  <div class="form-group col-md-2">
    <label>Invoice Number:</label>
    <input type="text" class="form-control" name="invoice_number" 
           value="<?= htmlspecialchars($purchase['invoice_no'] ?? '') ?>" required>
  </div>

  <div class="form-group col-md-3">
    <label>Payment Mode:</label>
    <select class="form-control" name="payment_mode" required>
      <option value="">-- Select --</option>
      <option value="CASH" <?= ($purchase['bill_type'] ?? '')=='CASH'?'selected':''; ?>>Cash</option>
      <option value="CREDIT" <?= ($purchase['bill_type'] ?? '')=='CREDIT'?'selected':''; ?>>Credit</option>
      <option value="CARD" <?= ($purchase['bill_type'] ?? '')=='CARD'?'selected':''; ?>>Card</option>
      <option value="UPI" <?= ($purchase['bill_type'] ?? '')=='UPI'?'selected':''; ?>>UPI</option>
      <option value="NETBANKING" <?= ($purchase['bill_type'] ?? '')=='NETBANKING'?'selected':''; ?>>Net Banking</option>
    </select>
  </div>
</div> 

<!-- Product search -->
<div class="form-row mb-3">
  <div class="form-group col-md-12 d-flex align-items-center">
    <label for="product_name" class="mr-2 mb-0">Product:</label>
    <input type="text" class="form-control" id="product_name" name="search_product" placeholder="Search product...">
  </div>
</div>


<!-- Product Table -->
    <div class="table-responsive">
      <table class="product-table table table-sm table-bordered">
        <thead class="thead-light">
          <tr>
            <th>ID</th><th>Pro Name</th><th>Company</th><th>Cat</th><th>Pack</th>
            <th>Batch</th><th>HSN</th><th>Ex Date</th><th>MRP</th><th>Rate</th>
            <th>Qty</th><th>Dis</th><th>F Qty</th><th>Compo</th>
            <th>IGST</th><th>CGST</th><th>SGST</th><th>Total</th><th>Margin</th>
            <th>Delete</th><th>Next</th>
          </tr>
        </thead>
        <tbody id="productDetails"></tbody>
    <?php foreach ($items as $row): ?>
<tr>
  <td><input type="text" class="form-control-plaintext" name="product_id[]" value="<?= htmlspecialchars($row['product_id'] ?? '') ?>" readonly></td>
  <td><input type="text" class="form-control-plaintext" name="product_name[]" value="<?= htmlspecialchars($row['product_name'] ?? '') ?>" readonly required></td>
  <td><input type="text" class="form-control-plaintext" name="company[]" value="<?= htmlspecialchars($row['company'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext" name="category[]" value="<?= htmlspecialchars($row['category'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext" name="pack[]" value="<?= htmlspecialchars($row['pack'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext" name="batch[]" value="<?= htmlspecialchars($row['batch'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext" name="hsn_code[]" value="<?= htmlspecialchars($row['hsn_code'] ?? '') ?>"></td>
  <td><input type="date" class="form-control-plaintext" name="expiry_date[]" value="<?= htmlspecialchars(date('Y-m-d', strtotime($row['expiry_date'] ?? date('Y-m-d')))) ?>"></td>
  <td><input type="text" class="form-control-plaintext mrps" name="mrp[]" value="<?= htmlspecialchars($row['mrp'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext rates" name="rate[]" value="<?= htmlspecialchars($row['rate'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext quantities" name="quantity[]" value="<?= htmlspecialchars($row['quantity'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext discounts" name="discount[]" value="<?= htmlspecialchars($row['discount'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext freeq" name="free_quantity[]" value="<?= htmlspecialchars($row['free_quantity'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext" name="composition[]" value="<?= htmlspecialchars($row['composition'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext igsts" name="igst[]" value="<?= htmlspecialchars($row['igst'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext cgsts" name="cgst[]" value="<?= htmlspecialchars($row['cgst'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext sgsts" name="sgst[]" value="<?= htmlspecialchars($row['sgst'] ?? '') ?>"></td>
  <td><input type="text" class="form-control-plaintext total" name="total[]" value="<?= htmlspecialchars($row['net_amount'] ?? '') ?>" readonly></td>
  <td><input type="text" class="form-control-plaintext margin" name="margin[]" value="" readonly></td>
  <td><button type="button" class="btn btn-sm btn-danger deleteRow">🗑</button></td>
  <td><button type="button" class="btn btn-sm btn-success btnNextProduct">✔</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- Bill Summary -->
<div class="mt-4">
  <h4 class="mb-3">🧾 Bill Summary</h4>
  <table class="table table-bordered text-center align-middle">
    <tr>
      <th>Total Items</th>
      <td><span id="total_items">0</span></td>
      <th>Subtotal (Before Tax)</th>
      <td>₹ <span id="subtotal_value">0.00</span></td>
      <th>Total Discount</th>
      <td>₹ <span id="total_discount">0.00</span></td>
    </tr>
    <tr>
      <th>Total GST</th>
      <td>₹ <span id="total_gst">0.00</span></td>
      <th class="table-primary">Grand Total</th>
      <td colspan="3" class="table-primary text-success fw-bold fs-5">
        ₹ <span id="grand_total">0.00</span>
      </td>
    </tr>
  </table>
</div>

<div class="d-flex justify-content-between mt-3">
  <a href="../../reports/purchase/list_purchase.php" class="btn btn-outline-secondary">⬅️ Back</a>
  <button type="submit" class="btn btn-primary">💾 Update Bill</button>
</div>

</form>
</div>
<!-- Product Modal (for new product) -->
<?php include __DIR__ . "/../components/product_modal.html"; ?>

 <!-- Product autocomplete -->
<script>
$(document).ready(function(){
    $('#purchase_date').focus();

    // // Fetch next purchase ID
    // $.getJSON('../ajax/get_next_purchase_id.php', function(data){
    //     $('#purchase_id').val(data.next_purchase_id);
    // });
    // Restrict Enter key everywhere except buttons
$(document).on("keypress", function(e){
    if (e.which === 13) {
        // Allow Enter only if focused element is a button
        if (!$(e.target).is("button, .btn")) {
            e.preventDefault();
        }
    }
});

    // Delete row (delegated)
    $('#productDetails').on('click', '.deleteRow', function(){
        $(this).closest('tr').remove();
        updateTotals();
    });

    // PRODUCT AUTOCOMPLETE (keeps your existing logic)
    $("#product_name").autocomplete({
        source: function(request, response){
            $.ajax({
                url: "../ajax/search_product.php",
                dataType: "json",
                data: {q: request.term},
                success: function(data){
                    if(data.length){
                        response($.map(data, function(item){
                            return {
                                label: item.product_name + " | Batch: " + item.batch_no + " | Expiry: " + (item.expiry_date || ''),
                                value: item.product_name,
                                data: item,
                                product_name: item.product_name // ensure consistent property
                            };
                        }));
                    } else {
                        // include product_name so select handler works
                        response([{
                            label: "➕ Add new product",
                            value: "",
                            product_name: "➕ Add new product"
                        }]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui){
            if (ui.item.product_name === "➕ Add new product") {
                $("#addProductModal").modal("show");
                $("#product_name").val("");
                return false;
            } else {
                appendProductRow(ui.item.data || ui.item);
                  // clear search box after selecting
                $("#product_name").val('').focus();
                
            }
        }
    });

    // Add new product via modal (AJAX)
    $('#addProductForm').on('submit', function(e){
        e.preventDefault();
        const data = $(this).serialize();
        $.post('../ajax/add_product.php', data, function(resp){
            if(resp && resp.success){
                $('#addProductModal').modal('hide');
                $('#addProductForm')[0].reset();
                $('#product_name').val('').focus();
                appendProductRow(resp.product);
            } else {
                alert(resp.message || "Unable to save product.");
            }
        }, 'json').fail(function(){
            alert('Server error while saving product.');
        });
    });

    // Delegate input changes for dynamic rows → recalc everything
    $('#productDetails').on('input', '.quantities, .rates, .mrps, .discounts, .cgsts, .sgsts, .igsts', function(){
        updateTotals();
    });

    // Append product row — NOTE the classes for correct selection
    function appendProductRow(p){
    p = p || {};
    const id          = p.id || '';
    const product_name = p.product_name || '';
    const company     = p.company || '';
    const category    = p.category || '';
    const pack        = p.pack || '';
    const batch_no    = p.batch_no || '';
    const hsn_code    = p.hsn_code || '';
    const expiry_date = p.expiry_date || '';
    const mrp         = p.mrp || '';
    const rate        = p.rate || '';
    const composition = p.composition || '';
    const igst        = p.igst || 0;
    const cgst        = p.cgst || 0;
    const sgst        = p.sgst || 0;

    let newRow = `
    <tr>
        <td><input type="text" class="form-control" name="product_id[]" value="${id}" readonly></td>
        <td><input type="text" class="form-control" name="product_name[]" value="${escapeHtml(product_name)}" readonly required></td>
        <td><input type="text" class="form-control" name="company[]" value="${escapeHtml(company)}"></td>
        <td><input type="text" class="form-control" name="category[]" value="${escapeHtml(category)}"></td>
        <td><input type="text" class="form-control" name="pack[]" value="${escapeHtml(pack)}"></td>
        <td><input type="text" class="form-control" name="batch[]" value="${escapeHtml(batch_no)}"></td>
        <td><input type="text" class="form-control" name="hsn_code[]" value="${escapeHtml(hsn_code)}"></td>
         <input type="date" name="expiry_date[]" value="<?= htmlspecialchars($prod['expiry_date'] ?? '') ?>">
              //  <td><input type="date" class="form-control" name="exp_date[]" value="${expiry_date}"></td>
        <td><input type="text" class="form-control mrps" name="mrp[]" value="${mrp}"></td>
        <td><input type="text" class="form-control rates" name="rate[]" value="${rate}"></td>
        <td><input type="text" class="form-control quantities" name="quantity[]" value="1"></td>
        <td><input type="text" class="form-control discounts" name="discount[]" value="0"></td>
        <td><input type="text" class="form-control freeq" name="free_quantity[]" value="0"></td>
        <td><input type="text" class="form-control" name="composition[]" value="${escapeHtml(composition)}"></td>
        <td><input type="text" class="form-control igsts" name="igst[]" value="${igst}"></td>
        <td><input type="text" class="form-control cgsts" name="cgst[]" value="${cgst}"></td>
        <td><input type="text" class="form-control sgsts" name="sgst[]" value="${sgst}"></td>
        <td><input type="text" class="form-control total" name="total[]" readonly></td>
        <td><input type="text" class="form-control margin" name="margin[]" readonly></td>
        <td><button type="button" class="btn btn-sm btn-danger deleteRow">🗑</button></td>
        <td><button type="button" class="btn btn-sm btn-success btnNextProduct">✔</button></td>
    </tr>`;
    
    $('#productDetails').append(newRow);
    updateTotals();

    // focus last inserted rate field
    $('#productDetails tr:last').find('.rates').focus();
}

    // updateTotals calculates per-row totals/margins and also aggregate bill totals
    function updateTotals(){
        let totalItems = $('#productDetails tr').length;
        let subtotal = 0;
        let totalDiscount = 0;
        let totalGst = 0;
        let grandTotal = 0;

        $('#productDetails tr').each(function(){
            let row = $(this);
            let qty      = parseFloat(row.find('.quantities').val()) || 0;
            let free_qty      = parseFloat(row.find('.freeq').val()) || 0;
            let rate     = parseFloat(row.find('.rates').val()) || 0;
            let mrp      = parseFloat(row.find('.mrps').val()) || 0;
            let discountPct = parseFloat(row.find('.discounts').val()) || 0;
            let cgst     = parseFloat(row.find('.cgsts').val()) || 0;
            let sgst     = parseFloat(row.find('.sgsts').val()) || 0;
            let igst     = parseFloat(row.find('.igsts').val()) || 0;

            let baseAmount = rate * qty;
            let discountAmount = (discountPct / 100) * baseAmount;
            let taxable = baseAmount - discountAmount;
            let gst_percent = cgst + sgst + igst;
            let gst_amount = taxable * (gst_percent / 100);

            // row total and margin
            let row_total = taxable + gst_amount;
            let row_margin = (((mrp*(qty+free_qty))-row_total)/(mrp*qty))*100; // absolute margin

            // write back per-row values
            row.find('.total').val(row_total.toFixed(2));
            row.find('.margin').val(row_margin.toFixed(2));

            // accumulate
            subtotal += taxable;
            totalDiscount += discountAmount;
            totalGst += gst_amount;
            grandTotal += row_total;
        });

        // Update summary fields
        $('#total_items').text(totalItems);
        $('#subtotal_value').text(subtotal.toFixed(2));
        $('#total_discount').text(totalDiscount.toFixed(2));
        $('#total_gst').text(totalGst.toFixed(2));
        $('#grand_total').text(grandTotal.toFixed(2));
    }

    // Next product button
    $(document).on('click', '.btnNextProduct', function(){
        $('#product_name').val('').focus();
    });

    // New bill reload
    $('#newBillButton').click(function(){
        location.reload();
    });

    // small helper: escape HTML to avoid breaking markup from product names
    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        return text.replace(/[&<>"'`=\/]/g, function (s) {
            return ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
                '/': '&#x2F;',
                '`': '&#x60;',
                '=': '&#x3D;'
            })[s];
        });
    }
});
// Autofocus on Product Name when modal is opened
$('#addProductModal').on('shown.bs.modal', function () {
    $(this).find('input[name="product_name"]').trigger('focus');
});

</script>
<script>
  // Auto-hide alerts after 3 seconds
  setTimeout(() => {
    const successAlert = document.getElementById("successAlert");
    const errorAlert   = document.getElementById("errorAlert");

    if (successAlert) {
      successAlert.classList.remove("show");
      successAlert.classList.add("fade");
    }
    if (errorAlert) {
      errorAlert.classList.remove("show");
      errorAlert.classList.add("fade");
    }
  }, 30000);
</script>
  <?php pageFooter("secondary"); ?>
  <script>
$(document).ready(function(){
    // auto-set supplier_name when dropdown changes
    $("#supplier_id").on("change", function(){
        let selectedName = $(this).find(":selected").data("name") || "";
        $("#supplier_name").val(selectedName);
    });

    // trigger once on load (in case of pre-selected supplier)
    $("#supplier_id").trigger("change");
});
</script>

</body>
</html>
