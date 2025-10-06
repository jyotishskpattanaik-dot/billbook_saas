<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../includes/sanitize.php';
include __DIR__ . "/../../includes/page_wrapper.php"; 

try {
    $pdo = getModulePDO();

    // customer list
    $sql = "SELECT id, customer_name, mobile_number
            FROM customer_details 
            WHERE company_id = :company_id 
              AND accounting_year_id = :year_id
            ORDER BY customer_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $COMPANY_ID,
        ':year_id'    => $YEAR_ID
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
<meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
<title>Create New Invoice</title>

<!-- Bootstrap + jQuery -->
<link rel="stylesheet" href="../../assets/css/page_style.css">
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">

<!-- Mobile-specific CSS -->
<link rel="stylesheet" href="../../assets/css/bill_mobile.css">
<link rel="stylesheet" href="../../assets/css/table_header_fix.css">

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js"></script>

<style>
/* Inline critical styles for immediate rendering */
input, select, textarea { text-transform: uppercase; }
.modal-lg { max-width: 900px; }
textarea { resize: none; height: 20px; }
</style>
</head>
<body>

<?php if (isset($_GET['success']) && $_GET['success'] == 1): ?>
  <div class="alert alert-success alert-dismissible fade show" role="alert" id="successAlert">
    ✅ Bill created successfully!
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

<div class="container-fluid mt-2">
   <?php pageHeader("🧾 Create New Invoice", "primary"); ?>

<form id="saleForm" method="POST" action="../save/save_bill.php">

<!-- Invoice Header Form -->
<div class="form-row mb-3">
  <div class="form-group col-md-2">
    <label for="bill_no">Invoice No:</label>
    <input type="text" class="form-control form-control-sm" id="bill_no" name="bill_no" value="AUTO" readonly style="background-color:#f8f9fa; color:#6c757d; font-weight:bold;">
    <small class="text-muted">Auto-generated</small>
  </div>

  <div class="form-group col-md-2">
    <label for="bill_date">Date:</label>
    <input type="date" class="form-control form-control-sm" id="bill_date" name="bill_date" 
           value="<?= date('Y-m-d'); ?>" max="<?= date('Y-m-d'); ?>" required>
  </div>

  <div class="form-group col-md-3">
    <label for="customer_name">Customer:</label>
    <select class="form-control form-control-sm" id="customer_name" name="customer_name" required>
      <option value="">-- Select Customer --</option>
      <?php foreach($parties as $p): ?>
        <option value="<?= $p['id']; ?>" 
                data-mobile="<?= htmlspecialchars($p['mobile_number']); ?>">
          <?= htmlspecialchars($p['customer_name']); ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group col-md-2">
    <label for="mobile_number">Contact Number:</label>
    <input type="text" class="form-control form-control-sm" id="mobile_number" name="mobile_number" required>
  </div>
  
  <div class="form-group col-md-2">
    <label for="doctor">Doctor:</label>
    <input type="text" class="form-control form-control-sm" id="doctor" name="doctor">
  </div>

  <div class="form-group col-md-1">
    <label for="payment_mode">Payment</label>
    <select class="form-control form-control-sm" id="payment_mode" name="payment_mode" required>
      <option value="">-- Select --</option>
      <option value="CASH" selected>CASH</option>
      <option value="CREDIT">CREDIT</option>
      <option value="CARD">CARD</option>
      <option value="UPI">UPI</option>
      <option value="NETBANKING">NETBANKING</option>
    </select>
  </div>
</div> 

<!-- Product Search Section -->
<div class="product-search-section">
  <label for="product_name" class="search-label">🔍 Search Product:</label>
  <div class="d-flex align-items-center">
    <input type="text" class="form-control form-control-sm" id="product_name" name="search_product" 
           placeholder="Type product name, batch, or HSN to search..." autocomplete="off">
    <small class="text-muted ml-2 d-none d-md-inline">Min 2 chars</small>
  </div>
  <small class="text-muted d-block d-md-none mt-1">Type at least 2 characters to search</small>
  <small class="text-info d-block mt-1">💡 Tip: Use keyboard shortcut Alt+P to focus search</small>
</div>

<!-- Scrollable Product Table -->
<div class="product-table-container">
  <table class="product-table">
  <thead>
  <tr>
    <th>ID</th>
    <th>Product Name</th>
    <th>Pack</th>
    <th>Batch</th>
    <th>HSN</th>
    <th>Exp Date</th>
    <th>MRP</th>
    <th>Qty</th>
    <th>Dis%</th>
    <th>IGST%</th>
    <th>CGST%</th>
    <th>SGST%</th>
    <th>Total</th>
    <th>Del</th>
    <th>Next</th>
  </tr>
  </thead>
  <tbody id="productDetails"></tbody>
  </table>
</div>

<!-- Scroll indicator for mobile -->
<div class="d-block d-md-none">
  <small class="text-muted">
    👈 Scroll left/right in the table above to see all columns
  </small>
</div>

<!-- Bill Summary Section -->
<div class="bill-summary">
  <h5>🧾 Bill Summary</h5>
  <div class="table-responsive">
    <table class="table table-bordered table-sm text-center align-middle mb-0">
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
</div>

<!-- Action Buttons -->
<div class="btn-group-mobile">
  <div class="btn-group-left">
    <a href="list_sales.php" class="btn btn-outline-secondary">📋 Sales List</a>
    <a href="../dashboard.php" class="btn btn-outline-info">🏠 Dashboard</a>
  </div>
  <button type="submit" class="btn btn-primary btn-lg">💾 Save Bill</button>
</div>

</form>
</div>

<script>
$(document).ready(function(){
    // Auto-fill customer mobile on change
    $("#customer_name").on("change", function(){
        let mobile = $(this).find(":selected").data("mobile") || "";
        $("#mobile_number").val(mobile);
    });

    // Set fixed invoice number for auto-generation
    $('#bill_no').val('AUTO').prop('readonly', true).css({
        'background-color': '#f8f9fa',
        'color': '#6c757d',
        'font-weight': 'bold'
    });
    
    // Remove the bill number fetching since we're using auto-generation
    console.log('Using auto-generated invoice numbers');

    // Product autocomplete with correct path
    $("#product_name").autocomplete({
        source: function(request, response){
            $.ajax({
                url: "../ajax/search_sales_products.php",
                dataType: "json",
                data: {q: request.term},
                timeout: 10000,
                success: function(data){
                    console.log('Search success, found items:', data.length);
                    
                    if (data && data.length > 0) {
                        response($.map(data, function(item){
                            return {
                                label: item.product_name + " | Pack: " + (item.pack || 'N/A') + " | Batch: " + (item.batch_no || 'N/A') + " | Stock: " + (item.available_qty || 0) + " | MRP: ₹" + (item.mrp || 0),
                                value: item.product_name,
                                data: item
                            };
                        }));
                    } else {
                        response([{
                            label: "No products found for '" + request.term + "'",
                            value: "",
                            data: null
                        }]);
                    }
                },
                error: function(xhr, status, error){
                    console.error('Product search error:', status, error);
                    console.error('Response:', xhr.responseText);
                    response([{
                        label: "❌ Search failed: " + error,
                        value: "",
                        data: null
                    }]);
                }
            });
        },
        minLength: 2,
        select: function(event, ui){
            if (ui.item.data) {
                appendProductRow(ui.item.data);
                let lastRow = $('#productDetails tr:last');
                setTimeout(function(){
                    lastRow.find('[name="quantity[]"]').focus().select();
                }, 100);
                $("#product_name").val('');
            }
            return false;
        },
        focus: function(event, ui) {
            return false; // Prevent value insertion on focus
        }
    });

    // Enhanced product search with Enter key support
    $("#product_name").on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            e.preventDefault();
            $(this).autocomplete('search', $(this).val());
        }
    });

    // Clear search field on focus
    $("#product_name").on('focus', function() {
        $(this).select();
    });

    // Append product row with validation
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
            <td><input type="date" class="form-control form-control-sm" name="expiry_date[]" value="${exp}" style="font-size:0.8rem;"></td>
            <td><input type="number" step="0.01" class="form-control form-control-sm mrps" name="mrp[]" value="${mrp}" style="font-size:0.8rem;"></td>
            <td>
              <input type="text" class="form-control form-control-sm quantities" name="quantity[]" value="1" data-stock="${stock}" style="font-size:0.8rem;" title="Available Stock: ${stock}&#13;&#10;Examples: 1.5, 2/3, 0.25" placeholder="1 or 1.5 or 2/3" inputmode="decimal">
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

    // Update totals function
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

    // Add quantity formatting and validation handlers with better error logging
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
        $(this).attr('placeholder', 'e.g: 1.5, 2/3, 1 1/2');
        // Select all text for easy editing
        this.select();
    });

    $('#productDetails').on('blur', '.quantities', function() {
        $(this).attr('placeholder', '1 or 1.5 or 2/3');
    });

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
        location.reload(); 
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

    // Mobile-specific enhancements
    if ($(window).width() <= 768) {
        // Add touch-friendly interactions
        $('.product-table-container').on('touchstart', function() {
            $(this).addClass('touching');
        });
        
        $('.product-table-container').on('touchend', function() {
            setTimeout(() => $(this).removeClass('touching'), 300);
        });
        
        // Show scroll hint on first product addition
        let scrollHintShown = false;
        const originalAppendProductRow = appendProductRow;
        appendProductRow = function(p) {
            originalAppendProductRow(p);
            if (!scrollHintShown && $(window).width() <= 768) {
                showToast('📱 Scroll horizontally in the table to see all columns', 'info');
                scrollHintShown = true;
            }
        };
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

<?php if (isset($_SESSION['bill_created'])): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let billNo = "<?= $_SESSION['bill_created']; ?>";
    <?php unset($_SESSION['bill_created']); ?>
    if (confirm("✅ Bill " + billNo + " created successfully!\nDo you want to print it?")) {
      window.location.href = "../print/bill_print.php?bill_no=" + encodeURIComponent(billNo);
    }
});
</script>
<?php endif; ?>

</body>
</html>