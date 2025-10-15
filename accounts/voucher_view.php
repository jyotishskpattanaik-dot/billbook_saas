<?php
require __DIR__ . '/../includes/init.php';
require __DIR__ . '/../includes/navigation_helper.php';

// ✅ Safe session start
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Check login/session
if (!isset($_SESSION['company_id'], $_SESSION['financial_year_id'])) {
    header("Location: ../public/login.php");
    exit;
}

if (!isset($_GET['voucher_id'])) {
    die("Voucher ID not provided.");
}

$voucherId = (int) $_GET['voucher_id'];
$username = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$userRole = $_SESSION['user_role'] ?? 'user';

// ✅ Get both database connections
$pdo = getModulePDO();   // module DB (e.g., pharma_retail_db)
$mainPDO = getMainPDO(); // main DB (user & company details)

$companyId = $_SESSION['company_id'];
$yearId    = $_SESSION['financial_year_id'];

// Get financial year label
try {
    $stmt = $pdo->prepare("SELECT year_start, year_end FROM accounting_years WHERE id = ?");
    $stmt->execute([$yearId]);
    $fy = $stmt->fetch(PDO::FETCH_ASSOC);
    $yearLabel = $fy ? date('Y', strtotime($fy['year_start'])) . "-" . date('Y', strtotime($fy['year_end'])) : "Unknown";
} catch (Exception $e) {
    $yearLabel = "Unknown";
}

// ✅ Fetch voucher details (no JOIN with users)
$stmt = $pdo->prepare("
    SELECT 
        v.*,
        eh.name AS category_name,
        pm.method_name AS payment_method
    FROM vouchers v
    LEFT JOIN expense_heads eh ON v.category_id = eh.id
    LEFT JOIN payment_methods pm ON v.payment_mode_id = pm.id
    WHERE v.id = :id AND v.company_id = :company_id AND v.accounting_year_id = :year_id
");
$stmt->execute([
    ':id' => $voucherId,
    ':company_id' => $companyId,
    ':year_id' => $yearId
]);
$voucher = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$voucher) {
    die("Voucher not found.");
}

// ✅ Fetch created_by username from main_db.users
$voucher['created_by_user'] = '-';
if (!empty($voucher['created_by'])) {
    $stmt = $mainPDO->prepare("SELECT username FROM users WHERE user_id = ? LIMIT 1");
    $stmt->execute([$voucher['created_by']]);
    $voucher['created_by_user'] = $stmt->fetchColumn() ?: '-';
}

// ✅ Fetch corresponding ledger entries from module DB
$stmt = $pdo->prepare("
    SELECT 
        le.entry_date,
        la1.account_name AS debit_account,
        la2.account_name AS credit_account,
        le.amount,
        le.narration
    FROM ledger_entries le
    JOIN ledger_accounts_master la1 ON le.debit_account_id = la1.id
    JOIN ledger_accounts_master la2 ON le.credit_account_id = la2.id
    WHERE le.ref_table = 'vouchers' 
      AND le.ref_id = :voucher_id
      AND le.company_id = :company_id 
      AND le.accounting_year_id = :year_id
");
$stmt->execute([
    ':voucher_id' => $voucherId,
    ':company_id' => $companyId,
    ':year_id' => $yearId
]);
$ledgerEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher View - <?= htmlspecialchars($voucher['voucher_no']) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=no">

    <!-- Required Libraries -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- ✅ Master Module CSS -->
    <link rel="stylesheet" href="../assets/css/module.css">

    <!-- Print Styles -->
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .main-content {
                margin-top: 0;
            }
            .voucher-container {
                box-shadow: none;
                border: 1px solid #000;
            }
        }

        .voucher-container {
            background: white;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .voucher-header {
            border-bottom: 3px solid #667eea;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .voucher-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3436;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .voucher-badge {
            display: inline-flex;
            align-items: center;
            padding: 8px 16px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
        }

        .info-item label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item .value {
            font-size: 1.1rem;
            color: #2d3436;
            font-weight: 500;
        }

        .narration-box {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 30px;
        }

        .narration-box label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 600;
            display: block;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .narration-box .content {
            color: #2d3436;
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-print {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(79, 172, 254, 0.3);
        }

        .footer-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-info-item {
            flex: 1;
            min-width: 200px;
        }

        .footer-info-item label {
            font-size: 0.85rem;
            color: #636e72;
            font-weight: 600;
            display: block;
            margin-bottom: 5px;
        }

        .footer-info-item .value {
            color: #2d3436;
            font-weight: 500;
        }

        /* Amount highlight */
        .amount-highlight {
            font-size: 1.3rem;
            font-weight: 700;
            color: #667eea;
        }
    </style>
</head>
<body>

    <!-- ✅ Include Reusable Navbar Component -->
    <?php include __DIR__ . '/../includes/navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Back Button -->
        <div class="no-print">
            <a href="voucher_list.php" class="back-button">
                <i class="fas fa-arrow-left"></i>
                Back to Voucher List
            </a>
        </div>

        <!-- Page Header -->
        <div class="page-header no-print">
            <h1>
                <div class="icon-wrapper info">
                    <i class="fas fa-file-invoice"></i>
                </div>
                Voucher Details
            </h1>
            <p>View complete voucher information and ledger entries</p>
        </div>

        <!-- Info Badge -->
        <div class="info-badge no-print">
            <i class="fas fa-calendar-alt"></i>
            FY: <?= $yearLabel ?>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons no-print" style="margin-bottom: 25px;">
            <button class="btn-print" onclick="window.print()">
                <i class="fas fa-print"></i>
                Print Voucher
            </button>
            
            <a href="voucher_edit.php?voucher_id=<?= $voucherId ?>" class="btn-primary">
                <i class="fas fa-edit"></i>
                Edit Voucher
            </a>
            
            <a href="<?= getModuleDashboardUrl() ?>" class="btn-secondary">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </div>

        <!-- Voucher Container -->
        <div class="voucher-container">
            <!-- Voucher Header -->
            <div class="voucher-header">
                <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
                    <div class="voucher-title">
                        <i class="fas fa-receipt" style="color: #667eea;"></i>
                        Voucher #<?= htmlspecialchars($voucher['voucher_no']) ?>
                    </div>
                    <div class="voucher-badge">
                        <?= ucfirst(htmlspecialchars($voucher['voucher_type'])) ?> Voucher
                    </div>
                </div>
            </div>

            <!-- Voucher Information Grid -->
            <div class="info-grid">
                <div class="info-item">
                    <label>Voucher Number</label>
                    <div class="value"><?= htmlspecialchars($voucher['voucher_no']) ?></div>
                </div>

                <div class="info-item">
                    <label>Voucher Date</label>
                    <div class="value">
                        <i class="fas fa-calendar"></i>
                        <?= date('d M Y', strtotime($voucher['voucher_date'])) ?>
                    </div>
                </div>

                <div class="info-item">
                    <label>Voucher Type</label>
                    <div class="value"><?= ucfirst(htmlspecialchars($voucher['voucher_type'])) ?></div>
                </div>

                <div class="info-item">
                    <label>Category</label>
                    <div class="value"><?= htmlspecialchars($voucher['category_name'] ?? '-') ?></div>
                </div>

                <div class="info-item">
                    <label>Payment Mode</label>
                    <div class="value"><?= htmlspecialchars($voucher['payment_method'] ?? '-') ?></div>
                </div>

                <div class="info-item">
                    <label>Paid To</label>
                    <div class="value"><?= htmlspecialchars($voucher['paid_to'] ?? '-') ?></div>
                </div>

                <div class="info-item" style="grid-column: span 2;">
                    <label>Total Amount</label>
                    <div class="value amount-highlight">
                        <i class="fas fa-rupee-sign"></i>
                        <?= number_format($voucher['amount'], 2) ?>
                    </div>
                </div>
            </div>

            <!-- Narration/Remarks Box -->
            <?php if (!empty($voucher['particulars']) || !empty($voucher['remarks'])): ?>
            <div class="narration-box">
                <label>
                    <i class="fas fa-file-alt"></i>
                    Narration / Remarks
                </label>
                <div class="content">
                    <?= nl2br(htmlspecialchars($voucher['particulars'] ?? $voucher['remarks'])) ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Ledger Entries Table -->
            <div class="table-container">
                <div class="table-header">
                    <h5>
                        <i class="fas fa-book"></i>
                        Ledger Entries
                    </h5>
                </div>

                <div class="table-responsive">
                    <?php if (!empty($ledgerEntries)): ?>
                        <table class="custom-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Debit Account</th>
                                    <th>Credit Account</th>
                                    <th class="text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ledgerEntries as $le): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($le['entry_date'])) ?></td>
                                    <td>
                                        <i class="fas fa-arrow-up text-success"></i>
                                        <?= htmlspecialchars($le['debit_account']) ?>
                                    </td>
                                    <td>
                                        <i class="fas fa-arrow-down text-danger"></i>
                                        <?= htmlspecialchars($le['credit_account']) ?>
                                    </td>
                                    <td class="text-right amount-cell">₹<?= number_format($le['amount'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr style="background: #f8f9fa; font-weight: 600;">
                                    <td colspan="3" class="text-right">Total Amount:</td>
                                    <td class="text-right amount-cell" style="color: #667eea; font-size: 1.1rem;">
                                        ₹<?= number_format($voucher['amount'], 2) ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <h5>No Ledger Entries</h5>
                            <p>No ledger entries found for this voucher.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer Information -->
            <div class="footer-info">
                <div class="footer-info-item">
                    <label>
                        <i class="fas fa-user"></i>
                        Created By
                    </label>
                    <div class="value"><?= htmlspecialchars($voucher['created_by_user'] ?? '-') ?></div>
                </div>

                <div class="footer-info-item">
                    <label>
                        <i class="fas fa-clock"></i>
                        Created At
                    </label>
                    <div class="value">
                        <?php if (!empty($voucher['created_at'])): ?>
                            <?= date('d M Y, h:i A', strtotime($voucher['created_at'])) ?>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </div>
                </div>

                <div class="footer-info-item">
                    <label>
                        <i class="fas fa-hashtag"></i>
                        Voucher ID
                    </label>
                    <div class="value">#<?= $voucherId ?></div>
                </div>
            </div>

            <!-- Action Buttons at Bottom -->
            <div class="action-buttons no-print" style="margin-top: 30px;">
                <a href="voucher_list.php" class="btn-secondary">
                    <i class="fas fa-arrow-left"></i>
                    Back to List
                </a>
                
                <a href="voucher_edit.php?voucher_id=<?= $voucherId ?>" class="btn-primary">
                    <i class="fas fa-edit"></i>
                    Edit Voucher
                </a>
                
                <button class="btn-danger" onclick="deleteVoucher()">
                    <i class="fas fa-trash-alt"></i>
                    Delete Voucher
                </button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function deleteVoucher() {
            if (confirm('⚠️ Are you sure you want to delete this voucher? This action cannot be undone.')) {
                // TODO: Implement delete functionality
                alert('Delete functionality not yet implemented. Voucher ID: <?= $voucherId ?>');
            }
        }
    </script>
</body>
</html>