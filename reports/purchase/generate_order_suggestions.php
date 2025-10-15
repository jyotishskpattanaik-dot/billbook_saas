    <?php
require_once __DIR__ . '/../../includes/init.php'; // adjust if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$company_id = $COMPANY_ID ?? ($_SESSION['company_id'] ?? null);
    $year_id    = $YEAR_ID ?? ($_SESSION['financial_year_id'] ?? null);
    $created_by = $CURRENT_USER ?? ($_SESSION['user_id'] ?? 'SYSTEM');

    if (!$company_id || !$year_id) {
        throw new Exception("Missing company/year in session");
    }

try {
    $pdo = getModulePDO(); // your module database connection

    // Create reorder_suggestions table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS reorder_suggestions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            company_id INT NOT NULL,
            product_id INT NOT NULL,
            product_name VARCHAR(255),
            avg_monthly_sales DECIMAL(12,2),
            current_stock DECIMAL(12,2),
            reorder_level DECIMAL(12,2),
            reorder_quantity INT,
            generated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_company_product (company_id, product_id)
        );
    ");

    // Optional: clear previous suggestions before regenerating
    $pdo->prepare("DELETE FROM reorder_suggestions WHERE company_id = ?")->execute([$company_id]);

    // Generate reorder suggestions
    $stmt = $pdo->prepare("
        INSERT INTO reorder_suggestions (
            company_id, product_id, product_name,
            avg_monthly_sales, current_stock, reorder_level, reorder_quantity
        )
        SELECT
            cs.company_id,
            cs.product_id,
            cs.product_name,
            ROUND(IFNULL(AVG(sd.qty), 0)) AS avg_monthly_sales,  -- average qty sold (rounded)
            cs.total_quantity AS current_stock,
            ROUND(IFNULL(AVG(sd.qty), 0) * 1.2) AS reorder_level, -- reorder level (20% buffer)
            GREATEST(0, ROUND((IFNULL(AVG(sd.qty), 0) * 1.2) - cs.total_quantity)) AS reorder_quantity
        FROM current_stock cs
        LEFT JOIN sale_bill_products sd
            ON sd.product_id = cs.product_id
            AND sd.company_id = cs.company_id
            AND sd.bill_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)
        WHERE cs.company_id = ?
        GROUP BY cs.product_id
        HAVING reorder_quantity > 0
    ");
    $stmt->execute([$company_id]);

    echo "✅ Reorder suggestions generated successfully.";

} catch (Exception $e) {
    echo "⚠️ Error generating reorder suggestions: " . $e->getMessage();
}
?>
