<?php
session_start();
require __DIR__ . '../vendor/autoload.php';
require __DIR__ . '../includes/public_db_helper.php';

$pdo = getMainPDO();

$order_id = $_GET['order_id'] ?? $_SESSION['order_id'] ?? null;

if (!$order_id) {
    die("Invalid order. Please start checkout again.");
}

// Fetch order details
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = :id");
$stmt->execute([':id' => $order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Determine if payment is required
$requires_payment = $order['plan_name'] !== 'free';
?>

<section class="ap-thank-you-area section-space">
    <div class="container">
        <div class="text-center mb-50">
            <h2 class="ap-section-title">Thank You for Your Order!</h2>
            <p>Your order has been successfully received.</p>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="ap-order-summary table-responsive">
                    <table class="table table-bordered w-100">
                        <tr>
                            <th>Order ID</th>
                            <td>#<?= htmlspecialchars($order['id']) ?></td>
                        </tr>
                        <tr>
                            <th>Plan Name</th>
                            <td><?= htmlspecialchars(ucfirst($order['plan_name'])) ?></td>
                        </tr>
                        <tr>
                            <th>Duration</th>
                            <td><?= htmlspecialchars($order['duration']) ?> Days</td>
                        </tr>
                        <tr>
                            <th>Amount</th>
                            <td>₹<?= number_format($order['amount'], 2) ?></td>
                        </tr>
                        <tr>
                            <th>Payment Status</th>
                            <td><?= ucfirst($order['status']) ?></td>
                        </tr>
                    </table>
                </div>

                <?php if ($requires_payment): ?>
                <div class="ap-order-payment mt-30 text-center">
                    <p>Please complete your payment to activate your plan.</p>
                    <a href="payment_gateway.php?order_id=<?= $order['id'] ?>" class="ap-btn btn-primary w-100">Proceed to Payment</a>
                </div>
                <?php else: ?>
                <div class="ap-order-success mt-30 text-center">
                    <p>Your Free Trial is now active. You can <a href="login.php">Login Here</a> to start using the app.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
