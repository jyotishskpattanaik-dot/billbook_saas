<?php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../includes/sanitize.php';
include __DIR__ . "/../../includes/page_wrapper.php"; 

$pdo = getModulePDO();

// ✅ Get suppliers for dropdown
$stmt = $pdo->query("SELECT id, supplier_name FROM supplier_details ORDER BY supplier_name ASC");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ Get preview data from session
$preview = $_SESSION['preview_data'] ?? null;
if (!$preview) {
    $_SESSION['error'] = "No preview data found. Please upload again.";
    header("Location: upload_purchase_form.php");
    exit;
}

// Header defaults
$invoice_number = $preview['invoice_number'] ?? '';
$purchase_date  = $preview['purchase_date'] ?? date('Y-m-d');
$bill_type      = $preview['bill_type'] ?? '';
$supplier_id    = $preview['supplier_id'] ?? '';
$products       = $preview['products'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <title>Preview Purchase</title>
    <link rel="stylesheet" href="../css/purchase.css">
    <link rel="stylesheet" href="/assets/css/page_style.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

    <style>
      body { background-color: #f8f9fa; font-size: 0.9rem; }
      .container-fluid { padding: 10px 20px; }

      .product-table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 0.8rem; white-space: nowrap; }
      .product-table th, .product-table td { padding: 4px; border: 1px solid #ddd; text-align: center; vertical-align: middle; }
      .table-responsive { margin-top: 10px; }

      input, select, textarea { text-transform: uppercase; font-size: 0.45rem; }
      textarea { resize: none; height: 10px; }
      .modal-lg { max-width: 900px; }

      /* Compact product inputs */
      .product-table input { font-size: 0.55rem; padding: 2px 4px; height: 26px; }
      .product-table input[type="text"],
      .product-table input[type="date"],
      .product-table input[type="number"] { text-align: center; }
    </style>
</head>
<body>
<div class="container-fluid">
    <h3 class="mb-4">🧾 Preview & Confirm Purchase</h3>

    <form method="post" action="save_upload_purchase.php">
      <!-- Purchase Header -->
      <div class="form-row mb-3">
        <div class="form-group col-md-2">
          <label for="purchase_id">Purchase ID:</label>
          <input type="text" class="form-control" id="purchase_id" name="purchase_id" readonly>
        </div>
        <div class="form-group col-md-2">
          <label for="purchase_date">Purchase Date:</label>
          <input type="date" class="form-control" id="purchase_date" name="purchase_date" 
                 value="<?= htmlspecialchars($purchase_date) ?>" max="<?= date('Y-m-d'); ?>" required>
        </div>
        <div class="form-group col-md-3">
          <label for="supplier_id">Supplier:</label>
          <select class="form-control" id="supplier_id" name="supplier_id" required>
            <option value="">-- Select Supplier --</option>
            <?php foreach($suppliers as $s): ?>
              <option value="<?= $s['id']; ?>" <?= $supplier_id == $s['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($s['supplier_name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group col-md-2">
          <label for="invoice_number">Invoice Number:</label>
          <input type="text" class="form-control" id="invoice_number" name="invoice_number" 
                 value="<?= htmlspecialchars($invoice_number) ?>" required>
        </div>
        <div class="form-group col-md-3">
          <label for="payment_mode">Payment Mode:</label>
          <select class="form-control" id="payment_mode" name="payment_mode" required>
            <option value="">-- Select --</option>
            <option value="CASH">Cash</option>
            <option value="CREDIT">Credit</option>
            <option value="CARD">Card</option>
            <option value="UPI">UPI</option>
            <option value="NETBANKING">Net Banking</option>
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

      <!-- Products Table -->
      <div class="table-responsive">
        <table class="product-table table table-sm table-bordered">
          <thead class="thead-light">
            <tr>
              <th>ID</th>
              <th>Product Name</th>
              <th>Company</th>
              <th>Category</th>
              <th>Pack</th>
              <th>Batch</th>
              <th>HSN</th>
              <th>Ex Date</th>
              <th>MRP</th>
              <th>Rate</th>
              <th>Qty</th>
              <th>F Qty</th>
              <th>Compo</th>
              <th>Disc %</th>
              <th>IGST %</th>
              <th>CGST %</th>
              <th>SGST %</th>
              <th>Total</th>
              <th>Margin</th>
              <th>Delete</th>
              <th>Next</th>
            </tr>
          </thead>
          <tbody id="productDetails">
          <?php foreach ($products as $i => $prod): ?>
            <tr>
              <td><input type="text" name="id[]" class="form-control" value="<?= htmlspecialchars($prod['id'] ?? '') ?>"></td>
              <td><input type="text" name="product_name[]" class="form-control" value="<?= htmlspecialchars($prod['product_name'] ?? '') ?>"></td>
              <td><input type="text" name="company[]" class="form-control" value="<?= htmlspecialchars($prod['company'] ?? '') ?>"></td>
              <td><input type="text" name="category[]" class="form-control" value="<?= htmlspecialchars($prod['category'] ?? '') ?>"></td>
              <td><input type="text" name="pack[]" class="form-control" value="<?= htmlspecialchars($prod['pack'] ?? '') ?>"></td>
              <td><input type="text" name="batch[]" class="form-control" value="<?= htmlspecialchars($prod['batch_no'] ?? '') ?>"></td>
              <td><input type="text" name="hsn_code[]" class="form-control" value="<?= htmlspecialchars($prod['hsn_code'] ?? '') ?>"></td>
              <td><input type="text" name="expiry_date[]" class="form-control" value="<?= htmlspecialchars($prod['expiry_date'] ?? '') ?>"></td>
              <td><input type="number" step="0.01" name="mrp[]" class="form-control mrps" value="<?= htmlspecialchars($prod['mrp'] ?? 0) ?>"></td>
              <td><input type="number" step="0.01" name="rate[]" class="form-control rates" value="<?= htmlspecialchars($prod['rate'] ?? 0) ?>"></td>
              <td><input type="number" name="quantity[]" class="form-control quantities" value="<?= htmlspecialchars($prod['quantity'] ?? 0) ?>"></td>
              <td><input type="number" name="free_quantity[]" class="form-control freeq" value="<?= htmlspecialchars($prod['free_quantity'] ?? 0) ?>"></td>
              <td><input type="text" name="composition[]" class="form-control" value="<?= htmlspecialchars($prod['composition'] ?? '') ?>"></td>
              <td><input type="number" step="0.01" name="discount[]" class="form-control discounts" value="<?= htmlspecialchars($prod['discount'] ?? 0) ?>"></td>
              <td><input type="number" step="0.01" name="igst[]" class="form-control igsts" value="<?= htmlspecialchars($prod['igst'] ?? 0) ?>"></td>
              <td><input type="number" step="0.01" name="cgst[]" class="form-control cgsts" value="<?= htmlspecialchars($prod['cgst'] ?? 0) ?>"></td>
              <td><input type="number" step="0.01" name="sgst[]" class="form-control sgsts" value="<?= htmlspecialchars($prod['sgst'] ?? 0) ?>"></td>
              <td><input type="text" class="form-control total" name="total[]" readonly></td>
              <td><input type="text" class="form-control margin" name="margin[]" readonly></td>
              <td><button type="button" class="btn btn-sm btn-danger deleteRow">🗑</button></td>
              <td><button type="button" class="btn btn-sm btn-success btnNextProduct">✔</button></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Bill Summary -->
      <div class="mt-4">
        <h4 class="mb-3">🧾 Bill Summary</h4>
        <div class="table-responsive">
          <table class="table table-bordered text-center align-middle table-sm">
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
              <td colspan="3" class="table-primary text-success font-weight-bold">
                ₹ <span id="grand_total">0.00</span>
              </td>
            </tr>
          </table>
        </div>
      </div>

      <!-- Save Button -->
      <div class="form-group text-right mt-3">
        <button type="submit" class="btn btn-success">✅ Save Purchase</button>
        <a href="upload_purchase_form.php" class="btn btn-secondary">Cancel</a>
      </div>
    </form>
</div>
<!-- Product Modal (for new product) -->
<?php include __DIR__ . "/../components/product_modal.html"; ?>
<!-- Product autocomplete -->
<script>
function escapeHtml(text) {
    if (!text) return "";
    return text.replace(/&/g, "&amp;")
               .replace(/</g, "&lt;")
               .replace(/>/g, "&gt;")
               .replace(/"/g, "&quot;")
               .replace(/'/g, "&#039;");
}

$(document).ready(function(){
    $('#purchase_date').focus();

    // Fetch next purchase ID
    $.getJSON('../ajax/get_next_purchase_id.php', function(data){
        $('#purchase_id').val(data.next_purchase_id);
    });

    // Restrict Enter key
    $(document).on("keypress", function(e){
        if (e.which === 13 && !$(e.target).is("button, .btn")) {
            e.preventDefault();
        }
    });

    // Delete row
    $('#productDetails').on('click', '.deleteRow', function(){
        $(this).closest('tr').remove();
        updateTotals();
    });

    // Product autocomplete
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
                                data: item
                            };
                        }));
                    } else {
                        response([{label: "➕ Add new product", 
                            value: "",
                            product_name: "➕ Add new product"
                        }]);
                    }
                }
            });
        },
        minLength: 2,
        select: function(event, ui){
            if (ui.item.label === "➕ Add new product") {
                $("#addProductModal").modal("show");
                $("#product_name").val("");
                return false;
            } else {
                appendProductRow(ui.item.data);
                $("#product_name").val('').focus();
            }
        }
    });

    // Add new product via modal
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

    // Delegate input changes
    $('#productDetails').on('input', '.quantities, .rates, .mrps, .discounts, .cgsts, .sgsts, .igsts', function(){
        updateTotals();
    });

    // Append product row — corrected
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
            <td><input type="text" class="form-control" name="id[]" value="${id}" readonly></td>
            <td><input type="text" class="form-control" name="product_name[]" value="${escapeHtml(product_name)}" readonly required></td>
            <td><input type="text" class="form-control" name="company[]" value="${escapeHtml(company)}"></td>
            <td><input type="text" class="form-control" name="category[]" value="${escapeHtml(category)}"></td>
            <td><input type="text" class="form-control" name="pack[]" value="${escapeHtml(pack)}"></td>
            <td><input type="text" class="form-control" name="batch[]" value="${escapeHtml(batch_no)}"></td>
            <td><input type="text" class="form-control" name="hsn_code[]" value="${escapeHtml(hsn_code)}"></td>
            <input type="date" name="expiry_date[]" value="<?= htmlspecialchars($prod['expiry_date'] ?? '') ?>">
            // <td><input type="date" class="form-control" name="expiry_date[]" value="${expiry_date}"></td>
            <td><input type="text" class="form-control mrps" name="mrp[]" value="${mrp}"></td>
            <td><input type="text" class="form-control rates" name="rate[]" value="${rate}"></td>
            <td><input type="text" class="form-control quantities" name="quantity[]" value="1"></td>
            <td><input type="text" class="form-control freeq" name="free_quantity[]" value="0"></td>
            <td><input type="text" class="form-control" name="composition[]" value="${escapeHtml(composition)}"></td>
            <td><input type="text" class="form-control discounts" name="discount[]" value="0"></td>
            <td><input type="text" class="form-control igsts" name="igst[]" value="${igst}"></td>
            <td><input type="text" class="form-control cgsts" name="cgst[]" value="${cgst}"></td>
            <td><input type="text" class="form-control sgsts" name="sgst[]" value="${sgst}"></td>
            <td><input type="text" class="form-control total" name="total[]" readonly></td>
            <td><input type="text" class="form-control margin" name="margin[]" readonly></td>
            <td><button type="button" class="btn btn-sm btn-danger deleteRow">🗑</button></td>
            <td><button type="button" class="btn btn-sm btn-success btnNextProduct">✔</button></td>
        </tr>`;
        
        $('#productDetails').append(newRow);
        $('#productDetails tr:last').find('.rates').focus();
        updateTotals();
    }

    // Next product button
    $(document).on('click', '.btnNextProduct', function(){
        $('#product_name').focus();
    });

    function updateTotals(){
        let total_items=0, subtotal=0, total_discount=0, total_gst=0, grand_total=0;
        $('#productDetails tr').each(function(){
            const qty = parseFloat($(this).find('.quantities').val()) || 0;
            const rate= parseFloat($(this).find('.rates').val()) || 0;
            const disc= parseFloat($(this).find('.discounts').val()) || 0;
            const igst= parseFloat($(this).find('.igsts').val()) || 0;
            const cgst= parseFloat($(this).find('.cgsts').val()) || 0;
            const sgst= parseFloat($(this).find('.sgsts').val()) || 0;
            const mrp = parseFloat($(this).find('.mrps').val()) || 0;

            let base_total = qty*rate;
            let discount_amount = base_total*disc/100;
            let after_discount = base_total-discount_amount;
            let gst_amount = after_discount*(igst+cgst+sgst)/100;
            let row_total = after_discount+gst_amount;

            $(this).find('.total').val(row_total.toFixed(2));
            $(this).find('.margin').val((mrp>0 && rate>0) ? (((mrp-rate)/mrp)*100).toFixed(2)+'%' : '0%');

            if(qty>0){ total_items++; }
            subtotal+=base_total;
            total_discount+=discount_amount;
            total_gst+=gst_amount;
            grand_total+=row_total;
        });

        $('#total_items').text(total_items);
        $('#subtotal_value').text(subtotal.toFixed(2));
        $('#total_discount').text(total_discount.toFixed(2));
        $('#total_gst').text(total_gst.toFixed(2));
        $('#grand_total').text(grand_total.toFixed(2));
    }

    updateTotals();
});
</script>
</body>
</html>
