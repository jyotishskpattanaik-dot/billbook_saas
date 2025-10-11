<?php
/**
 * Common Accounting Helper Functions
 * ----------------------------------
 * Safe transaction handling for vouchers, ledger entries, etc.
 */

if (!function_exists('addLedgerEntry')) {
    /**
     * Insert a ledger double-entry transaction
     */
    function addLedgerEntry(
        PDO $pdo,
        int $companyId,
        int $yearId,
        string $debitAccount,
        string $creditAccount,
        float $amount,
        string $narration,
        string $refTable,
        int $refId,
        string $createdBy
    ): bool {
        $startedTransaction = false;

        try {
            // ✅ Start transaction only if none active
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            // Get debit and credit account IDs
            $stmt = $pdo->prepare("SELECT id FROM ledger_accounts WHERE company_id = ? AND account_name = ? LIMIT 1");
            $stmt->execute([$companyId, $debitAccount]);
            $debitId = $stmt->fetchColumn();

            $stmt->execute([$companyId, $creditAccount]);
            $creditId = $stmt->fetchColumn();

            if (!$debitId || !$creditId) {
                throw new Exception("Invalid ledger accounts: $debitAccount or $creditAccount");
            }

            // Insert into ledger_entries
            $stmt = $pdo->prepare("
                INSERT INTO ledger_entries
                (company_id, accounting_year_id, entry_date, debit_account_id, credit_account_id, amount, narration, ref_table, ref_id, created_by)
                VALUES (:company_id, :year_id, CURDATE(), :debit_id, :credit_id, :amount, :narration, :ref_table, :ref_id, :created_by)
            ");
            $stmt->execute([
                ':company_id' => $companyId,
                ':year_id' => $yearId,
                ':debit_id' => $debitId,
                ':credit_id' => $creditId,
                ':amount' => $amount,
                ':narration' => $narration,
                ':ref_table' => $refTable,
                ':ref_id' => $refId,
                ':created_by' => $createdBy
            ]);

            if ($startedTransaction) {
                $pdo->commit();
            }

            return true;

        } catch (Exception $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Ledger Entry Error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('addVoucher')) {
    /**
     * Create an expense/payment voucher and auto ledger entry.
     */
    function addVoucher(
        PDO $pdo,
        int $companyId,
        int $yearId,
        string $type,         // expense, payment, journal, receipt
        string $categoryName, // e.g., "Fuel" or "Salary"
        float $amount,
        string $paymentMode,  // Cash, Bank, POS, UPI
        string $paidTo,
        string $narration,
        string $createdBy
    ): bool {
        $startedTransaction = false;

        try {
            // ✅ Start transaction only if none active
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
                $startedTransaction = true;
            }

            // Get category ID (from expense_heads)
            $stmt = $pdo->prepare("SELECT id FROM expense_heads WHERE name = ? LIMIT 1");
            $stmt->execute([$categoryName]);
            $categoryId = $stmt->fetchColumn() ?: null;

            // Get payment mode ID
            $stmt = $pdo->prepare("SELECT id FROM payment_methods WHERE method_name = ? LIMIT 1");
            $stmt->execute([$paymentMode]);
            $modeId = $stmt->fetchColumn() ?: null;

            // Generate voucher number
            $stmt = $pdo->query("SELECT COUNT(*) FROM vouchers");
            $nextNo = $stmt->fetchColumn() + 1;
            $voucherNo = strtoupper(substr($type, 0, 3)) . "/" . date("Y") . "/" . str_pad($nextNo, 4, "0", STR_PAD_LEFT);

            // Insert into vouchers
            $stmt = $pdo->prepare("
                INSERT INTO vouchers 
                (company_id, accounting_year_id, voucher_no, voucher_type, voucher_date, particulars, category_id, payment_mode_id, amount, paid_to, remarks, created_by)
                VALUES 
                (:company_id, :year_id, :voucher_no, :voucher_type, CURDATE(), :particulars, :category_id, :payment_mode_id, :amount, :paid_to, :remarks, :created_by)
            ");
            $stmt->execute([
                ':company_id' => $companyId,
                ':year_id' => $yearId,
                ':voucher_no' => $voucherNo,
                ':voucher_type' => $type,
                ':particulars' => $narration,
                ':category_id' => $categoryId,
                ':payment_mode_id' => $modeId,
                ':amount' => $amount,
                ':paid_to' => $paidTo,
                ':remarks' => '',
                ':created_by' => $createdBy
            ]);

            $voucherId = $pdo->lastInsertId();

            // Determine accounts
            $debitAccount = $categoryName;
            $creditAccount = in_array(strtolower($paymentMode), ['cash', 'pos', 'upi']) ? 'Cash' : 'Bank';

            // Add ledger entry safely
            addLedgerEntry(
                $pdo,
                $companyId,
                $yearId,
                $debitAccount,
                $creditAccount,
                $amount,
                $narration,
                'vouchers',
                $voucherId,
                $createdBy
            );

            if ($startedTransaction) {
                $pdo->commit();
            }

            return true;

        } catch (Exception $e) {
            if ($startedTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Voucher Creation Error: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('addCashBookEntry')) {
    /**
     * Update cash book inflow/outflow
     */
    function addCashBookEntry(
        PDO $pdo,
        int $companyId,
        int $yearId,
        string $particulars,
        float $inflow,
        float $outflow,
        string $mode,
        string $refType,
        int $refId
    ): void {
        $stmt = $pdo->prepare("
            INSERT INTO cash_book (company_id, accounting_year_id, entry_date, particulars, inflow, outflow, mode, reference_type, reference_id)
            VALUES (:company_id, :year_id, CURDATE(), :particulars, :inflow, :outflow, :mode, :ref_type, :ref_id)
        ");
        $stmt->execute([
            ':company_id' => $companyId,
            ':year_id' => $yearId,
            ':particulars' => $particulars,
            ':inflow' => $inflow,
            ':outflow' => $outflow,
            ':mode' => $mode,
            ':ref_type' => $refType,
            ':ref_id' => $refId
        ]);
    }
}
