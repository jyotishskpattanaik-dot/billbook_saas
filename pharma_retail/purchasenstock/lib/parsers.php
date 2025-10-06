<?php
// lib/parsers.php
use PhpOffice\PhpSpreadsheet\IOFactory;
use Smalot\PdfParser\Parser as PdfParser;

require_once __DIR__ . '/../../../vendor/autoload.php';

// 1) safe trim to avoid PHP 8 deprecated warnings
function safe_trim($v) {
    return trim((string)($v ?? ''));
}

// 2) normalize header using alias map
function normalizeHeader($header, $aliasMap) {
    $h = mb_strtolower(safe_trim($header));
    foreach ($aliasMap as $key => $alts) {
        foreach ($alts as $alt) {
            if ($h === mb_strtolower($alt)) return $key;
        }
    }
    // fallback: try contains match
    foreach ($aliasMap as $key => $alts) {
        foreach ($alts as $alt) {
            if (mb_stripos($h, mb_strtolower($alt)) !== false) return $key;
        }
    }
    return $h;
}

// 3) date parser (robust) — default day to 01 for month-year
function parsePharmaExpiryDate($date_string) {
    $d = safe_trim($date_string);
    if ($d === '') return null;
    $d_lower = mb_strtolower($d);
    $na = ['n/a','na','-','--','---','not available','na.'];
    if (in_array($d_lower, $na)) return null;

    // yyyy-mm-dd
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) return $d;

    // mm[-\/]yy or mm[-\/]yyyy
    if (preg_match('/^(\d{1,2})[-\/](\d{2,4})$/', $d, $m)) {
        $month = str_pad($m[1],2,'0',STR_PAD_LEFT);
        $year = $m[2];
        if (strlen($year) === 2) $year = (int)("20".$year);
        return sprintf('%04d-%02d-01', $year, $month);
    }

    // MonthName-yy or MonthName-yyyy
    if (preg_match('/^([a-zA-Z]{3,9})[-\/\s]?(\d{2,4})$/', $d, $m)) {
        $month = date('m', strtotime("1 ".$m[1]));
        $year = $m[2];
        if (strlen($year) === 2) $year = (int)("20".$year);
        return sprintf('%04d-%02d-01', $year, $month);
    }

    // try strtotime but restrict range
    $ts = strtotime($d);
    if ($ts !== false) {
        $y = (int)date('Y', $ts);
        $cy = (int)date('Y');
        if ($y >= ($cy-2) && $y <= ($cy+40)) {
            return date('Y-m-d', $ts);
        }
    }

    // fallback
    return null;
}

// 4) parse Excel/CSV using PhpSpreadsheet — returns array of rows (associative by header)
function readSpreadsheetRows($filepath) {
    $spreadsheet = IOFactory::load($filepath);
    $sheet = $spreadsheet->getActiveSheet();
    $rows = [];
    $header = [];
    foreach ($sheet->getRowIterator() as $rowIndex => $row) {
        $cells = [];
        $cellIterator = $row->getCellIterator();
        $cellIterator->setIterateOnlyExistingCells(false);
        foreach ($cellIterator as $cell) {
            $cells[] = $cell->getValue();
        }
        if ($rowIndex == 1) {
            $header = array_map('safe_trim',$cells);
            continue;
        }
        // build assoc
        $assoc = [];
        for ($i=0;$i<count($header);$i++) {
            $assoc[$header[$i] ?? 'col'.$i] = $cells[$i] ?? '';
        }
        $rows[] = $assoc;
    }
    return $rows;
}

// 5) read CSV quickly
function readCsvRows($filepath, $delimiter = ',') {
    $fh = fopen($filepath,'r');
    if (!$fh) return [];
    $header = fgetcsv($fh, 0, $delimiter);
    if ($header === false) return [];
    $data = [];
    while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
        $assoc = [];
        for ($i=0;$i<count($header);$i++) {
            $assoc[safe_trim($header[$i] ?? 'col'.$i)] = $row[$i] ?? '';
        }
        $data[] = $assoc;
    }
    fclose($fh);
    return $data;
}

// 6) PDF text extractor using smalot/pdfparser
function extractTextFromPdf($filePath) {
    $parser = new PdfParser();
    $pdf = $parser->parseFile($filePath);
    return $pdf->getText();
}
// For scanned PDFs, use Tesseract: convert each page to image and OCR — implement when needed

// 7) map uploaded headers to canonical keys using aliasMap
function mapHeadersAndRows($rows, $aliasMap) {
    $mappedRows = [];
    if (empty($rows)) return $mappedRows;
    $inputHeaders = array_keys($rows[0]);
    $map = [];
    foreach ($inputHeaders as $i => $h) {
        $map[$i] = normalizeHeader($h, $aliasMap);
    }
    foreach ($rows as $row) {
        $mapped = [];
        foreach ($row as $key => $val) {
            $canonical = normalizeHeader($key, $aliasMap);
            $mapped[$canonical] = $val;
        }
        $mappedRows[] = $mapped;
    }
    return $mappedRows;
}

// 8) sanitize product row for preview (not DB-sanitized yet)
function cleanForPreview($row) {
    return [
        'product_name' => htmlspecialchars(trim((string)($row['product_name'] ?? ''))),
        'company' => htmlspecialchars(trim((string)($row['company'] ?? ''))),
        'category' => htmlspecialchars(trim((string)($row['category'] ?? ''))),
        'pack' => htmlspecialchars(trim((string)($row['pack'] ?? ''))),
        'batch_no' => htmlspecialchars(trim((string)($row['batch_no'] ?? ''))),
        'hsn_code' => htmlspecialchars(trim((string)($row['hsn_code'] ?? ''))),
        'expiry_date' => parsePharmaExpiryDate($row['expiry_date'] ?? $row['expiry_date'] ?? ''),
        'mrp' => floatval($row['mrp'] ?? 0),
        'rate' => floatval($row['rate'] ?? 0),
        'quantity' => floatval($row['quantity'] ?? 0),
        'free_quantity' => floatval($row['free_quantity'] ?? 0),
        'discount' => floatval($row['discount'] ?? 0),
        'sgst' => floatval($row['sgst'] ?? 0),
        'cgst' => floatval($row['cgst'] ?? 0),
        'igst' => floatval($row['igst'] ?? 0),
        'composition' => htmlspecialchars(trim((string)($row['composition'] ?? '')))
    ];
}
