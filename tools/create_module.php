<?php
//used to create new modules in the main_db.need to run in the browser with proper root.
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 🔑 Root (or admin) MySQL credentials
$rootUser = "root";
$rootPass = "";
$host     = "localhost";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $moduleName = trim($_POST['module_name']);
    $dbUser     = trim($_POST['db_user']);
    $dbPass     = trim($_POST['db_pass']);

    if (empty($moduleName) || empty($dbUser) || empty($dbPass)) {
        die("⚠️ All fields are required!");
    }

    $dbName = $moduleName . "_db";

    try {
        $pdo = new PDO("mysql:host=$host", $rootUser, $rootPass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1️⃣ Create Database
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        // 2️⃣ Create User
        $pdo->exec("CREATE USER IF NOT EXISTS '$dbUser'@'localhost' IDENTIFIED BY '$dbPass'");

        // 3️⃣ Grant privileges
        $pdo->exec("GRANT ALL PRIVILEGES ON `$dbName`.* TO '$dbUser'@'localhost'");
        $pdo->exec("FLUSH PRIVILEGES");

        // 4️⃣ Insert into main_db.modules
        $pdo->exec("USE main_db");
        $stmt = $pdo->prepare("
            INSERT INTO modules (module_name, db_name, db_user, db_pass, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$moduleName, $dbName, $dbUser, $dbPass]);

        echo "<p style='color:green;'>✅ Module <b>$moduleName</b> created successfully!</p>";

    } catch (PDOException $e) {
        die("❌ Error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create New Module</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow">
                <div class="card-header bg-primary text-white">
                    <h4>Create New Module</h4>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Module Name</label>
                            <input type="text" name="module_name" class="form-control" placeholder="e.g. pharma_retail" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database User</label>
                            <input type="text" name="db_user" class="form-control" placeholder="e.g. retail_user" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" name="db_pass" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-success w-100">Create Module</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>
