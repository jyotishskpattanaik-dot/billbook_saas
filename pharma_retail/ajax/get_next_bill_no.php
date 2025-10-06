<?php
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;

    if (!$company_id || !$year_id) {
        throw new Exception("Session missing company/year context");
    }

    // ✅ Query last bill from bill_summery (not sale_bill_master)
    $stmt = $pdo->prepare("
        SELECT bill_no 
        FROM bill_summery 
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
        $lastId = $row['bill_no'];
        $num = (int) filter_var($lastId, FILTER_SANITIZE_NUMBER_INT);
        $nextId = 'SALE' . str_pad($num + 1, 4, '0', STR_PAD_LEFT);
    } else {
        $nextId = 'SALE0001'; // First bill
    }

    echo json_encode(['next_bill_no' => $nextId]);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
