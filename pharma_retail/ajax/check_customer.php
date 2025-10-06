<?php
session_start();
require __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

try {
    $pdo = getModulePDO();
    $company_id = $_SESSION['company_id'] ?? $COMPANY_ID;
    $year_id    = $_SESSION['financial_year_id'] ?? $YEAR_ID;

    $customer_name  = strtoupper(trim($_POST['customer_name'] ?? ''));
    $mobile_number = trim($_POST['mobile_number'] ?? '');

    $response = ["exists_name" => false, "exists_contact" => false];

    if ($customer_name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_details WHERE customer_name = ? AND company_id = ? AND accounting_year_id = ?");
        $stmt->execute([$customer_name, $company_id, $year_id]);
        if ($stmt->fetchColumn() > 0) $response["exists_name"] = true;
    }

    if ($mobile_number) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM customer_details WHERE mobile_number = ? AND company_id = ? AND accounting_year_id = ?");
        $stmt->execute([$mobile_number, $company_id, $year_id]);
        if ($stmt->fetchColumn() > 0) $response["exists_contact"] = true;
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
