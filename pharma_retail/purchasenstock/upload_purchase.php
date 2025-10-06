<?php
session_start();
require_once __DIR__ . '/lib/parsers.php';
$aliasMap = require __DIR__ . '/config/field_aliases.php';

ini_set('display_errors',1);
error_reporting(E_ALL);

// Validate file
if (!isset($_FILES['bill_file']) || $_FILES['bill_file']['error'] !== UPLOAD_ERR_OK) {
    $_SESSION['error'] = "Upload failed";
    header("Location: upload_purchase_form.php");
    exit;
}

$tmp = $_FILES['bill_file']['tmp_name'];
$name = $_FILES['bill_file']['name'];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

// read rows array of associative rows
$rawRows = [];
if (in_array($ext, ['csv'])) {
    $rawRows = readCsvRows($tmp);
} elseif (in_array($ext, ['xls','xlsx'])) {
    $rawRows = readSpreadsheetRows($tmp);
} elseif ($ext === 'pdf') {
    $text = extractTextFromPdf($tmp);
    // minimal approach: split lines and try to parse table-like rows
    // better: present the raw text in a UI and ask user to help map (advanced)
    $lines = preg_split('/\r\n|\r|\n/', $text);
    // Try heuristics to create rows from lines — but this is complex; fallback -> show whole text in preview
    $_SESSION['preview_text'] = $text;
    // Redirect to a PDF preview UI where user may select table/lines 
    header("Location: preview_purchase.php");
    exit;
} else {
    $_SESSION['error'] = "Unsupported file format.";
    header("Location: upload_purchase_form.php");
    exit;
}

// Map headers → canonical keys and then build product rows
$mappedRows = mapHeadersAndRows($rawRows, $aliasMap);

// convert each mapped row to cleaned preview row
$products = [];
foreach ($mappedRows as $mr) {
    $products[] = cleanForPreview($mr);
}

// use optional overrides from upload form
$preview = [
  'supplier_name' => safe_trim($_POST['supplier_name'] ?? $mappedRows[0]['supplier_name'] ?? ''),
  'invoice_number' => safe_trim($_POST['invoice_number'] ?? $mappedRows[0]['invoice_number'] ?? ''),
  'purchase_date' => safe_trim($_POST['purchase_date'] ?? $mappedRows[0]['purchase_date'] ?? date('Y-m-d')),
  'bill_type' => safe_trim($_POST['bill_type'] ?? $mappedRows[0]['bill_type'] ?? ''),
  'products' => $products
];

// Save to session and redirect to preview
$_SESSION['preview_data'] = $preview;
header("Location: preview_purchase.php");
exit;
