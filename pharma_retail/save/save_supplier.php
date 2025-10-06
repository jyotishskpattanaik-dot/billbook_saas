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
        $sup_id         = "SUP" . rand(1000, 9999);
        $supplier_name  = strtoupper(trim($_POST['supplier_name']));
        $contact_number = trim($_POST['contact_number']);
        $address        = strtoupper(trim($_POST['address']));
        $district       = strtoupper(trim($_POST['district']));
        $state          = strtoupper(trim($_POST['state']));
        $dl_no          = strtoupper(trim($_POST['dl_no']));
        $gstin_no       = strtoupper(trim($_POST['gstin_no']));
        $fssai_no       = strtoupper(trim($_POST['fssai_no']));
        $email          = strtolower(trim($_POST['email']));
        $acc_type       = "SUPPLIER";

        // 🔍 Check for duplicates (within same company + year)
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM supplier_details 
            WHERE (supplier_name = ? OR contact_number = ?)
              AND company_id = ? 
              AND accounting_year_id = ?
        ");
        $checkStmt->execute([$supplier_name, $contact_number, $company_id, $year_id]);
        $exists = $checkStmt->fetchColumn();

        if ($exists > 0) {
            echo "<script>
                    alert('⚠️ Supplier with this name or contact number already exists for this company & year!');
                    window.location.href = '../add/add_supplier.php';
                  </script>";
            exit;
        }

        // ✅ Insert into DB with company + year
        $stmt = $pdo->prepare("
            INSERT INTO supplier_details 
            (sup_id, supplier_name, contact_number, email, address, district, state, acc_type, dl_no, gstin_no, fssai_no, 
             company_id, accounting_year_id, created_by, modified_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $sup_id, $supplier_name, $contact_number, $email, $address, $district, $state, $acc_type, $dl_no,
            $gstin_no, $fssai_no, $company_id, $year_id, $logged_in_user, $logged_in_user
        ]);

        echo "<script>
                alert('✅ Supplier added successfully!');
                window.location.href = '../add/add_supplier.php';
              </script>";

    } catch (Exception $e) {
        echo "<script>
                alert('❌ Error: " . addslashes($e->getMessage()) . "');
                window.location.href = '../add/add_supplier.php';
              </script>";
    }
} else {
    echo "<script>
            alert('⚠️ Invalid request!');
            window.location.href = '../add/add_supplier.php';
          </script>";
}
