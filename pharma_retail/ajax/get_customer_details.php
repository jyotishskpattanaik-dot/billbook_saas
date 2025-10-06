<?php
require __DIR__ . '/../../includes/init.php';
$pdo = getModulePDO();

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT mobile_number FROM customer_details WHERE id = :id");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($row ?: []);
