<?php
// payment_gateway.php
require __DIR__ . '/../includes/public_db_helper.php';
require __DIR__ . '/../vendor/autoload.php';

$pdo = getPublicPDO();

// Get order details
$order_id = $_GET['order_id'] ?? null;
$order_number = $_GET['order_number'] ?? null;

if (!$order_id && !$order_number) {
    header("Location: checkout.php");
    exit;
}

// Fetch order from database
try {
    if ($order_id) {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :order_id");
        $stmt->execute([':order_id' => $order_id]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = :order_number");
        $stmt->execute([':order_number' => $order_number]);
    }
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        header("Location: checkout.php");
        exit;
    }
    
    // Redirect if already paid
    if ($order['payment_status'] === 'completed') {
        header("Location: success.php?order=" . $order['order_number']);
        exit;
    }
    
} catch (Exception $e) {
    error_log("Payment gateway error: " . $e->getMessage());
    die("Error loading payment page. Please contact support.");
}

// Payment Gateway Configuration
// Choose your gateway: 'razorpay', 'phonepe', 'payu', 'cashfree'
$gateway = 'razorpay'; // Change this based on your preference

// Razorpay Configuration (Get from Razorpay Dashboard)
$razorpay_key_id = "rzp_test_XXXXXXXXXXXXXXXX"; // Replace with your Key ID
$razorpay_key_secret = "XXXXXXXXXXXXXXXXXXXXXXXX"; // Replace with your Key Secret

// PhonePe Configuration
$phonepe_merchant_id = "MERCHANTUAT"; // Replace with your Merchant ID
$phonepe_salt_key = "099eb0cd-02cf-4e2a-8aca-3e6c6aff0399"; // Replace with your Salt Key
$phonepe_salt_index = "1"; // Replace with your Salt Index

// PayU Configuration
$payu_merchant_key = "YOUR_MERCHANT_KEY"; // Replace
$payu_merchant_salt = "YOUR_MERCHANT_SALT"; // Replace

// Prepare order data
$amount_in_paise = $order['amount'] * 100; // Convert to paise for Razorpay
$customer_name = $order['first_name'] . ' ' . $order['last_name'];
$customer_email = $order['email'];
$customer_phone = $order['phone'];
?>
<!doctype html>
<html class="no-js" lang="zxx">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="ie=edge">
    <title>Payment - <?php echo htmlspecialchars($order['order_number']); ?></title>
    <meta name="description" content="Complete your payment securely">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="../assets/images/logo/favicon.svg">
    <!-- CSS here -->
    <link rel="stylesheet" href="../assets/css/vendor/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/animate.min.css">
    <link rel="stylesheet" href="../assets/css/vendor/spacing.css">
    <link rel="stylesheet" href="../assets/css/main.css">
    
    <?php if ($gateway === 'razorpay'): ?>
    <!-- Razorpay Checkout Script -->
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <?php endif; ?>
    
    <style>
        .payment-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .payment-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .payment-header h2 {
            color: #1a1a1a;
            margin-bottom: 10px;
        }
        
        .order-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: #0066cc;
        }
        
        .payment-methods {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .payment-method-card {
            border: 2px solid #dee2e6;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .payment-method-card:hover {
            border-color: #0066cc;
            background: #f0f7ff;
        }
        
        .payment-method-card.active {
            border-color: #0066cc;
            background: #e6f2ff;
        }
        
        .payment-method-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .security-badge {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 20px;
        }
        
        .loader {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .loader.active {
            display: block;
        }
    </style>
</head>

<body>

    <?php include 'preload.php'; ?>
    <?php include 'header.php'; ?>

    <main class="ap-main-area">
        <section class="section-space">
            <div class="container">
                <div class="payment-container">
                    
                    <div class="payment-header">
                        <h2>Complete Your Payment</h2>
                        <p class="text-muted">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                    </div>

                    <!-- Order Summary -->
                    <div class="order-summary">
                        <h5 class="mb-3">Order Summary</h5>
                        <div class="summary-row">
                            <span><?php echo ucfirst($order['plan_name']); ?> Plan (<?php echo $order['duration']; ?> <?php echo ucfirst($order['billing_period']); ?>)</span>
                            <span>₹<?php echo number_format($order['amount'], 2); ?></span>
                        </div>
                        <?php if ($order['discount_percent'] > 0): ?>
                        <div class="summary-row">
                            <span>Discount (<?php echo $order['discount_percent']; ?>%)</span>
                            <span class="text-success">Applied</span>
                        </div>
                        <?php endif; ?>
                        <div class="summary-row">
                            <span>GST</span>
                            <span class="text-muted">(Included)</span>
                        </div>
                        <div class="summary-row">
                            <span>Total Amount</span>
                            <span>₹<?php echo number_format($order['amount'], 2); ?></span>
                        </div>
                    </div>

                    <!-- Payment Methods Selection -->
                    <h5 class="mb-3">Select Payment Method</h5>
                    <div class="payment-methods">
                        <div class="payment-method-card active" data-method="upi">
                            <div class="payment-method-icon">📱</div>
                            <div>UPI</div>
                        </div>
                        <div class="payment-method-card" data-method="card">
                            <div class="payment-method-icon">💳</div>
                            <div>Card</div>
                        </div>
                        <div class="payment-method-card" data-method="netbanking">
                            <div class="payment-method-icon">🏦</div>
                            <div>Net Banking</div>
                        </div>
                        <div class="payment-method-card" data-method="wallet">
                            <div class="payment-method-icon">👛</div>
                            <div>Wallet</div>
                        </div>
                    </div>

                    <!-- Pay Button -->
                    <button id="payButton" class="ap-btn btn-primary w-100" style="font-size: 1.1rem; padding: 15px;">
                        Pay ₹<?php echo number_format($order['amount'], 2); ?>
                    </button>

                    <!-- Loader -->
                    <div class="loader" id="loader">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Processing...</span>
                        </div>
                        <p class="mt-2">Processing your payment...</p>
                    </div>

                    <!-- Security Badge -->
                    <div class="security-badge">
                        🔒 Secured by SSL encryption | 100% Safe & Secure
                    </div>

                </div>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>

    <script src="../assets/js/vendor/jquery-3.7.1.min.js"></script>
    <script src="../assets/js/vendor/bootstrap.bundle.min.js"></script>

    <script>
    // Payment method selection
    let selectedMethod = 'upi';
    
    document.querySelectorAll('.payment-method-card').forEach(card => {
        card.addEventListener('click', function() {
            document.querySelectorAll('.payment-method-card').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            selectedMethod = this.dataset.method;
        });
    });

    <?php if ($gateway === 'razorpay'): ?>
    // Razorpay Integration
    document.getElementById('payButton').addEventListener('click', function() {
        var options = {
            "key": "<?php echo $razorpay_key_id; ?>",
            "amount": "<?php echo $amount_in_paise; ?>",
            "currency": "INR",
            "name": "Your Company Name",
            "description": "<?php echo ucfirst($order['plan_name']); ?> Plan Subscription",
            "order_id": "<?php echo $order['order_number']; ?>",
            "handler": function (response) {
                // Payment successful
                document.getElementById('loader').classList.add('active');
                
                // Send payment details to server
                fetch('payment_callback.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_order_id: response.razorpay_order_id,
                        razorpay_signature: response.razorpay_signature,
                        order_id: <?php echo $order['id']; ?>,
                        order_number: "<?php echo $order['order_number']; ?>",
                        amount: <?php echo $order['amount']; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = 'success.php?order=<?php echo $order['order_number']; ?>&type=paid';
                    } else {
                        alert('Payment verification failed. Please contact support.');
                        document.getElementById('loader').classList.remove('active');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Something went wrong. Please contact support.');
                    document.getElementById('loader').classList.remove('active');
                });
            },
            "prefill": {
                "name": "<?php echo $customer_name; ?>",
                "email": "<?php echo $customer_email; ?>",
                "contact": "<?php echo $customer_phone; ?>"
            },
            "theme": {
                "color": "#0066cc"
            },
            "method": {
                "upi": selectedMethod === 'upi',
                "card": selectedMethod === 'card',
                "netbanking": selectedMethod === 'netbanking',
                "wallet": selectedMethod === 'wallet'
            }
        };
        
        var rzp = new Razorpay(options);
        
        rzp.on('payment.failed', function (response){
            alert('Payment failed: ' + response.error.description);
        });
        
        rzp.open();
    });

    <?php elseif ($gateway === 'phonepe'): ?>
    // PhonePe Integration
    document.getElementById('payButton').addEventListener('click', function() {
        document.getElementById('loader').classList.add('active');
        
        // Create payment request
        fetch('phonepe_initiate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                order_id: <?php echo $order['id']; ?>,
                order_number: "<?php echo $order['order_number']; ?>",
                amount: <?php echo $order['amount']; ?>,
                customer_name: "<?php echo $customer_name; ?>",
                customer_email: "<?php echo $customer_email; ?>",
                customer_phone: "<?php echo $customer_phone; ?>",
                payment_method: selectedMethod
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.redirect_url) {
                window.location.href = data.redirect_url;
            } else {
                alert('Failed to initiate payment. Please try again.');
                document.getElementById('loader').classList.remove('active');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Something went wrong. Please try again.');
            document.getElementById('loader').classList.remove('active');
        });
    });

    <?php elseif ($gateway === 'payu'): ?>
    // PayU Integration
    document.getElementById('payButton').addEventListener('click', function() {
        document.getElementById('loader').classList.add('active');
        
        // Submit form to PayU
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'https://test.payu.in/_payment'; // Use 'https://secure.payu.in/_payment' for production
        
        var params = {
            key: "<?php echo $payu_merchant_key; ?>",
            txnid: "<?php echo $order['order_number']; ?>",
            amount: "<?php echo $order['amount']; ?>",
            productinfo: "<?php echo ucfirst($order['plan_name']); ?> Plan",
            firstname: "<?php echo $order['first_name']; ?>",
            email: "<?php echo $customer_email; ?>",
            phone: "<?php echo $customer_phone; ?>",
            surl: "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/payment_success.php'; ?>",
            furl: "<?php echo 'https://' . $_SERVER['HTTP_HOST'] . '/payment_failure.php'; ?>",
            hash: "" // Calculate hash on server side
        };
        
        // Calculate hash: sha512(key|txnid|amount|productinfo|firstname|email|||||||||salt)
        // This should be done on server side for security
        
        for (var key in params) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = params[key];
            form.appendChild(input);
        }
        
        document.body.appendChild(form);
        form.submit();
    });

    <?php else: ?>
    // Default/Demo Mode
    document.getElementById('payButton').addEventListener('click', function() {
        alert('Payment gateway not configured. Please set up Razorpay, PhonePe, or PayU in payment_gateway.php');
    });
    <?php endif; ?>
    </script>

</body>
</html>