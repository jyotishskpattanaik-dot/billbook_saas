<?php
session_start();
require __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

try {
    $pdo = getModulePDO();
    $company_id = $_SESSION['company_id'] ?? $COMPANY_ID;
    $year_id    = $_SESSION['financial_year_id'] ?? $YEAR_ID;

    $supplier_name  = strtoupper(trim($_POST['supplier_name'] ?? ''));
    $contact_number = trim($_POST['contact_number'] ?? '');

    $response = ["exists_name" => false, "exists_contact" => false];

    if ($supplier_name) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier_details WHERE supplier_name = ? AND company_id = ? AND accounting_year_id = ?");
        $stmt->execute([$supplier_name, $company_id, $year_id]);
        if ($stmt->fetchColumn() > 0) $response["exists_name"] = true;
    }

    if ($contact_number) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier_details WHERE contact_number = ? AND company_id = ? AND accounting_year_id = ?");
        $stmt->execute([$contact_number, $company_id, $year_id]);
        if ($stmt->fetchColumn() > 0) $response["exists_contact"] = true;
    }

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
