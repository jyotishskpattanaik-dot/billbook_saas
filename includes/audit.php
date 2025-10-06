<?php
// includes/audit.php
require_once __DIR__ . '/init.php'; // to get getMainPDO()

function logAudit($companyId, $userId, $module, $action, $tableName, $recordId = null, $oldValues = null, $newValues = null) {
    try {
        $pdo = getMainPDO();

        // Convert arrays to JSON
        if (is_array($oldValues)) {
            $oldValues = json_encode($oldValues, JSON_UNESCAPED_UNICODE);
        }
        if (is_array($newValues)) {
            $newValues = json_encode($newValues, JSON_UNESCAPED_UNICODE);
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (company_id, user_id, module, action, table_name, record_id, old_values, new_values, ip_address, user_agent) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $companyId,
            $userId,
            $module,
            $action,
            $tableName,
            $recordId,
            $oldValues,
            $newValues,
            $ip,
            $userAgent
        ]);
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
    }
}
