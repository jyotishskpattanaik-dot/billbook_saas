<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use App\Core\Database;

if (!isset($_SESSION['pending_user_id'])) {
    header("Location: login.php");
    exit;
}

$pdo = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = $_POST['company_name'] ?? '';
    $address     = $_POST['address'] ?? '';
    $gst         = $_POST['gst_number'] ?? '';
    $dl          = $_POST['dl_number'] ?? '';
    $fssai       = $_POST['fssai_number'] ?? '';
    $contact     = $_POST['contact_no'] ?? '';
    $email       = $_POST['email'] ?? '';

    if (!empty($companyName)) {
        // Insert company
        $stmt = $pdo->prepare("INSERT INTO companies (company_name, address, gst_number,dl_number,fssai_number, contact_no, email) 
                               VALUES (?, ?, ?,?,?, ?, ?)");
        $stmt->execute([$companyName, $address, $gst,$dl,$fssai, $contact, $email]);

        $companyId = $pdo->lastInsertId();

        // Assign company to user as ADMIN
        $stmt = $pdo->prepare("UPDATE users SET company_id = ?, role = 'admin' WHERE id = ?");
        $stmt->execute([$companyId, $_SESSION['pending_user_id']]);

        $_SESSION['company_id'] = $companyId;
        $_SESSION['user_role'] = 'admin';

        unset($_SESSION['pending_user_id']);

        header("Location: create_year.php");
        exit;
    } else {
        $error = "❌ Company name is required!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Company</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Company Setup</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Company Name</label>
                            <input type="text" id="company_name" name="company_name" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="gst_number" class="form-label">GST Number</label>
                            <input type="text" id="gst_number" name="gst_number" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="gst_number" class="form-label">DL. NO</label>
                            <input type="text" id="gst_number" name="dl_number" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="gst_number" class="form-label">FSSAI Number</label>
                            <input type="text" id="gst_number" name="fssai_number" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="contact_no" class="form-label">Contact No</label>
                            <input type="text" id="contact_no" name="contact_no" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Save & Continue</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted text-center small">
                    © <?= date('Y') ?> Your Company
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
