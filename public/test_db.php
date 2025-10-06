<?php
require __DIR__ . '/../includes/public_db_helper.php';

try {
    $pdo = getPublicPDO();
    echo "Database connected!<br>";
    
    // Check if orders table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'orders'");
    echo $stmt->rowCount() > 0 ? "orders table exists<br>" : "orders table NOT found<br>";
    
    // Check orders table structure
    $stmt = $pdo->query("DESCRIBE orders");
    echo "<h3>Orders Table Structure:</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
    // Check users table structure
    $stmt = $pdo->query("DESCRIBE users");
    echo "<h3>Users Table Structure:</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";
    
    // Check subscription table structure
    $stmt = $pdo->query("DESCRIBE subscriptions");
    echo "<h3>Subscriptions Table Structure:</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";

    // Check login_users table structure
    $stmt = $pdo->query("DESCRIBE login_users");
    echo "<h3>Login_users Table Structure:</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";

    // Check user_modules table structure
    $stmt = $pdo->query("DESCRIBE user_modules");
    echo "<h3>User_modules Table Structure:</h3><pre>";
    print_r($stmt->fetchAll());
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}