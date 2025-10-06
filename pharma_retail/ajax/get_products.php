<?php
// get_suppliers.php
require __DIR__ . '/../../includes/init.php';

try {
    $pdo = getModulePDO();

    // Fetch suppliers (filtered by company + year for isolation)
    $company_id = $_SESSION['company_id'] ?? 0;
    $year_id    = $_SESSION['financial_year_id'] ?? 0;

    $sql = "SELECT id, supplier_name 
            FROM supplier_details
            WHERE company_id = :company_id 
              AND accounting_year_id = :year_id
            ORDER BY supplier_name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $company_id,
        ':year_id'    => $year_id
    ]);

    $options = "";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $options .= "<option value='{$row['id']}'>" . htmlspecialchars($row['supplier_name']) . "</option>";
    }

    echo $options;

} catch (Exception $e) {
    echo "<option value=''>Error: " . htmlspecialchars($e->getMessage()) . "</option>";
}

