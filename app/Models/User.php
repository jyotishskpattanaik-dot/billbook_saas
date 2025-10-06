<?php
namespace App\Models;

use App\Core\Database;
use PDO;

class User {
    private $db;

    // Constructor - Get database connection using the singleton method
    public function __construct() {
        // Get PDO connection from Database singleton
        $this->db = Database::getConnection();
    }

    // Register a new user
    public function register($name, $email, $password, $module) {
        try {
            // Hash the password before saving
            $hashed = password_hash($password, PASSWORD_BCRYPT);
            
            // Prepare the SQL statement
            $stmt = $this->db->prepare(
                "INSERT INTO login_users (name, email, password, module) VALUES (?, ?, ?, ?)"
            );

            // Execute the query with provided parameters
            return $stmt->execute([$name, $email, $hashed, $module]);
        } catch (\PDOException $e) {
            // Error handling: Log the error and return false
            // (You can replace echo with logging to a file for production)
            echo "Database error: " . $e->getMessage();
            return false;
        }
    }

    // Method to check if the user already exists
    public function userExists($email) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM login_users WHERE email = ?");
            $stmt->execute([$email]);
            return $stmt->fetch(PDO::FETCH_ASSOC);  // Returns user data if exists, else false
        } catch (\PDOException $e) {
            echo "Database error: " . $e->getMessage();
            return false;
        }
    }
}
