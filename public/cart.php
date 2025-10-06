<?php
require __DIR__ . '/../../includes/public_db_helper.php';
require __DIR__ . '/../../vendor/autoload.php';

$pdo = getPublicPDO();

// 🚀 Skip cart for free trial
$module = $_GET['module'] ?? null;
if (isset($_GET['plan']) && $_GET['plan'] === 'free_trial' && $module) {
    header("Location: checkout.php?plan=free_trial&price=0&module=" . urlencode($module));
    exit;
}

// ✅ Read parameters from query string
$plan = $_GET['plan'] ?? 'silver';
$module = $_GET['module'] ?? null;
$period = $_GET['period'] ?? 'yearly';
$years = isset($_GET['years']) ? (int)$_GET['years'] : null;
$month = isset($_GET['month']) ? (int)$_GET['month'] : null;

// Check if price is directly provided (for free trial or direct links)
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

// Mapping for display
$usersMap = [
    'free_trial' => 'Full Access',
    'bronze' => '1 User',
    'silver' => '3 Users',
    'gold' => '5 Users'
];

$accessMap = [
    'free_trial' => 'All Features (Trial)',
    'bronze' => 'Limited Access',
    'silver' => 'Medium Access',
    'gold' => 'Maximum Access'
];

// Billing label
$billingLabel = '';
if ($plan === 'free_trial') {
    $billingLabel = '14 Days Trial';
} elseif ($period === 'monthly') {
    $billingLabel = $month . ' Month' . ($month > 1 ? 's' : '');
} elseif ($period === 'yearly') {
    $billingLabel = $years . ' Year' . ($years > 1 ? 's' : '');
}

// Calculate savings percentage
$savingsPercent = 0;
if ($plan !== 'free_trial' && $period === 'yearly' && $years > 1) {
    $savingsPercent = ($years === 2) ? 10 : 15;
}
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Cart - Your Subscription Plan</title>
    <meta name="description" content="Review your subscription plan and proceed to checkout.">
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
    <main>

        <!-- cart area start -->
        <section class="ap-cart-area section-space">
            <div class="container">
                <div class="row gy-30">
                    <div class="col-xl-9">
                        <div class="ap-cart-content">
                            <div class="ap-cart-table table-responsive text-center">
                                <table class="table table-bordered w-100">
                                    <thead>
                                        <tr>
                                            <th>Plan</th>
                                            <th>Module</th>
                                            <th>Users</th>
                                            <th>Access Level</th>
                                            <th>Billing Cycle</th>
                                            <th>Price</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong><?php echo ucfirst($plan); ?> Plan</strong></td>
                                            <td><strong><?php echo ucfirst($module); ?> Module</strong></td>
                                            <td><?php echo $usersMap[$plan] ?? '-'; ?></td>
                                            <td><?php echo $accessMap[$plan] ?? '-'; ?></td>
                                            <td>
                                                <?php echo $billingLabel; ?>
                                                <?php if ($savingsPercent > 0): ?>
                                                    <span class="badge bg-success ms-2">Save <?php echo $savingsPercent; ?>%</span>
                                                <?php endif; ?>
                                            </td>
                                            <td id="plan-price"><strong>₹<?php echo number_format($price); ?></strong></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Referral Code -->
                            <div class="ap-cart-bottom d-flex justify-content-between align-items-center mt-4">
                                <?php if ($plan !== 'free_trial'): ?>
                                <div class="ap-cart-coupon">
                                    <div class="ap-cart-coupon-input d-flex gap-2">
                                        <input type="text" id="referral-code" class="form-control" placeholder="Enter Referral Code" style="max-width: 250px;">
                                        <button type="button" id="apply-referral" class="ap-btn btn-primary">Apply Code</button>
                                    </div>
                                    <small id="referral-message" class="text-muted mt-2 d-block"></small>
                                </div>
                                <div>
                                    <button class="ap-btn btn-secondary" id="update-cart">Update Cart</button>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success w-100 mb-0">
                                    <strong>🎉 Free Trial!</strong> No payment required. Start your 14-day trial now.
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Cart Totals -->
                    <div class="col-xl-3">
                        <h3 class="mb-4">Cart Totals</h3>
                        <div class="mb-4">
                            <table class="table table-bordered w-100">
                                <tbody>
                                    <tr>
                                        <td>Subtotal</td>
                                        <td id="subtotal"><strong>₹<?php echo number_format($price); ?></strong></td>
                                    </tr>
                                    <?php if ($plan !== 'free_trial'): ?>
                                    <tr>
                                        <td>Discount</td>
                                        <td id="discount" class="text-success">₹0</td>
                                    </tr>
                                    <tr>
                                        <td>GST</td>
                                        <td class="text-muted"><small>(Included)</small></td>
                                    </tr>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-success">
                                            <small><strong>No payment required</strong></small>
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                    <tr class="table-active">
                                        <td class="fw-bold">Total</td>
                                        <td class="fw-bold" id="total">₹<?php echo number_format($price); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div>
                            <a href="checkout.php?plan=<?php echo $plan; ?>&period=<?php echo $period; ?>&price=<?php echo $price; ?>" 
                               id="checkout-link" 
                               class="ap-btn btn-primary w-100 text-center">
                                <?php echo ($plan === 'free_trial') ? 'Start Free Trial' : 'Proceed To Checkout'; ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <!-- cart area end -->

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
    // ✅ Initialize JS variables with PHP values
    let selectedPlan = "<?php echo $plan; ?>";
    let selectedModule = "<?php echo $module; ?>";
    let selectedPeriod = "<?php echo $period; ?>";
    let basePrice = <?php echo (int)$price; ?>;
    let discountPercent = 0;
    let discountAmount = 0;
    let isTrial = (selectedPlan === 'free_trial');

    // Referral codes with discount %
    const referralDiscounts = {
        "WELCOME10": 10,
        "SAVE20": 20,
        "FIRST15": 15,
        "SPECIAL25": 25
    };

   function updatePrices() {
    if (isTrial) {
        return;
    }

    let discountedPrice = basePrice;

    if (discountPercent > 0) {
        discountAmount = Math.round(basePrice * discountPercent / 100);
        discountedPrice = basePrice - discountAmount;
    } else {
        discountAmount = 0;
    }

    document.getElementById("subtotal").innerHTML = "<strong>₹" + basePrice.toLocaleString() + "</strong>";
    document.getElementById("discount").innerHTML = discountAmount > 0 
        ? "<strong class='text-success'>-₹" + discountAmount.toLocaleString() + "</strong>" 
        : "₹0";
    document.getElementById("total").innerHTML = "₹" + discountedPrice.toLocaleString();

    const checkoutLink = document.getElementById("checkout-link");
    checkoutLink.href = `checkout.php?plan=${selectedPlan}&module=${selectedModule}&period=${selectedPeriod}&price=${discountedPrice}&discount=${discountPercent}`;
}
    // Only initialize referral functionality if not a trial
    if (!isTrial) {
        // Apply referral code
        document.getElementById("apply-referral").addEventListener("click", function() {
            const codeInput = document.getElementById("referral-code");
            const code = codeInput.value.trim().toUpperCase();
            const messageEl = document.getElementById("referral-message");

            if (referralDiscounts[code]) {
                discountPercent = referralDiscounts[code];
                messageEl.innerHTML = `<span class="text-success">✓ Referral applied! ${discountPercent}% discount</span>`;
                messageEl.classList.remove("text-danger");
                messageEl.classList.add("text-success");
            } else if (code === "") {
                messageEl.innerHTML = `<span class="text-danger">Please enter a referral code</span>`;
                messageEl.classList.remove("text-success");
                messageEl.classList.add("text-danger");
                discountPercent = 0;
            } else {
                messageEl.innerHTML = `<span class="text-danger">✗ Invalid referral code</span>`;
                messageEl.classList.remove("text-success");
                messageEl.classList.add("text-danger");
                discountPercent = 0;
            }
            updatePrices();
        });

        // Update button (manual refresh)
        document.getElementById("update-cart").addEventListener("click", function() {
            updatePrices();
            const messageEl = document.getElementById("referral-message");
            if (discountPercent > 0) {
                messageEl.innerHTML = `<span class="text-success">Cart updated with ${discountPercent}% discount applied</span>`;
            } else {
                messageEl.innerHTML = `<span class="text-info">Cart updated</span>`;
            }
        });

        // Allow Enter key to apply referral
        document.getElementById("referral-code").addEventListener("keypress", function(e) {
            if (e.key === "Enter") {
                document.getElementById("apply-referral").click();
            }
        });

        // Initialize default
        updatePrices();
    }
    </script>

</body>

</html>