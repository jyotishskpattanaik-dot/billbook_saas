<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

try {
    $pdo = getModulePDO();

    // Sanitize input
    $product_name = strtoupper(trim($_POST['product_name'] ?? ''));
    $batch_no     = strtoupper(trim($_POST['batch_no'] ?? ''));
    $hsn_code     = trim($_POST['hsn_code'] ?? '');
    $pack         = trim($_POST['pack'] ?? '');
    $mrp          = floatval($_POST['mrp'] ?? 0);
    $rate         = floatval($_POST['rate'] ?? 0);
    $expiry_date  = $_POST['expiry_date'] ?? null;
    $sgst         = floatval($_POST['sgst'] ?? 0);
    $cgst         = floatval($_POST['cgst'] ?? 0);
    $igst         = floatval($_POST['igst'] ?? 0);
    $company      = trim($_POST['company'] ?? '');
    $category     = trim($_POST['category'] ?? '');
    $composition  = trim($_POST['composition'] ?? '');

    // Validate required
    if (!$product_name || !$batch_no) {
        echo json_encode(['success'=>false, 'message'=>'Product Name and Batch No are required']);
        exit;
    }

    // ✅ Check if product with same name + batch already exists
    $stmt = $pdo->prepare("SELECT id FROM product_master WHERE product_name=:p AND batch_no=:b LIMIT 1");
    $stmt->execute([':p'=>$product_name, ':b'=>$batch_no]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // 🔄 Update existing record
        $update = $pdo->prepare("
            UPDATE product_master
            SET hsn_code=:hsn_code, pack=:pack, mrp=:mrp, rate=:rate, expiry_date=:expiry_date,
                sgst=:sgst, cgst=:cgst, igst=:igst, company=:company, category=:category, composition=:composition,
                updated_at = NOW()
            WHERE id=:id
        ");
        $update->execute([
            ':hsn_code'=>$hsn_code,
            ':pack'=>$pack,
            ':mrp'=>$mrp,
            ':rate'=>$rate,
            ':expiry_date'=>$expiry_date ?: null,
            ':sgst'=>$sgst,
            ':cgst'=>$cgst,
            ':igst'=>$igst,
            ':company'=>$company,
            ':category'=>$category,
            ':composition'=>$composition,
            ':id'=>$existing['id']
        ]);

        $product_id = $existing['id'];
    } else {
        // ➕ Insert new product batch
        $insert = $pdo->prepare("
            INSERT INTO product_master 
            (product_name, batch_no, hsn_code, pack, mrp, rate, expiry_date, sgst, cgst, igst, 
             company, category, composition, created_at)
            VALUES 
            (:product_name, :batch_no, :hsn_code, :pack, :mrp, :rate, :expiry_date, 
             :sgst, :cgst, :igst, :company, :category, :composition, NOW())
        ");
        $insert->execute([
            ':product_name'=>$product_name,
            ':batch_no'=>$batch_no,
            ':hsn_code'=>$hsn_code,
            ':pack'=>$pack,
            ':mrp'=>$mrp,
            ':rate'=>$rate,
            ':expiry_date'=>$expiry_date ?: null,
            ':sgst'=>$sgst,
            ':cgst'=>$cgst,
            ':igst'=>$igst,
            ':company'=>$company,
            ':category'=>$category,
            ':composition'=>$composition
        ]);

        $product_id = $pdo->lastInsertId();
    }

    // ✅ Return JSON response for UI
    echo json_encode([
        'success' => true,
        'product' => [
            'id'           => $product_id,
            'product_name' => $product_name,
            'batch_no'     => $batch_no,
            'hsn_code'     => $hsn_code,
            'pack'         => $pack,
            'mrp'          => $mrp,
            'rate'         => $rate,
            'expiry_date'  => $expiry_date,
            'sgst'         => $sgst,
            'cgst'         => $cgst,
            'igst'         => $igst,
            'company'      => $company,
            'category'     => $category,
            'composition'  => $composition
        ]
    ]);

} catch (Exception $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
