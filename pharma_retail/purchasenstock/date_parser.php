<?php
/**
 * Enhanced Date Parser for Pharmaceutical Bills
 * Handles various date formats commonly found in pharma bills
 */

/**
 * Parse pharmaceutical expiry dates to MySQL compatible format
 * 
 * Handles formats like:
 * - jan-27, jan/2027 -> 2027-01-01
 * - 05-28, 5/28, 05/28 -> 2028-05-01
 * - Empty dates -> null
 * 
 * @param string $date_string The input date string
 * @return string|null MySQL compatible date or null
 */
function parsePharmaExpiryDate($date_string) {
    if (empty($date_string) || trim($date_string) === '' || strtolower(trim($date_string)) === 'null') {
        return null;
    }

    $date_string = trim($date_string);
    
    // Handle "N/A", "NA", "Not Available" etc.
    $na_patterns = ['n/a', 'na', 'not available', 'not applicable', '-', '--', '---'];
    if (in_array(strtolower($date_string), $na_patterns)) {
        return null;
    }

    // Month name mapping
    $month_names = [
        'jan' => '01', 'january' => '01',
        'feb' => '02', 'february' => '02',
        'mar' => '03', 'march' => '03',
        'apr' => '04', 'april' => '04',
        'may' => '05', 'may' => '05',
        'jun' => '06', 'june' => '06',
        'jul' => '07', 'july' => '07',
        'aug' => '08', 'august' => '08',
        'sep' => '09', 'september' => '09',
        'oct' => '10', 'october' => '10',
        'nov' => '11', 'november' => '11',
        'dec' => '12', 'december' => '12'
    ];

    // Pattern 1: Month name with year (jan-27, jan/2027, january-2027)
    $pattern1 = '/^([a-z]{3,9})[-\/\s]?(\d{2,4})$/i';
    if (preg_match($pattern1, $date_string, $matches)) {
        $month_name = strtolower(trim($matches[1]));
        $year = trim($matches[2]);
        
        if (isset($month_names[$month_name])) {
            $month = $month_names[$month_name];
            
            // Handle 2-digit years
            if (strlen($year) === 2) {
                $current_year = date('Y');
                $current_century = floor($current_year / 100) * 100;
                $year = $current_century + (int)$year;
                
                // If year is less than current year, assume next century
                if ($year < $current_year) {
                    $year += 100;
                }
            }
            
            return sprintf('%04d-%02d-01', (int)$year, (int)$month);
        }
    }

    // Pattern 2: MM-YY, MM/YY, MM-YYYY, MM/YYYY (05-28, 5/28, 05/2028)
    $pattern2 = '/^(\d{1,2})[-\/\s](\d{2,4})$/';
    if (preg_match($pattern2, $date_string, $matches)) {
        $month = (int)$matches[1];
        $year = (int)$matches[2];
        
        if ($month >= 1 && $month <= 12) {
            // Handle 2-digit years
            if ($year < 100) {
                $current_year = date('Y');
                $current_century = floor($current_year / 100) * 100;
                $year = $current_century + $year;
                
                // If year is less than current year, assume next century
                if ($year < $current_year) {
                    $year += 100;
                }
            }
            
            return sprintf('%04d-%02d-01', $year, $month);
        }
    }

    // Pattern 3: Standard date formats (DD/MM/YYYY, DD-MM-YYYY, YYYY-MM-DD)
    $standard_formats = [
        'd/m/Y',    // 31/12/2024
        'd-m-Y',    // 31-12-2024
        'Y-m-d',    // 2024-12-31 (already correct)
        'd/m/y',    // 31/12/24
        'd-m-y',    // 31-12-24
        'm/d/Y',    // 12/31/2024 (US format)
        'm-d-Y',    // 12-31-2024
        'Y/m/d',    // 2024/12/31
    ];

    foreach ($standard_formats as $format) {
        $parsed_date = DateTime::createFromFormat($format, $date_string);
        if ($parsed_date !== false && $parsed_date->format($format) === $date_string) {
            return $parsed_date->format('Y-m-d');
        }
    }

    // Pattern 4: Try strtotime as last resort
    $timestamp = strtotime($date_string);
    if ($timestamp !== false) {
        $parsed_date = new DateTime('@' . $timestamp);
        // Only accept reasonable pharmaceutical expiry dates (not too far in past/future)
        $current_year = date('Y');
        $parsed_year = (int)$parsed_date->format('Y');
        
        if ($parsed_year >= ($current_year - 1) && $parsed_year <= ($current_year + 20)) {
            return $parsed_date->format('Y-m-d');
        }
    }

    // If all parsing fails, log it and return null
    error_log("Unable to parse expiry date: " . $date_string);
    return null;
}

/**
 * Parse and split GST rates
 * 
 * Handles cases where:
 * - Individual CGST/SGST rates are provided
 * - Combined GST rate is provided (splits into CGST + SGST)
 * - IGST is provided separately
 * 
 * @param float $cgst_input CGST rate input
 * @param float $sgst_input SGST rate input  
 * @param float $igst_input IGST rate input
 * @param float $total_gst_input Total GST rate (if combined)
 * @return array ['cgst' => float, 'sgst' => float, 'igst' => float]
 */
function parseGstRates($cgst_input, $sgst_input, $igst_input, $total_gst_input = 0) {
    $cgst = (float)$cgst_input;
    $sgst = (float)$sgst_input;
    $igst = (float)$igst_input;
    $total_gst = (float)$total_gst_input;

    // Case 1: IGST is provided (inter-state transaction)
    if ($igst > 0) {
        return [
            'cgst' => 0,
            'sgst' => 0, 
            'igst' => $igst
        ];
    }

    // Case 2: Both CGST and SGST are provided
    if ($cgst > 0 && $sgst > 0) {
        return [
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => 0
        ];
    }

    // Case 3: Only CGST is provided, assume equal SGST
    if ($cgst > 0 && $sgst == 0) {
        return [
            'cgst' => $cgst,
            'sgst' => $cgst,
            'igst' => 0
        ];
    }

    // Case 4: Only SGST is provided, assume equal CGST  
    if ($sgst > 0 && $cgst == 0) {
        return [
            'cgst' => $sgst,
            'sgst' => $sgst,
            'igst' => 0
        ];
    }

    // Case 5: Total GST is provided, split equally between CGST and SGST
    if ($total_gst > 0) {
        $half_rate = $total_gst / 2;
        return [
            'cgst' => $half_rate,
            'sgst' => $half_rate,
            'igst' => 0
        ];
    }

    // Case 6: No GST rates provided, use default pharmaceutical GST (usually 12% total = 6% CGST + 6% SGST)
    if ($cgst == 0 && $sgst == 0 && $igst == 0 && $total_gst == 0) {
        return [
            'cgst' => 6.00,
            'sgst' => 6.00,
            'igst' => 0
        ];
    }

    return [
        'cgst' => $cgst,
        'sgst' => $sgst,
        'igst' => $igst
    ];
}

/**
 * Validate and clean pharmaceutical product data
 * 
 * @param array $product_data Raw product data from file
 * @return array Cleaned and validated product data
 */
function cleanPharmaProductData($product_data) {
    // Clean product name
    $product_data['product_name'] = ucwords(strtolower(trim($product_data['product_name'])));
    
    // Clean company name
    $product_data['company'] = ucwords(strtolower(trim($product_data['company'] ?? '')));
    
    // Parse expiry date
    $product_data['expiry_date'] = parsePharmaExpiryDate($product_data['expiry_date'] ?? '');
    
    // Parse GST rates
    $gst_rates = parseGstRates(
        $product_data['cgst'] ?? 0,
        $product_data['sgst'] ?? 0, 
        $product_data['igst'] ?? 0,
        $product_data['total_gst'] ?? 0
    );
    
    $product_data['cgst'] = $gst_rates['cgst'];
    $product_data['sgst'] = $gst_rates['sgst'];
    $product_data['igst'] = $gst_rates['igst'];
    
    // Clean batch number
    $product_data['batch_no'] = strtoupper(trim($product_data['batch_no'] ?? ''));
    if (empty($product_data['batch_no'])) {
        $product_data['batch_no'] = 'BATCH' . date('Ymd') . rand(100, 999);
    }
    
    // Ensure numeric fields are properly formatted
    $numeric_fields = ['mrp', 'rate', 'quantity', 'free_quantity', 'discount'];
    foreach ($numeric_fields as $field) {
        $product_data[$field] = max(0, (float)($product_data[$field] ?? 0));
    }
    
    return $product_data;
}

/**
 * Test the date parser with common pharmaceutical date formats
 */
function testPharmaDateParser() {
    $test_dates = [
        'jan-27' => '2027-01-01',
        'jan/2027' => '2027-01-01', 
        'january-2027' => '2027-01-01',
        '05-28' => '2028-05-01',
        '5/28' => '2028-05-01',
        '05/28' => '2028-05-01',
        'dec-25' => '2025-12-01',
        '12/26' => '2026-12-01',
        '31/12/2025' => '2025-12-31',
        '2025-12-31' => '2025-12-31',
        '' => null,
        'N/A' => null,
        'not available' => null
    ];
    
    echo "<h3>Pharma Date Parser Test Results:</h3>";
    echo "<table border='1'><tr><th>Input</th><th>Expected</th><th>Actual</th><th>Status</th></tr>";
    
    foreach ($test_dates as $input => $expected) {
        $actual = parsePharmaExpiryDate($input);
        $status = ($actual === $expected) ? '✅ PASS' : '❌ FAIL';
        
        echo "<tr>";
        echo "<td>" . htmlspecialchars($input) . "</td>";
        echo "<td>" . ($expected ?? 'null') . "</td>";
        echo "<td>" . ($actual ?? 'null') . "</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Uncomment to test the date parser
// testPharmaDateParser();
?>