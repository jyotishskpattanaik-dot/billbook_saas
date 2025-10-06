<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

try {
    $pdo = getModulePDO();
    $productId = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

    if ($productId <= 0) {
        echo json_encode([]);
        exit;
    }

    // Get context (company + year)
    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;

    // Find the product name for the given ID
    $stmt = $pdo->prepare("
        SELECT product_name 
        FROM product_master 
        WHERE id = :id 
          AND company_id = :company_id 
          AND accounting_year_id = :year_id
        LIMIT 1
    ");
    $stmt->execute([
        ':id' => $productId,
        ':company_id' => $company_id,
        ':year_id' => $year_id
    ]);
    $base = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$base) {
        echo json_encode([]);
        exit;
    }

    // Fetch batches of that product
    $stmt2 = $pdo->prepare("
        SELECT id, product_name, batch_no, hsn_code, pack, 
               DATE_FORMAT(expiry_date, '%Y-%m-%d') AS expiry_date,
               mrp, rate, sgst, cgst, igst
        FROM product_master
        WHERE product_name = :pname
          AND company_id = :company_id 
          AND accounting_year_id = :year_id
        ORDER BY expiry_date IS NULL, expiry_date ASC, id DESC
        LIMIT 10
    ");
    $stmt2->execute([
        ':pname' => $base['product_name'],
        ':company_id' => $company_id,
        ':year_id' => $year_id
    ]);
    $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($rows);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
