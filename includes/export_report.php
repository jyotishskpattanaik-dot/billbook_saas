<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/init.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use Dompdf\Dompdf;

// --- Get Parameters ---
$report = $_GET['report'] ?? '';
$format = $_GET['format'] ?? 'excel';
$from   = $_GET['from'] ?? null;
$to     = $_GET['to'] ?? null;

// --- DB Connections ---
$pdo = getModulePDO();
$mainPdo = getMainPDO();

$data = [];
$columns = [];
$title = strtoupper($report) . " REPORT";

// --- Fetch data based on report type ---
switch ($report) {
    case 'sales':
        $sql = "SELECT bill_no, bill_date, customer_name, grand_total FROM bill_summary WHERE company_id = :company_id";
        $params = [':company_id' => $COMPANY_ID];
        if ($from && $to) {
            $sql .= " AND bill_date BETWEEN :from AND :to";
            $params[':from'] = $from;
            $params[':to']   = $to;
        }
        $sql .= " ORDER BY bill_date DESC, bill_no DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = ['Bill No', 'Date', 'Customer', 'Amount'];
        break;

    case 'purchase':
        $sql = "SELECT purchase_no, purchase_date, supplier_name, grand_total FROM purchase_summary WHERE company_id = :company_id";
        $params = [':company_id' => $COMPANY_ID];
        if ($from && $to) {
            $sql .= " AND purchase_date BETWEEN :from AND :to";
            $params[':from'] = $from;
            $params[':to']   = $to;
        }
        $sql .= " ORDER BY purchase_date DESC, purchase_no DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = ['Purchase No', 'Date', 'Supplier', 'Amount'];
        break;

    case 'gst':
        $sql = "SELECT invoice_no, invoice_date, customer_name, gst_amount, total_amount FROM gst_summary WHERE company_id = :company_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':company_id' => $COMPANY_ID]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = ['Invoice No', 'Date', 'Customer', 'GST Amount', 'Total'];
        break;

    case 'expiry':
        $sql = "SELECT product_name, batch_no, expiry_date, quantity FROM stock WHERE company_id = :company_id AND expiry_date <= :to_date";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':company_id' => $COMPANY_ID, ':to_date' => $to ?? date('Y-m-d', strtotime('+1 month'))]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = ['Product', 'Batch', 'Expiry Date', 'Quantity'];
        break;

    case 'misc':
        $sql = "SELECT product_name, stock_qty, unit_price FROM stock WHERE company_id = :company_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':company_id' => $COMPANY_ID]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $columns = ['Product', 'Stock Qty', 'Unit Price'];
        break;

    default:
        die("❌ Invalid report type.");
}

// --- Export logic ---
if (in_array($format, ['excel', 'csv'])) {
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle($title);

    // Column headers
    foreach ($columns as $i => $col) {
        $sheet->setCellValueByColumnAndRow($i + 1, 1, $col);
    }

    // Data rows
    foreach ($data as $r => $row) {
        foreach (array_values($row) as $c => $val) {
            $sheet->setCellValueByColumnAndRow($c + 1, $r + 2, $val);
        }
    }

    if ($format === 'excel') {
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header("Content-Disposition: attachment; filename={$report}_report.xlsx");
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    } else {
        header('Content-Type: text/csv');
        header("Content-Disposition: attachment; filename={$report}_report.csv");
        $writer = new Csv($spreadsheet);
        $writer->save('php://output');
        exit;
    }
}

if ($format === 'pdf') {
    $html = "<h2>$title</h2><table border='1' cellpadding='5' cellspacing='0'><thead><tr>";
    foreach ($columns as $col) $html .= "<th>$col</th>";
    $html .= "</tr></thead><tbody>";
    foreach ($data as $row) {
        $html .= "<tr>";
        foreach ($row as $val) $html .= "<td>$val</td>";
        $html .= "</tr>";
    }
    $html .= "</tbody></table>";

    $dompdf = new Dompdf();
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    $dompdf->stream("{$report}_report.pdf", ["Attachment" => true]);
    exit;
}

if ($format === 'word') {
    $phpWord = new PhpWord();
    $section = $phpWord->addSection();
    $section->addText($title, ['bold' => true, 'size' => 16]);

    $table = $section->addTable(['borderSize' => 6, 'cellMargin' => 50]);
    // Header
    $table->addRow();
    foreach ($columns as $col) $table->addCell(2000)->addText($col, ['bold' => true]);

    // Data
    foreach ($data as $row) {
        $table->addRow();
        foreach ($row as $val) $table->addCell(2000)->addText($val);
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header("Content-Disposition: attachment; filename={$report}_report.docx");
    $writer = IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save('php://output');
    exit;
}

die("❌ Invalid export format.");
