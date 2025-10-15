<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../accounts/helpers/accounts_functions.php';
require __DIR__ . '/../includes/navigation_helper.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId = $_SESSION['financial_year_id'];
$createdBy = $_SESSION['user_id'] ?? 'SYSTEM';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Invalid request method");
    }

    $bill_no = trim($_POST['bill_no'] ?? '');
    $customer_name = trim($_POST['customer_name'] ?? '');
    $payment_mode = trim($_POST['payment_mode'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');

    if (empty($bill_no) || $amount <= 0) {
        throw new Exception("Bill number or amount missing");
    }

    // ✅ Insert into received_payments
    $stmt = $pdo->prepare("
        INSERT INTO received_payments
        (company_id, accounting_year_id, bill_no, customer_name, payment_date, payment_mode, amount, remarks, created_by)
        VALUES (:company_id, :year_id, :bill_no, :customer_name, :payment_date, :payment_mode, :amount, :remarks, :created_by)
    ");
    $stmt->execute([
        ':company_id' => $companyId,
        ':year_id' => $yearId,
        ':bill_no' => $bill_no,
        ':customer_name' => $customer_name,
        ':payment_date' => $payment_date,
        ':payment_mode' => $payment_mode,
        ':amount' => $amount,
        ':remarks' => $remarks,
        ':created_by' => $createdBy
    ]);

    // ✅ Update paid amount in bill_summary
    $stmt = $pdo->prepare("
        UPDATE bill_summary 
        SET amount = COALESCE(amount, 0) + :amount 
        WHERE bill_no = :bill_no AND company_id = :company_id AND accounting_year_id = :year_id
    ");
    $stmt->execute([
        ':amount' => $amount,
        ':bill_no' => $bill_no,
        ':company_id' => $companyId,
        ':year_id' => $yearId
    ]);

    // ✅ Get bill grand total for balance computation
    $stmt = $pdo->prepare("
        SELECT grand_total, amount, customer_name 
        FROM bill_summary 
        WHERE bill_no = :bill_no AND company_id = :company_id AND accounting_year_id = :year_id
    ");
    $stmt->execute([
        ':bill_no' => $bill_no,
        ':company_id' => $companyId,
        ':year_id' => $yearId
    ]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($bill) {
        $balance = $bill['grand_total'] - $bill['amount'];

        // ✅ Auto ledger entry (Customer Dr / Cash/Bank Cr)
        $debitAccount = 'Cash';
        $creditAccount = $bill['customer_name'];
        $narration = "Payment received for Bill #{$bill_no}";

        addLedgerEntry(
            $pdo,
            $companyId,
            $yearId,
            $debitAccount,
            $creditAccount,
            $amount,
            $narration,
            'received_payments',
            $bill_no,
            $createdBy
        );
    }

    $_SESSION['success'] = "Payment of ₹" . number_format($amount, 2) . " recorded successfully for Bill $bill_no.";
    header("Location: receive_payment.php?success=1");
    exit;

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['error'] = "Error recording payment: " . $e->getMessage();
    header("Location: receive_payment.php?error=1");
    exit;
}
?>
