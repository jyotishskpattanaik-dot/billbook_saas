<?php
session_start();
require __DIR__ . '/../../includes/init.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    try {
        $pdo = getModulePDO();

        // Logged-in user & context
        $logged_in_user = currentUser();
        $company_id     = $_SESSION['company_id'] ?? $COMPANY_ID;
        $year_id        = $_SESSION['financial_year_id'] ?? $YEAR_ID;

        // Collect form data
        $cat_id         = "CAT" . rand(10, 999);
        $category  = strtoupper(trim($_POST['category']));
       
        // 🔍 Check for duplicates (within same company + year)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM category_master 
            WHERE (category = ? 
              AND company_id = ? 
              AND accounting_year_id = ?
        ");
        $checkStmt->execute([$category]);
        $exists = $checkStmt->fetchColumn();

        if ($exists > 0) {
            echo "<script>
                    alert('⚠️ category with this name already exists for this company & year!');
                    window.location.href = '../add/add_category.php';
                  </script>";
            exit;
        }

        // ✅ Insert into DB with company + year
        $stmt = $pdo->prepare("
            INSERT INTO category_details 
            (cus_id, category, created_by, creation_date)
            VALUES ( ?, ?,NOW())
        ");

        $stmt->execute([
            $cat_id, $category, $logged_in_user, $logged_in_user
        ]);

        echo "<script>
                alert('✅ category added successfully!');
                window.location.href = '../add/add_category.php';
              </script>";

    } catch (Exception $e) {
        echo "<script>
                alert('❌ Error: " . addslashes($e->getMessage()) . "');
                window.location.href = '../add/add_category.php';
              </script>";
    }
} else {
    echo "<script>
            alert('⚠️ Invalid request!');
            window.location.href = '../add/add_category.php';
          </script>";
}
