<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

// ✅ Access Control: only admin can view this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied. Admins only.");
}

$username  = htmlspecialchars($_SESSION['user_name']);
$companyId = $_SESSION['company_id'] ?? null;
$yearId    = $_SESSION['financial_year_id'] ?? null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Control Panel</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">

    <!-- Bootstrap + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">

    <style>
        body { font-family: 'Inter', sans-serif; background: #f8f9fa; }
        .container { margin-top: 40px; }
        .card { border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .card h5 { margin-bottom: 15px; }
        .btn-action { width: 100%; margin-top: 10px; }
    </style>
</head>
<body>

<div class="container">
    
    <h2 class="mb-4"><i class="fas fa-shield-alt"></i> Admin Control Panel</h2>
   <!-- Back to Dashboard button -->
<a href="../pharma_retail/dashboard.php" class="btn btn-outline-secondary mb-3">
    <i class="fas fa-arrow-left"></i> Back to Dashboard
</a>

    <p>Welcome, <strong><?= $username ?></strong>. Manage your company and users below.</p>

    <div class="row g-4">
        <!-- Audit Logs -->
<div class="col-md-4">
    <div class="card p-3">
        <h5><i class="fas fa-clipboard-list"></i> Audit Logs</h5>
        <p>View user actions, company changes, and system activities.</p>
        <a href="audit_logs.php" class="btn btn-outline-info btn-action">View Logs</a>
    </div>
</div>

        <!-- Company Profile -->
        <div class="col-md-4">
            <div class="card p-3">
                <h5><i class="fas fa-building"></i> Company Profile</h5>
                <p>View and update company details like name, address, GST, etc.</p>
                <a href="company_profile.php" class="btn btn-primary btn-action">Manage</a>
            </div>
        </div>

        <!-- User Management -->
        <div class="col-md-4">
            <div class="card p-3">
                <h5><i class="fas fa-users-cog"></i> User Management</h5>
                <p>Add, edit, or remove users. Control access levels and permissions.</p>
                <a href="manage_users.php" class="btn btn-success btn-action">Manage</a>
            </div>
        </div>

        <!-- Subscription / Plan -->
        <div class="col-md-4">
            <div class="card p-3">
                <h5><i class="fas fa-gem"></i> Subscription Plan</h5>
                <p>Upgrade or downgrade your subscription plan. Manage seats and features.</p>
                <a href="manage_plan.php" class="btn btn-warning btn-action">Manage</a>
            </div>
        </div>

        <!-- Payments -->
        <div class="col-md-4">
            <div class="card p-3">
                <h5><i class="fas fa-credit-card"></i> Payments & Renewal</h5>
                <p>Make payments, download invoices, and renew financial year access.</p>
                <a href="payments.php" class="btn btn-danger btn-action">Manage</a>
            </div>
        </div>

        <!-- Access Control -->
        <div class="col-md-4">
            <div class="card p-3">
                <h5><i class="fas fa-lock"></i> Access Control</h5>
                <p>Restrict or grant access to different parts of the application for users.</p>
                <a href="access_control.php" class="btn btn-dark btn-action">Manage</a>
            </div>
        </div>

        <!-- System Settings -->
        <div class="col-md-4">
            <div class="card p-3">
                <h5><i class="fas fa-cogs"></i> System Settings</h5>
                <p>Configure system preferences, backup, restore, and other tools.</p>
                <a href="system_settings.php" class="btn btn-secondary btn-action">Manage</a>
            </div>
        </div>
    </div>
</div>

</body>
</html>
