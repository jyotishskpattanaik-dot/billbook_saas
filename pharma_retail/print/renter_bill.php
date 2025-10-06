<? $format = $_GET['format'] ?? 'retail';

switch ($format) {
    case 'gst':
        include "templates/gst_invoice.php";
        break;
    case 'detailed':
        include "templates/detailed_bill.php";
        break;
    default:
        include "templates/retail_bill.php";
}
