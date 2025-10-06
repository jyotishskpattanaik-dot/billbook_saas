<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../includes/init.php';
require __DIR__ . '/print_bill_template.php';

$pdo = getModulePDO();
$mainPdo = getMainPDO();

$bill_no = $_GET['bill_no'] ?? '';
if (empty($bill_no)) {
    die("Bill number missing.");
}

// --- Fetch bill summary ---
$stmt = $pdo->prepare("SELECT * FROM bill_summary WHERE bill_no = ? AND company_id = ? AND accounting_year_id = ?");
$stmt->execute([$bill_no, $COMPANY_ID, $YEAR_ID]);
$bill = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Fetch products ---
$stmt = $pdo->prepare("SELECT * FROM sale_bill_products WHERE bill_no = ? AND sale_id = ?");
$stmt->execute([$bill_no, $bill['id']]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Company details ---
$stmt = $mainPdo->prepare("SELECT company_name, gst_number, dl_number, fssai_number, address, contact_no FROM companies WHERE id = ?");
$stmt->execute([$COMPANY_ID]);
$company = $stmt->fetch(PDO::FETCH_ASSOC);

// --- Render Template ---
renderInvoiceTemplate($company, $bill, $products, $bill_no);
