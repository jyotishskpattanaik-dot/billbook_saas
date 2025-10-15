<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../accounts/helpers/accounts_functions.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getModulePDO();

$companyId = $_SESSION['company_id'];
$yearId = $_SESSION['financial_year_id'];
$createdBy = $_SESSION['user_id'];

$customerName = trim($_POST['customer_name']);
$billNo = trim($_POST['bill_no']);
$amount = floatval($_POST['amount']);
$mode = trim($_POST['mode']);

if ($amount <= 0) {
    die("Invalid payment amount.");
}

if (recordPaymentAgainstBill($pdo, $companyId, $yearId, $customerName, $billNo, $amount, $mode, $createdBy)) {
    $_SESSION['success'] = "Payment of ₹$amount received successfully for Bill #$billNo";
} else {
    $_SESSION['error'] = "Error recording payment.";
}

header("Location: receive_payment.php");
exit;
