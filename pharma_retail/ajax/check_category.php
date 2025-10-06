
<?php
session_start();
require __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

try {
    $pdo = getModulePDO();
    $company_id = $_SESSION['company_id'] ?? $COMPANY_ID;
    $year_id    = $_SESSION['financial_year_id'] ?? $YEAR_ID;

    $category  = strtoupper(trim($_POST['category'] ?? ''));
    

    $response = ["exists_name" => false];

    if ($category) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM category_master WHERE category = ? AND company_id = ? AND accounting_year_id = ?");
        $stmt->execute([$category, $company_id, $year_id]);
        if ($stmt->fetchColumn() > 0) $response["exists_name"] = true;
    }

    
    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
