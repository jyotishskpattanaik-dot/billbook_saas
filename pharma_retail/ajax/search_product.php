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
            SELECT id, product_name,company,category, batch_no, hsn_code, pack, mrp, rate, expiry_date,
                   sgst, cgst, igst,composition
            FROM product_master
            WHERE product_name LIKE :q OR batch_no LIKE :q
            ORDER BY product_name ASC, expiry_date DESC
            LIMIT 10
        ");
        $stmt->execute([':q' => "%$q%"]);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Always add “Add new product” option
    $results[] = [
        'id' => 0,
        'product_name' => "➕ Add new product",
       'batch_no' => '',
       'company' => '',
        'category' => '',
        'hsn_code' => '',
        'pack' => '',
        'mrp' => '',
        'rate' => '',
        'expiry_date' => '',
        'sgst' => '',
        'cgst' => '',
        'igst' => '',
        'composition' => ''
    ];

    echo json_encode($results);

} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
