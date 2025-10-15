<?php
require __DIR__ . '/../includes/public_db_helper.php';
require __DIR__ . '/../vendor/autoload.php';

$pdo = getPublicPDO();

// ✅ Get order number from URL
$order_number = $_GET['order'] ?? null;
$type = $_GET['type'] ?? 'paid'; // 'trial' or 'paid'

// ✅ Handle missing order number
if (!$order_number) {
    header("Location: ../index.php");
    exit;
}

// ✅ Fetch order details
try {
    $stmt = $pdo->prepare("
        SELECT o.*, s.expiry_date AS subscription_expiry
        FROM orders o
        LEFT JOIN subscriptions s ON o.id = s.order_id
        WHERE o.order_number = :order_number
    ");
    $stmt->execute([':order_number' => $order_number]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header("Location: ../index.php");
        exit;
    }

    $email = $order['email'] ?? '';
    $module_name = $order['module'] ?? 'N/A';
} catch (Exception $e) {
    error_log("Success page error: " . $e->getMessage());
    header("Location: ../index.php");
    exit;
}

$is_trial = ($type === 'trial' || $order['amount'] == 0);
$plan_display = ucfirst($order['plan_name'] ?? 'Unknown');

// ✅ Debug log
file_put_contents(
    __DIR__ . '/../debug_checkout.log',
    date('Y-m-d H:i:s') . " SUCCESS for {$email}, ORDER={$order_number}, MODULE={$module_name}\n",
    FILE_APPEND
);
?>
<!doctype html>
<html class="no-js" lang="en">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Order Confirmation - Thank You!</title>
    <meta name="description" content="Your order has been confirmed successfully.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/logo/favicon.svg">

    <!-- CSS here -->
    <link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/animate.min.css">
    <link rel="stylesheet" href="../assets/css/plugins/swiper.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/magnific-popup.css">
    <link rel="stylesheet" href="../assets/css/vendor/icomoon.css">
    <link rel="stylesheet" href="../assets/css/vendor/spacing.css">
    <link rel="stylesheet" href="../assets/css/main.css">

    <style>
        .success-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease-out;
        }
        .success-icon svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        @keyframes scaleIn {
            from { transform: scale(0); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .order-details-card {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 30px;
            margin: 30px 0;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #dee2e6;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { font-weight: 600; color: #495057; }
        .detail-value { color: #212529; text-align: right; }
        .next-steps {
            background: white;
            border: 2px solid #0066cc;
            border-radius: 12px;
            padding: 25px;
            margin: 30px 0;
        }
        .step-item {
            display: flex;
            align-items: start;
            margin-bottom: 20px;
        }
        .step-item:last-child { margin-bottom: 0; }
        .step-number {
            width: 35px;
            height: 35px;
            background: #0066cc;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 15px;
            flex-shrink: 0;
        }
        .step-content { flex: 1; }
    </style>
</head>

<body>
    <?php include 'preload.php'; ?>
    <?php include 'header.php'; ?>
    <?php include 'offcanvas_area.php'; ?>

    <main class="ap-main-area">
        <section class="section-space">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-8">

                        <div class="success-icon">
                            <svg viewBox="0 0 52 52"><path d="M14 27l7.5 7.5L38 18"/></svg>
                        </div>

                        <div class="text-center mb-4">
                            <?php if ($is_trial): ?>
                                <h2 class="mb-3">🎉 Free Trial Activated!</h2>
                                <p class="lead">Your 14-day free trial has been successfully activated.</p>
                            <?php else: ?>
                                <h2 class="mb-3">✅ Payment Successful!</h2>
                                <p class="lead">Thank you for your purchase. Your subscription is now active.</p>
                            <?php endif; ?>
                        </div>

                        <div class="order-details-card">
                            <h4 class="mb-4">Order Details</h4>

                            <div class="detail-row">
                                <span class="detail-label">Order Number:</span>
                                <span class="detail-value"><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Name:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Email:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($order['email']); ?></span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Plan:</span>
                                <span class="detail-value">
                                    <strong><?php echo $plan_display; ?> Plan</strong><br>
                                    <small style="color:#6c757d;">Module: <?php echo ucfirst(htmlspecialchars($module_name)); ?></small>
                                </span>
                            </div>

                            <div class="detail-row">
                                <span class="detail-label">Duration:</span>
                                <span class="detail-value">
                                    <?php 
                                    if ($is_trial) echo '14 Days Trial';
                                    else echo $order['duration'] . ' ' . ucfirst($order['billing_period']) . ($order['duration'] > 1 ? 's' : '');
                                    ?>
                                </span>
                            </div>

                            <?php if ($order['subscription_expiry']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Valid Until:</span>
                                <span class="detail-value"><?php echo date('F d, Y', strtotime($order['subscription_expiry'])); ?></span>
                            </div>
                            <?php endif; ?>

                            <?php if (!$is_trial): ?>
                            <div class="detail-row">
                                <span class="detail-label">Amount Paid:</span>
                                <span class="detail-value"><strong style="font-size: 1.3rem; color: #28a745;">₹<?php echo number_format($order['amount'], 2); ?></strong></span>
                            </div>
                            <?php if ($order['transaction_id']): ?>
                            <div class="detail-row">
                                <span class="detail-label">Transaction ID:</span>
                                <span class="detail-value"><small><?php echo htmlspecialchars($order['transaction_id']); ?></small></span>
                            </div>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>

                        <div class="next-steps">
                            <h4 class="mb-4">What's Next?</h4>
                            <div class="step-item">
                                <div class="step-number">1</div>
                                <div class="step-content">
                                    <h6>Check Your Email</h6>
                                    <p class="mb-0">We've sent a confirmation email to <strong><?php echo htmlspecialchars($order['email']); ?></strong> with your subscription details.</p>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">2</div>
                                <div class="step-content">
                                    <h6>Access Your Dashboard</h6>
                                    <p class="mb-0">Log in to your account dashboard to start using all the features included in your <?php echo $plan_display; ?> plan.</p>
                                </div>
                            </div>
                            <div class="step-item">
                                <div class="step-number">3</div>
                                <div class="step-content">
                                    <h6>Need Help?</h6>
                                    <p class="mb-0">Our support team is here to help. Contact us anytime at <a href="mailto:support@yourcompany.com">support@yourcompany.com</a></p>
                                </div>
                            </div>
                        </div>

                        <div class="text-center mt-5">
                            <a href="../public/login.php" class="ap-btn btn-primary me-3 mb-3">Go to Login</a>
                            <a href="../index.php" class="ap-btn btn-secondary mb-3">Back to Home</a>
                        </div>

                        <?php if (!$is_trial): ?>
                        <div class="text-center mt-3">
                            <a href="download_invoice.php?order=<?php echo urlencode($order['order_number']); ?>" class="text-muted">📄 Download Invoice</a>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <div class="backtotop-wrap cursor-pointer">
        <svg class="backtotop-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>

    <script src="../assets/js/vendor/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/vendor/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/plugins/swiper.min.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
        // Prevent back button after successful order
        window.history.pushState(null, "", window.location.href);
        window.onpopstate = function() {
            window.history.pushState(null, "", window.location.href);
        };
    </script>
</body>
</html>
