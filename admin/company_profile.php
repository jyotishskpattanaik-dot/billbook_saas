<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php'; // ✅ your DB helper
require_once __DIR__ . '/../includes/audit.php';

// ✅ Access Control
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: ../public/login.php?error=unauthorized");
    exit;
}

$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) {
    die("❌ No company ID found in session.");
}

$pdo = getMainPDO(); // ✅ main_db connection

// 🔹 Handle update request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name = $_POST['company_name'] ?? '';
    $address      = $_POST['address'] ?? '';
    $gst_no       = $_POST['gst_number'] ?? '';
    $contact_no   = $_POST['contact_no'] ?? '';

    $sql = "UPDATE companies 
            SET company_name = ?, address = ?, gst_number = ?, contact_no = ? 
            WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$company_name, $address, $gst_no, $contact_no, $companyId]);

    $message = "✅ Company profile updated successfully!";
}

// 🔹 Fetch current details
$sql = "SELECT company_name, address, gst_number, contact_no 
        FROM companies WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$companyId]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$company) {
    die("❌ Company not found.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Company Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .container { max-width: 700px; margin-top: 40px; }
        .card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .form-label { font-weight: 600; }
    </style>
</head>
<body>

<div class="container">
    <a href="control_panel.php" class="btn btn-outline-secondary mb-3">
        <i class="fas fa-arrow-left"></i> Back to Control Panel
    </a>

    <div class="card p-4">
        <h3><i class="fas fa-building"></i> Company Profile</h3>
        <p class="text-muted">Update your company details below.</p>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Company Name</label>
                <input type="text" name="company_name" class="form-control" 
                       value="<?= htmlspecialchars($company['company_name']) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label">Address</label>
                <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($company['address']) ?></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label">GST No.</label>
                <input type="text" name="gst_number" class="form-control" 
                       value="<?= htmlspecialchars($company['gst_number']) ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Contact No.</label>
                <input type="text" name="contact_no" class="form-control" 
                       value="<?= htmlspecialchars($company['contact_no']) ?>">
            </div>

            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Changes
            </button>
        </form>
    </div>
</div>

</body>
</html>
