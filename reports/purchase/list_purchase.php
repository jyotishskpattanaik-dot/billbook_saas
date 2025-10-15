<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../public/login.php");
    exit;
}

$username = htmlspecialchars($_SESSION['user_name']);
$userRole = $_SESSION['user_role'] ?? 'user';

try {
    $pdo = getModulePDO();

    // Get financial year label
    $stmt = $pdo->prepare("SELECT year_start, year_end FROM accounting_years WHERE id = ?");
    $stmt->execute([$YEAR_ID]);
    $fy = $stmt->fetch(PDO::FETCH_ASSOC);
    $yearLabel = $fy ? date('Y', strtotime($fy['year_start'])) . "-" . date('Y', strtotime($fy['year_end'])) : "Unknown";

    // ✅ Fetch purchase headers with totals
    $sql = "
        SELECT 
            p.purchase_id,
            p.purchase_date,
            p.invoice_no,
            p.supplier_name,
            COALESCE(SUM(sd.net_amount), 0) AS bill_amount
        FROM purchase_details p
        LEFT JOIN stock_details sd 
               ON p.purchase_id = sd.purchase_id
              AND p.company_id = sd.company_id
              AND p.accounting_year_id = sd.accounting_year_id
        WHERE p.company_id = :company_id
          AND p.accounting_year_id = :year_id
        GROUP BY p.purchase_id, p.purchase_date, p.invoice_no, p.supplier_name
        ORDER BY p.purchase_date DESC, p.purchase_id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':company_id' => $COMPANY_ID,
        ':year_id'    => $YEAR_ID
    ]);
    $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Bills - Pharma Retail</title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">

    <!-- Required Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- ✅ Master Module CSS -->
    <link rel="stylesheet" href="../../assets/css/module.css">
</head>
<body>

    <!-- ✅ Include Reusable Navbar Component -->
    <?php include __DIR__ . '/../../includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Back Button -->
        <!-- <a href="../../pharma_retail/dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i>
            Back to Dashboard
        </a> -->

        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <div class="icon-wrapper purple">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                Purchase Bills Register
            </h1>
            <p>View and manage all purchase transactions</p>
        </div>

        <!-- Info Badge -->
        <!-- <div class="info-badge">
            <i class="fas fa-calendar-alt"></i>
            FY: <?= $yearLabel ?>
        </div> -->

        <!-- Stats Summary -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalCount"><?= count($purchases) ?></h3>
                    <p>Total Purchases</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon success">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-content">
                    <h3 id="totalAmount">₹<?= number_format(array_sum(array_column($purchases, 'bill_amount')), 2) ?></h3>
                    <p>Total Amount</p>
                </div>
            </div>
        </div>

        <!-- Filters and Action Buttons -->
        <div class="filters-section">
            <div class="date-filter">
                <label for="startDate">
                    <i class="fas fa-calendar"></i>
                    From Date
                </label>
                <input type="date" id="startDate" class="form-control-date">
                
                <label for="endDate">
                    <i class="fas fa-calendar"></i>
                    To Date
                </label>
                <input type="date" id="endDate" class="form-control-date">
                
                <button class="btn-filter" id="filterBtn">
                    <i class="fas fa-filter"></i>
                    Apply Filter
                </button>
                
                <button class="btn-reset" id="resetBtn">
                    <i class="fas fa-redo"></i>
                    Reset
                </button>
            </div>
            
            <a href="../../pharma_retail/purchasenstock/add_new_purchase.php" class="btn-success">
                <i class="fas fa-plus-circle"></i>
                Add New Purchase
            </a>
        </div>

        <!-- Table Container -->
        <div class="table-container">
            <div class="table-header">
                <h5>
                    <i class="fas fa-list"></i>
                    Purchase List
                </h5>
            </div>

            <div class="table-responsive">
                <?php if (!empty($purchases)): ?>
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Purchase ID</th>
                                <th>Date</th>
                                <th>Invoice Number</th>
                                <th>Supplier</th>
                                <th class="text-right">Amount</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($purchases as $p): ?>
                            <tr>
                                <td><strong>#<?= htmlspecialchars($p['purchase_id']) ?></strong></td>
                                <td><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                                <td><?= htmlspecialchars($p['invoice_no']) ?></td>
                                <td><?= htmlspecialchars($p['supplier_name']) ?></td>
                                <td class="text-right amount-cell">₹<?= number_format($p['bill_amount'], 2) ?></td>
                                <td class="text-center">
                                    <a href="../../pharma_retail/purchasenstock/edit_purchase.php?purchase_id=<?= urlencode($p['purchase_id']) ?>" 
                                       class="btn-edit">
                                        <i class="fas fa-edit"></i>
                                        Edit
                                    </a>
                                    <button class="btn-delete deleteBtn" data-id="<?= $p['purchase_id'] ?>">
                                        <i class="fas fa-trash-alt"></i>
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h5>No Purchases Found</h5>
                        <p>You haven't recorded any purchases yet.</p>
                        <a href="../../pharma_retail/purchasenstock/add_new_purchase.php" class="btn-success">
                            <i class="fas fa-plus-circle"></i>
                            Add Your First Purchase
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Store all purchases for filtering
        const allPurchases = <?= json_encode($purchases) ?>;
        
        // Initialize date inputs with current month
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('startDate').valueAsDate = firstDay;
            document.getElementById('endDate').valueAsDate = today;
        });

        // Filter functionality
        document.getElementById('filterBtn').addEventListener('click', function() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            
            if (!startDate || !endDate) {
                alert('Please select both start and end dates');
                return;
            }
            
            const filtered = allPurchases.filter(p => {
                const purchaseDate = new Date(p.purchase_date);
                const start = new Date(startDate);
                const end = new Date(endDate);
                return purchaseDate >= start && purchaseDate <= end;
            });
            
            renderTable(filtered);
            updateStats(filtered);
        });

        // Reset functionality
        document.getElementById('resetBtn').addEventListener('click', function() {
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            
            document.getElementById('startDate').valueAsDate = firstDay;
            document.getElementById('endDate').valueAsDate = today;
            
            renderTable(allPurchases);
            updateStats(allPurchases);
        });

        // Render table with filtered data
        function renderTable(data) {
            const tbody = document.querySelector('.custom-table tbody');
            
            if (data.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center" style="padding: 60px 20px;">
                            <i class="fas fa-inbox" style="font-size: 3rem; color: #dfe6e9; margin-bottom: 15px; display: block;"></i>
                            <h5 style="color: #495057; font-weight: 600;">No Purchases Found</h5>
                            <p style="color: #636e72;">No purchases match the selected date range.</p>
                        </td>
                    </tr>
                `;
                return;
            }
            
            tbody.innerHTML = data.map(p => `
                <tr>
                    <td><strong>#${escapeHtml(p.purchase_id)}</strong></td>
                    <td>${formatDate(p.purchase_date)}</td>
                    <td>${escapeHtml(p.invoice_no)}</td>
                    <td>${escapeHtml(p.supplier_name)}</td>
                    <td class="text-right amount-cell">₹${numberFormat(p.bill_amount)}</td>
                    <td class="text-center">
                        <a href="../../pharma_retail/purchasenstock/edit_purchase.php?purchase_id=${encodeURIComponent(p.purchase_id)}" 
                           class="btn-edit">
                            <i class="fas fa-edit"></i>
                            Edit
                        </a>
                        <button class="btn-delete deleteBtn" data-id="${p.purchase_id}">
                            <i class="fas fa-trash-alt"></i>
                            Delete
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        // Update stats
        function updateStats(data) {
            const totalAmount = data.reduce((sum, p) => sum + parseFloat(p.bill_amount || 0), 0);
            document.getElementById('totalCount').textContent = data.length;
            document.getElementById('totalAmount').textContent = '₹' + numberFormat(totalAmount);
        }

        // Helper functions
        function formatDate(dateStr) {
            const date = new Date(dateStr);
            const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            return `${date.getDate()} ${months[date.getMonth()]} ${date.getFullYear()}`;
        }

        function numberFormat(num) {
            return parseFloat(num).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Delete functionality
        $(document).on("click", ".deleteBtn", function(){
            if(confirm("⚠️ Are you sure you want to delete this purchase bill? This action will rollback stock and cannot be undone.")){
                let pid = $(this).data("id");
                // TODO: Implement actual delete functionality
                alert("Delete handler not yet implemented. Purchase ID = " + pid);
            }
        });
    </script>
</body>
</html>