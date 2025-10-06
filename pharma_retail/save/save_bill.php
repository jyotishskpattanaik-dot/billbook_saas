<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getModulePDO();

function debugLog($message) {
    $logFile = __DIR__ . "/debug.log";
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
        // Round to 6 decimal places to handle small fractions like 1/30
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
function validateQuantity($qty, $stock) {
    if ($qty <= 0) {
        return ['valid' => false, 'error' => 'Quantity must be greater than 0'];
    }
    
    if ($qty > $stock) {
        return ['valid' => false, 'error' => "Insufficient stock (available: $stock, requested: $qty)"];
    }
    
    // Check for reasonable decimal precision (max 3 decimal places)
    if (round($qty, 3) != $qty) {
        $qty = round($qty, 3);
    }
    
    return ['valid' => true, 'quantity' => $qty];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // --- Session values ---
    $company_id = $COMPANY_ID ?? ($_SESSION['company_id'] ?? null);
    $year_id    = $YEAR_ID ?? ($_SESSION['financial_year_id'] ?? null);
    $created_by = $CURRENT_USER ?? 'SYSTEM';

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company or financial year in session.");
    }

    debugLog("=== NEW SALE SAVE START ===");
    debugLog("POST DATA: " . print_r($_POST, true));

    // --- Header fields ---
    $bill_no_input = trim($_POST['bill_no'] ?? '');
    $bill_date     = trim($_POST['bill_date'] ?? date('Y-m-d'));
    $customer_id   = intval($_POST['customer_name'] ?? 0);
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $doctor        = trim($_POST['doctor'] ?? '');
    $payment_mode  = trim($_POST['payment_mode'] ?? '');

    // Generate auto invoice number if needed
    $bill_no = '';
    if (empty($bill_no_input) || $bill_no_input === 'AUTO') {
        // Generate bill number based on current year and sequence
        $current_year = date('Y');
        $stmt_max = $pdo->prepare("SELECT MAX(CAST(SUBSTRING(bill_no, -6) AS UNSIGNED)) as max_seq 
                                  FROM bill_summary 
                                  WHERE company_id = ? AND accounting_year_id = ? 
                                  AND bill_no LIKE ?");
        $prefix = "INV{$current_year}";
        $stmt_max->execute([$company_id, $year_id, "{$prefix}%"]);
        $max_seq = $stmt_max->fetchColumn() ?? 0;
        $next_seq = $max_seq + 1;
        $bill_no = $prefix . str_pad($next_seq, 6, '0', STR_PAD_LEFT);
        
        debugLog("Auto-generated bill number: $bill_no (sequence: $next_seq)");
    } else {
        $bill_no = $bill_no_input;
    }

    if (empty($bill_no)) {
        throw new Exception("Failed to generate bill number");
    }

    // Check if bill number already exists
    $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM bill_summary WHERE bill_no = ? AND company_id = ? AND accounting_year_id = ?");
    $stmt_check->execute([$bill_no, $company_id, $year_id]);
    if ($stmt_check->fetchColumn() > 0) {
        throw new Exception("Bill number $bill_no already exists");
    }

    if ($customer_id <= 0) {
        throw new Exception("Please select a valid customer");
    }

    // Resolve customer name
    $stmt_cust = $pdo->prepare("SELECT customer_name FROM customer_details WHERE id = ? AND company_id = ? AND accounting_year_id = ?");
    $stmt_cust->execute([$customer_id, $company_id, $year_id]);
    $customer_name = $stmt_cust->fetchColumn();
    
    if (!$customer_name) {
        throw new Exception("Invalid customer selected");
    }

    // --- Product arrays ---
    $product_ids   = $_POST['product_id'] ?? [];
    $product_names = $_POST['product_name'] ?? [];
    $packs         = $_POST['pack'] ?? [];
    $batches       = $_POST['batch'] ?? [];
    $hsns          = $_POST['hsn_code'] ?? [];
    $expiry_dates     = $_POST['expiry_date'] ?? [];
    $mrps          = $_POST['mrp'] ?? [];
    $quantities    = $_POST['quantity'] ?? [];
    $discounts     = $_POST['discount'] ?? [];
    $igsts         = $_POST['igst'] ?? [];
    $cgsts         = $_POST['cgst'] ?? [];
    $sgsts         = $_POST['sgst'] ?? [];

    if (count($product_ids) === 0) {
        throw new Exception("No products added to the bill");
    }

    // Pre-validate all quantities and check stock
    debugLog("=== VALIDATING QUANTITIES ===");
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
        
        // Check stock availability
        $stmt_stock_check = $pdo->prepare("SELECT total_quantity FROM current_stock WHERE product_id = ? AND batch_no = ?");
        $stmt_stock_check->execute([$pid, $batch]);
        $available_stock = $stmt_stock_check->fetchColumn();
        
        if ($available_stock === false) {
            throw new Exception("Stock record not found for product $pname, batch $batch");
        }
        
        $validation = validateQuantity($qty, $available_stock);
        if (!$validation['valid']) {
            throw new Exception("$pname: " . $validation['error']);
        }
        
        $validated_products[] = [
            'index' => $i,
            'quantity' => $validation['quantity'],
            'available_stock' => $available_stock
        ];
    }

    $pdo->beginTransaction();

    // --- STEP 1: Insert into bill_summary ---
    $sql_summary = "INSERT INTO bill_summary 
        (company_id, accounting_year_id, bill_type, bill_no, bill_date, 
         customer_name, mobile_number, doctor, discount_amount, grand_total, gst_amount, 
         creation_date, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, NOW(), ?)";
    $stmt_summary = $pdo->prepare($sql_summary);
    $stmt_summary->execute([
        $company_id, $year_id, $payment_mode, $bill_no, $bill_date,
        $customer_name, $mobile_number, $doctor, $created_by
    ]);

    $sale_id = $pdo->lastInsertId();

    // --- Prepare insert for products ---
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

    debugLog("=== PROCESSING VALIDATED PRODUCTS ===");

    // --- STEP 2: Loop through validated products ---
    foreach ($validated_products as $vp) {
        $i = $vp['index'];
        $qty = $vp['quantity']; // Use validated quantity
        
        $pid   = intval($product_ids[$i]);
        $pname = trim($product_names[$i]);
        $pack  = trim($packs[$i] ?? '');
        $batch = trim($batches[$i] ?? '');
        $hsn   = trim($hsns[$i] ?? '');
        $exp   = trim($expiry_dates[$i] ?? '');
        $mrp   = floatval($mrps[$i] ?? 0);
        $disc  = floatval($discounts[$i] ?? 0);
        $igst  = floatval($igsts[$i] ?? 0);
        $cgst  = floatval($cgsts[$i] ?? 0);
        $sgst  = floatval($sgsts[$i] ?? 0);

        if ($mrp <= 0) {
            throw new Exception("Invalid MRP for product $pname");
        }

        debugLog("Processing: $pname, Qty: $qty, MRP: $mrp, Discount: $disc%");

        // --- Lock stock record for update ---
        $stmt_stock = $pdo->prepare("SELECT product_id, batch_no, total_quantity 
            FROM current_stock 
            WHERE product_id = ? AND batch_no = ? FOR UPDATE");
        $stmt_stock->execute([$pid, $batch]);
        $stock_row = $stmt_stock->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock_row) {
            throw new Exception("Stock record not found for product $pname, batch $batch");
        }
        
        // Double-check stock (in case it changed between validation and processing)
        if ($qty > $stock_row['total_quantity']) {
            throw new Exception("Insufficient stock for $pname (available: {$stock_row['total_quantity']}, requested: $qty)");
        }

        // --- Enhanced price calculation for fractional quantities ---
        $base_amount     = round($mrp * $qty, 4); // More precision for fractional calculations
        $discount_amount = round(($disc / 100) * $base_amount, 2);
        $taxable_amount  = round($base_amount - $discount_amount, 2);

        $igst_amount = round(($igst / 100) * $taxable_amount, 2);
        $cgst_amount = round(($cgst / 100) * $taxable_amount, 2);
        $sgst_amount = round(($sgst / 100) * $taxable_amount, 2);
        $gst_amount  = round($igst_amount + $cgst_amount + $sgst_amount, 2);
        $net_amount  = round($taxable_amount + $gst_amount, 2);

        $gst_pct = $igst + $cgst + $sgst;

        debugLog("Calculations - Base: $base_amount, Discount: $discount_amount, Taxable: $taxable_amount, GST: $gst_amount, Net: $net_amount");

        // --- Insert product row ---
        $stmt_product->execute([
            $sale_id, $bill_no, $payment_mode, $pid, $pname, $pack, $batch, $hsn, $exp, $mrp, $qty,
            $taxable_amount, $net_amount, $gst_pct, $gst_amount,
            $cgst, $cgst_amount, $sgst, $sgst_amount, $igst, $igst_amount,
            $disc, $discount_amount, $created_by
        ]);

        // --- Update stock with fractional deduction ---
        $new_total_qty = round($stock_row['total_quantity'] - $qty, 3);
        $stmt_update = $pdo->prepare("UPDATE current_stock 
            SET total_quantity = ?, 
                quantity = GREATEST(0, ROUND(quantity - ?, 3)),
                status = CASE WHEN ? <= 0 THEN 'OUT' ELSE status END
            WHERE product_id = ? AND batch_no = ?");
        $stmt_update->execute([$new_total_qty, $qty, $new_total_qty, $pid, $batch]);

        debugLog("Stock updated - Product: $pname, Batch: $batch, Deducted: $qty, New Stock: $new_total_qty");

        // --- Update totals ---
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

    debugLog("Final Totals - Taxable: $total_taxable_amount, GST: $total_gst_amount, Discount: $total_discount_amount, Grand: $grand_total");

    // --- STEP 3: Update bill_summeay with totals ---
    $stmt_update_summary = $pdo->prepare("UPDATE bill_summary 
        SET discount_amount = ?, grand_total = ?, gst_amount = ?
        WHERE id = ?");
    $stmt_update_summary->execute([
        $total_discount_amount, $grand_total, $total_gst_amount, $sale_id
    ]);

    // --- STEP 4: Insert into sale_bill_master ---
    $stmt_master = $pdo->prepare("INSERT INTO sale_bill_master
        (sale_id, company_id, accounting_year_id, bill_no, customer_name, 
         mobile_number, doctor, bill_date, bill_type,
         taxable_amount, net_amount, gst_amount, discount_amount, 
         created_by, creation_date) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt_master->execute([
        $sale_id, $company_id, $year_id, $bill_no, $customer_name,
        $mobile_number, $doctor, $bill_date, $payment_mode,
        $total_taxable_amount, $grand_total, $total_gst_amount, $total_discount_amount,
        $created_by
    ]);

    $pdo->commit();

    debugLog("=== SALE SAVED SUCCESSFULLY ===");
    debugLog("Bill No: $bill_no, Sale ID: $sale_id, Grand Total: $grand_total");

    // Pass bill_no via session for prompt
    $_SESSION['bill_created'] = $bill_no;
    header("Location: ../salesnbilling/create_bill.php?success=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    debugLog("❌ Error: " . $e->getMessage());
    $_SESSION['error'] = "Error saving bill: " . $e->getMessage();
    header("Location: ../salesnbilling/create_bill.php");
    exit;
}
?>