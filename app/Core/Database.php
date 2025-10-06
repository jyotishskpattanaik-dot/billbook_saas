<?php
namespace App\Core;

use PDO;
use PDOException;

class Database {
    private static $instance = null;
    private $conn;

    private $host = "localhost";
    private $db_name = "main_db";   // replace with your actual database name
    private $username = "root";     // change if needed
    private $password = "";         // change if needed

    // 🔒 Private constructor to prevent multiple instances
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die("❌ Database connection failed: " . $e->getMessage());
        }
    }

    // ✅ Singleton: return one shared instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // ✅ Get PDO connection
    public static function getConnection() {
        return self::getInstance()->conn;
    }
}
