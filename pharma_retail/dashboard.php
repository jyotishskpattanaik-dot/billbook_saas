<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../public/login.php");
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
$userRole = $_SESSION['user_role'] ?? 'user';

use App\Core\ModuleDatabase;

// ✅ Redirect to login if session expired
if (!isset($_SESSION['company_id']) || !isset($_SESSION['financial_year_id'])) {
    session_unset();
    session_destroy();
    header("Location: ../public/login.php");
    exit;
}

$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];
$username  = htmlspecialchars($_SESSION["user_name"]);
$module    = $_SESSION['user_module'];
$userRole  = $_SESSION['user_role'] ?? 'user';

try {
    $pdo = ModuleDatabase::getConnection();

    // Daily Sales
    $stmt = $pdo->prepare("
        SELECT SUM(net_amount) as total 
        FROM sale_bill_master 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id 
          AND DATE(bill_date) = CURDATE()
    ");
    $stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
    $dailySales = $stmt->fetchColumn() ?? 0;

    // Monthly Sales
    $stmt = $pdo->prepare("
        SELECT SUM(net_amount) as total 
        FROM sale_bill_master 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id 
          AND MONTH(bill_date) = MONTH(CURDATE()) 
          AND YEAR(bill_date) = YEAR(CURDATE())
    ");
    $stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
    $monthlySales = $stmt->fetchColumn() ?? 0;

    // Daily Purchase
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM purchase_details 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id 
          AND DATE(purchase_date) = CURDATE()
    ");
    $stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
    $dailyPurchase = $stmt->fetchColumn() ?? 0;

    // Monthly Purchase
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total 
        FROM purchase_details 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id 
          AND MONTH(purchase_date) = MONTH(CURDATE()) 
          AND YEAR(purchase_date) = YEAR(CURDATE())
    ");
    $stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
    $monthlyPurchase = $stmt->fetchColumn() ?? 0;

    // Low Stock Count
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM current_stock 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id 
          AND total_quantity <= 10
    ");
    $stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
    $lowStockCount = $stmt->fetchColumn() ?? 0;

    // Expiring Soon Count (next 30 days)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM current_stock 
        WHERE company_id = :company_id 
          AND accounting_year_id = :year_id 
          AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute([':company_id' => $companyId, ':year_id' => $yearId]);
    $expiringSoonCount = $stmt->fetchColumn() ?? 0;

    // Financial year label
    $stmt = $pdo->prepare("SELECT year_start, year_end FROM accounting_years WHERE id = ?");
    $stmt->execute([$yearId]);
    $fy = $stmt->fetch(PDO::FETCH_ASSOC);
    $yearLabel = $fy ? date('Y', strtotime($fy['year_start'])) . "-" . date('Y', strtotime($fy['year_end'])) : "Unknown";

} catch (Exception $e) {
    $dailySales = $monthlySales = $dailyPurchase = $monthlyPurchase = 0;
    $lowStockCount = $expiringSoonCount = 0;
    $yearLabel = "Unknown";
    error_log("Dashboard Error: " . $e->getMessage());
}

function formatCurrency($amount) {
    return "₹" . number_format($amount, 2);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pharma Retail Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="../assets/css/dashboard_mobile.css">
    <link rel="stylesheet" href="../assets/css/master_style.css">
    
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

        .navbar-menu {
            display: flex;
            align-items: center;
            gap: 5px;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .navbar-menu > li {
            position: relative;
        }

        .nav-link-top {
            color: white;
            text-decoration: none;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-radius: 4px;
            font-size: 0.95rem;
        }

        .nav-link-top:hover {
            background: rgba(255,255,255,0.15);
        }

        /* Mega Dropdown for Reports */
        .reports-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            min-width: 800px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            border-radius: 8px;
            padding: 30px;
            display: none;
            opacity: 0;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            margin-top: 0;
        }

        .navbar-menu > li:hover .reports-dropdown {
            display: grid;
            opacity: 1;
            transform: translateY(0);
        }

        .reports-dropdown {
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
        }

        .report-category {
            position: relative;
        }

        .report-category-title {
            font-weight: 600;
            font-size: 1rem;
            color: #667eea;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding-bottom: 8px;
            border-bottom: 2px solid #e9ecef;
        }

        .report-category-title i {
            font-size: 1.1rem;
        }

        .report-links {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .report-link {
            color: #495057;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            transition: all 0.2s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .report-link:hover {
            background: #f8f9fa;
            color: #667eea;
            transform: translateX(5px);
        }

        .report-link i {
            font-size: 0.85rem;
            opacity: 0.6;
        }

        /* User Actions */
        .navbar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .notification-icon {
            position: relative;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            padding: 8px;
        }

        .notification-badge {
            position: absolute;
            top: 2px;
            right: 2px;
            background: #ff4757;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 0.7rem;
            font-weight: 600;
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

        /* Sidebar - Simplified */
        .sidebar {
            position: fixed;
            left: 0;
            top: 60px;
            bottom: 0;
            width: 260px;
            background: white;
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
            overflow-y: auto;
            transition: all 0.3s ease;
            z-index: 900;
        }

        .sidebar-user {
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .sidebar-user .user-info h6 {
            margin: 0;
            font-weight: 600;
        }

        .sidebar-user .user-info small {
            opacity: 0.9;
        }

        .sidebar-nav {
            padding: 20px 0;
        }

        .nav-section {
            margin-bottom: 25px;
        }

        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 20px;
            margin-bottom: 10px;
        }

        .nav-item {
            margin: 2px 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            color: #495057;
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s ease;
            font-size: 0.95rem;
        }

        .nav-link:hover {
            background: #f8f9fa;
            color: #667eea;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .nav-link i {
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 60px;
            padding: 30px;
            min-height: calc(100vh - 60px);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            transition: transform 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-content h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0 0 5px 0;
            color: #2d3436;
        }

        .stat-content p {
            color: #636e72;
            margin: 0;
            font-size: 0.9rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .stat-icon.sales { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-icon.purchase { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-icon.warning { background: linear-gradient(135deg, #ffa751 0%, #ffe259 100%); color: white; }
        .stat-icon.danger { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; }

        .stat-trend {
            font-size: 0.85rem;
            font-weight: 500;
        }

        .trend-up { color: #00b894; }
        .trend-down { color: #d63031; }

        /* Actions Grid */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .action-card h5 {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2d3436;
        }

        .action-card p {
            color: #636e72;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .btn-success { background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%); color: white; }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.show {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .navbar-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
                background: none;
                border: none;
                color: white;
                font-size: 1.5rem;
                cursor: pointer;
            }

            .reports-dropdown {
                min-width: 100vw;
                grid-template-columns: 1fr;
                left: -20px;
            }
        }

        .mobile-menu-toggle {
            display: none;
        }

        @media (min-width: 769px) {
            .sidebar-close {
                display: none;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navigation Bar -->
    <nav class="top-navbar">
        <div class="top-navbar-content">
            <div style="display: flex; align-items: center; gap: 20px;">
                <button class="mobile-menu-toggle" id="mobileMenuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <a href="dashboard.php" class="navbar-brand">
                    <i class="fas fa-pills"></i>
                    PHARMA RETAIL
                </a>
            </div>

            <ul class="navbar-menu">
                <li>
                    <a href="dashboard.php" class="nav-link-top">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                
                <li>
                    <a href="#" class="nav-link-top">
                        <i class="fas fa-chart-bar"></i>
                        Reports
                        <i class="fas fa-chevron-down" style="font-size: 0.7rem;"></i>
                    </a>
                    
                    <!-- Mega Dropdown -->
                    <div class="reports-dropdown">
                        <!-- Purchase Reports -->
                        <div class="report-category">
                            <div class="report-category-title">
                                <i class="fas fa-shopping-cart"></i>
                                Purchase Reports
                            </div>
                            <div class="report-links">
                                <a href="../reports/purchase/list_purchase.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Purchase Register
                                </a>
                                <a href="../reports/purchase/view_order_suggestions.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Purchase Order
                                </a>
                                <a href="../reports/purchase/monthly_purchase_report.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Monthly Purchase Report
                                </a>
                            </div>
                        </div>

                        <!-- Sales Reports -->
                        <div class="report-category">
                            <div class="report-category-title">
                                <i class="fas fa-cash-register"></i>
                                Sales Reports
                            </div>
                            <div class="report-links">
                                <a href="../reports/sales/list_sales.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Sales Register
                                </a>
                                <a href="../reports/misc/mis.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    MIS Report
                                </a>
                                <a href="salesnbilling/monthly_sales_report.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Monthly Sales Report
                                </a>
                            </div>
                        </div>

                        <!-- GST Reports -->
                        <div class="report-category">
                            <div class="report-category-title">
                                <i class="fas fa-calculator"></i>
                                GST Reports
                            </div>
                            <div class="report-links">
                                <a href="../reports/gst_reports/gst_summary.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    GST Summary
                                </a>
                            </div>
                        </div>

                        <!-- Expiry Reports -->
                        <div class="report-category">
                            <div class="report-category-title">
                                <i class="fas fa-calendar-times"></i>
                                Expiry Reports
                            </div>
                            <div class="report-links">
                                <a href="../reports/expiry_reports/expiring_soon.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Expiring Soon
                                </a>
                            </div>
                        </div>

                        <!-- Accounts Reports -->
                        <div class="report-category">
                            <div class="report-category-title">
                                <i class="fas fa-file-invoice-dollar"></i>
                                Accounts Reports
                            </div>
                            <div class="report-links">
                                <a href="../accounts/ledger_summary.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Ledger Summary
                                </a>
-                                <a href="../accounts/customer_ledger.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Customer Ledger
                                </a>
                                <a href="../accounts/create_voucher.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Create Voucher
                                </a>
                                <a href="../accounts/voucher_edit.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Edit Voucher
                                </a>
                                <a href="../accounts/receive_payment.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Customer Payment
                                </a>
                                <a href="../accounts/cash_book_list.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Cash Book
                                </a>
                            </div>
                        </div>

                        <!-- Misc Reports -->
                        <div class="report-category">
                            <div class="report-category-title">
                                <i class="fas fa-ellipsis-h"></i>
                                Misc Reports
                            </div>
                            <div class="report-links">
                                <a href="../reports/misc_reports/stock_summary.php" class="report-link">
                                    <i class="fas fa-angle-right"></i>
                                    Stock Summary
                                </a>
                            </div>
                        </div>
                    </div>
                </li>

                <?php if ($userRole === 'admin'): ?>
                <li>
                    <a href="../admin/control_panel.php" class="nav-link-top">
                        <i class="fas fa-shield-alt"></i>
                        Control Panel
                    </a>
                </li>
                <?php endif; ?>
            </ul>

            <div class="navbar-actions">
                <div class="notification-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($lowStockCount + $expiringSoonCount > 0): ?>
                        <span class="notification-badge"><?= $lowStockCount + $expiringSoonCount ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="user-profile">
                    <div class="user-avatar">
                        <?= strtoupper(substr($username, 0, 2)) ?>
                    </div>
                    <span><?= $username ?></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <button class="sidebar-close" id="sidebarClose" style="position: absolute; right: 10px; top: 10px; background: none; border: none; font-size: 1.5rem; color: white; cursor: pointer;">
            <i class="fas fa-times"></i>
        </button>
        
        <div class="sidebar-user">
            <div class="user-info">
                <h6><?= $username ?></h6>
                <small>FY: <?= $yearLabel ?></small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Operations</div>
                <div class="nav-item">
                    <a href="salesnbilling/create_bill.php" class="nav-link">
                        <i class="fas fa-cash-register"></i>
                        New Sale
                    </a>
                </div>
                <div class="nav-item">
                    <a href="purchasenstock/add_new_purchase.php" class="nav-link">
                        <i class="fas fa-cart-plus"></i>
                        New Purchase
                    </a>
                </div>
                <div class="nav-item">
                    <a href="purchasenstock/upload_purchase.php" class="nav-link">
                        <i class="fas fa-upload"></i>
                        Purchase Upload
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-clock"></i>
                        Expiry Management
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <div class="nav-item">
                    <a href="add/add_customer.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Customers
                    </a>
                </div>
                <div class="nav-item">
                    <a href="add/add_supplier.php" class="nav-link">
                        <i class="fas fa-truck"></i>
                        Suppliers
                    </a>
                </div>
            </div>

            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <div class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </div>
                <div class="nav-item">
                    <a href="../public/logout.php" class="nav-link">
                        <i class="fas fa-sign-out-alt"></i>
                        Logout
                    </a>
                </div>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3><?= formatCurrency($dailySales) ?></h3>
                        <p>Today's Sales</p>
                    </div>
                    <div class="stat-icon sales">
                        <i class="fas fa-chart-line"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">+12% from yesterday</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3><?= formatCurrency($monthlySales) ?></h3>
                        <p>Monthly Sales</p>
                    </div>
                    <div class="stat-icon sales">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">+8% from last month</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3><?= formatCurrency($dailyPurchase) ?></h3>
                        <p>Today's Purchases</p>
                    </div>
                    <div class="stat-icon purchase">
                        <i class="fas fa-shopping-cart"></i>
                    </div>
                </div>
                <div class="stat-trend trend-down">-5% from yesterday</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3><?= formatCurrency($monthlyPurchase) ?></h3>
                        <p>Monthly Purchases</p>
                    </div>
                    <div class="stat-icon purchase">
                        <i class="fas fa-truck"></i>
                    </div>
                </div>
                <div class="stat-trend trend-up">+15% from last month</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3><?= $lowStockCount ?></h3>
                        <p>Low Stock Items</p>
                    </div>
                    <div class="stat-icon warning">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                </div>
                <div class="stat-trend trend-down">Requires attention</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-content">
                        <h3><?= $expiringSoonCount ?></h3>
                        <p>Expiring Soon</p>
                    </div>
                    <div class="stat-icon danger">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
                <div class="stat-trend trend-down">Next 30 days</div>
            </div>
        </div>

        <!-- Quick Actions Grid -->
        <div class="actions-grid">
            <div class="action-card">
                <h5>Create New Sale</h5>
                <p>Process customer transactions, manage inventory, and generate sales receipts with support for fractional quantities.</p>
                <a href="salesnbilling/create_bill.php" class="btn-action btn-primary">
                    <i class="fas fa-plus-circle"></i>New Sale
                </a>
            </div>

            <div class="action-card">
                <h5>Record Purchase</h5>
                <p>Add new inventory, update stock levels, and manage supplier transactions efficiently.</p>
                <a href="purchasenstock/add_new_purchase.php" class="btn-action btn-success">
                    <i class="fas fa-truck"></i>New Purchase
                </a>
            </div>

            <div class="action-card">
                <h5>Purchase Upload</h5>
                <p>Bulk upload purchase data, streamline inventory updates with CSV imports.</p>
                <a href="purchasenstock/upload_purchase.php" class="btn-action btn-success">
                    <i class="fas fa-upload"></i>Purchase Upload
                </a>
            </div>

            <div class="action-card">
                <h5>Manage Customers</h5>
                <p>Add new customers, update contact information, and maintain customer database.</p>
                <a href="add/add_customer.php" class="btn-action btn-warning">
                    <i class="fas fa-user-plus"></i>Add Customer
                </a>
            </div>

            <div class="action-card">
                <h5>Supplier Management</h5>
                <p>Register new suppliers, manage vendor relationships, and track supplier performance.</p>
                <a href="add/add_supplier.php" class="btn-action btn-warning">
                    <i class="fas fa-handshake"></i>Add Supplier
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarClose = document.getElementById('sidebarClose');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');

            // Mobile sidebar toggle
            function toggleSidebar() {
                sidebar.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }

            function closeSidebar() {
                sidebar.classList.remove('show');
                document.body.style.overflow = '';
            }

            mobileMenuToggle?.addEventListener('click', toggleSidebar);
            sidebarClose?.addEventListener('click', closeSidebar);

            // Close sidebar when clicking nav links on mobile
            document.querySelectorAll('.nav-link').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth <= 768) closeSidebar();
                });
            });

            // Highlight active link based on URL
            const currentPage = window.location.pathname.split("/").pop();
            document.querySelectorAll('.nav-link').forEach(link => {
                if (link.getAttribute('href') && link.getAttribute('href').split("/").pop() === currentPage) {
                    link.classList.add('active');
                }
            });

            // Window resize handling
            window.addEventListener('resize', () => {
                if (window.innerWidth > 768) closeSidebar();
            });

            // Escape key closes sidebar
            document.addEventListener('keydown', e => {
                if (e.key === 'Escape' && sidebar.classList.contains('show')) {
                    closeSidebar();
                }
            });

            // Auto-refresh dashboard stats (5 minutes)
            setTimeout(() => location.reload(), 300000);
        });
    </script>
</body>
</html>