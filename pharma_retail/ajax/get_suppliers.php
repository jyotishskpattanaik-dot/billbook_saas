<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

try {
    $pdo = getModulePDO();

    // ✅ Get company + year from session
    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;
    $created_by = currentUser();

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company/year context in session");
    }

    // ✅ Collect inputs
    $supplier_name  = strtoupper(trim($_POST['supplier_name'] ?? ''));
    $contact_number = trim($_POST['contact_number'] ?? '');
    $gstin_no       = strtoupper(trim($_POST['gstin_no'] ?? ''));

    if ($supplier_name === '') {
        echo json_encode(['success' => false, 'message' => 'Supplier name is required']);
        exit;
    }

    // 🔍 Check duplicates (same company + year)
    $check = $pdo->prepare("
        SELECT COUNT(*) 
        FROM supplier_details 
        WHERE supplier_name = ? AND company_id = ? AND accounting_year_id = ?
    ");
    $check->execute([$supplier_name, $company_id, $year_id]);
    if ($check->fetchColumn() > 0) {
        echo json_encode(['success' => false, 'message' => 'Supplier already exists']);
        exit;
    }

    // ✅ Insert into supplier_details
    $sql = "
        INSERT INTO supplier_details 
        (supplier_name, contact_number, gstin_no, company_id, accounting_year_id, created_by, modified_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $supplier_name,
        $contact_number,
        $gstin_no,
        $company_id,
        $year_id,
        $created_by,
        $created_by
    ]);

    echo json_encode([
        'success' => true,
        'id'      => $pdo->lastInsertId(),
        'name'    => $supplier_name
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
