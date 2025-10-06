<?php
require __DIR__ . '/../../includes/init.php';
header('Content-Type: application/json');

try {
    $pdo = getModulePDO();
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';

    // ✅ Always scope search to current company + year
    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;

    if (!$company_id || !$year_id) {
        throw new Exception("Session missing company/year context");
    }

    if ($q !== '') {
        $stmt = $pdo->prepare("
            SELECT sup_id AS id, supplier_name
            FROM supplier_details 
            WHERE company_id = :company_id
              AND accounting_year_id = :year_id
              AND supplier_name LIKE :q
            ORDER BY supplier_name ASC 
            LIMIT 10
        ");
        $stmt->execute([
            ':company_id' => $company_id,
            ':year_id'    => $year_id,
            ':q'          => '%' . $q . '%'
        ]);
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $suppliers = [];
    }

    echo json_encode($suppliers);
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
