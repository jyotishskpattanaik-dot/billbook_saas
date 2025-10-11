<?php
function getModuleDashboardUrl(): string {
    $module = $_SESSION['user_module'] ?? 'main';
    $base = "/billbook.in";

    switch ($module) {
        case 'pharma_retail':
            return "$base/pharma_retail/dashboard.php";
        case 'pharma_wholesale':
            return "$base/pharma_wholesale/dashboard.php";
        case 'retail_other':
            return "$base/retail_other/dashboard.php";
        case 'wholesale_others':
            return "$base/wholesale_others/dashboard.php";
        default:
            return "$base/public/login.php"; // fallback (maybe main control panel)
    }
}
