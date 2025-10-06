<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

try {
    $pdo = getModulePDO();

    $q = trim($_GET['q'] ?? '');
    $results = [];

    if ($q !== '') {
        $stmt = $pdo->prepare("
            SELECT 
                product_id, 
                product_name, 
                batch_no, 
                hsn_code, 
                pack, 
                mrp, 
                expiry_date,
                sgst, 
                cgst, 
                igst,
                total_quantity AS available_qty
            FROM current_stock
            WHERE (product_name LIKE :q OR batch_no LIKE :q)
              AND total_quantity > 0  -- ✅ Only show products with stock
            ORDER BY product_name ASC, expiry_date ASC
            LIMIT 10
        ");
        $stmt->execute([':q' => "%$q%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
