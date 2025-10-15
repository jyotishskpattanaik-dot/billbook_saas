<?php
session_start();

require __DIR__ . '/../vendor/autoload.php';

// ✅ Access Control: only admin can view this page
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied. Admins only.");
}

use App\Core\ModuleDatabase;

$username  = htmlspecialchars($_SESSION['user_name']);
$companyId = $_SESSION['company_id'] ?? null;
$yearId    = $_SESSION['financial_year_id'] ?? null;

// Get financial year label
try {
    $pdo = ModuleDatabase::getConnection();
    $stmt = $pdo->prepare("SELECT year_start, year_end FROM accounting_years WHERE id = ?");
    $stmt->execute([$yearId]);
    $fy = $stmt->fetch(PDO::FETCH_ASSOC);
    $yearLabel = $fy ? date('Y', strtotime($fy['year_start'])) . "-" . date('Y', strtotime($fy['year_end'])) : "Unknown";
} catch (Exception $e) {
    $yearLabel = "Unknown";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Control Panel - Pharma Retail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8f9fa;
        }

        /* Top Navigation Bar */
        .top-navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 60px;
        }

        .top-navbar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
            padding: 0 20px;
            max-width: 100%;
        }

        .navbar-brand {
            font-size: 1.5rem;
            font-weight: 700;
            color: white !important;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            cursor: pointer;
            padding: 6px 12px;
            border-radius: 20px;
            transition: all 0.3s ease;
        }

        .user-profile:hover {
            background: rgba(255,255,255,0.15);
        }

        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-content {
            margin-top: 60px;
            padding: 40px 30px;
            min-height: calc(100vh - 60px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .page-header .icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .page-header p {
            color: #636e72;
            font-size: 1rem;
            margin: 0;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            color: #495057;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 20px;
        }

        .back-button:hover {
            background: #f8f9fa;
            border-color: #667eea;
            color: #667eea;
            transform: translateX(-3px);
        }

        /* Admin Cards Grid */
        .admin-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }

        .admin-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .admin-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .admin-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .admin-card:hover::before {
            transform: scaleY(1);
        }

        .card-header-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }

        .admin-card:hover .card-header-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .admin-card h5 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #2d3436;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-card p {
            color: #636e72;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .btn-admin {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
            width: 100%;
            border: none;
            font-size: 0.95rem;
        }

        .btn-admin:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Color Themes for Different Cards */
        .card-audit .card-header-icon {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .card-audit .btn-admin {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .card-company .card-header-icon {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .card-company .btn-admin {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }

        .card-users .card-header-icon {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .card-users .btn-admin {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }

        .card-subscription .card-header-icon {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        .card-subscription .btn-admin {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }

        .card-payments .card-header-icon {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        .card-payments .btn-admin {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }

        .card-access .card-header-icon {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
        }
        .card-access .btn-admin {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
        }

        .card-system .card-header-icon {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #495057;
        }
        .card-system .btn-admin {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            color: #495057;
        }

        /* Info Badge */
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: #e3f2fd;
            border-radius: 20px;
            color: #1976d2;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 20px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }

            .page-header h1 {
                font-size: 1.5rem;
            }

            .admin-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="top-navbar">
        <div class="top-navbar-content">
            <a href="../pharma_retail/dashboard.php" class="navbar-brand">
                <i class="fas fa-pills"></i>
                PHARMA RETAIL
            </a>

            <div class="navbar-actions">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($username, 0, 2)) ?>
                    </div>
                    <span><?= $username ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-content">
        <a href="../pharma_retail/dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a>

        <div class="page-header">
            <h1>
                <div class="icon-wrapper">
                    <i class="fas fa-shield-alt"></i>
                </div>
                Admin Control Panel
            </h1>
            <p>Welcome, <strong><?= $username ?></strong>. Manage your company and system settings below.</p>
        </div>

        <div class="info-badge">
            <i class="fas fa-info-circle"></i>
            FY: <?= $yearLabel ?>
        </div>

        <!-- Admin Cards Grid -->
        <div class="admin-grid">
            <!-- Audit Logs -->
            <div class="admin-card card-audit">
                <div class="card-header-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <h5>Audit Logs</h5>
                <p>View user actions, company changes, and system activities. Track all modifications and maintain security compliance.</p>
                <a href="audit_logs.php" class="btn-admin">
                    <i class="fas fa-eye"></i>
                    View Logs
                </a>
            </div>

            <!-- Company Profile -->
            <div class="admin-card card-company">
                <div class="card-header-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h5>Company Profile</h5>
                <p>View and update company details like name, address, GST number, contact information, and business configuration.</p>
                <a href="company_profile.php" class="btn-admin">
                    <i class="fas fa-edit"></i>
                    Manage Company
                </a>
            </div>

            <!-- User Management -->
            <div class="admin-card card-users">
                <div class="card-header-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <h5>User Management</h5>
                <p>Add, edit, or remove users. Control access levels, assign roles, and manage user permissions across the system.</p>
                <a href="manage_users.php" class="btn-admin">
                    <i class="fas fa-users"></i>
                    Manage Users
                </a>
            </div>

            <!-- Subscription Plan -->
            <div class="admin-card card-subscription">
                <div class="card-header-icon">
                    <i class="fas fa-gem"></i>
                </div>
                <h5>Subscription Plan</h5>
                <p>Upgrade or downgrade your subscription plan. Manage seats, features, and billing preferences for your organization.</p>
                <a href="manage_plan.php" class="btn-admin">
                    <i class="fas fa-crown"></i>
                    Manage Plan
                </a>
            </div>

            <!-- Payments & Renewal -->
            <div class="admin-card card-payments">
                <div class="card-header-icon">
                    <i class="fas fa-credit-card"></i>
                </div>
                <h5>Payments & Renewal</h5>
                <p>Make payments, download invoices, renew financial year access, and manage payment history and billing details.</p>
                <a href="payments.php" class="btn-admin">
                    <i class="fas fa-wallet"></i>
                    Manage Payments
                </a>
            </div>

            <!-- Access Control -->
            <div class="admin-card card-access">
                <div class="card-header-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <h5>Access Control</h5>
                <p>Restrict or grant access to different modules and features. Configure role-based permissions and security policies.</p>
                <a href="access_control.php" class="btn-admin">
                    <i class="fas fa-key"></i>
                    Configure Access
                </a>
            </div>

            <!-- System Settings -->
            <div class="admin-card card-system">
                <div class="card-header-icon">
                    <i class="fas fa-cogs"></i>
                </div>
                <h5>System Settings</h5>
                <p>Configure system preferences, backup and restore data, manage integrations, and access advanced configuration tools.</p>
                <a href="system_settings.php" class="btn-admin">
                    <i class="fas fa-sliders-h"></i>
                    System Settings
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>