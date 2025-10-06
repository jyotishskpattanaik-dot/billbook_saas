<?php
/**
 * includes/public_db_helper.php
 * Helper functions for public-facing pages
 */

use SendGrid\Mail\Mail;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Identify that this session belongs to the public checkout flow
if (!isset($_SESSION['public_checkout'])) {
    $_SESSION['public_checkout'] = true;
}

/**
 * Get PDO connection to main_db
 */
function getPublicPDO(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=localhost;dbname=main_db;charset=utf8mb4";
            $username = "root";
            $password = "";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $pdo = new PDO($dsn, $username, $password, $options);
        } catch (Exception $e) {
            error_log("Public DB Connection failed: " . $e->getMessage());
            throw new Exception("Database connection failed. Please try again later.");
        }
    }

    return $pdo;
}

/**
 * Send email using SendGrid (if configured), fallback to mails.log
 */
function sendEmail($to, $subject, $message, $from = 'litu1pattanaik@gmail.com') {
    $htmlMessage = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #0066cc; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background: #f9f9f9; }
            .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
            .button { display: inline-block; padding: 12px 24px; background: #0066cc; color: white; text-decoration: none; border-radius: 5px; }
            .credentials-box { background: white; padding: 15px; border-left: 4px solid #0066cc; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'><h1>billbook.in</h1></div>
            <div class='content'>$message</div>
            <div class='footer'>
                <p>&copy; " . date('Y') . " billbook.in. All rights reserved.</p>
                <p>If you didn’t request this email, please ignore it.</p>
            </div>
        </div>
    </body>
    </html>";

    try {
        // NOTE: Replace with your real SendGrid API key if available
        $apiKey = 'SG.your_actual_api_key_here';

        if (!empty($apiKey) && strpos($apiKey, 'your_actual_api_key_here') === false) {
            $email = new Mail();
            $email->setFrom($from, "billbook.in");
            $email->setSubject($subject);
            $email->addTo($to);
            $email->addContent("text/html", $htmlMessage);

            $sendgrid = new \SendGrid($apiKey);
            $response = $sendgrid->send($email);

            if ($response->statusCode() === 202) {
                return true;
            }
        }

        // Local fallback (no SendGrid / localhost)
        $logFile = __DIR__ . '/../mails.log';
        $logEntry = "=== MAIL " . date('Y-m-d H:i:s') . " ===\nTo: $to\nSubject: $subject\n\n$message\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return true;

    } catch (Exception $e) {
        error_log('SendGrid error: ' . $e->getMessage());

        // Always log locally as fallback
        $logFile = __DIR__ . '/../mails.log';
        $logEntry = "=== MAIL (FAILED SENDGRID) " . date('Y-m-d H:i:s') . " ===\nTo: $to\nSubject: $subject\n\n$message\n\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);
        return false;
    }
}

/**
 * Log public activity (safe for checkout & user actions)
 */
function logPublicActivity($action, $description, $user_id = null, $order_id = null) {
    try {
        $pdo = getPublicPDO();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, order_id, action, description, ip_address, user_agent, created_at)
            VALUES (:user_id, :order_id, :action, :description, :ip, :user_agent, NOW())
        ");
        $stmt->execute([
            ':user_id' => $user_id,
            ':order_id' => $order_id,
            ':action' => $action,
            ':description' => $description,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (Exception $e) {
        error_log("Failed to log public activity: " . $e->getMessage());
    }
}

/**
 * Send welcome email to new users
 */
function sendWelcomeEmail($email, $firstName, $password, $orderNumber, $planName) {
    $loginUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/public/login.php'; // localhost safe
    $message = "
        <h2>Welcome to billbook.in, $firstName!</h2>
        <p>Thank you for signing up. Your account has been created successfully.</p>
        <div class='credentials-box'>
            <h3>Your Login Credentials:</h3>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Password:</strong> <code>$password</code></p>
            <p><strong>Order Number:</strong> $orderNumber</p>
            <p><strong>Plan:</strong> " . ucfirst($planName) . "</p>
        </div>
        <p><strong>Note:</strong> Please change your password after first login.</p>
        <p style='text-align: center; margin: 30px 0;'>
            <a href='$loginUrl' class='button'>Login to Your Account</a>
        </p>
        <p>If you have any questions, feel free to contact our support team.</p>
    ";
    return sendEmail($email, 'Welcome to BillBook - Your Account is Ready!', $message);
}

/**
 * Send payment confirmation email
 */
function sendPaymentConfirmationEmail($email, $firstName, $orderNumber, $amount, $planName, $duration) {
    $message = "
        <h2>Payment Successful!</h2>
        <p>Dear $firstName,</p>
        <p>Your payment for the subscription has been received successfully.</p>
        <div class='credentials-box'>
            <h3>Payment Details:</h3>
            <p><strong>Order Number:</strong> $orderNumber</p>
            <p><strong>Amount Paid:</strong> ₹" . number_format($amount, 2) . "</p>
            <p><strong>Plan:</strong> " . ucfirst($planName) . "</p>
            <p><strong>Duration:</strong> $duration</p>
        </div>
        <p>You can now enjoy full access to your chosen plan.</p>
        <p>Thank you for choosing <strong>billbook.in</strong>!</p>
    ";
    return sendEmail($email, "Payment Confirmation - Order #$orderNumber", $message);
}

/**
 * Utility functions
 */
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function isValidPhone($phone) {
    return preg_match('/^[6-9][0-9]{9}$/', $phone);
}

function isValidGSTIN($gstin) {
    if (empty($gstin)) return true;
    return preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin);
}

function formatCurrency($amount, $symbol = '₹') {
    return $symbol . number_format($amount, 2);
}

function generateOrderNumber() {
    return 'ORD-' . strtoupper(uniqid());
}

function calculateExpiryDate($period, $duration) {
    if ($period === 'monthly') {
        return date('Y-m-d H:i:s', strtotime("+$duration months"));
    } elseif ($period === 'yearly') {
        return date('Y-m-d H:i:s', strtotime("+$duration years"));
    }
    return null;
}
