<?php
namespace App\Core;

use PDO;
use PDOException;

class ModuleDatabase {
    public static function getConnection() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['module_db'])) {
            throw new \Exception("❌ Module DB credentials not found in session");
        }

        $creds = $_SESSION['module_db'];
        $dsn   = "mysql:host={$creds['host']};dbname={$creds['name']};charset=utf8mb4";

        try {
            $pdo = new PDO($dsn, $creds['user'], $creds['pass']);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            return $pdo;
        } catch (PDOException $e) {
            die("❌ Module DB connection failed: " . $e->getMessage());
        }
    }
}
