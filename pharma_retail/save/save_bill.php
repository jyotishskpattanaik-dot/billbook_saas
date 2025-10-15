<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/../../accounts/helpers/accounts_functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getModulePDO();

function debugLog($message) {
    $logFile = __DIR__ . "/debug.log";
    file_put_contents($logFile, "\n" . date("Y-m-d H:i:s") . " - " . $message, FILE_APPEND);
}

// --- FRACTIONAL QUANTITY HANDLER ---
function parseQuantity($input) {
    if (!$input) return 0;
    $input = trim($input);

    if (preg_match('/^(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/', $input, $m)) {
        return round(floatval($m[1]) / floatval($m[2]), 6);
    }
    if (preg_match('/^(\d+)\s+(\d+(?:\.\d+)?)\/(\d+(?:\.\d+)?)$/', $input, $m)) {
        return round(floatval($m[1]) + (floatval($m[2]) / floatval($m[3])), 6);
    }
    return floatval($input);
}

function validateQuantity($qty, $stock) {
    if ($qty <= 0) return ['valid' => false, 'error' => 'Quantity must be > 0'];
    if ($qty > $stock) return ['valid' => false, 'error' => "Insufficient stock (available: $stock)"];
    return ['valid' => true, 'quantity' => round($qty, 3)];
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new Exception("Invalid request method");

    $company_id = $_SESSION['company_id'] ?? null;
    $year_id    = $_SESSION['financial_year_id'] ?? null;
    $created_by = $_SESSION['username'] ?? 'SYSTEM';

    if (!$company_id || !$year_id) throw new Exception("Missing company or financial year in session.");

    debugLog("=== SALE SAVE START ===");

    $bill_no_input = trim($_POST['bill_no'] ?? '');
    $bill_date     = trim($_POST['bill_date'] ?? date('Y-m-d'));
    $customer_id   = intval($_POST['customer_name'] ?? 0);
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $doctor        = trim($_POST['doctor'] ?? '');
    $payment_mode  = trim($_POST['payment_mode'] ?? 'cash');

   // --- Auto Bill Number ---
if (empty($bill_no_input) || strtoupper($bill_no_input) === 'AUTO') {

    // get both pdo connections
    $mainPDO = getMainPDO();   // <-- add this if not already available
    $modulePDO = getModulePDO();

    // Fetch company name from main database
    $stmt = $mainPDO->prepare("SELECT company_name FROM companies WHERE id = ?");
    $stmt->execute([$company_id]);
    $company_name = $stmt->fetchColumn();

    if (empty($company_name)) {
        throw new Exception("Invalid company ID: company not found in main database");
    }

    // Extract company prefix (first two uppercase letters ignoring spaces)
    $company_prefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $company_name), 0, 2));

    // Year code (2 digits)
    $year_code = date('y');

    // Prefix format
    $bill_prefix = "{$company_prefix}-{$year_code}-";

    // Use the module PDO to check the max bill_no
    $stmt = $modulePDO->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(bill_no, '-', -1) AS UNSIGNED))
        FROM bill_summary
        WHERE company_id = ?
          AND accounting_year_id = ?
          AND bill_no LIKE CONCAT(?, '%')
    ");
    $stmt->execute([$company_id, $year_id, $bill_prefix]);
    $max_seq = $stmt->fetchColumn() ?? 0;

    // Increment serial
    $next_seq = str_pad($max_seq + 1, 6, '0', STR_PAD_LEFT);

    // Final bill number
    $bill_no = "{$bill_prefix}{$next_seq}";

} else {
    $bill_no = $bill_no_input;
}


    if (!$bill_no) throw new Exception("Bill number generation failed");

    $stmt = $pdo->prepare("SELECT customer_name FROM customer_details WHERE id=? AND company_id=? AND accounting_year_id=?");
    $stmt->execute([$customer_id, $company_id, $year_id]);
    $customer_name = $stmt->fetchColumn() ?: throw new Exception("Invalid customer");

    $product_ids   = $_POST['product_id'] ?? [];
    $product_names = $_POST['product_name'] ?? [];
    $packs         = $_POST['pack'] ?? [];
    $batches       = $_POST['batch'] ?? [];
    $hsns          = $_POST['hsn_code'] ?? [];
    $expiry_dates  = $_POST['expiry_date'] ?? [];
    $mrps          = $_POST['mrp'] ?? [];
    $quantities    = $_POST['quantity'] ?? [];
    $discounts     = $_POST['discount'] ?? [];
    $igsts         = $_POST['igst'] ?? [];
    $cgsts         = $_POST['cgst'] ?? [];
    $sgsts         = $_POST['sgst'] ?? [];

    if (empty($product_ids)) throw new Exception("No products added");

    // Validate quantities
    $validated = [];
    foreach ($product_ids as $i => $pid) {
        $pid = intval($pid);
        if ($pid <= 0) continue;
        $qty = parseQuantity($quantities[$i]);
        $stmt = $pdo->prepare("SELECT total_quantity FROM current_stock WHERE product_id=? AND batch_no=?");
        $stmt->execute([$pid, $batches[$i]]);
        $stock = $stmt->fetchColumn() ?? 0;

        $v = validateQuantity($qty, $stock);
        if (!$v['valid']) throw new Exception("{$product_names[$i]}: {$v['error']}");
        $validated[] = ['index'=>$i, 'quantity'=>$v['quantity']];
    }

    $pdo->beginTransaction();

    // Bill Summary
    $stmt = $pdo->prepare("INSERT INTO bill_summary 
        (company_id, accounting_year_id, bill_type, bill_no, bill_date, customer_name, mobile_number, doctor, discount_amount, grand_total, gst_amount, creation_date, created_by)
        VALUES (?,?,?,?,?,?,?,?,0,0,0,NOW(),?)");
    $stmt->execute([$company_id, $year_id, $payment_mode, $bill_no, $bill_date, $customer_name, $mobile_number, $doctor, $created_by]);
    $sale_id = $pdo->lastInsertId();

    $stmt_p = $pdo->prepare("INSERT INTO sale_bill_products
        (sale_id, bill_no, bill_type, product_id, product_name, pack, batch_no, hsn_code, expiry_date, mrp, quantity,
         taxable_amount, net_amount, gst, gst_amount, cgst, cgst_amount, sgst, sgst_amount, igst, igst_amount,
         discount, discount_amount, created_by, creation_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?, ?, NOW())");

    $grand_total = $total_gst = $total_discount = $total_taxable = 0;

    foreach ($validated as $v) {
        $i = $v['index'];
        $qty = $v['quantity'];
        $mrp = floatval($mrps[$i]);
        $disc = floatval($discounts[$i]);
        $igst = floatval($igsts[$i]);
        $cgst = floatval($cgsts[$i]);
        $sgst = floatval($sgsts[$i]);
        $pname = $product_names[$i];
        $pid = intval($product_ids[$i]);
        $batch = $batches[$i];
        $hsn = $hsns[$i];
        $exp = $expiry_dates[$i];
        $pack = $packs[$i];

        $stmt = $pdo->prepare("SELECT total_quantity FROM current_stock WHERE product_id=? AND batch_no=? FOR UPDATE");
        $stmt->execute([$pid, $batch]);
        $stock = $stmt->fetchColumn();
        if ($qty > $stock) throw new Exception("$pname stock insufficient");

        $base = $mrp * $qty;
        $disc_amt = round($base * ($disc / 100), 2);
        $taxable = $base - $disc_amt;
        $igst_amt = round($taxable * ($igst / 100), 2);
        $cgst_amt = round($taxable * ($cgst / 100), 2);
        $sgst_amt = round($taxable * ($sgst / 100), 2);
        $gst_amt = $igst_amt + $cgst_amt + $sgst_amt;
        $net = $taxable + $gst_amt;
        $gst_pct = $igst + $cgst + $sgst;

        $stmt_p->execute([$sale_id, $bill_no, $payment_mode, $pid, $pname, $pack, $batch, $hsn, $exp, $mrp, $qty,
            $taxable, $net, $gst_pct, $gst_amt, $cgst, $cgst_amt, $sgst, $sgst_amt, $igst, $igst_amt,
            $disc, $disc_amt, $created_by]);

        $pdo->prepare("UPDATE current_stock 
            SET total_quantity = ROUND(total_quantity - ?,3),
                quantity = GREATEST(0, ROUND(quantity - ?,3)),
                status = CASE WHEN total_quantity <= 0 THEN 'OUT' ELSE status END
            WHERE product_id=? AND batch_no=?")->execute([$qty,$qty,$pid,$batch]);

        $total_taxable += $taxable;
        $total_gst += $gst_amt;
        $total_discount += $disc_amt;
        $grand_total += $net;
    }

    $pdo->prepare("UPDATE bill_summary SET discount_amount=?, grand_total=?, gst_amount=? WHERE id=?")
        ->execute([$total_discount, $grand_total, $total_gst, $sale_id]);

    $pdo->prepare("INSERT INTO sale_bill_master 
        (sale_id, company_id, accounting_year_id, bill_no, customer_name, mobile_number, doctor, bill_date, bill_type, 
         taxable_amount, net_amount, gst_amount, discount_amount, created_by, creation_date)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())")
         ->execute([$sale_id, $company_id, $year_id, $bill_no, $customer_name, $mobile_number, $doctor, $bill_date,
            $payment_mode, $total_taxable, $grand_total, $total_gst, $total_discount, $created_by]);

    // --- SALES ACCOUNTING MODULE ---
    createSalesSummary($pdo, $company_id, $year_id, $sale_id, $customer_name, $bill_no, $bill_date, $grand_total, $payment_mode, 'Auto entry from sale', $created_by);

    if (strtolower($payment_mode) !== 'credit') {
        createCustomerReceipt($pdo, $sale_id, $company_id, $year_id, $customer_name, $bill_no, $payment_mode, $grand_total, $created_by);
        addCashBookEntry($pdo, $company_id, $year_id, "Cash Sale #$bill_no - $customer_name", $grand_total, 0, $payment_mode, 'bill_summary', $sale_id);
    }

    // --- DOUBLE ENTRY LEDGER ---
    $sales_account = "Sales";
    $customer_account = $customer_name;
    if (strtolower($payment_mode) === 'credit') {
        addLedgerEntry($pdo, $company_id, $year_id, $customer_account, $sales_account, $grand_total, "Credit Sale - $bill_no", 'bill_summary', $sale_id, $created_by);
    } else {
        $cash_or_bank = ucfirst(strtolower($payment_mode)); // Cash, Bank, etc.
        addLedgerEntry($pdo, $company_id, $year_id, $cash_or_bank, $sales_account, $grand_total, "Cash Sale - $bill_no", 'bill_summary', $sale_id, $created_by);
    }

    $pdo->commit();
    debugLog("✅ SALE SUCCESS: $bill_no, Total: $grand_total");

    $_SESSION['bill_created'] = $bill_no;
    header("Location: ../salesnbilling/create_bill.php?success=1");
    exit;
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    debugLog("❌ ERROR: " . $e->getMessage());
    $_SESSION['error'] = "Error saving bill: " . $e->getMessage();
    header("Location: ../salesnbilling/create_bill.php");
    exit;
}
?>
