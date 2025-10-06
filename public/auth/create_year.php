<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['company_id']) || !isset($_SESSION['module_db'])) {
    header("Location: /billbook.in/login.php");
    exit;
}

try {
    $db = $_SESSION['module_db'];
    $pdo = new PDO(
        "mysql:host={$db['host']};dbname={$db['name']}",
        $db['user'],
        $db['pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- Build predefined financial year list ---
$financialYears = [
    "2024-2025" => ["2024-04-01", "2025-03-31"],
    "2025-2026" => ["2025-04-01", "2026-03-31"],
    "2026-2027" => ["2026-04-01", "2027-03-31"],
    "2027-2028" => ["2027-04-01", "2028-03-31"]
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $yearKey = $_POST['financial_year'] ?? '';

    if (!empty($yearKey) && isset($financialYears[$yearKey])) {
        [$yearStart, $yearEnd] = $financialYears[$yearKey];

        // Prevent duplicates
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM accounting_years 
                               WHERE company_id = ? AND year_start = ? AND year_end = ?");
        $stmt->execute([$_SESSION['company_id'], $yearStart, $yearEnd]);
        $exists = $stmt->fetchColumn();

        if ($exists > 0) {
            $error = "⚠️ Financial Year $yearKey already exists for this company!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO accounting_years (company_id, year_start, year_end, status) 
                                   VALUES (?, ?, ?, 'ACTIVE')");
            $stmt->execute([$_SESSION['company_id'], $yearStart, $yearEnd]);

            $_SESSION['financial_year_id'] = $pdo->lastInsertId();

            $module = strtolower($_SESSION['user_module']);
            header("Location: /billbook.in/$module/dashboard.php"); // ✅ no /public/
            exit;
        }
    } else {
        $error = "❌ Please select a financial year!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Accounting Year</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow-lg border-0 rounded-3">
                <div class="card-header bg-primary text-white text-center">
                    <h4 class="mb-0">Setup Accounting Year</h4>
                </div>
                <div class="card-body p-4">
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label for="financial_year" class="form-label">Select Financial Year</label>
                            <select id="financial_year" name="financial_year" class="form-select" required>
                                <option value="">-- Select Year --</option>
                                <?php foreach ($financialYears as $label => [$start, $end]): ?>
                                    <option value="<?= htmlspecialchars($label) ?>">
                                        <?= $label ?> (<?= $start ?> to <?= $end ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-success btn-lg">Save & Continue</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer text-muted text-center small">
                    © <?= date('Y') ?> Your Company
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

