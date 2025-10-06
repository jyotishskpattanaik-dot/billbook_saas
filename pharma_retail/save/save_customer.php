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
        $cus_id         = "CUS" . rand(1000, 9999);
        $customer_name  = strtoupper(trim($_POST['customer_name']));
        $mobile_number = trim($_POST['mobile_number']);
        $acc_type       = "CUSTOMER";

        // 🔍 Check for duplicates (within same company + year)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM customer_details 
            WHERE (customer_name = ? OR mobile_number = ?)
              AND company_id = ? 
              AND accounting_year_id = ?
        ");
        $checkStmt->execute([$customer_name, $mobile_number, $company_id, $year_id]);
        $exists = $checkStmt->fetchColumn();

        if ($exists > 0) {
            echo "<script>
                    alert('⚠️ Customer with this name or mobile number already exists for this company & year!');
                    window.location.href = '../add/add_customer.php';
                  </script>";
            exit;
        }

        // ✅ Insert into DB with company + year
        $stmt = $pdo->prepare("
            INSERT INTO customer_details 
            (cus_id, customer_name, mobile_number,company_id, accounting_year_id, created_by, modified_by)
            VALUES ( ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $cus_id, $customer_name, $mobile_number, $company_id, $year_id, $logged_in_user, $logged_in_user
        ]);

        echo "<script>
                alert('✅ Customer added successfully!');
                window.location.href = '../add/add_customer.php';
              </script>";

    } catch (Exception $e) {
        echo "<script>
                alert('❌ Error: " . addslashes($e->getMessage()) . "');
                window.location.href = '../add/add_customer.php';
              </script>";
    }
} else {
    echo "<script>
            alert('⚠️ Invalid request!');
            window.location.href = '../add/add_customer.php';
          </script>";
}
