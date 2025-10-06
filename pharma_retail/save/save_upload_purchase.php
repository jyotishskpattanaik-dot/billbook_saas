<?php
require_once __DIR__ . '/../../includes/init.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getModulePDO();

// debug helper
function debugLog($msg) {
    file_put_contents(__DIR__ . "/debug.log", "\n" . date("Y-m-d H:i:s") . " - " . $msg, FILE_APPEND);
}

// simple sanitizer + uppercase
function clean($v) {
    $v = $v ?? '';
    $v = trim(strip_tags($v));
    return mb_strtoupper($v);
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    // session values
    $company_id = $COMPANY_ID ?? ($_SESSION['company_id'] ?? null);
    $year_id    = $YEAR_ID ?? ($_SESSION['financial_year_id'] ?? null);
    $created_by = $CURRENT_USER ?? ($_SESSION['user_id'] ?? 'SYSTEM');

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company/year in session");
    }

    debugLog("=== SAVE PURCHASE START ===");
    debugLog("POST: " . print_r($_POST, true));

    // header fields
    $purchase_id    = clean($_POST['purchase_id'] ?? '');
    $purchase_date  = trim($_POST['purchase_date'] ?? '');
    $supplier_id    = intval($_POST['supplier_id'] ?? 0);
    $invoice_number = clean($_POST['invoice_number'] ?? '');
    $bill_type      = clean($_POST['payment_mode'] ?? '');
    //$remarks        = clean($_POST['remarks'] ?? '');

    // get supplier name
    $stmt = $pdo->prepare("SELECT supplier_name FROM supplier_details WHERE id = :id LIMIT 1");
    debugLog("SELECT supplier_name for supplier_id={$supplier_id}");
    $stmt->execute([':id' => $supplier_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $_SESSION['error'] = "Invalid supplier selected.";
        header("Location: ../purchasenstock/upload_purchase.php");
        exit;
    }
    $supplier_name = clean($row['supplier_name']);

    // basic validation
    if ($purchase_id === '' || $purchase_date === '' || $supplier_name === '') {
        $_SESSION['error'] = "Purchase ID, Date and Supplier are required.";
        header("Location: ../purchasenstock/upload_purchase.php");
        exit;
    }
    if (!DateTime::createFromFormat('Y-m-d', $purchase_date)) {
        $_SESSION['error'] = "Invalid date format. Use YYYY-MM-DD.";
        header("Location: ../purchasenstock/upload_purchase.php");
        exit;
    }

    // product arrays
    $product_names = $_POST['product_name'] ?? [];
    if (!is_array($product_names) || count($product_names) === 0) {
        $_SESSION['error'] = "No products added to this purchase.";
        header("Location: ../purchasenstock/upload_purchase.php");
        exit;
    }

    $pdo->beginTransaction();

    // 1) insert purchase header
    $sqlPurchase = "
        INSERT INTO purchase_details
        (purchase_id, supplier_id, supplier_name, invoice_no, amount, purchase_date, created_at, created_by, bill_type, company_id, accounting_year_id)
        VALUES (:purchase_id, :supplier_id, :supplier_name, :invoice_no, :amount, :purchase_date, NOW(), :created_by, :bill_type, :company_id, :year_id)
    ";
    debugLog("INSERT purchase header");
    $stmt = $pdo->prepare($sqlPurchase);
    $stmt->execute([
        ':purchase_id'   => $purchase_id,
        ':supplier_id'   => $supplier_id,
        ':supplier_name' => $supplier_name,
        ':invoice_no'    => $invoice_number,
        ':amount'        => 0, // will update later
        ':purchase_date' => $purchase_date,
        ':created_by'    => $created_by,
        ':bill_type'     => $bill_type,
        ':company_id'    => $company_id,
        ':year_id'       => $year_id
    ]);
    $purchase_db_id = $pdo->lastInsertId();

    $grand_total = 0.0;

    // prepare statements we'll reuse
    $selProduct = $pdo->prepare("SELECT id FROM product_master WHERE product_name = :product_name AND batch_no = :batch_no LIMIT 1");
    $insProduct = $pdo->prepare("
        INSERT INTO product_master
        (product_name, company, category, composition, batch_no, hsn_code, pack, mrp, rate, expiry_date, sgst, cgst, igst, created_at)
        VALUES (:product_name, :company, :category, :composition, :batch_no, :hsn, :pack, :mrp, :rate, :expiry_date, :sgst, :cgst, :igst, NOW())
    ");
    $insStockDetails = $pdo->prepare("
        INSERT INTO stock_details
        (product_id, supplier_id, supplier_name, invoice_no, product_name, company, composition, pack, quantity, mrp, exp_date, batch, category,
         purchase_id, purchase_date, amount, created_by, free_quantity, rate, discount,
         total_discount, total_taxable_amount, total_payble_amount, gst_amount, igst, igst_amount, cgst, sgst,
         cgst_amount, sgst_amount, net_amount, hsn_code, total_quantity, bill_type, company_id, accounting_year_id, created_at)
        VALUES
        (:product_id, :supplier_id, :supplier_name, :invoice_no, :product_name, :company, :composition, :pack, :quantity, :mrp, :exp_date, :batch, :category,
         :purchase_id, :purchase_date, :amount, :created_by, :free_quantity, :rate, :discount,
         :total_discount, :total_taxable_amount, :total_payble_amount, :gst_amount, :igst, :igst_amount, :cgst, :sgst,
         :cgst_amount, :sgst_amount, :net_amount, :hsn_code, :total_quantity, :bill_type, :company_id, :year_id, NOW())
    ");
    $insOrUpdCurrentStock = $pdo->prepare("
        INSERT INTO current_stock
        (product_id, supplier_id, product_name, company, category, pack, batch_no, expiry_date, composition, hsn_code,
         quantity, free_quantity, total_quantity, rate, mrp, discount, total_discount, sgst, sgst_amount, cgst, cgst_amount,
         igst, igst_amount, taxable_amount, net_amount, purchase_id, invoice_no, purchase_date, supplier_name,
         created_by, company_id, accounting_year_id, created_at)
        VALUES
        (:product_id, :supplier_id, :product_name, :company, :category, :pack, :batch_no, :expiry_date, :composition, :hsn_code,
         :quantity, :free_quantity, :total_quantity, :rate, :mrp, :discount, :total_discount, :sgst, :sgst_amount, :cgst, :cgst_amount,
         :igst, :igst_amount, :taxable_amount, :net_amount, :purchase_id, :invoice_no, :purchase_date, :supplier_name,
         :created_by, :company_id, :year_id, NOW())
        ON DUPLICATE KEY UPDATE
            quantity = quantity + VALUES(quantity),
            free_quantity = free_quantity + VALUES(free_quantity),
            total_quantity = total_quantity + VALUES(total_quantity),
            last_modified = NOW()
    ");

    // loop products
    $rows = count($product_names);
    for ($i = 0; $i < $rows; $i++) {
        $p_name      = clean($_POST['product_name'][$i] ?? '');
        $company     = clean($_POST['company'][$i] ?? '');
        $category    = clean($_POST['category'][$i] ?? '');
        $pack        = clean($_POST['pack'][$i] ?? '');
        $batch       = clean($_POST['batch'][$i] ?? '');
        $hsn         = clean($_POST['hsn_code'][$i] ?? '');
        $composition = clean($_POST['composition'][$i] ?? '');

        // ✅ expiry date handling
        $raw_exp_date = trim($_POST['exp_date'][$i] ?? '');
        if ($raw_exp_date === '' || !DateTime::createFromFormat('Y-m-d', $raw_exp_date)) {
            $exp_date = "0000-00-00";
        } else {
            $exp_date = $raw_exp_date;
        }

        $mrp         = floatval($_POST['mrp'][$i] ?? 0);
        $rate        = floatval($_POST['rate'][$i] ?? 0);
        $qty         = floatval($_POST['quantity'][$i] ?? 0.0);
        $discount    = floatval($_POST['discount'][$i] ?? 0.0);
        $free_qty    = floatval($_POST['free_quantity'][$i] ?? 0.0);

        if ($p_name === '' || $qty <= 0 || $rate <= 0) {
            continue; // skip invalid row
        }

        // ensure product_master exists
        $selProduct->execute([':product_name' => $p_name, ':batch_no' => $batch]);
        $prodRow = $selProduct->fetch(PDO::FETCH_ASSOC);
        if (!$prodRow) {
            $insProduct->execute([
                ':product_name' => $p_name,
                ':company' => $company,
                ':category' => $category,
                ':composition' => $composition,
                ':batch_no' => $batch,
                ':hsn' => $hsn,
                ':pack' => $pack,
                ':mrp' => $mrp,
                ':rate' => $rate,
                ':expiry_date' => $exp_date,
                ':sgst' => floatval($_POST['sgst'][$i] ?? 0),
                ':cgst' => floatval($_POST['cgst'][$i] ?? 0),
                ':igst' => floatval($_POST['igst'][$i] ?? 0)
            ]);
            $product_id = $pdo->lastInsertId();
        } else {
            $product_id = $prodRow['id'];
        }

        // calculations
        $baseAmount = $rate * $qty;
        $discountAmount = ($discount / 100.0) * $baseAmount;
        $taxableAmount = $baseAmount - $discountAmount;

        $sgst = floatval($_POST['sgst'][$i] ?? 0);
        $cgst = floatval($_POST['cgst'][$i] ?? 0);
        $igst = floatval($_POST['igst'][$i] ?? 0);

        $sgstAmount = $taxableAmount * ($sgst / 100.0);
        $cgstAmount = $taxableAmount * ($cgst / 100.0);
        $igstAmount = $taxableAmount * ($igst / 100.0);
        $gstAmount = $sgstAmount + $cgstAmount + $igstAmount;

        $netAmount = $taxableAmount + $gstAmount;
        $total_quantity = $qty + $free_qty;

        $grand_total += $netAmount;

        // insert stock_details
        $insStockDetails->execute([
            ':product_id' => $product_id,
            ':supplier_id' => $supplier_id,
            ':supplier_name' => $supplier_name,
            ':invoice_no' => $invoice_number,
            ':product_name' => $p_name,
            ':company' => $company,
            ':composition' => $composition,
            ':pack' => $pack,
            ':quantity' => $qty,
            ':mrp' => $mrp,
            ':exp_date' => $exp_date,
            ':batch' => $batch,
            ':category' => $category,
            ':purchase_id' => $purchase_id,
            ':purchase_date' => $purchase_date,
            ':amount' => $netAmount,
            ':created_by' => $created_by,
            ':free_quantity' => $free_qty,
            ':rate' => $rate,
            ':discount' => $discount,
            ':total_discount' => $discountAmount,
            ':total_taxable_amount' => $taxableAmount,
            ':total_payble_amount' => $netAmount,
            ':gst_amount' => $gstAmount,
            ':igst' => $igst,
            ':igst_amount' => $igstAmount,
            ':cgst' => $cgst,
            ':sgst' => $sgst,
            ':cgst_amount' => $cgstAmount,
            ':sgst_amount' => $sgstAmount,
            ':net_amount' => $netAmount,
            ':hsn_code' => $hsn,
            ':total_quantity' => $total_quantity,
            ':bill_type' => $bill_type,
            ':company_id' => $company_id,
            ':year_id' => $year_id
        ]);

        // insert/update current_stock
        $insOrUpdCurrentStock->execute([
            ':product_id' => $product_id,
            ':supplier_id' => $supplier_id,
            ':product_name' => $p_name,
            ':company' => $company,
            ':category' => $category,
            ':pack' => $pack,
            ':batch_no' => $batch,
            ':expiry_date' => $exp_date,
            ':composition' => $composition,
            ':hsn_code' => $hsn,
            ':quantity' => $qty,
            ':free_quantity' => $free_qty,
            ':total_quantity' => $total_quantity,
            ':rate' => $rate,
            ':mrp' => $mrp,
            ':discount' => $discount,
            ':total_discount' => $discountAmount,
            ':sgst' => $sgst,
            ':sgst_amount' => $sgstAmount,
            ':cgst' => $cgst,
            ':cgst_amount' => $cgstAmount,
            ':igst' => $igst,
            ':igst_amount' => $igstAmount,
            ':taxable_amount' => $taxableAmount,
            ':net_amount' => $netAmount,
            ':purchase_id' => $purchase_id,
            ':invoice_no' => $invoice_number,
            ':purchase_date' => $purchase_date,
            ':supplier_name' => $supplier_name,
            ':created_by' => $created_by,
            ':company_id' => $company_id,
            ':year_id' => $year_id
        ]);
    } // end products loop

    // update purchase header totals
    $stmtUp = $pdo->prepare("UPDATE purchase_details SET amount = :amount WHERE id = :id");
    $stmtUp->execute([':amount' => $grand_total, ':id' => $purchase_db_id]);

    $pdo->commit();

    $_SESSION['success'] = "Purchase saved successfully! Total amount: " . number_format($grand_total, 2);
    header("Location: ../purchasenstock/add_new_purchase.php?success=1");
    exit;

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    debugLog("ERROR: " . $e->getMessage());
    $_SESSION['error'] = "Error saving purchase: " . $e->getMessage();
    header("Location: ../purchasenstock/add_new_purchase.php");
    exit;
}
