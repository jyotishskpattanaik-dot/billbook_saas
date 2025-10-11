<?php
session_start();
require __DIR__ . '/../../includes/public_db_helper.php';
require __DIR__ . '/../../vendor/autoload.php';

// Debug helper
function debugLog($msg) {
    $debugFile = __DIR__ . '/../debug_checkout.log';
    file_put_contents($debugFile, date('Y-m-d H:i:s') . " - $msg\n", FILE_APPEND);
}

$pdo = getPublicPDO();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyName = trim($_POST['company_name'] ?? '');
    $address     = trim($_POST['address'] ?? '');
    $gst         = trim($_POST['gst_number'] ?? '');
    $dl          = trim($_POST['dl_number'] ?? '');
    $fssai       = trim($_POST['fssai_number'] ?? '');
    $contact     = trim($_POST['contact_no'] ?? '');
    $email       = trim($_POST['email'] ?? '');
    $phone       = trim($_POST['contact_no'] ?? '');

    if (!$companyName) {
        $error = "❌ Company name is required!";
    } else {
        try {
            // 1️⃣ Find existing user_id using email + phone
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND phone = ?");
            $stmt->execute([$email, $phone]);
            $user = $stmt->fetch();

            if (!$user) {
                throw new Exception("User not found with provided email & phone!");
            }
            $user_id = $user['user_id'];
            debugLog("Found user_id: $user_id");

            // 2️⃣ Fetch the last order for this user
            $stmt = $pdo->prepare("
                SELECT plan_name, module, created_at AS order_date, expiry_date
                FROM orders
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user_id]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);

            // 3️⃣ Prepare company info from order or defaults
            $plan        = $order['plan_name'] ?? 'free_trial';
            $module      = $order['module'] ?? null;
            $active_from = $order['order_date'] ?? date('Y-m-d');
            $active_till = $order['expiry_date'] ?? date('Y-m-d', strtotime('+7 days'));

            $planLimits = [
                'free_trial' => 1,
                'bronze'     => 1,
                'silver'     => 3,
                'gold'       => 5,
                'diamond'    => 25
            ];
            $userLimit = $planLimits[strtolower($plan)] ?? 1;

            // 4️⃣ Insert company
            $stmt = $pdo->prepare("
                INSERT INTO companies 
                    (user_id, company_name, address, gst_number, dl_number, fssai_number, contact_no, email,
                     plan, user_limit, active_from, active_till, module, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id, $companyName, $address, $gst, $dl, $fssai, $contact, $email,
                $plan, $userLimit, $active_from, $active_till, $module
            ]);
            $companyId = $pdo->lastInsertId();
            debugLog("Company created with ID: $companyId | Plan: $plan | Module: $module");

            // 5️⃣ Update user record
            $stmt = $pdo->prepare("UPDATE users SET company_id = ?, role = 'admin' WHERE user_id = ?");
            $stmt->execute([$companyId, $user_id]);

            // 6️⃣ Update subscriptions table to attach company_id
            $stmt = $pdo->prepare("UPDATE subscriptions SET company_id = ? WHERE user_id = ?");
            $stmt->execute([$companyId, $user_id]);
            debugLog("Subscriptions updated with company_id: $companyId");

            // 7️⃣ Store session info
            $_SESSION['company_id'] = $companyId;
            $_SESSION['user_role']  = 'admin';

            // Redirect to financial year setup
            header("Location: create_year.php");
            exit;

        } catch (Exception $e) {
            $error = "❌ Database error: " . $e->getMessage();
            debugLog($error);
        }
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
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
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
                            <label for="dl_number" class="form-label">DL Number</label>
                            <input type="text" id="dl_number" name="dl_number" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label for="fssai_number" class="form-label">FSSAI Number</label>
                            <input type="text" id="fssai_number" name="fssai_number" class="form-control">
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
                    © <?= date('Y') ?> BillBook.in
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
