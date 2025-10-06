<?php
session_start();

// ✅ Get parameters from URL
$plan = $_GET['plan'] ?? 'silver';
$module = $_GET['module'] ?? null;  // ADD THIS LINE
$period = $_GET['period'] ?? 'yearly';
$years = isset($_GET['years']) ? (int)$_GET['years'] : null;
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;
$discount = isset($_GET['discount']) ? (int)$_GET['discount'] : 0;

// After getting all parameters, validate module for paid plans
if ($plan !== 'free_trial' && empty($module)) {
    // Redirect back to pricing if module is missing
    header("Location: pricing.php?error=module_required");
    exit;
}
// Check if price is directly provided
if (isset($_GET['price'])) {
    $price = (int)$_GET['price'];
} else {
    // Price calculation based on plan and duration
    $pricing = [
        'bronze' => [
            'monthly' => 150,
            'yearly' => [1 => 1200, 2 => 2160, 3 => 3060]
        ],
        'silver' => [
            'monthly' => 250,
            'yearly' => [1 => 2500, 2 => 4500, 3 => 6375]
        ],
        'gold' => [
            'monthly' => 350,
            'yearly' => [1 => 3500, 2 => 6300, 3 => 8925]
        ]
    ];

    // Calculate price
    $price = 0;
    if ($period === 'monthly' && $month) {
        $price = $pricing[$plan]['monthly'] * $month;
    } elseif ($period === 'yearly' && $years) {
        $price = $pricing[$plan]['yearly'][$years] ?? 0;
    }
}

// Plan display names
$planNames = [
    'free_trial' => 'Free Trial',
    'bronze' => 'Bronze',
    'silver' => 'Silver',
    'gold' => 'Gold'
];

// ✅ Module display name (formatted nicely)
$moduleDisplay = $module ? ucfirst(str_replace('_', ' ', $module)) : 'Not Selected';

// Billing duration display
$durationDisplay = '';
if ($plan === 'free_trial') {
    $durationDisplay = '(14 Days Trial)';
} elseif ($period === 'monthly') {
    $durationDisplay = '(' . $month . ' Month' . ($month > 1 ? 's' : '') . ')';
} elseif ($period === 'yearly') {
    $durationDisplay = '(' . $years . ' Year' . ($years > 1 ? 's' : '') . ')';
} else {
    $durationDisplay = '';
}
$isFreeTrialPlan = ($plan === 'free_trial' || $price === 0);
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Checkout - <?php echo $planNames[$plan] ?? 'Plan'; ?></title>
    <meta name="description" content="Complete your subscription purchase securely.">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- Place favicon.ico in the root directory -->
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/logo/favicon.svg">
    <!-- CSS here -->
    <link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/animate.min.css">
    <link rel="stylesheet" href="../assets/css/plugins/swiper.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/magnific-popup.css">
    <link rel="stylesheet" href="../assets/css/vendor/icomoon.css">
    <link rel="stylesheet" href="../assets/css/vendor/spacing.css">
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>

    <?php include 'preload.php'; ?>
    <?php include 'header.php'; ?>
    <?php include 'offcanvas_area.php'; ?>

    <!-- Body main wrapper start -->
    <main class="ap-main-area">

        <!-- checkout area start -->
        <section class="ap-checkout-area section-space">
            <div class="container">
                
                <?php
                // Display errors if any
                if (isset($_SESSION['checkout_errors']) && !empty($_SESSION['checkout_errors'])) {
                    echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
                    echo '<strong>Please fix the following errors:</strong><ul class="mb-0 mt-2">';
                    foreach ($_SESSION['checkout_errors'] as $error) {
                        echo '<li>' . htmlspecialchars($error) . '</li>';
                    }
                    echo '</ul>';
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
                    echo '</div>';
                    unset($_SESSION['checkout_errors']);
                }
                ?>
                
                <div class="row gy-30">
                    <!-- Billing Details -->
                    <div class="col-lg-6">
                        <div class="ap-checkout-bill-area">
                            <h3 class="mb-25">Billing Details</h3>
                            <div class="ap-checkout-bill-form">
                                <!-- TEMPORARY: Change action to debug_process_checkout.php to see what's happening -->
                                <!-- After debugging, change back to process_checkout.php -->
                                <form action="process_checkout.php" method="POST" id="checkoutForm">
                                    <!-- Hidden fields for plan details -->
                                    <input type="hidden" name="plan" value="<?php echo htmlspecialchars($plan); ?>">
                                    <input type="hidden" name="module" value="<?php echo htmlspecialchars($module); ?>">  <!-- ADD THIS -->
                                    <input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>">
                                    <input type="hidden" name="duration" value="<?php echo htmlspecialchars($years ?? $month ?? 1); ?>">
                                    <input type="hidden" name="price" value="<?php echo $price; ?>">
                                    <input type="hidden" name="discount" value="<?php echo $discount; ?>">
                                    
                                    <div class="ap-checkout-bill-inner">
                                        <div class="row gy-30">
                                            <div class="col-md-6">
                                                <div class="ap-checkout-input">
                                                    <label>First Name <span class="required">*</span></label>
                                                    <input type="text" name="first_name" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="ap-checkout-input">
                                                    <label>Last Name <span class="required">*</span></label>
                                                    <input type="text" name="last_name" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="ap-checkout-input">
                                                    <label>Email Address <span class="required">*</span></label>
                                                    <input type="email" name="email" class="form-control" required>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="ap-checkout-input">
                                                    <label>Phone <span class="required">*</span></label>
                                                    <input type="tel" name="phone" class="form-control" pattern="[0-9]{10}" maxlength="10" required>
                                                    <small class="text-muted">Enter 10-digit mobile number</small>
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="ap-checkout-input">
                                                    <label>Company Name (Optional)</label>
                                                    <input type="text" name="company" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-12">
                                                <div class="ap-checkout-input">
                                                    <label>GSTIN (Optional)</label>
                                                    <input type="text" name="gstin" class="form-control" placeholder="For tax invoice" pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}">
                                                    <small class="text-muted">Format: 22AAAAA0000A1Z5</small>
                                                </div>
                                            </div>
                                            
                                            <?php if (!$isFreeTrialPlan): ?>
                                            <div class="col-md-12">
                                                <div class="ap-checkout-input">
                                                    <label>
                                                        <input type="checkbox" name="terms" id="terms" required>
                                                        I agree to the <a href="terms.php" target="_blank">Terms & Conditions</a> <span class="required">*</span>
                                                    </label>
                                                </div>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Order Summary & Payment -->
                    <div class="col-lg-6">
                        <div class="ap-order-wrapper">
                            <h3 class="mb-25">Your Order</h3>
                            <div class="ap-order-table table-responsive mb-40">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th class="product-name">Plan Details</th>
                                            <th class="product-total text-end">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                       <tr>
                                    <td><strong><?php echo $planNames[$plan] ?? ucfirst($plan); ?></strong> <?php echo $durationDisplay; ?><br>
                                    <small>Module: <?php echo htmlspecialchars($moduleDisplay); ?></small></td>
                                    <td class="text-end"><span class="amount">₹<?php echo number_format($price); ?></span></td>
                                </tr>
                                       
                                        <?php if ($discount > 0): ?>
                                        <tr>
                                            <td class="product-name">Discount Applied (<?php echo $discount; ?>%)</td>
                                            <td class="product-total text-end text-success">
                                                <span class="amount">Applied</span>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                        
                                        <?php if (!$isFreeTrialPlan): ?>
                                        <tr>
                                            <td class="product-name">GST</td>
                                            <td class="product-total text-end">
                                                <small class="text-muted">(Included)</small>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr class="order-total">
                                            <th>Order Total <?php echo !$isFreeTrialPlan ? '(incl. GST)' : ''; ?></th>
                                            <td class="text-end">
                                                <strong>
                                                    <span class="amount" style="font-size: 1.5rem; color: #0066cc;">
                                                        ₹<?php echo number_format($price); ?>
                                                    </span>
                                                </strong>
                                            </td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <?php if ($isFreeTrialPlan): ?>
                                <!-- Free Trial - No Payment Required -->
                                <div class="alert alert-success mb-4">
                                    <h5>🎉 Free Trial - No Payment Required</h5>
                                    <p class="mb-0">Start your 14-day free trial now. No credit card needed!</p>
                                </div>
                                
                                <div class="ap-order-button-payment mt-3">
                                    <button type="submit" class="ap-btn btn-primary w-100" style="background: #28a745; border-color: #28a745;">
                                        Start Free Trial Now
                                    </button>
                                </div>
                            <?php else: ?>
                                <!-- Payment Methods -->
                                <h3 class="mb-20">Select Payment Method</h3>
                                <div class="ap-section-faq">
                                    <div class="accordion" id="paymentAccordion">
                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="upiOption">
                                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#upi" aria-expanded="true" aria-controls="upi">
                                                    <input type="radio" name="payment_method" value="upi" class="me-2" required checked> UPI / QR Code
                                                </button>
                                            </h2>
                                            <div id="upi" class="accordion-collapse collapse show" aria-labelledby="upiOption" data-bs-parent="#paymentAccordion">
                                                <div class="accordion-body">
                                                    <p>Pay instantly using UPI apps like PhonePe, Google Pay, Paytm, etc.</p>
                                                    <small class="text-muted">✓ Instant confirmation</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="cardOption">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#card" aria-expanded="false" aria-controls="card">
                                                    <input type="radio" name="payment_method" value="card" class="me-2"> Credit / Debit Card
                                                </button>
                                            </h2>
                                            <div id="card" class="accordion-collapse collapse" aria-labelledby="cardOption" data-bs-parent="#paymentAccordion">
                                                <div class="accordion-body">
                                                    <p>Pay securely using Visa, MasterCard, RuPay, or Amex cards.</p>
                                                    <small class="text-muted">✓ 100% secure payment</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="netbankingOption">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#netbanking" aria-expanded="false" aria-controls="netbanking">
                                                    <input type="radio" name="payment_method" value="netbanking" class="me-2"> Net Banking
                                                </button>
                                            </h2>
                                            <div id="netbanking" class="accordion-collapse collapse" aria-labelledby="netbankingOption" data-bs-parent="#paymentAccordion">
                                                <div class="accordion-body">
                                                    <p>Pay directly from your bank account using Net Banking.</p>
                                                    <small class="text-muted">✓ Supports all major banks</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="accordion-item">
                                            <h2 class="accordion-header" id="walletOption">
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#wallet" aria-expanded="false" aria-controls="wallet">
                                                    <input type="radio" name="payment_method" value="wallet" class="me-2"> Wallets
                                                </button>
                                            </h2>
                                            <div id="wallet" class="accordion-collapse collapse" aria-labelledby="walletOption" data-bs-parent="#paymentAccordion">
                                                <div class="accordion-body">
                                                    <p>Pay via Paytm Wallet, Amazon Pay, or other supported wallets.</p>
                                                    <small class="text-muted">✓ Quick & convenient</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Security Badge -->
                                <div class="text-center mt-3 mb-3">
                                    <small class="text-muted">
                                        🔒 Your payment information is encrypted and secure
                                    </small>
                                </div>

                                <!-- Place Order -->
                                <div class="ap-order-button-payment mt-3">
                                    <button type="submit" class="ap-btn btn-primary w-100">
                                        Proceed to Payment - ₹<?php echo number_format($price); ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- checkout area end -->

    </main>
    <!-- Body main wrapper end -->
     
    <?php include 'footer.php'; ?>

    <!-- back to top -->
    <div class="backtotop-wrap cursor-pointer">
        <svg class="backtotop-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
            <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98" />
        </svg>
    </div>
    <!-- Backtotop end -->

    <!-- JS here -->
    <script src="../assets/js/vendor/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/vendor/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/plugins/rangeslider.min.js"></script>
    <script src="../assets/js/vendor/magnific-popup.min.js"></script>
    <script src="../assets/js/vendor/isotope.pkgd.min.js"></script>
    <script src="../assets/js/vendor/imagesloaded.pkgd.min.js"></script>
    <script src="../assets/js/vendor/ajax-form.js"></script>
    <script src="../assets/js/vendor/purecounter.js"></script>
    <script src="../assets/js/plugins/waypoints.min.js"></script>
    <script src="../assets/js/plugins/swiper.min.js"></script>
    <script src="../assets/js/plugins/wow.js"></script>
    <script src="../assets/js/plugins/nice-select.min.js"></script>
    <script src="../assets/js/plugins/easypie.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
    // Form validation and payment method selection
    document.getElementById('checkoutForm').addEventListener('submit', function(e) {
        const isFreeTrialPlan = <?php echo $isFreeTrialPlan ? 'true' : 'false'; ?>;
        
        if (!isFreeTrialPlan) {
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!paymentMethod) {
                e.preventDefault();
                alert('Please select a payment method');
                return false;
            }
        }
        
        // Show loading state
        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Processing...';
    });

    // Auto-select payment method when accordion is opened
    document.querySelectorAll('.accordion-button').forEach(button => {
        button.addEventListener('click', function() {
            const radio = this.querySelector('input[type="radio"]');
            if (radio) {
                radio.checked = true;
            }
        });
    });

    // Phone number validation
    const phoneInput = document.querySelector('input[name="phone"]');
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }
    </script>

</body>

</html>