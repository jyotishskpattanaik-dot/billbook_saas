<?php
/**
 * REUSABLE NAVBAR COMPONENT
 * Save this as: includes/navbar.php
 * Include it in every page with: <?php include __DIR__ . '/../../includes/navbar.php'; ?>
 */

// Ensure session variables are available
$username = $username ?? htmlspecialchars($_SESSION['user_name'] ?? 'User');
$userRole = $userRole ?? ($_SESSION['user_role'] ?? 'user');
?>

<!-- Top Navigation Bar -->
<nav class="top-navbar">
    <div class="top-navbar-content">
        <a href="../../pharma_retail/dashboard.php" class="navbar-brand">
            <i class="fas fa-pills"></i>
            PHARMA RETAIL
        </a>

        <ul class="navbar-menu">
            <li>
                <a href="../../pharma_retail/dashboard.php" class="nav-link-top">
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
                            <a href="../../reports/purchase/list_purchase.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Purchase Register
                            </a>
                            <a href="../../reports/purchase/view_order_suggestions.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Purchase Order
                            </a>
                            <a href="../../reports/purchase/monthly_purchase_report.php" class="report-link">
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
                            <a href="../../reports/sales/list_sales.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Sales Register
                            </a>
                            <a href="../../reports/misc/mis.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                MIS Report
                            </a>
                            <a href="../../pharma_retail/salesnbilling/monthly_sales_report.php" class="report-link">
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
                            <a href="../../reports/gst_reports/gst_summary.php" class="report-link">
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
                            <a href="../../reports/expiry_reports/expiring_soon.php" class="report-link">
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
                            <a href="../../accounts/ledger_summary.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Ledger Summary
                            </a>
                            <a href="../../accounts/voucher_list.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Voucher Register
                            </a>
                            <a href="../../accounts/create_voucher.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Create Voucher
                            </a>
                            <a href="../../accounts/voucher_edit.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Edit Voucher
                            </a>
                            <a href="../../accounts/receive_payment.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Customer Payment
                            </a>
                            <a href="../../accounts/cash_book_list.php" class="report-link">
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
                            <a href="../../reports/misc_reports/stock_summary.php" class="report-link">
                                <i class="fas fa-angle-right"></i>
                                Stock Summary
                            </a>
                        </div>
                    </div>
                </div>
            </li>

            <?php if ($userRole === 'admin'): ?>
            <li>
                <a href="../../admin/control_panel.php" class="nav-link-top">
                    <i class="fas fa-shield-alt"></i>
                    Control Panel
                </a>
            </li>
            <?php endif; ?>
        </ul>

        <div class="navbar-actions">
            <div class="notification-icon">
                <i class="fas fa-bell"></i>
                <?php if (isset($lowStockCount, $expiringSoonCount) && ($lowStockCount + $expiringSoonCount) > 0): ?>
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