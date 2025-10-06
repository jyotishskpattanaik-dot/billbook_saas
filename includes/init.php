<?php
// includes/init.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../vendor/autoload.php';

use App\Core\ModuleDatabase;

/**
 * Ensure company and financial year are set in the session.
 */
function requireSessionContext() {
    if (!isset($_SESSION['company_id']) || !isset($_SESSION['financial_year_id'])) {
        die("❌ Company or Financial Year not selected. Please log in again.");
    }
}

/**
 * Returns a PDO connection for the current logged-in module.
 */
function getModulePDO(): PDO {
    try {
        return ModuleDatabase::getConnection();
    } catch (Exception $e) {
        die("❌ DB Connection failed: " . $e->getMessage());
    }
}

/**
 * Logged-in user name (for inserts/updates/audit trails).
 */
function currentUser(): string {
    return $_SESSION['user_name'] ?? 'SYSTEM';
}

// --- Expose context variables globally ---
requireSessionContext();

$COMPANY_ID   = $_SESSION['company_id'];
$YEAR_ID      = $_SESSION['financial_year_id'];
$CURRENT_USER = currentUser();

/**
 * SELECT - Get list of rows with company/year filter
 */
function getFilteredData(PDO $pdo, string $sql, array $params = []): array {
    global $COMPANY_ID, $YEAR_ID;

    $params[':company_id'] = $COMPANY_ID;
    $params[':year_id']    = $YEAR_ID;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * SELECT - Get single value with company/year filter
 */
function getFilteredValue(PDO $pdo, string $sql, array $params = []) {
    global $COMPANY_ID, $YEAR_ID;

    $params[':company_id'] = $COMPANY_ID;
    $params[':year_id']    = $YEAR_ID;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

/**
 * INSERT - Add a new record with company/year auto-attached
 */
function insertFiltered(PDO $pdo, string $table, array $data): int {
    global $COMPANY_ID, $YEAR_ID, $CURRENT_USER;

    $data['company_id']         = $COMPANY_ID;
    $data['accounting_year_id'] = $YEAR_ID;
    $data['created_by']         = $CURRENT_USER;

    $columns = array_keys($data);
    $placeholders = array_map(fn($col) => ":" . $col, $columns);

    $sql = "INSERT INTO {$table} (" . implode(",", $columns) . ") 
            VALUES (" . implode(",", $placeholders) . ")";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($data);

    return (int)$pdo->lastInsertId();
}

/**
 * UPDATE - Modify a record with company/year enforced
 */
function updateFiltered(PDO $pdo, string $table, array $data, string $where, array $params = []): int {
    global $COMPANY_ID, $YEAR_ID, $CURRENT_USER;

    $data['updated_by'] = $CURRENT_USER;

    $setParts = [];
    foreach ($data as $col => $val) {
        $setParts[] = "$col = :$col";
    }

    $sql = "UPDATE {$table} 
            SET " . implode(", ", $setParts) . "
            WHERE company_id = :company_id 
              AND accounting_year_id = :year_id 
              AND {$where}";

    $data['company_id']         = $COMPANY_ID;
    $data['accounting_year_id'] = $YEAR_ID;

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($data, $params));

    return $stmt->rowCount();
}

/**
 * DELETE - Remove a record with company/year enforced
 */
function deleteFiltered(PDO $pdo, string $table, string $where, array $params = []): int {
    global $COMPANY_ID, $YEAR_ID;

    $sql = "DELETE FROM {$table}
            WHERE company_id = :company_id 
              AND accounting_year_id = :year_id 
              AND {$where}";

    $params[':company_id'] = $COMPANY_ID;
    $params[':year_id']    = $YEAR_ID;

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->rowCount();
}

/**
 * Returns a PDO connection to the MAIN DB (global tables like company_details).
 */
function getMainPDO(): PDO {
    try {
        // 🔹 Replace with your actual DSN/credentials for main_db
        $dsn = "mysql:host=localhost;dbname=main_db;charset=utf8mb4";
        $username = "root";
        $password = "";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        return new PDO($dsn, $username, $password, $options);
    } catch (Exception $e) {
        die("❌ MAIN DB Connection failed: " . $e->getMessage());
    }
}
