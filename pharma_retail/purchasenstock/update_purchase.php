<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../accounts/helpers/accounts_functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getModulePDO();

function debugLog($message) {
    $logFile = __DIR__ . "/debug.log";
    file_put_contents($logFile, "\n" . date("Y-m-d H:i:s") . " - " . $message, FILE_APPEND);
}

function debugExecute($stmt, $params, $tag = "") {
    debugLog("[$tag] QUERY: " . $stmt->queryString . " | PARAMS: " . json_encode($params));
    return $stmt->execute($params);
}

try {
    $pdo = getModulePDO();
    $pdo->beginTransaction();
    debugLog("=== UPDATE PURCHASE START ===");

    // --- Session Values ---
    $company_id = $COMPANY_ID ?? ($_SESSION['company_id'] ?? null);
    $year_id    = $YEAR_ID ?? ($_SESSION['financial_year_id'] ?? null);
    $created_by = $CURRENT_USER ?? (currentUser() ?? 'SYSTEM');

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company or financial year in session.");
    }

    debugLog("POST: " . print_r($_POST, true));

    // --- HEADER FIELDS ---
    $purchase_id    = trim($_POST['purchase_id'] ?? '');
    $purchase_date  = trim($_POST['purchase_date'] ?? '');
    $supplier_id    = intval($_POST['supplier_id'] ?? 0);
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $bill_type      = trim($_POST['payment_mode'] ?? '');
    $remarks        = trim($_POST['remarks'] ?? '');

    // --- Validation ---
    if ($purchase_id === '' || $purchase_date === '' || $supplier_id <= 0) {
        $_SESSION['error'] = "Purchase ID, Date and Supplier are required.";
        header("Location: ../purchasenstock/edit_purchase.php?purchase_id=" . urlencode($purchase_id));
        exit;
    }

    if (!DateTime::createFromFormat('Y-m-d', $purchase_date)) {
        $_SESSION['error'] = "Invalid date format. Please use YYYY-MM-DD.";
        header("Location: ../purchasenstock/edit_purchase.php?purchase_id=" . urlencode($purchase_id));
        exit;
    }

    // ✅ Fetch supplier_name from DB
    $stmt = $pdo->prepare("SELECT supplier_name FROM supplier_details WHERE id = ? LIMIT 1");
    debugExecute($stmt, [$supplier_id], "GET supplier_name");
    $supplierRow = $stmt->fetch(PDO::FETCH_ASSOC);
    $supplier_name = $supplierRow['supplier_name'] ?? '';

    if (empty($supplier_name)) {
        throw new Exception("Supplier not found with ID: $supplier_id");
    }

    // --- Update purchase_details header ---
    $updatePurchaseSql = "
        UPDATE purchase_details
           SET supplier_id = ?, 
               supplier_name = ?, 
               invoice_no = ?, 
               purchase_date = ?, 
               bill_type = ?
         WHERE purchase_id = ? 
           AND company_id = ? 
           AND accounting_year_id = ?
    ";
    $stmt = $pdo->prepare($updatePurchaseSql);
    debugExecute($stmt, [
        $supplier_id,
        $supplier_name,
        $invoice_number,
        $purchase_date,
        $bill_type,
        $purchase_id,
        $company_id,
        $year_id
    ], "UPDATE purchase_details");

    // --- Clear child rows ---
    $stmt = $pdo->prepare("DELETE FROM stock_details WHERE purchase_id = ? AND company_id = ? AND accounting_year_id = ?");
    debugExecute($stmt, [$purchase_id, $company_id, $year_id], "DELETE stock_details");

    $stmt = $pdo->prepare("DELETE FROM current_stock WHERE purchase_id = ? AND company_id = ? AND accounting_year_id = ?");
    debugExecute($stmt, [$purchase_id, $company_id, $year_id], "DELETE current_stock");

  // --- Clean up old linked entries before recreating them ---
try {
    // 🔍 Find existing purchase_summary_id first
    $stmt = $pdo->prepare("
        SELECT id FROM purchase_summary 
        WHERE purchase_id = ? AND company_id = ? AND accounting_year_id = ? LIMIT 1
    ");
    $stmt->execute([$purchase_id, $company_id, $year_id]);
    $old_summary = $stmt->fetch(PDO::FETCH_ASSOC);
    $old_summary_id = $old_summary['id'] ?? null;

    // 1️⃣ Delete supplier payments
    $stmt = $pdo->prepare("
        DELETE FROM supplier_payments 
        WHERE purchase_id = ? AND company_id = ? AND accounting_year_id = ?
    ");
    $stmt->execute([$purchase_id, $company_id, $year_id]);

    // 2️⃣ Delete ledger entries and cash book using OLD reference_id
    if ($old_summary_id) {
        $stmt = $pdo->prepare("
            DELETE FROM ledger_entries 
            WHERE reference_id = ? AND company_id = ? AND accounting_year_id = ?
        ");
        $stmt->execute([$old_summary_id, $company_id, $year_id]);

        $stmt = $pdo->prepare("
            DELETE FROM cash_book 
            WHERE reference_id = ? AND company_id = ? AND accounting_year_id = ?
        ");
        $stmt->execute([$old_summary_id, $company_id, $year_id]);
    }

    // 3️⃣ Finally delete old purchase summary
    $stmt = $pdo->prepare("
        DELETE FROM purchase_summary 
        WHERE purchase_id = ? AND company_id = ? AND accounting_year_id = ?
    ");
    $stmt->execute([$purchase_id, $company_id, $year_id]);

} catch (PDOException $e) {
    throw new Exception("Error cleaning old purchase entries: " . $e->getMessage());
}



    $grand_total = 0.0;

    // --- Product arrays ---
    $product_ids   = $_POST['product_id'] ?? [];
    $product_names = $_POST['product_name'] ?? [];

    if (!is_array($product_names) || count($product_names) === 0) {
        $_SESSION['error'] = "No products added to this bill.";
        header("Location: ../purchasenstock/edit_purchase.php?purchase_id=" . urlencode($purchase_id));
        exit;
    }

    $rows = count($product_names);

    for ($i = 0; $i < $rows; $i++) {
        $p_id       = isset($product_ids[$i]) ? intval($product_ids[$i]) : null;
        $p_name     = trim($product_names[$i] ?? '');
        $company    = trim($_POST['company'][$i] ?? '');
        $category   = trim($_POST['category'][$i] ?? '');
        $pack       = trim($_POST['pack'][$i] ?? '');
        $batch      = trim($_POST['batch'][$i] ?? '');
        $hsn        = trim($_POST['hsn_code'][$i] ?? '');
        $expiry_date = trim($_POST['expiry_date'][$i] ?? null);
        $mrp        = floatval($_POST['mrp'][$i] ?? 0);
        $rate       = floatval($_POST['rate'][$i] ?? 0);
        $qty        = floatval($_POST['quantity'][$i] ?? 0);
        $discount   = floatval($_POST['discount'][$i] ?? 0);
        $free_qty   = floatval($_POST['free_quantity'][$i] ?? 0);
        $composition= trim($_POST['composition'][$i] ?? '');
        $igst       = floatval($_POST['igst'][$i] ?? 0);
        $cgst       = floatval($_POST['cgst'][$i] ?? 0);
        $sgst       = floatval($_POST['sgst'][$i] ?? 0);

        if ($p_name === '' || $qty <= 0 || $rate <= 0) {
            continue;
        }

        // --- Validation ---
        if ($mrp < 0 || $discount < 0 || $discount > 100) {
            continue;
        }

        if ($igst < 0 || $cgst < 0 || $sgst < 0) {
            continue;
        }

        // --- Ensure product_id from product_master ---
        if (!$p_id) {
            $stmt = $pdo->prepare("SELECT id FROM product_master WHERE product_name = ? AND batch_no = ? LIMIT 1");
            debugExecute($stmt, [$p_name, $batch], "SELECT product_master");
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                $stmt = $pdo->prepare("
                    INSERT INTO product_master 
                    (product_name, company, category, composition, batch_no, hsn_code, pack, mrp, rate, expiry_date, sgst, cgst, igst, created_at) 
                    VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
                ");
                debugExecute($stmt, [$p_name, $company, $category, $composition, $batch, $hsn, $pack, $mrp, $rate, $expiry_date, $sgst, $cgst, $igst], "INSERT product_master");
                $p_id = $pdo->lastInsertId();
            } else {
                $p_id = $existing['id'];
            }
        }

        // --- Calculations ---
        $baseAmount     = $rate * $qty;
        $discountAmount = ($discount / 100) * $baseAmount;
        $taxableAmount  = $baseAmount - $discountAmount;

        $igstAmount = $taxableAmount * ($igst / 100);
        $cgstAmount = $taxableAmount * ($cgst / 100);
        $sgstAmount = $taxableAmount * ($sgst / 100);

        $gstAmount = $igstAmount + $cgstAmount + $sgstAmount;
        $netAmount = $taxableAmount + $gstAmount;

        $total_quantity = $qty + $free_qty;
        $grand_total   += $netAmount;

        // --- stock_details insert (36 columns, 36 values) ---
        $stmt = $pdo->prepare("
            INSERT INTO stock_details 
            (product_id, supplier_id, supplier_name, invoice_no, product_name, company, composition, pack, quantity, mrp, expiry_date, batch, category, 
             purchase_id, purchase_date, amount, created_by, free_quantity, rate, discount, 
             total_discount, total_taxable_amount, total_payble_amount, gst_amount, igst, igst_amount, cgst, sgst, 
             cgst_amount, sgst_amount, net_amount, hsn_code, total_quantity, bill_type, company_id, accounting_year_id, created_at) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        debugExecute($stmt, [
            $p_id,                   // product_id
            $supplier_id,            // supplier_id
            $supplier_name,          // supplier_name
            $invoice_number,         // invoice_no
            $p_name,                 // product_name
            $company,                // company
            $composition,            // composition
            $pack,                   // pack
            $qty,                    // quantity
            $mrp,                    // mrp
            $expiry_date,            // expiry_date
            $batch,                  // batch
            $category,               // category
            $purchase_id,            // purchase_id
            $purchase_date,          // purchase_date
            $netAmount,              // amount
            $created_by,             // created_by
            $free_qty,               // free_quantity
            $rate,                   // rate
            $discount,               // discount
            $discountAmount,         // total_discount
            $taxableAmount,          // total_taxable_amount
            $netAmount,              // total_payble_amount
            $gstAmount,              // gst_amount
            $igst,                   // igst
            $igstAmount,             // igst_amount
            $cgst,                   // cgst
            $sgst,                   // sgst
            $cgstAmount,             // cgst_amount
            $sgstAmount,             // sgst_amount
            $netAmount,              // net_amount
            $hsn,                    // hsn_code
            $total_quantity,         // total_quantity
            $bill_type,              // bill_type
            $company_id,             // company_id
            $year_id                 // accounting_year_id
        ], "INSERT stock_details");

        // --- current_stock insert (31 columns, 31 values) ---
        $stmt = $pdo->prepare("
            INSERT INTO current_stock 
            (product_id, product_name, company, category, pack, batch_no, expiry_date, composition, hsn_code, 
             quantity, free_quantity, total_quantity, rate, mrp, discount, total_discount, sgst, sgst_amount, cgst, cgst_amount, 
             igst, igst_amount, taxable_amount, net_amount, purchase_id, invoice_no, purchase_date, supplier_id, supplier_name, 
             created_at, created_by, company_id, accounting_year_id) 
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
        ");
        debugExecute($stmt, [
            $p_id,                          // product_id
            $p_name,                        // product_name
            $company,                       // company
            $category,                      // category
            $pack,                          // pack
            $batch,                         // batch_no
            $expiry_date,                    // expiry_date
            $composition,                   // composition
            $hsn,                           // hsn_code
            $qty,                           // quantity
            $free_qty,                      // free_quantity
            $total_quantity,                // total_quantity
            $rate,                          // rate
            $mrp,                           // mrp
            $discount,                      // discount
            $discountAmount,                // total_discount
            $sgst,                          // sgst
            $sgstAmount,                    // sgst_amount
            $cgst,                          // cgst
            $cgstAmount,                    // cgst_amount
            $igst,                          // igst
            $igstAmount,                    // igst_amount
            $taxableAmount,                 // taxable_amount
            $netAmount,                     // net_amount
            $purchase_id,                   // purchase_id
            $invoice_number,                // invoice_no
            $purchase_date,                 // purchase_date
            $supplier_id,                   // supplier_id
            $supplier_name,                 // supplier_name
            date("Y-m-d H:i:s"),           // created_at
            $created_by,                    // created_by
            $company_id,                    // company_id
            $year_id                        // accounting_year_id
        ], "INSERT current_stock");
    }

    // --- Update final amount ---
    $stmt = $pdo->prepare("UPDATE purchase_details SET amount=? WHERE purchase_id=? AND company_id=? AND accounting_year_id=?");
    debugExecute($stmt, [$grand_total, $purchase_id, $company_id, $year_id], "UPDATE final amount");
    
     // --- ACCOUNTING MODULE INTEGRATION START ---
// require_once __DIR__ . '/../../accounts/helpers/account_functions.php';

// Step 1️⃣ - Create Purchase Summary FIRST
$purchase_summary_id = createPurchaseSummary(
    $pdo,
    $company_id,
    $year_id,
    $purchase_id,
    $supplier_name,
    $invoice_number,
    $purchase_date,
    $grand_total,
    $bill_type,
    $remarks,
    $created_by
);

// Step 2️⃣ - Determine Debit/Credit accounts
if (strtolower($bill_type) === 'cash') {
    $debitAccount  = 'Purchase';
    $creditAccount = 'Cash';
} else {
    $debitAccount  = 'Purchase';
    $creditAccount = $supplier_name; // Credit supplier
}

// Step 3️⃣ - Create Ledger Entries
addLedgerEntry(
    $pdo,
    $company_id,
    $year_id,
    $debitAccount,
    $creditAccount,
    $grand_total,
    "Purchase Invoice #$invoice_number",
    'purchase_summary',
    $purchase_summary_id,
    $created_by,
    $purchase_date
);

// Step 4️⃣ - If Cash Purchase → record Cash Book + Supplier Payment
if (strtolower($bill_type) === 'cash') {
    // Cash Book entry
    addCashBookEntry(
        $pdo,
        $company_id,
        $year_id,
        "Payment for Purchase Invoice #$invoice_number",
        $grand_total,
        0,
        'Cash',
        'supplier_payments',
        $purchase_summary_id,
        $purchase_date
    );

    // Supplier Payment entry
    createSupplierPayment(
        $pdo,
        $purchase_id,
        $company_id,
        $year_id,
        $supplier_name,
        $invoice_number,
        $bill_type,
        $grand_total,
        $created_by
    );
}

// --- ACCOUNTING MODULE INTEGRATION END ---

    $pdo->commit();
    debugLog("=== COMMIT SUCCESSFUL (purchase_id=$purchase_id, total=$grand_total) ===");

    $_SESSION['success'] = "Purchase updated successfully! Total amount: " . number_format($grand_total, 2);
    header("Location: ../../reports/purchase/list_purchase.php?success=1");
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
        debugLog("!!! ROLLBACK due to error: " . $e->getMessage());
    }
    error_log("update_purchase error: " . $e->getMessage());
    $_SESSION['error'] = "Error updating purchase: " . $e->getMessage();
    header("Location: ../purchasenstock/edit_purchase.php?purchase_id=" . urlencode($purchase_id));
    exit;
}
?>