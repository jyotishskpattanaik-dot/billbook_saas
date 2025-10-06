<?php
session_start();
require __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo = getModulePDO();
        $logged_in_user = currentUser();

        // Context
        $company_id = $_SESSION['company_id'] ?? 0;
        $year_id    = $_SESSION['financial_year_id'] ?? 0;

        // Collect form data
        $sup_id         = "SUP" . rand(1000, 9999);
        $supplier_name  = strtoupper(trim($_POST['supplier_name']));
        $contact_number = trim($_POST['contact_number']);
        $address        = strtoupper(trim($_POST['address'] ?? ''));
        $gstin_no       = strtoupper(trim($_POST['gstin_no'] ?? ''));
        $fssai_no       = strtoupper(trim($_POST['fssai_no'] ?? ''));
        $email          = strtolower(trim($_POST['email'] ?? ''));
        $acc_type       = "SUPPLIER";

        // 🔍 Duplicate check only within same company + year
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM supplier_details 
            WHERE (supplier_name = ? OR contact_number = ?)
              AND company_id = ? AND accounting_year_id = ?
        ");
        $checkStmt->execute([$supplier_name, $contact_number, $company_id, $year_id]);
        if ($checkStmt->fetchColumn() > 0) {
            echo json_encode(["success" => false, "message" => "⚠️ Supplier already exists for this company/year"]);
            exit;
        }

        // ✅ Insert supplier
        $stmt = $pdo->prepare("
            INSERT INTO supplier_details 
            (sup_id, supplier_name, contact_number, email, address, acc_type, gstin_no, fssai_no,
             company_id, accounting_year_id, created_by, modified_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $sup_id, $supplier_name, $contact_number, $email, $address, $acc_type,
            $gstin_no, $fssai_no, $company_id, $year_id, $logged_in_user, $logged_in_user
        ]);

        // Return numeric auto-increment `id` + display name
        echo json_encode([
            "success" => true,
            "id"   => $pdo->lastInsertId(),
            "name" => $supplier_name
        ]);

    } catch (Exception $e) {
        echo json_encode(["success" => false, "message" => $e->getMessage()]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
}
