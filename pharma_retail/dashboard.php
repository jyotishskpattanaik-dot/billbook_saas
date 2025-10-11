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
$userRole  = $_SESSION['user_role'] ?? 'user'; // ✅ Default to user if not set
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

// Helper function for formatting currency
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
    
    <!-- Bootstrap 5 + Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- External CSS -->
    <link rel="stylesheet" href="../assets/css/dashboard_mobile.css">
    <link rel="stylesheet" href="../assets/css/master_style.css">
    <style>
        .submenu {
    display: none;
    padding-left: 15px;
}

.submenu.show {
    display: block;
}

.submenu a.active {
    font-weight: 600;
    color: #0d6efd;
}

.submenu-toggle.expanded {
    font-weight: 600;
    color: #0d6efd;
}

    </style>
</head>
<body>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <h2>PHARMA RETAIL</h2>
            <button class="sidebar-close" id="sidebarClose">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">
                <?= strtoupper(substr($username, 0, 2)) ?>
            </div>
            <div class="user-info">
                <h6><?= $username ?></h6>
                <small>FY: <?= $yearLabel ?></small>
            </div>
        </div>

        
        <!-- <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Main</div>
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </div>
            </div> -->
              <!-- ✅ Admin-only Control Panel (fixed path) -->
            <!-- <?php if ($userRole === 'admin'): ?>
            <div class="nav-item">
                <a href="../../admin/control_panel.php" class="nav-link">
                    <i class="fas fa-shield-alt"></i>
                    Control Panel
                </a>
            </div>
            <?php endif; ?> -->

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
    <div class="nav-section-title">Reports</div>

    <!-- Purchase Reports -->
    <div class="nav-item">
        <a href="#" class="nav-link submenu-toggle" data-target="purchase-reports-submenu">
            <i class="fas fa-file-invoice"></i> Purchase Reports
        </a>
        <div class="submenu" id="purchase-reports-submenu">
            <a href="../reports/purchase/list_purchase.php" class="nav-link">Purchase Rigister</a>
            <a href="../reports/purchase/monthly_purchase_report.php" class="nav-link">Monthly Purchase Report</a>
        </div>
    </div>

    <!-- Sales Reports -->
    <div class="nav-item">
        <a href="#" class="nav-link submenu-toggle" data-target="sales-reports-submenu">
            <i class="fas fa-receipt"></i> Sales Reports
        </a>
        <div class="submenu" id="sales-reports-submenu">
           <!-- <a href="../reports/sales/list_sales.php" class="nav-link">Sales Register</a> -->
         <a href="../reports/sales/list_sales.php" class="nav-link">Sales Register</a>
         <a href="../reports/misc/mis.php" class="nav-link"></a>
            <a href="salesnbilling/monthly_sales_report.php" class="nav-link">Monthly Sales Report</a>
        </div>
    </div>

    <!-- GST Reports -->
    <div class="nav-item">
        <a href="#" class="nav-link submenu-toggle" data-target="gst-reports-submenu">
            <i class="fas fa-calculator"></i> GST Reports
        </a>
        <div class="submenu" id="gst-reports-submenu">
            <a href="../reports/gst_reports/gst_summary.php" class="nav-link">GST Summary</a>
        </div>
    </div>

    <!-- Expiry Reports -->
    <div class="nav-item">
        <a href="#" class="nav-link submenu-toggle" data-target="expiry-reports-submenu">
            <i class="fas fa-calendar-times"></i> Expiry Reports
        </a>
        <div class="submenu" id="expiry-reports-submenu">
            <a href="../reports/expiry_reports/expiring_soon.php" class="nav-link">Expiring Soon</a>
        </div>
    </div>
     <!-- Accounts Reports -->
    <div class="nav-item">
        <a href="#" class="nav-link submenu-toggle" data-target="accounts-submenu">
            <i class="fas fa-calendar-times"></i> Accounts Reports
        </a>
        <div class="submenu" id="accounts-submenu">
            <a href="../accounts/ledger_summary.php" class="nav-link">Ledger Summary</a>
            <a href="../accounts/voucher_list.php" class="nav-link">Voucher Register</a>
             <a href="../accounts/create_voucher.php" class="nav-link">Create Voucher</a>
             <a href="../accounts/voucher_view.php" class="nav-link">Voucher</a>
             <a href="../accounts/voucher_edit.php" class="nav-link">Edit Voucher</a>
             <!-- <a href="../accounts/expense_heads.php" class="nav-link">Expense Catagories</a> -->
            <a href="../accounts/ledger_entries_list.php" class="nav-link">Ledger Entries</a>
        </div>
    </div>

    <!-- Misc Reports -->
    <div class="nav-item">
        <a href="#" class="nav-link submenu-toggle" data-target="misc-reports-submenu">
            <i class="fas fa-ellipsis-h"></i> Misc Reports
        </a>
        <div class="submenu" id="misc-reports-submenu">
            <a href="../reports/misc_reports/stock_summary.php" class="nav-link">Stock Summary</a>
        </div>
    </div>
</div>

            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
                    </a>
                </div>
                <div class="d-flex align-items-center">
    <!-- Profile -->
    <a href="profile.php" class="btn btn-sm btn-outline-primary me-3">
        <i class="fas fa-user"></i> Profile
    </a>

    <!-- Control Panel (only for admin) -->
    <?php if ($userRole === 'admin'): ?>
        <a href="../admin/control_panel.php" class="btn btn-sm btn-outline-primary me-3">
            <i class="fas fa-shield-alt"></i> Control Panel
        </a>
    <?php endif; ?>

    <!-- Logout -->
    <a href="../public/logout.php" class="btn btn-sm btn-outline-danger">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>           
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Topbar -->
<div class="topbar">
    <div class="topbar-left">
        <button class="mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h4>Dashboard Overview</h4>
    </div>
    <div class="topbar-actions">
        <!-- ✅ Control Panel shortcut (admin only) -->
        <?php if ($userRole === 'admin'): ?>
            <a href="../admin/control_panel.php" class="btn btn-sm btn-outline-primary me-3">
                <i class="fas fa-shield-alt"></i> Control Panel
            </a>
        <?php endif; ?>

        <div class="notification-badge">
            <i class="fas fa-bell"></i>
            <?php if ($lowStockCount + $expiringSoonCount > 0): ?>
                <span class="badge"><?= $lowStockCount + $expiringSoonCount ?></span>
            <?php endif; ?>
        </div>
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?= $username ?></span>
        </div>
    </div>
</div>

        <!-- Content Area -->
        <div class="content-area">
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
                    <p>Add new inventory, update stock levels, and manage supplier transactions efficiently.</p>
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
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
       document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarClose = document.getElementById('sidebarClose');
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');

    // ---------- Mobile sidebar toggle ----------
    function toggleSidebar() {
        sidebar.classList.toggle('show');
        sidebarOverlay.classList.toggle('show');
        document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        sidebarOverlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    mobileMenuToggle?.addEventListener('click', toggleSidebar);
    sidebarClose?.addEventListener('click', closeSidebar);
    sidebarOverlay?.addEventListener('click', closeSidebar);

    // Close sidebar when clicking nav links (except submenu toggles) on mobile
    document.querySelectorAll('.nav-link').forEach(link => {
        if (!link.classList.contains('submenu-toggle')) {
            link.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        }
    });

    // ---------- Submenu toggle (accordion behavior) ----------
    const toggles = document.querySelectorAll('.submenu-toggle');
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-target');
            const submenu = document.getElementById(targetId);

            // Close all other submenus
            document.querySelectorAll('.submenu').forEach(menu => {
                if (menu !== submenu) menu.classList.remove('show');
            });

            document.querySelectorAll('.submenu-toggle').forEach(other => {
                if (other !== this) other.classList.remove('expanded');
            });

            // Toggle clicked submenu
            submenu.classList.toggle('show');
            this.classList.toggle('expanded');
        });
    });

    // ---------- Highlight active link based on URL ----------
    const currentPage = window.location.pathname.split("/").pop();
    document.querySelectorAll('.submenu a').forEach(link => {
        if (link.getAttribute('href').split("/").pop() === currentPage) {
            link.classList.add('active');
            link.closest('.submenu').classList.add('show');
            link.closest('.submenu').previousElementSibling.classList.add('expanded');
        }
    });

    // ---------- Window resize handling ----------
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) closeSidebar();
    });

    // ---------- Escape key closes sidebar ----------
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape' && sidebar.classList.contains('show')) {
            closeSidebar();
        }
    });

    // ---------- Swipe support for mobile ----------
    let touchStartX = 0, touchEndX = 0;
    document.addEventListener('touchstart', e => touchStartX = e.changedTouches[0].screenX);
    document.addEventListener('touchend', e => {
        touchEndX = e.changedTouches[0].screenX;
        const swipeDistance = touchEndX - touchStartX;
        if (window.innerWidth <= 768) {
            if (swipeDistance > 100 && touchStartX < 50 && !sidebar.classList.contains('show')) toggleSidebar();
            else if (swipeDistance < -100 && sidebar.classList.contains('show')) closeSidebar();
        }
    });

    // ---------- Auto-refresh dashboard stats ----------
    setTimeout(() => location.reload(), 300000); // 5 min
});

</script>
</body>
</html>