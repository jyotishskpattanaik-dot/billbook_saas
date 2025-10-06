<?php
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    // ✅ Always use current session’s company & year
    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;

    if (!$company_id || !$year_id) {
        throw new Exception("Session missing company/year context");
    }

    // ✅ Query last purchase_id for this company & year
    $stmt = $pdo->prepare("
        SELECT purchase_id 
        FROM purchase_details 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute([
        ':company_id' => $company_id,
        ':year_id'    => $year_id
    ]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $lastId = $row['purchase_id'];
        $num = (int) filter_var($lastId, FILTER_SANITIZE_NUMBER_INT);
        $nextId = 'PUR' . str_pad($num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        // First purchase entry for this company/year
        $nextId = 'PUR0001';
    }

    // ✅ Output JSON
    echo json_encode(['next_purchase_id' => $nextId]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
