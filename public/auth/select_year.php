<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['company_id']) || !isset($_SESSION['available_years']) || !isset($_SESSION['module_db'])) {
    header("Location: ../login.php");
    exit;
}

$years = $_SESSION['available_years'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedYearId = $_POST['year_id'] ?? null;

    if ($selectedYearId) {
        $_SESSION['financial_year_id'] = $selectedYearId;
        unset($_SESSION['available_years']); // cleanup

        $module = strtolower($_SESSION['user_module']);
header("Location: /billbook.in/pharma_retail/$module/dashboard.php");
exit;

    } else {
        $error = "❌ Please select a financial year!";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Select Accounting Year</title>
</head>
<body>
    <h2>Select Accounting Year</h2>
    <?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>

    <form method="POST">
        <label for="year_id">Choose Year:</label><br>
        <select name="year_id" id="year_id" required>
            <option value="">-- Select --</option>
            <?php foreach ($years as $year): ?>
                <option value="<?= htmlspecialchars($year['id']) ?>">
                    <?= htmlspecialchars($year['year_start']) ?> to <?= htmlspecialchars($year['year_end']) ?>
                    (<?= $year['status'] ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <br><br>
        <button type="submit">Continue</button>
    </form>
</body>
</html>
