<?php
session_start();

$error_message = $_GET['error'] ?? 'Payment failed. Please try again.';
$order_number = $_GET['order'] ?? null;
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Payment Failed</title>
    <meta name="description" content="Payment failed">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="assets/images/logo/favicon.svg">
    <!-- CSS here -->
    <link rel="stylesheet" href="assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/vendor/animate.min.css">
    <link rel="stylesheet" href="assets/css/vendor/spacing.css">
    <link rel="stylesheet" href="assets/css/main.css">
    
    <style>
        .failure-icon {
            width: 100px;
            height: 100px;
            margin: 0 auto 30px;
            background: #dc3545;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: scaleIn 0.5s ease-out;
        }
        
        .failure-icon svg {
            width: 50px;
            height: 50px;
            stroke: white;
            stroke-width: 3;
            stroke-linecap: round;
            stroke-linejoin: round;
            fill: none;
        }
        
        @keyframes scaleIn {
            from {
                transform: scale(0);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        
        .failure-container {
            max-width: 600px;
            margin: 80px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .error-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
            margin: 30px 0;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }
        
        .help-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 25px;
            margin-top: 30px;
            text-align: left;
        }
        
        .help-item {
            display: flex;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .help-item:last-child {
            margin-bottom: 0;
        }
        
        .help-icon {
            width: 24px;
            height: 24px;
            background: #0066cc;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
            font-size: 14px;
        }
    </style>
</head>

<body>

    <?php include 'preload.php'; ?>
    <?php include 'header.php'; ?>
    <?php include 'offcanvas_area.php'; ?>

    <main class="ap-main-area">
        <section class="section-space">
            <div class="container">
                <div class="failure-container">
                    
                    <!-- Failure Icon -->
                    <div class="failure-icon">
                        <svg viewBox="0 0 52 52">
                            <line x1="16" y1="16" x2="36" y2="36"/>
                            <line x1="36" y1="16" x2="16" y2="36"/>
                        </svg>
                    </div>
                    
                    <!-- Failure Message -->
                    <div class="text-center">
                        <h2 class="mb-3 text-danger">Payment Failed</h2>
                        <p class="lead text-muted">We couldn't process your payment. Please try again.</p>
                    </div>

                    <!-- Error Details -->
                    <div class="error-box">
                        <strong>⚠️ Error:</strong>
                        <p class="mb-0 mt-2"><?php echo htmlspecialchars($error_message); ?></p>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <?php if ($order_number): ?>
                        <a href="payment_gateway.php?order_number=<?php echo urlencode($order_number); ?>" 
                           class="ap-btn btn-primary">
                            Try Again
                        </a>
                        <?php else: ?>
                        <a href="checkout.php" class="ap-btn btn-primary">Return to Checkout</a>
                        <?php endif; ?>
                        <a href="pricing.php" class="ap-btn btn-secondary">View Plans</a>
                    </div>

                    <!-- Help Section -->
                    <div class="help-section">
                        <h5 class="mb-3">Common Issues & Solutions</h5>
                        
                        <div class="help-item">
                            <div class="help-icon">1</div>
                            <div>
                                <strong>Insufficient Balance</strong>
                                <p class="mb-0 text-muted small">Check if you have sufficient balance in your account/wallet</p>
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <div class="help-icon">2</div>
                            <div>
                                <strong>Bank/Card Issues</strong>
                                <p class="mb-0 text-muted small">Your bank might have declined the transaction. Try another payment method</p>
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <div class="help-icon">3</div>
                            <div>
                                <strong>Network Issues</strong>
                                <p class="mb-0 text-muted small">Poor internet connection can cause payment failures. Ensure stable connection</p>
                            </div>
                        </div>
                        
                        <div class="help-item">
                            <div class="help-icon">4</div>
                            <div>
                                <strong>Daily Limits</strong>
                                <p class="mb-0 text-muted small">You might have exceeded your daily transaction limit</p>
                            </div>
                        </div>
                    </div>

                    <!-- Support Contact -->
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            Still having issues? 
                            <a href="contact.php" class="text-primary">Contact Support</a> or 
                            email us at <a href="mailto:support@yourcompany.com">support@yourcompany.com</a>
                        </p>
                    </div>

                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <!-- back to top -->
    <div class="backtotop-wrap cursor-pointer">
        <svg class="backtotop-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>

    <script src="assets/js/vendor/jquery-3.7.1.min.js"></script>
    <script src="assets/js/vendor/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

</body>
</html>