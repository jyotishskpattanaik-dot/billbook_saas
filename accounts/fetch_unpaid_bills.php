<?php
require __DIR__ . '/../includes/init.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$pdo = getModulePDO();
$companyId = $_SESSION['company_id'];
$yearId = $_SESSION['financial_year_id'];

$customer = $_GET['customer'] ?? '';
if (!$customer) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT bill_no, grand_total, 
           (grand_total - COALESCE(amount_paid,0)) AS balance_amount
    FROM bill_summary
    WHERE customer_name = ? 
      AND company_id = ? AND accounting_year_id = ?
      AND (grand_total - COALESCE(amount_paid,0)) > 0
");
$stmt->execute([$customer, $companyId, $yearId]);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($bills);
