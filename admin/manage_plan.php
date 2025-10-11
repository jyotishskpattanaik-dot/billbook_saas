<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied.");
}

$pdo = getMainPDO();
$companyId = $_SESSION['company_id'] ?? null;
if (!$companyId) die("⚠️ No company found in session.");

// Fetch current subscription with module name
$stmt = $pdo->prepare("
    SELECT s.*, m.module_name 
    FROM subscriptions s
    LEFT JOIN modules m ON s.module_id = m.id
    WHERE s.company_id = ?
");
$stmt->execute([$companyId]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// Pricing structure
$plans = [
    'bronze' => [
        'users' => 1,
        'color' => '#cd7f32',
        'monthly' => 150,
        'yearly' => [1 => 1200, 2 => 2160, 3 => 3060]
    ],
    'silver' => [
        'users' => 3,
        'color' => '#C0C0C0',
        'monthly' => 250,
        'yearly' => [1 => 2500, 2 => 4500, 3 => 6375]
    ],
    'gold' => [
        'users' => 5,
        'color' => '#FFD700',
        'monthly' => 350,
        'yearly' => [1 => 3500, 2 => 6300, 3 => 8925]
    ]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $plan = $_POST['plan'] ?? null;
    $durationType = $_POST['duration_type'] ?? null;
    $duration = (int)($_POST['duration'] ?? 0);

    if (!isset($plans[$plan])) die("Invalid plan");

    $price = $durationType === 'monthly'
        ? $plans[$plan]['monthly']
        : $plans[$plan]['yearly'][$duration];

    $_SESSION['pending_payment'] = [
        'company_id' => $companyId,
        'plan' => $plan,
        'users' => $plans[$plan]['users'],
        'duration_type' => $durationType,
        'duration' => $duration,
        'price' => $price
    ];

    header("Location: payments.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Subscription Plan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        .plan-card {
            transition: all 0.3s ease;
            border-radius: 12px;
            cursor: pointer;
        }
        .plan-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .plan-card.active {
            border: 2px solid #0d6efd;
            background-color: #f8f9fa;
        }
        .plan-header {
            color: #fff;
            font-weight: bold;
            padding: 12px;
            border-radius: 10px 10px 0 0;
        }
        .price-btn {
            margin: 3px;
        }
        #summaryText {
            font-size: 1.1rem;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h2><i class="fas fa-gem"></i> Manage Subscription Plan</h2>
    <p class="text-muted">
    <?= $subscription ? "You can renew or upgrade your current plan." : "Select a plan to start your subscription." ?>
</p>

    <a href="control_panel.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back</a>

    <div class="card p-4 shadow-sm mb-4">
        <h5>Current Subscription</h5>
        <?php if ($subscription): ?>
            <p><strong>Module:</strong> <?= htmlspecialchars($subscription['module_name'] ?? '-') ?></p>
            <p><strong>Plan:</strong> <?= ucfirst(htmlspecialchars($subscription['plan_name'] ?? '-')) ?></p>
            <p><strong>Allowed Users:</strong> <?= htmlspecialchars($subscription['user_limit'] ?? '-') ?></p>
            <p><strong>Start Date:</strong> <?= htmlspecialchars($subscription['start_date'] ?? '-') ?></p>
            <p><strong>End Date:</strong> <?= htmlspecialchars($subscription['expiry_date'] ?? '-') ?></p>
        <?php else: ?>
            <p>No active subscription found.</p>
        <?php endif; ?>
    </div>

    <form method="POST" id="subscriptionForm">
        <input type="hidden" name="plan" id="selectedPlan">
        <input type="hidden" name="duration_type" id="durationType">
        <input type="hidden" name="duration" id="duration">

        <div class="row g-4">
            <?php foreach ($plans as $key => $p): ?>
            <div class="col-md-4">
                <div class="card plan-card text-center" data-plan="<?= $key ?>">
                    <div class="plan-header" style="background-color: <?= $p['color'] ?>;">
                        <h4 class="mb-0 text-uppercase"><?= $key ?> PLAN</h4>
                    </div>
                    <div class="card-body">
                        <p class="mt-2"><strong><?= $p['users'] ?></strong> User<?= $p['users'] > 1 ? 's' : '' ?> Allowed</p>
                        <hr>
                        <h6>Monthly</h6>
                        <button type="button"
                                class="btn btn-outline-primary btn-sm price-btn"
                                data-type="monthly"
                                data-duration="1"
                                data-plan="<?= $key ?>"
                                data-price="<?= $p['monthly'] ?>">
                            ₹<?= $p['monthly'] ?> / month
                        </button>
                        <hr>
                        <h6>Yearly</h6>
                        <?php foreach ($p['yearly'] as $yrs => $price): ?>
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm price-btn"
                                    data-type="yearly"
                                    data-duration="<?= $yrs ?>"
                                    data-plan="<?= $key ?>"
                                    data-price="<?= $price ?>">
                                <?= $yrs ?> Year<?= $yrs>1?'s':'' ?> @ ₹<?= $price ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="text-center mt-4">
            <h5 id="summaryText" class="text-primary"></h5>
            <button type="submit" class="btn btn-success mt-3" id="proceedBtn" style="display:none;">
                <i class="fas fa-credit-card"></i> Proceed to Payment
            </button>
        </div>
    </form>
</div>

<script>
// --- Replace the old JS section with this ---
const planCards = document.querySelectorAll('.plan-card');
const summary = document.getElementById('summaryText');
const proceedBtn = document.getElementById('proceedBtn');

let selectedPlan = null;

planCards.forEach(card => {
    const plan = card.dataset.plan;

    // When you click anywhere on the card, mark it as selected
    card.addEventListener('click', () => {
        planCards.forEach(c => c.classList.remove('active'));
        card.classList.add('active');
        selectedPlan = plan;
    });

    // Handle button click inside card (monthly/yearly)
    card.querySelectorAll('.price-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation(); // prevent card click re-trigger
            const type = this.dataset.type;
            const duration = this.dataset.duration;
            const price = this.dataset.price;

            document.getElementById('selectedPlan').value = selectedPlan || plan;
            document.getElementById('durationType').value = type;
            document.getElementById('duration').value = duration;

            summary.innerHTML = `You selected <strong>${(selectedPlan || plan).toUpperCase()}</strong> (${type === 'monthly' ? 'Monthly' : duration + ' Year' + (duration>1?'s':'')}) — ₹${price}`;
            proceedBtn.style.display = 'inline-block';
        });
    });
});

</script>
</body>
</html>
