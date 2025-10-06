<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getModulePDO();

function debugLog($message) {
    $logFile = __DIR__ . "/update_debug.log";
    file_put_contents($logFile, "\n" . date("Y-m-d H:i:s") . " - " . $message, FILE_APPEND);
}

// Enhanced quantity parser for fractional sales
function parseQuantity($input) {
    if (!$input) return 0;
    $input = trim($input);
    
    // Handle fractions like "2/3", "1/2", "3/4", "1/30", "1/15"
    if (preg_match('/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/', $input, $matches)) {
        $numerator = floatval($matches[1]);
        $denominator = floatval($matches[2]);
        if ($denominator == 0) return 0;
        $result = $numerator / $denominator;
        return round($result, 6);
    }
    
    // Handle mixed numbers like "1 1/2" or "2 3/4"
    if (preg_match('/^(\d+)\s+(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/', $input, $matches)) {
        $whole = floatval($matches[1]);
        $numerator = floatval($matches[2]);
        $denominator = floatval($matches[3]);
        if ($denominator == 0) return $whole;
        $fraction = $numerator / $denominator;
        return round($whole + $fraction, 6);
    }
    
    // Handle decimal numbers
    return floatval($input);
}

// Validate fractional quantity
function validateQuantity($qty, $stock, $original_qty = 0) {
    if ($qty <= 0) {
        return ['valid' => false, 'error' => 'Quantity must be greater than 0'];
    }
    
    // For updates, add back the original quantity to available stock for validation
    $effective_stock = $stock + $original_qty;
    
    if ($qty > $effective_stock) {
        return ['valid' => false, 'error' => "Insufficient stock (available: $effective_stock, requested: $qty)"];
    }
    
    // Check for reasonable decimal precision (max 6 decimal places)
    if (round($qty, 6) != $qty) {
        $qty = round($qty, 6);
    }
    
    return ['valid' => true, 'quantity' => $qty];
}

try {
    debugLog("=== UPDATE BILL PROCESS STARTED ===");
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // Session values
    $company_id = $COMPANY_ID ?? ($_SESSION['company_id'] ?? null);
    $year_id    = $YEAR_ID ?? ($_SESSION['financial_year_id'] ?? null);
    $created_by = $CURRENT_USER ?? 'SYSTEM';

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company or financial year in session.");
    }

    // Header fields
    $bill_no       = trim($_POST['bill_no'] ?? '');
    $bill_date     = trim($_POST['bill_date'] ?? date('Y-m-d'));
    $customer_id   = intval($_POST['customer_name'] ?? 0);
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $doctor        = trim($_POST['doctor'] ?? '');
    $payment_mode  = trim($_POST['payment_mode'] ?? '');

    if (empty($bill_no)) {
        throw new Exception("Bill number is required");
    }

    if ($customer_id <= 0) {
        throw new Exception("Please select a valid customer");
    }

    debugLog("Updating bill: $bill_no");

    // Get customer name
    $stmt_cust = $pdo->prepare("SELECT customer_name FROM customer_details WHERE id = ? AND company_id = ? AND accounting_year_id = ?");
    $stmt_cust->execute([$customer_id, $company_id, $year_id]);
    $customer_name = $stmt_cust->fetchColumn();
    
    if (!$customer_name) {
        throw new Exception("Invalid customer selected");
    }

    // Fetch original bill details for stock restoration
    $stmt_original = $pdo->prepare("SELECT sale_id FROM sale_bill_master WHERE bill_no = ? AND company_id = ? AND accounting_year_id = ?");
    $stmt_original->execute([$bill_no, $company_id, $year_id]);
    $original_bill = $stmt_original->fetch(PDO::FETCH_ASSOC);
    
    if (!$original_bill) {
        throw new Exception("Original bill not found");
    }
    
    $sale_id = $original_bill['sale_id'];

    // Get original products for stock restoration
    $stmt_orig_products = $pdo->prepare("SELECT product_id, batch_no, quantity FROM sale_bill_products WHERE sale_id = ?");
    $stmt_orig_products->execute([$sale_id]);
    $original_products = $stmt_orig_products->fetchAll(PDO::FETCH_ASSOC);

    // Product arrays from form
    $product_ids   = $_POST['product_id'] ?? [];
    $product_names = $_POST['product_name'] ?? [];
    $packs         = $_POST['pack'] ?? [];
    $batches       = $_POST['batch'] ?? [];
    $hsns          = $_POST['hsn_code'] ?? [];
    $exp_dates     = $_POST['expiry_date'] ?? [];
    $mrps          = $_POST['mrp'] ?? [];
    $quantities    = $_POST['quantity'] ?? [];
    $discounts     = $_POST['discount'] ?? [];
    $igsts         = $_POST['igst'] ?? [];
    $cgsts         = $_POST['cgst'] ?? [];
    $sgsts         = $_POST['sgst'] ?? [];

    if (count($product_ids) === 0) {
        throw new Exception("No products in the bill");
    }

    debugLog("Processing " . count($product_ids) . " products");

    // Pre-validate all quantities
    $validated_products = [];
    
    for ($i = 0; $i < count($product_ids); $i++) {
        $pid   = intval($product_ids[$i]);
        $pname = trim($product_names[$i]);
        $batch = trim($batches[$i] ?? '');
        $qty_input = $quantities[$i] ?? '';
        
        if ($pid <= 0) continue;
        
        // Parse fractional quantity
        $qty = parseQuantity($qty_input);
        debugLog("Product $pname: Input '$qty_input' parsed to $qty");
        
        if ($qty <= 0) {
            throw new Exception("Invalid quantity '$qty_input' for product $pname");
        }
        
        // Get current stock and original quantity for this product
        $stmt_stock_check = $pdo->prepare("SELECT total_quantity FROM current_stock WHERE product_id = ? AND batch_no = ?");
        $stmt_stock_check->execute([$pid, $batch]);
        $current_stock = $stmt_stock_check->fetchColumn();
        
        if ($current_stock === false) {
            throw new Exception("Stock record not found for product $pname, batch $batch");
        }
        
        // Find original quantity for this product/batch combination
        $original_qty = 0;
        foreach ($original_products as $orig) {
            if ($orig['product_id'] == $pid && $orig['batch_no'] == $batch) {
                $original_qty = $orig['quantity'];
                break;
            }
        }
        
        $validation = validateQuantity($qty, $current_stock, $original_qty);
        if (!$validation['valid']) {
            throw new Exception("$pname: " . $validation['error']);
        }
        
        $validated_products[] = [
            'index' => $i,
            'quantity' => $validation['quantity'],
            'current_stock' => $current_stock,
            'original_qty' => $original_qty
        ];
    }

    debugLog("All products validated. Starting database transaction...");
    $pdo->beginTransaction();

    // STEP 1: Restore stock for original products
    debugLog("Restoring stock for original products...");
    foreach ($original_products as $orig) {
        $stmt_restore = $pdo->prepare("UPDATE current_stock 
            SET total_quantity = total_quantity + ?,
                quantity = quantity + ?,
                status = CASE 
                    WHEN total_quantity + ? > 0 AND status = 'OUT' THEN 'ACTIVE' 
                    ELSE status 
                END
            WHERE product_id = ? AND batch_no = ?");
        $stmt_restore->execute([
            $orig['quantity'], $orig['quantity'], $orig['quantity'], 
            $orig['product_id'], $orig['batch_no']
        ]);
        debugLog("Restored {$orig['quantity']} units for product {$orig['product_id']}, batch {$orig['batch_no']}");
    }

    // STEP 2: Delete old bill products
    $stmt_delete_products = $pdo->prepare("DELETE FROM sale_bill_products WHERE sale_id = ?");
    $stmt_delete_products->execute([$sale_id]);
    debugLog("Deleted old bill products");

    // STEP 3: Insert new products and deduct stock
    $sql_product = "INSERT INTO sale_bill_products
        (sale_id, bill_no, bill_type, product_id, product_name, pack, batch_no, hsn_code, expiry_date,
         mrp, quantity, taxable_amount, net_amount, gst, gst_amount, cgst, cgst_amount,
         sgst, sgst_amount, igst, igst_amount, discount, discount_amount, created_by, creation_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt_product = $pdo->prepare($sql_product);

    // Totals
    $grand_total = 0;
    $total_gst_amount = 0;
    $total_discount_amount = 0;
    $total_taxable_amount = 0;

    debugLog("Processing new products...");
    foreach ($validated_products as $vp) {
        $i = $vp['index'];
        $qty = $vp['quantity'];
        
        $pid   = intval($product_ids[$i]);
        $pname = trim($product_names[$i]);
        $pack  = trim($packs[$i] ?? '');
        $batch = trim($batches[$i] ?? '');
        $hsn   = trim($hsns[$i] ?? '');
        $exp   = trim($exp_dates[$i] ?? '');
        $mrp   = floatval($mrps[$i] ?? 0);
        $disc  = floatval($discounts[$i] ?? 0);
        $igst  = floatval($igsts[$i] ?? 0);
        $cgst  = floatval($cgsts[$i] ?? 0);
        $sgst  = floatval($sgsts[$i] ?? 0);

        if ($mrp <= 0) {
            throw new Exception("Invalid MRP for product $pname");
        }

        debugLog("Processing: $pname, Qty: $qty, MRP: $mrp");

        // Lock stock for update
        $stmt_stock = $pdo->prepare("SELECT product_id, batch_no, total_quantity 
            FROM current_stock 
            WHERE product_id = ? AND batch_no = ? FOR UPDATE");
        $stmt_stock->execute([$pid, $batch]);
        $stock_row = $stmt_stock->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock_row) {
            throw new Exception("Stock record not found for product $pname, batch $batch");
        }
        
        if ($qty > $stock_row['total_quantity']) {
            throw new Exception("Insufficient stock for $pname (available: {$stock_row['total_quantity']}, requested: $qty)");
        }

        // Calculate prices with enhanced precision for fractional quantities
        $base_amount     = round($mrp * $qty, 4);
        $discount_amount = round(($disc / 100) * $base_amount, 2);
        $taxable_amount  = round($base_amount - $discount_amount, 2);

        $igst_amount = round(($igst / 100) * $taxable_amount, 2);
        $cgst_amount = round(($cgst / 100) * $taxable_amount, 2);
        $sgst_amount = round(($sgst / 100) * $taxable_amount, 2);
        $gst_amount  = round($igst_amount + $cgst_amount + $sgst_amount, 2);
        $net_amount  = round($taxable_amount + $gst_amount, 2);

        $gst_pct = $igst + $cgst + $sgst;

        debugLog("Calculations - Net: $net_amount, GST: $gst_amount");

        // Insert product
        $stmt_product->execute([
            $sale_id, $bill_no, $payment_mode, $pid, $pname, $pack, $batch, $hsn, $exp, $mrp, $qty,
            $taxable_amount, $net_amount, $gst_pct, $gst_amount,
            $cgst, $cgst_amount, $sgst, $sgst_amount, $igst, $igst_amount,
            $disc, $discount_amount, $created_by
        ]);

        // Deduct new stock
        $new_total_qty = round($stock_row['total_quantity'] - $qty, 6);
        $stmt_update = $pdo->prepare("UPDATE current_stock 
            SET total_quantity = ?, 
                quantity = GREATEST(0, ROUND(quantity - ?, 6)),
                status = CASE WHEN ? <= 0 THEN 'OUT' ELSE status END
            WHERE product_id = ? AND batch_no = ?");
        $stmt_update->execute([$new_total_qty, $qty, $new_total_qty, $pid, $batch]);

        debugLog("Stock updated - New total: $new_total_qty");

        // Update totals
        $total_taxable_amount  += $taxable_amount;
        $total_gst_amount      += $gst_amount;
        $grand_total           += $net_amount;
        $total_discount_amount += $discount_amount;
    }

    // Round final totals
    $total_taxable_amount  = round($total_taxable_amount, 2);
    $total_gst_amount      = round($total_gst_amount, 2);
    $grand_total           = round($grand_total, 2);
    $total_discount_amount = round($total_discount_amount, 2);

    debugLog("Final totals calculated");

    // STEP 4: Update bill_summary
    $stmt_update_summary = $pdo->prepare("UPDATE bill_summary 
        SET customer_name = ?, mobile_number = ?, doctor = ?, bill_date = ?, bill_type = ?,
            discount_amount = ?, grand_total = ?, gst_amount = ?
        WHERE id = ?");
    $stmt_update_summary->execute([
        $customer_name, $mobile_number, $doctor, $bill_date, $payment_mode,
        $total_discount_amount, $grand_total, $total_gst_amount, $sale_id
    ]);

    // STEP 5: Update sale_bill_master
    $stmt_update_master = $pdo->prepare("UPDATE sale_bill_master
        SET customer_name = ?, mobile_number = ?, doctor = ?, bill_date = ?, bill_type = ?,
            taxable_amount = ?, net_amount = ?, gst_amount = ?, discount_amount = ?
        WHERE sale_id = ?");
    $stmt_update_master->execute([
        $customer_name, $mobile_number, $doctor, $bill_date, $payment_mode,
        $total_taxable_amount, $grand_total, $total_gst_amount, $total_discount_amount, $sale_id
    ]);

    $pdo->commit();

    debugLog("=== BILL UPDATED SUCCESSFULLY ===");
    debugLog("Bill No: $bill_no, Sale ID: $sale_id, Grand Total: $grand_total");

    $_SESSION['success'] = "Bill $bill_no updated successfully!";
    header("Location: ../salesnbilling/create_bill.php?bill_no=" . urlencode($bill_no) . "&success=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    debugLog("❌ Error: " . $e->getMessage());
    debugLog("❌ Error trace: " . $e->getTraceAsString());
    $_SESSION['error'] = "Error updating bill: " . $e->getMessage();
    header("Location: ../salesnbilling/create_bill.php?bill_no=" . urlencode($bill_no ?? ''));
    exit;
}
?>