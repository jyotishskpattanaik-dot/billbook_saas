<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../includes/sanitize.php';
include __DIR__ . "/../../includes/page_wrapper.php";

$bill_no = $_GET['bill_no'] ?? null;
if (!$bill_no) {
    die("❌ Invalid bill NO");
}

try {
    $pdo = getModulePDO();

    // 🔹 Fetch sale header using bill_no (bill_no is unique)
    $stmt = $pdo->prepare("SELECT * FROM sale_bill_master 
                           WHERE bill_no = :bill_no 
                             AND company_id = :company_id 
                             AND accounting_year_id = :year_id LIMIT 1");
    $stmt->execute([
        ':bill_no' => $bill_no,
        ':company_id' => $COMPANY_ID,
        ':year_id' => $YEAR_ID
    ]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) {
        die("❌ Sale not found.");
    }

    // 🔹 Fetch sale products (try multiple joins to get product details)
    $stmt = $pdo->prepare("SELECT sbp.*, 
                                  COALESCE(p.product_name, sbp.product_name) as product_name,
                                  COALESCE(p.pack, sbp.pack) as pack,
                                  COALESCE(p.hsn_code, sbp.hsn_code) as hsn_code,
                                  COALESCE(p.total_quantity, 0) AS available_qty 
                           FROM sale_bill_products sbp
                           LEFT JOIN current_stock p ON p.id = sbp.product_id
                           WHERE sbp.bill_no = :bill_no");
    $stmt->execute([':bill_no' => $bill_no]);
    $bill_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 🔹 Fetch customers
    $stmt = $pdo->prepare("SELECT id, customer_name, mobile_number 
                           FROM customer_details 
                           WHERE company_id = :company_id 
                             AND accounting_year_id = :year_id
                           ORDER BY customer_name ASC");
    $stmt->execute([
        ':company_id' => $COMPANY_ID,
        ':year_id' => $YEAR_ID
    ]);
    $parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Invoice <?= htmlspecialchars($bill_no) ?></title>
<link rel="stylesheet" href="/assets/css/page_style.css">
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<style>
.product-table { width: 100%; border-collapse: collapse; margin-top: 5px; font-size: 0.9rem; }
.product-table th, .product-table td { padding: 5px; border: 1px solid #ddd; text-align: center; }
.product-table input { font-size: 0.85rem; padding: 2px; }
input, select, textarea { text-transform: uppercase; }
.modal-lg { max-width: 900px; }
textarea { resize: none; height: 20px; }
.stock-warning { color: red; font-size: 0.7rem; display: none; }
.form-control-sm { font-size: 0.85rem; }
</style>
</head>
<body>
<div class="container-fluid mt-2">
   <?php pageHeader("✏ Edit Invoice #" . htmlspecialchars($bill_no), "warning"); ?>

<form id="saleForm" method="POST" action="../save/update_bill.php">
  <input type="hidden" name="bill_no" value="<?= htmlspecialchars($bill_no) ?>">

<div class="form-row mb-2">
  <div class="form-group col-md-2">
    <label>Invoice No:</label>
    <input type="text" class="form-control form-control-sm" name="bill_no_display" value="<?= htmlspecialchars($bill['bill_no'] ?? '') ?>" readonly>
  </div>
  <div class="form-group col-md-2">
    <label>Date:</label>
    <input type="date" class="form-control form-control-sm" name="bill_date" value="<?= htmlspecialchars($bill['bill_date'] ?? '') ?>">
  </div>
  <div class="form-group col-md-3">
    <label>Customer:</label>
    <select class="form-control form-control-sm" id="customer_name" name="customer_name" required>
      <option value="">-- Select Customer --</option>
      <?php foreach($parties as $p): ?>
        <option value="<?= $p['id']; ?>" 
          <?= ($p['id'] == ($bill['customer_name'] ?? '')) ? 'selected' : '' ?>
          data-mobile="<?= htmlspecialchars($p['mobile_number']); ?>">
          <?= htmlspecialchars($p['customer_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="form-group col-md-2">
    <label>Contact Number:</label>
    <input type="text" class="form-control form-control-sm" id="mobile_number" name="mobile_number" value="<?= htmlspecialchars($bill['mobile_number'] ?? '') ?>">
  </div>
  <div class="form-group col-md-2">
    <label>Doctor:</label>
    <input type="text" class="form-control form-control-sm" name="doctor" value="<?= htmlspecialchars($bill['doctor'] ?? '') ?>">
  </div>
  <div class="form-group col-md-1">
    <label>Payment</label>
    <select class="form-control form-control-sm" name="payment_mode">
      <?php $modes = ["CASH","CREDIT","CARD","UPI","NETBANKING"]; foreach($modes as $m): ?>
        <option value="<?= $m ?>" <?= (($bill['payment_mode'] ?? '') == $m) ? 'selected' : '' ?>><?= $m ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<!-- Product search -->
<div class="form-row mb-3">
  <div class="form-group col-md-10 d-flex align-items-center">
    <label for="product_name" class="mr-2 mb-0">Product:</label>
    <input type="text" class="form-control form-control-sm" id="product_name" name="search_product" placeholder="Search product...">
  </div>
  <div class="form-group col-md-2">
    <button type="button" class="btn btn-primary btn-sm" id="newBillButton">🆕 New Bill</button>
  </div>
</div>

<!-- Product Table -->
<table class="product-table">
<thead>
<tr>
<th width="5%">ID</th>
<th width="20%">Product Name</th>
<th width="8%">Pack</th>
<th width="8%">Batch</th>
<th width="8%">HSN</th>
<th width="8%">Exp Date</th>
<th width="6%">MRP</th>
<th width="6%">Qty</th>
<th width="5%">Dis%</th>
<th width="5%">IGST%</th>
<th width="5%">CGST%</th>
<th width="5%">SGST%</th>
<th width="8%">Total</th>
<th width="4%">Del</th>
<th width="4%">Next</th>
</tr>
</thead>
<tbody id="productDetails">
<?php foreach($bill_products as $p): ?>
<tr>
    <td><input type="text" class="form-control form-control-sm" name="product_id[]" value="<?= htmlspecialchars($p['product_id'] ?? '') ?>" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
  <td><input type="text" class="form-control form-control-sm" name="product_name[]" value="<?= htmlspecialchars($p['product_name'] ?? 'N/A') ?>" readonly required style="font-size:0.8rem; background-color:#f8f9fa; font-weight:bold;"></td>
  <td><input type="text" class="form-control form-control-sm" name="pack[]" value="<?= htmlspecialchars($p['pack'] ?? 'N/A') ?>" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
  <td><input type="text" class="form-control form-control-sm" name="batch[]" value="<?= htmlspecialchars($p['batch'] ?? $p['batch_no'] ?? 'N/A') ?>" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
  <td><input type="text" class="form-control form-control-sm" name="hsn_code[]" value="<?= htmlspecialchars($p['hsn_code'] ?? 'N/A') ?>" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
  <td><input type="date" class="form-control form-control-sm" name="expiry_date[]" value="<?= htmlspecialchars(date('Y-m-d', strtotime($p['expiry_date'] ?? $p['expiry_date'] ?? date('Y-m-d')))) ?>" style="font-size:0.8rem;"></td>
  <td><input type="number" step="0.01" class="form-control form-control-sm mrps" name="mrp[]" value="<?= htmlspecialchars($p['mrp'] ?? '0') ?>" style="font-size:0.8rem;"></td>
  <td>
    <input type="text" class="form-control form-control-sm quantities" name="quantity[]" value="<?= htmlspecialchars($p['quantity'] ?? '1') ?>" data-stock="<?= htmlspecialchars($p['available_qty'] ?? 0) ?>" style="font-size:0.8rem;" inputmode="decimal" placeholder="1 or 1.5 or 2/3" title="Available Stock: <?= htmlspecialchars($p['available_qty'] ?? 0) ?>&#13;&#10;Examples: 1.5, 2/3, 1/30">
    <div class="stock-warning">⚠ Max: <?= htmlspecialchars($p['available_qty'] ?? 0) ?></div>
    <small class="text-muted qty-help">Fractional OK</small>
  </td>
  <td><input type="number" step="0.01" class="form-control form-control-sm discounts" name="discount[]" value="<?= htmlspecialchars($p['discount'] ?? '0') ?>" style="font-size:0.8rem;"></td>
  <td><input type="number" step="0.01" class="form-control form-control-sm igsts" name="igst[]" value="<?= htmlspecialchars($p['igst'] ?? '0') ?>" style="font-size:0.8rem;"></td>
  <td><input type="number" step="0.01" class="form-control form-control-sm cgsts" name="cgst[]" value="<?= htmlspecialchars($p['cgst'] ?? '0') ?>" style="font-size:0.8rem;"></td>
  <td><input type="number" step="0.01" class="form-control form-control-sm sgsts" name="sgst[]" value="<?= htmlspecialchars($p['sgst'] ?? '0') ?>" style="font-size:0.8rem;"></td>
  <td><input type="number" step="0.01" class="form-control form-control-sm total" name="total[]" value="<?= htmlspecialchars($p['net_amount'] ?? '0') ?>" readonly style="font-size:0.8rem; background-color:#e9ecef;"></td>
  <td><button type="button" class="btn btn-sm btn-danger deleteRow">🗑</button></td>
  <td><button type="button" class="btn btn-sm btn-success btnNextProduct">✔</button></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

<!-- Bill Summary -->
<div class="mt-4">
  <h5 class="mb-3">🧾 Bill Summary</h5>
  <table class="table table-bordered table-sm text-center align-middle">
    <tr>
      <th>Total Items</th>
      <td><span id="total_items">0</span></td>
      <th>Subtotal (Excl. GST)</th>
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

<div class="d-flex justify-content-between mt-3">
  <div>
    <a href="list_sales.php" class="btn btn-outline-secondary mr-2">⬅ Back to List</a>
    <a href="create_bill.php" class="btn btn-outline-primary">🆕 Create New Bill</a>
  </div>
  <button type="submit" class="btn btn-warning">💾 Update Bill</button>
</div>

</form>
</div>

<script>
$(document).ready(function(){
    // Initialize totals calculation on page load
    updateTotals();

    // Auto-fill customer mobile on change
    $("#customer_name").on("change", function(){
        let mobile = $(this).find(":selected").data("mobile") || "";
        $("#mobile_number").val(mobile);
    });

    // Set initial customer mobile on page load
    let selectedCustomer = $("#customer_name option:selected");
    if (selectedCustomer.length > 0) {
        let initialMobile = selectedCustomer.data("mobile") || "";
        $("#mobile_number").val(initialMobile);
    }

    // Product autocomplete
    $("#product_name").autocomplete({
        source: function(request, response){
            $.ajax({
                url: "../ajax/search_sales_products.php",
                dataType: "json",
                data: {q: request.term},
                success: function(data){
                    response($.map(data, function(item){
                        return {
                            label: item.product_name + " | Batch: " + item.batch_no + " | Stock: " + item.available_qty,
                            value: item.product_name,
                            data: item
                        };
                    }));
                },
                error: function(){
                    response([]);
                }
            });
        },
        minLength: 2,
        select: function(event, ui){
            appendProductRow(ui.item.data);
            let lastRow = $('#productDetails tr:last');
            lastRow.find('[name="quantity[]"]').focus();
            $("#product_name").val('');
            return false;
        }
    });

    // Append product row with enhanced fractional support and validation
    function appendProductRow(p){
        // Check if product already exists
        let existingProduct = false;
        $('#productDetails tr').each(function(){
            let existingId = $(this).find('[name="product_id[]"]').val();
            let existingBatch = $(this).find('[name="batch[]"]').val();
            if (existingId === p.product_id && existingBatch === p.batch_no) {
                existingProduct = true;
                // Increase quantity instead of adding new row
                let qtyField = $(this).find('[name="quantity[]"]');
                let currentQty = parseQuantity(qtyField.val()) || 0;
                let newQty = currentQty + 1;
                qtyField.val(formatQuantity(newQty)).focus().select();
                updateTotals();
                return false;
            }
        });

        if (existingProduct) {
            return;
        }

        const id   = p.product_id || '';
        const name = p.product_name || 'Unknown Product';
        const pack = p.pack || 'N/A';
        const batch = p.batch_no || 'N/A';
        const hsn  = p.hsn_code || '';
        const exp  = p.expiry_date || '';
        const mrp  = p.mrp || '0';
        const igst = p.igst || 0;
        const cgst = p.cgst || 0;
        const sgst = p.sgst || 0;
        const stock = p.available_qty || 0;

        let newRow = `
        <tr>
            <td><input type="text" class="form-control form-control-sm" name="product_id[]" value="${id}" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
            <td><input type="text" class="form-control form-control-sm" name="product_name[]" value="${escapeHtml(name)}" readonly style="font-size:0.8rem; background-color:#f8f9fa; font-weight:bold;"></td>
            <td><input type="text" class="form-control form-control-sm" name="pack[]" value="${escapeHtml(pack)}" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
            <td><input type="text" class="form-control form-control-sm" name="batch[]" value="${escapeHtml(batch)}" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
            <td><input type="text" class="form-control form-control-sm" name="hsn_code[]" value="${escapeHtml(hsn)}" readonly style="font-size:0.8rem; background-color:#f8f9fa;"></td>
               <td><input type="date" name="expiry_date[]" value="<?= htmlspecialchars($prod['expiry_date'] ?? '') ?>"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm mrps" name="mrp[]" value="${mrp}" style="font-size:0.8rem;"></td>
            <td>
              <input type="text" class="form-control form-control-sm quantities" name="quantity[]" value="1" data-stock="${stock}" style="font-size:0.8rem;" inputmode="decimal" placeholder="1 or 1.5 or 2/3" title="Available Stock: ${stock}&#13;&#10;Examples: 1.5, 2/3, 1/30">
              <div class="stock-warning">⚠ Max: ${stock}</div>
              <small class="text-muted qty-help">Fractional OK</small>
            </td>
            <td><input type="number" step="0.01" class="form-control form-control-sm discounts" name="discount[]" value="0" style="font-size:0.8rem;"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm igsts" name="igst[]" value="${igst}" style="font-size:0.8rem;"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm cgsts" name="cgst[]" value="${cgst}" style="font-size:0.8rem;"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm sgsts" name="sgst[]" value="${sgst}" style="font-size:0.8rem;"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm total" name="total[]" readonly style="font-size:0.8rem; background-color:#e9ecef;"></td>
            <td><button type="button" class="btn btn-sm btn-danger deleteRow" title="Delete Row">🗑</button></td>
            <td><button type="button" class="btn btn-sm btn-success btnNextProduct" title="Add Next Product">✔</button></td>
        </tr>`;
        $('#productDetails').append(newRow);
        updateTotals();
        
        // Show success message
        showToast('✅ Product added successfully!', 'success');
    }

    // Enhanced quantity parser for fractional sales
    function parseQuantity(input){
        if (!input) return 0;
        input = input.toString().trim();
        
        // Handle fractions like "2/3", "1/2", "3/4", "1/30", "1/15"
        if (/^\d+(\.\d+)?\/\d+(\.\d+)?$/.test(input)) {
            let parts = input.split("/");
            let numerator = parseFloat(parts[0]) || 0;
            let denominator = parseFloat(parts[1]) || 1;
            if (denominator === 0) return 0;
            let result = numerator / denominator;
            // Round to 6 decimal places to handle small fractions
            return Math.round(result * 1000000) / 1000000;
        }
        
        // Handle mixed numbers like "1 1/2" or "2 3/4"
        if (/^\d+\s+\d+(\.\d+)?\/\d+(\.\d+)?$/.test(input)) {
            let parts = input.split(/\s+/);
            let whole = parseInt(parts[0]) || 0;
            let fractionParts = parts[1].split("/");
            let numerator = parseFloat(fractionParts[0]) || 0;
            let denominator = parseFloat(fractionParts[1]) || 1;
            if (denominator === 0) return whole;
            let fraction = numerator / denominator;
            let result = whole + fraction;
            return Math.round(result * 1000000) / 1000000;
        }
        
        // Handle decimal numbers
        let val = parseFloat(input);
        return isNaN(val) ? 0 : val;
    }

    // Format quantity for display
    function formatQuantity(qty) {
        if (qty === 0) return '0';
        
        // If it's a whole number, show as integer
        if (qty % 1 === 0) return qty.toString();
        
        // For very small decimals (like 1/30 = 0.033333), show more precision
        if (qty < 0.1) {
            return qty.toFixed(6).replace(/\.?0+$/, '');
        }
        
        // For normal decimals, show up to 3 decimal places
        return qty.toFixed(3).replace(/\.?0+$/, '');
    }

    // Validate quantity input
    function validateQuantity(input, stock) {
        let qty = parseQuantity(input);
        
        if (qty < 0) {
            return { valid: false, message: "Quantity cannot be negative", corrected: 0 };
        }
        
        if (qty > stock && stock < 999999) {
            return { valid: false, message: `Max available: ${stock}`, corrected: stock };
        }
        
        return { valid: true, quantity: qty };
    }

    // Update totals function with enhanced validation
    function updateTotals(){
        let subtotal = 0, totalDiscount = 0, totalGST = 0, grandTotal = 0, totalItems = 0;

        $('#productDetails tr').each(function(){
            let $row = $(this);
            let mrp = parseFloat($row.find('[name="mrp[]"]').val()) || 0;
            let qtyInput = $row.find('[name="quantity[]"]').val();
            let stock = parseFloat($row.find('[name="quantity[]"]').data("stock")) || 999999;

            // Validate and parse quantity
            let validation = validateQuantity(qtyInput, stock);
            let qty = validation.quantity || 0;
            
            if (!validation.valid) {
                if (validation.corrected !== undefined) {
                    qty = validation.corrected;
                    $row.find('[name="quantity[]"]').val(formatQuantity(qty));
                }
                $row.find('.stock-warning').text('⚠ ' + validation.message).show();
            } else {
                $row.find('.stock-warning').hide();
            }

            let discountPct = parseFloat($row.find('[name="discount[]"]').val()) || 0;
            let igst = parseFloat($row.find('[name="igst[]"]').val()) || 0;
            let cgst = parseFloat($row.find('[name="cgst[]"]').val()) || 0;
            let sgst = parseFloat($row.find('[name="sgst[]"]').val()) || 0;
            let gstPct = igst + cgst + sgst;

            if (qty > 0 && mrp > 0) {
                // Calculate discount amount first
                let discountAmount = mrp * (discountPct / 100);
                let discountedPrice = mrp - discountAmount;
                
                // Calculate GST on discounted price
                let basePrice = discountedPrice / (1 + gstPct / 100);
                let gstAmount = discountedPrice - basePrice;

                let lineBase = basePrice * qty;
                let lineGST = gstAmount * qty;
                let lineTotal = discountedPrice * qty;
                let lineDiscount = discountAmount * qty;

                $row.find('.total').val(lineTotal.toFixed(2));

                subtotal += lineBase;
                totalGST += lineGST;
                grandTotal += lineTotal;
                totalDiscount += lineDiscount;
                totalItems++;
                
                // Update quantity display if it was formatted
                let currentQtyDisplay = $row.find('[name="quantity[]"]').val();
                if (currentQtyDisplay != formatQuantity(qty)) {
                    $row.find('[name="quantity[]"]').val(formatQuantity(qty));
                }
            } else {
                $row.find('.total').val('0.00');
            }
        });

        $('#total_items').text(totalItems);
        $('#subtotal_value').text(subtotal.toFixed(2));
        $('#total_discount').text(totalDiscount.toFixed(2));
        $('#total_gst').text(totalGST.toFixed(2));
        $('#grand_total').text(grandTotal.toFixed(2));
    }

    // Event delegates
    $('#productDetails').on('input', '.quantities, .discounts, .igsts, .cgsts, .sgsts, .mrps', function(){
        updateTotals();
    });
    
    $('#productDetails').on('click', '.deleteRow', function(){ 
        $(this).closest('tr').remove(); 
        updateTotals(); 
    });
    
    $(document).on('click', '.btnNextProduct', function(){ 
        $('#product_name').focus(); 
    });
    
    $('#newBillButton').click(function(){ 
        window.location.href = 'create_bill.php';
    });

    // Enhanced input handlers for fractional quantities
    $('#productDetails').on('blur', '.quantities', function() {
        let $input = $(this);
        let rawValue = $input.val().trim();
        
        console.log('Quantity input blur - Raw value:', rawValue);
        
        if (!rawValue) {
            $input.val('1');
            return;
        }
        
        // Test if it's a valid fractional input
        let qty = parseQuantity(rawValue);
        console.log('Parsed quantity:', qty);
        
        if (qty > 0) {
            // Format the display value
            let formatted = formatQuantity(qty);
            console.log('Formatted quantity:', formatted);
            $input.val(formatted);
            // Trigger totals update
            updateTotals();
        } else {
            // Invalid input, reset to 1
            console.warn('Invalid quantity input:', rawValue);
            $input.val('1');
            showToast('Invalid quantity format. Examples: 1.5, 1/2, 1/30, 2 3/4', 'warning');
        }
    });

    // Allow decimal input and common fraction characters
    $('#productDetails').on('keypress', '.quantities', function(e) {
        let char = String.fromCharCode(e.which);
        let currentValue = this.value;
        
        // Allow numbers, decimal point, slash, and space
        if (/[0-9.\/ ]/.test(char)) {
            return true;
        }
        
        // Allow backspace, delete, tab, escape, enter
        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
            (e.keyCode === 65 && e.ctrlKey === true) ||
            (e.keyCode === 67 && e.ctrlKey === true) ||
            (e.keyCode === 86 && e.ctrlKey === true) ||
            (e.keyCode === 88 && e.ctrlKey === true)) {
            return true;
        }
        
        e.preventDefault();
        return false;
    });

    // Add hints on focus
    $('#productDetails').on('focus', '.quantities', function() {
        $(this).attr('placeholder', 'e.g: 1.5, 2/3, 1/30');
        // Select all text for easy editing
        this.select();
    });

    $('#productDetails').on('blur', '.quantities', function() {
        $(this).attr('placeholder', '1 or 1.5 or 2/3');
    });

    // Enhanced toast notification function
    function showToast(message, type = 'info') {
        let toastClass = 'alert-info';
        if (type === 'success') toastClass = 'alert-success';
        else if (type === 'warning') toastClass = 'alert-warning';
        else if (type === 'error') toastClass = 'alert-danger';
        
        let toast = `
            <div class="alert ${toastClass} alert-dismissible fade show position-fixed" 
                 style="top: 20px; right: 20px; z-index: 9999; min-width: 300px; max-width: 400px;">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>`;
        $('body').append(toast);
        
        setTimeout(function(){
            $('.alert.position-fixed').fadeOut(function() {
                $(this).remove();
            });
        }, 4000);
    }

    // Add keyboard shortcuts
    $(document).on('keydown', function(e) {
        // Alt + P to focus on product search
        if (e.altKey && e.which === 80) {
            e.preventDefault();
            $('#product_name').focus();
        }
        
        // Escape to clear search
        if (e.which === 27 && $('#product_name').is(':focus')) {
            $('#product_name').val('').autocomplete('close');
        }
    });

    // HTML escape function
    function escapeHtml(text) {
        if (typeof text !== 'string') return text;
        return text.replace(/[&<>"'`=\/]/g, function (s) {
            return ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                '/': '&#x2F;', '`': '&#x60;', '=': '&#x3D;'
            })[s];
        });
    }
});

</script>

<script>
  // Auto-hide alerts after 30 seconds
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
</body>
</html>