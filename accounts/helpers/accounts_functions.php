<?php

// Simple logging helper to write debug info to a file
if (!function_exists('debugLog')) {
    function debugLog($message) {
        $logFile = __DIR__ . '/debug.log'; // path relative to helpers folder
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($logFile, "\n[$timestamp] $message", FILE_APPEND);
    }
}

// account_functions.php
// Modular accounting helper functions for Purchases, Sales, Payments, and Receipts

require_once __DIR__ . '/../../includes/init.php'; // ensure $pdo connection exists

/* ============================================================
   LEDGER ACCOUNT MANAGEMENT
   ============================================================ */

/**
 * Ensure ledger account exists (create if not) with basic account type
 */
function ensureLedgerAccount(PDO $pdo, int $company_id, string $account_name, string $account_type = 'Sundry Debtor'): int
{
    $stmt = $pdo->prepare("SELECT id FROM ledger_accounts WHERE company_id = ? AND account_name = ?");
    $stmt->execute([$company_id, $account_name]);
    $account = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($account) return (int)$account['id'];

    // auto-detect type for common accounts
    $nameLower = strtolower($account_name);
    if (in_array($nameLower, ['cash', 'bank', 'upi', 'pos'])) {
        $account_type = 'Asset';
    } elseif (in_array($nameLower, ['purchase'])) {
        $account_type = 'Expense';
    } elseif (in_array($nameLower, ['sales'])) {
        $account_type = 'Income';
    }

    $insert = $pdo->prepare("
        INSERT INTO ledger_accounts 
        (company_id, account_name, account_type, opening_balance, dr_cr, entry_date, created_at)
        VALUES (?, ?, ?, 0, 'Dr', CURDATE(), NOW())
    ");
    $insert->execute([$company_id, $account_name, $account_type]);

    return (int)$pdo->lastInsertId();
}

/* ============================================================
   LEDGER ENTRIES (DOUBLE ENTRY)
   ============================================================ */

/**
 * Add double-entry ledger record (assumes active transaction)
 */
function addLedgerEntry(
    PDO $pdo,
    int $company_id,
    int $year_id,
    string $debitAccount,
    string $creditAccount,
    float $amount,
    string $narration,
    string $reference_table,
    int $reference_id,
    string $created_by,
    ?string $entry_date = null
) {
    $entry_date = $entry_date ?? date('Y-m-d');

    // Prevent duplicate entries for the same reference
    $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM ledger_entries WHERE reference_table = ? AND reference_id = ?");
    $stmtCheck->execute([$reference_table, $reference_id]);
    if ($stmtCheck->fetchColumn() > 0) return;

    $debit_id  = ensureLedgerAccount($pdo, $company_id, $debitAccount);
    $credit_id = ensureLedgerAccount($pdo, $company_id, $creditAccount);

    // Debit
    $stmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (company_id, accounting_year_id, account_id, dr_cr, amount, narration, reference_table, reference_id, created_by, entry_date, created_at)
        VALUES (?, ?, ?, 'Dr', ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$company_id, $year_id, $debit_id, $amount, $narration, $reference_table, $reference_id, $created_by, $entry_date]);

    // Credit
    $stmt = $pdo->prepare("
        INSERT INTO ledger_entries
        (company_id, accounting_year_id, account_id, dr_cr, amount, narration, reference_table, reference_id, created_by, entry_date, created_at)
        VALUES (?, ?, ?, 'Cr', ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$company_id, $year_id, $credit_id, $amount, $narration, $reference_table, $reference_id, $created_by, $entry_date]);
}

/**
 * Delete all ledger entries by reference
 */
function deleteLedgerEntries(PDO $pdo, string $reference_table, int $reference_id)
{
    $stmt = $pdo->prepare("DELETE FROM ledger_entries WHERE reference_table = ? AND reference_id = ?");
    $stmt->execute([$reference_table, $reference_id]);
}

/* ============================================================
   CASH BOOK
   ============================================================ */

function addCashBookEntry(
    PDO $pdo,
    int $company_id,
    int $year_id,
    string $description,
    float $cash_in,
    float $cash_out,
    string $payment_mode,
    string $reference_table,
    int $reference_id,
    ?string $entry_date = null
) {
    $entry_date = $entry_date ?? date('Y-m-d');

    $stmt = $pdo->prepare("
        INSERT INTO cash_book
        (company_id, accounting_year_id, description, cash_in, cash_out, payment_mode, reference_table, reference_id, entry_date, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$company_id, $year_id, $description, $cash_in, $cash_out, ucfirst(strtolower($payment_mode)), $reference_table, $reference_id, $entry_date]);
}

function deleteCashBookEntries(PDO $pdo, string $reference_table, int $reference_id)
{
    $stmt = $pdo->prepare("DELETE FROM cash_book WHERE reference_table = ? AND reference_id = ?");
    $stmt->execute([$reference_table, $reference_id]);
}

/* ============================================================
   PURCHASE MODULE
   ============================================================ */

function createPurchaseSummary(
    PDO $pdo,
    int $company_id,
    int $year_id,
    string $purchase_id,
    string $supplier_name,
    string $invoice_number,
    string $purchase_date,
    float $grand_total,
    string $bill_type,
    string $remarks,
    string $created_by
) {
    $stmt = $pdo->prepare("
        INSERT INTO purchase_summary 
        (company_id, accounting_year_id, purchase_id, supplier_name, invoice_no, purchase_date, grand_total, amount_paid, payment_mode, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $amount_paid = (strtolower($bill_type) === 'credit') ? 0 : $grand_total;
    $stmt->execute([$company_id, $year_id, $purchase_id, $supplier_name, $invoice_number, $purchase_date, $grand_total, $amount_paid, $bill_type, $remarks, $created_by]);
    return (int)$pdo->lastInsertId();
}

function deletePurchaseSummary(PDO $pdo, string $purchase_id)
{
    $stmt = $pdo->prepare("DELETE FROM purchase_summary WHERE purchase_id = ?");
    $stmt->execute([$purchase_id]);
}

function createSupplierPayment(
    PDO $pdo,
    string $purchase_id,
    int $company_id,
    int $year_id,
    string $supplier_name,
    string $invoice_number,
    string $bill_type,
    float $amount,
    string $created_by
) {
    $stmt = $pdo->prepare("
        INSERT INTO supplier_payments
        (purchase_id, company_id, accounting_year_id, supplier_name, invoice_no, payment_date, payment_mode, amount_paid, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, CURDATE(), ?, ?, 'Auto payment on purchase entry', ?, NOW())
    ");
    $stmt->execute([$purchase_id, $company_id, $year_id, $supplier_name, $invoice_number, $bill_type, $amount, $created_by]);
    return (int)$pdo->lastInsertId();
}

function deleteSupplierPayments(PDO $pdo, string $purchase_id)
{
    $stmt = $pdo->prepare("DELETE FROM supplier_payments WHERE purchase_id = ?");
    $stmt->execute([$purchase_id]);
}

/* ============================================================
   SALES MODULE
   ============================================================ */

function createSalesSummary(
    PDO $pdo,
    int $company_id,
    int $year_id,
    string $sale_id,
    string $customer_name,
    string $bill_no,
    string $bill_date,
    float $net_amount,
    string $bill_type,
    string $remarks,
    string $created_by
) {
    $stmt = $pdo->prepare("
        INSERT INTO sales_summary
        (company_id, accounting_year_id, sale_id, customer_name, bill_no, bill_date, net_amount, amount_received, payment_mode, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $amount_received = (strtolower($bill_type) === 'credit') ? 0 : $net_amount;
    $stmt->execute([$company_id, $year_id, $sale_id, $customer_name, $bill_no, $bill_date, $net_amount, $amount_received, $bill_type, $remarks, $created_by]);
    return (int)$pdo->lastInsertId();
}

function deleteSalesSummary(PDO $pdo, string $sale_id)
{
    $stmt = $pdo->prepare("DELETE FROM sales_summary WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
}

function createCustomerReceipt(
    PDO $pdo,
    string $sale_id,
    int $company_id,
    int $year_id,
    string $customer_name,
    string $bill_no,
    string $payment_mode,
    float|string $amount_received,
    string $created_by,
    ?string $payment_date = null
) {
    $payment_date = $payment_date ?? date('Y-m-d');
    $amount_received = (float)$amount_received; // ensures float internally

    $stmt = $pdo->prepare("
        INSERT INTO customer_receipts
        (sale_id, company_id, accounting_year_id, customer_name, bill_no, payment_date, payment_mode, amount_received, remarks, created_by, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Auto receipt on sale entry', ?, NOW())
    ");
    $stmt->execute([$sale_id, $company_id, $year_id, $customer_name, $bill_no, $payment_date, $payment_mode, $amount_received, $created_by]);
    return (int)$pdo->lastInsertId();
}


function deleteCustomerReceipts(PDO $pdo, string $sale_id)
{
    $stmt = $pdo->prepare("DELETE FROM customer_receipts WHERE sale_id = ?");
    $stmt->execute([$sale_id]);
}

/* ============================================================
   COMBINED CLEANUP FUNCTIONS
   ============================================================ */

function deleteAllPurchaseAccounting(PDO $pdo, string $purchase_id)
{
    deleteLedgerEntries($pdo, 'purchase_summary', $purchase_id);
    deleteCashBookEntries($pdo, 'supplier_payments', $purchase_id);
    deletePurchaseSummary($pdo, $purchase_id);
    deleteSupplierPayments($pdo, $purchase_id);
}

function deleteAllSalesAccounting(PDO $pdo, string $sale_id)
{
    deleteLedgerEntries($pdo, 'sales_summary', $sale_id);
    deleteCashBookEntries($pdo, 'customer_receipts', $sale_id);
    deleteSalesSummary($pdo, $sale_id);
    deleteCustomerReceipts($pdo, $sale_id);
}

function addVoucher(
    PDO $pdo,
    int $companyId,
    int $yearId,
    string $voucherType,
    string $category,
    float $amount,
    string $paymentMode,
    string $counterparty,
    string $narration,
    string $createdBy,
    ?string $voucherDate = null,
    ?string $remarks = null
) {
    $voucherDate = $voucherDate ?? date('Y-m-d');
    $createdAt = date('Y-m-d H:i:s');
    $referenceTable = 'vouchers';

    try {
        // ✅ Start transaction safely
        if (!$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }

        // 1️⃣ Generate next voucher number
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM vouchers");
        $stmt->execute();
        $nextId = $stmt->fetchColumn();
        $voucherNo = "VCH-" . str_pad($nextId, 5, '0', STR_PAD_LEFT);

        // 2️⃣ Insert into vouchers table
        $stmt = $pdo->prepare("
            INSERT INTO vouchers 
            (company_id, accounting_year_id, voucher_no, voucher_type, voucher_date, narration, category, payment_mode, amount, counterparty, remarks, created_by, created_at)
            VALUES 
            (:company_id, :accounting_year_id, :voucher_no, :voucher_type, :voucher_date, :narration, :category, :payment_mode, :amount, :counterparty, :remarks, :created_by, :created_at)
        ");

        $stmt->execute([
            ':company_id' => $companyId,
            ':accounting_year_id' => $yearId,
            ':voucher_no' => $voucherNo,
            ':voucher_type' => $voucherType,
            ':voucher_date' => $voucherDate,
            ':narration' => $narration,
            ':category' => $category,
            ':payment_mode' => $paymentMode,
            ':amount' => $amount,
            ':counterparty' => $counterparty,
            ':remarks' => $remarks,
            ':created_by' => $createdBy,
            ':created_at' => $createdAt,
        ]);

        $voucherId = $pdo->lastInsertId();

        // 3️⃣ Determine cash flow direction
        $cashIn = 0;
        $cashOut = 0;

        if (strtolower($voucherType) === 'receipt') {
            $cashIn = $amount;
            $description = "Received from $counterparty";
        } elseif (strtolower($voucherType) === 'payment' || strtolower($voucherType) === 'expense') {
            $cashOut = $amount;
            $description = "Paid to $counterparty";
        } else {
            $description = "Voucher entry: $voucherType";
        }

        // 4️⃣ Get last closing balance
        $stmt = $pdo->prepare("
            SELECT closing_balance 
            FROM cash_book 
            WHERE company_id = ? AND accounting_year_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$companyId, $yearId]);
        $lastClosing = $stmt->fetchColumn() ?: 0;

        // 5️⃣ Calculate new closing balance
        $closingBalance = $lastClosing + $cashIn - $cashOut;

        // 6️⃣ Insert into cash_book
        $stmt = $pdo->prepare("
            INSERT INTO cash_book 
            (voucher_no, company_id, accounting_year_id, entry_date, description, cash_in, cash_out, payment_mode, reference_table, reference_id, closing_balance, created_at)
            VALUES 
            (:voucher_no, :company_id, :accounting_year_id, :entry_date, :description, :cash_in, :cash_out, :payment_mode, :reference_table, :reference_id, :closing_balance, :created_at)
        ");

        $stmt->execute([
            ':voucher_no' => $voucherNo,
            ':company_id' => $companyId,
            ':accounting_year_id' => $yearId,
            ':entry_date' => $voucherDate,
            ':description' => $description,
            ':cash_in' => $cashIn,
            ':cash_out' => $cashOut,
            ':payment_mode' => $paymentMode,
            ':reference_table' => $referenceTable,
            ':reference_id' => $voucherId,
            ':closing_balance' => $closingBalance,
            ':created_at' => $createdAt,
        ]);

        // ✅ Commit only if active
        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        debugLog("✅ Voucher added successfully: $voucherNo (Type: $voucherType, Amount: $amount, Counterparty: $counterparty)");
        return $voucherNo;

    } catch (Throwable $e) {
        // ✅ Roll back safely only if transaction active
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        debugLog("❌ addVoucher error: " . $e->getMessage());
        return false;
    }
}





?>
