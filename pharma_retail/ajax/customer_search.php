<?php
require __DIR__ . '/../../includes/init.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = getModulePDO();

$companyId = $_SESSION['company_id'] ?? null;
$query = $_GET['q'] ?? '';

if (!$companyId || !$query) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT cus_id, customer_name, mobile_number 
    FROM customer_details 
    WHERE company_id = :company_id AND customer_name LIKE :query 
    ORDER BY customer_name LIMIT 10
");
$stmt->execute([
    ':company_id' => $companyId,
    ':query' => "%$query%"
]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
