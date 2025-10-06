<?php
/**
 * Page Wrapper - reusable header/footer with border + colored rows
 * Usage:
 *   include __DIR__ . "/../includes/page_wrapper.php";
 *   pageHeader("Page Title", "primary");   // open container
 *   // ... your content here ...
 *   pageFooter("secondary");              // close container
 */

function pageHeader($title = "Page", $color = "primary") {
    echo '<div class="card shadow-lg border-dark">';
    echo '<div class="card-header bg-' . htmlspecialchars($color) . ' text-white text-center">';
    echo '<h4>' . htmlspecialchars($title) . '</h4>';
    echo '</div>';
    echo '<div class="card-body">';
}

function pageFooter($color = "dark") {
    echo '</div>'; // close card-body
    echo '<div class="card-footer bg-' . htmlspecialchars($color) . ' text-white text-center">';
    echo '<small>Thank you for using our system</small>';
    echo '</div>';
    echo '</div>'; // close card
}
