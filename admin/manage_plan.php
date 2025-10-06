<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../includes/init.php';

// ✅ Only admin can access
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    die("❌ Access denied.");
}

$pdo = getMainPDO();
$companyId = $_SESSION['company_id'] ?? null;

if (!$companyId) {
    die("⚠️ No company found in session.");
}

// --- Fetch company subscription ---
$stmt = $pdo->prepare("SELECT * FROM company_subscription WHERE company_id = ?");
$stmt->execute([$companyId]);
$subscription = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Available plans (hardcoded for now, can move to DB) ---
$availablePlans = [
    'basic'   => ['name' => 'Basic',   'price' => 999,  'max_seats' => 5],
    'standard'=> ['name' => 'Standard','price' => 2499, 'max_seats' => 20],
    'premium' => ['name' => 'Premium', 'price' => 4999, 'max_seats' => 100],
];

$errors = [];
$success = null;

// --- Handle form submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newTier  = $_POST['tier'] ?? $subscription['tier'];
    $newSeats = (int) ($_POST['seats'] ?? $subscription['seats']);

    if (!isset($availablePlans[$newTier])) {
        $errors[] = "Invalid plan selected.";
    } elseif ($newSeats < 1 || $newSeats > $availablePlans[$newTier]['max_seats']) {
        $errors[] = "Seats must be between 1 and " . $availablePlans[$newTier]['max_seats'];
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE company_subscription SET tier = ?, seats = ?, updated_at = NOW() WHERE company_id = ?");
            $stmt->execute([$newTier, $newSeats, $companyId]);
            $success = "✅ Subscription updated successfully.";
            $subscription['tier']  = $newTier;
            $subscription['seats'] = $newSeats;
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Plan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4">
    <h2><i class="fas fa-gem"></i> Manage Subscription Plan</h2>
    <a href="control_panel.php" class="btn btn-secondary mb-3"><i class="fas fa-arrow-left"></i> Back</a>

    <?php if ($errors): ?>
        <div class="alert alert-danger"><?= implode("<br>", $errors) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card p-4 shadow-sm bg-white">
        <h5>Current Subscription</h5>
        <?php if ($subscription): ?>
            <p><strong>Tier:</strong> <?= htmlspecialchars($subscription['tier']) ?></p>
            <p><strong>Seats:</strong> <?= htmlspecialchars($subscription['seats']) ?></p>
            <p><strong>Start Date:</strong> <?= htmlspecialchars($subscription['start_date'] ?? '-') ?></p>
            <p><strong>End Date:</strong> <?= htmlspecialchars($subscription['end_date'] ?? '-') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($subscription['status'] ?? '-') ?></p>
        <?php else: ?>
            <p>No active subscription found.</p>
        <?php endif; ?>
    </div>

    <form method="POST" class="card p-4 shadow-sm bg-white mt-4">
        <h5>Update Subscription</h5>

        <div class="mb-3">
            <label class="form-label">Select Plan</label>
            <select name="tier" class="form-select">
                <?php foreach ($availablePlans as $key => $plan): ?>
                    <option value="<?= $key ?>" <?= ($subscription['tier'] ?? '') === $key ? 'selected' : '' ?>>
                        <?= $plan['name'] ?> (₹<?= number_format($plan['price']) ?>/year, up to <?= $plan['max_seats'] ?> seats)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label">Number of Seats</label>
            <input type="number" name="seats" class="form-control" value="<?= htmlspecialchars($subscription['seats'] ?? 1) ?>" min="1">
        </div>

        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Plan</button>
        <a href="payments.php" class="btn btn-success"><i class="fas fa-credit-card"></i> Make Payment / Renew</a>
    </form>
</div>
</body>
</html>
