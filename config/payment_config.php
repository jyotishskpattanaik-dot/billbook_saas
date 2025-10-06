<?php
// config/payment_config.php
// Centralized payment gateway configuration

return [
    // Active payment gateway: 'razorpay', 'phonepe', 'payu', 'cashfree'
    'active_gateway' => 'razorpay',
    
    // Company Details
    'company' => [
        'name' => 'billbook.in',
        'email' => 'litu1pattanaik@gmail.com',
        'phone' => '+91-7735364889',
        'logo' => 'assets/images/logo/logo.png'
    ],
    
    // Razorpay Configuration
    'razorpay' => [
        'enabled' => true,
        'mode' => 'test', // 'test' or 'live'
        'test' => [
            'key_id' => 'rzp_test_XXXXXXXXXXXXXXXX',
            'key_secret' => 'XXXXXXXXXXXXXXXXXXXXXXXX'
        ],
        'live' => [
            'key_id' => 'rzp_live_XXXXXXXXXXXXXXXX',
            'key_secret' => 'XXXXXXXXXXXXXXXXXXXXXXXX'
        ],
        'webhook_secret' => 'your_webhook_secret',
        'theme_color' => '#0066cc',
        'payment_methods' => [
            'card' => true,
            'netbanking' => true,
            'wallet' => true,
            'upi' => true,
            'emi' => false,
            'paylater' => false
        ]
    ],
    
    // PhonePe Configuration
    'phonepe' => [
        'enabled' => true,
        'mode' => 'test', // 'test' or 'live'
        'test' => [
            'merchant_id' => 'MERCHANTUAT',
            'salt_key' => '099eb0cd-02cf-4e2a-8aca-3e6c6aff0399',
            'salt_index' => '1',
            'api_url' => 'https://api-preprod.phonepe.com/apis/pg-sandbox'
        ],
        'live' => [
            'merchant_id' => 'YOUR_MERCHANT_ID',
            'salt_key' => 'YOUR_SALT_KEY',
            'salt_index' => '1',
            'api_url' => 'https://api.phonepe.com/apis/hermes'
        ]
    ],
    
    // PayU Configuration
    'payu' => [
        'enabled' => true,
        'mode' => 'test', // 'test' or 'live'
        'test' => [
            'merchant_key' => 'YOUR_TEST_MERCHANT_KEY',
            'merchant_salt' => 'YOUR_TEST_MERCHANT_SALT',
            'api_url' => 'https://test.payu.in/_payment'
        ],
        'live' => [
            'merchant_key' => 'YOUR_LIVE_MERCHANT_KEY',
            'merchant_salt' => 'YOUR_LIVE_MERCHANT_SALT',
            'api_url' => 'https://secure.payu.in/_payment'
        ]
    ],
    
    // Cashfree Configuration
    'cashfree' => [
        'enabled' => false,
        'mode' => 'test',
        'test' => [
            'app_id' => 'YOUR_TEST_APP_ID',
            'secret_key' => 'YOUR_TEST_SECRET_KEY',
            'api_url' => 'https://sandbox.cashfree.com/pg'
        ],
        'live' => [
            'app_id' => 'YOUR_LIVE_APP_ID',
            'secret_key' => 'YOUR_LIVE_SECRET_KEY',
            'api_url' => 'https://api.cashfree.com/pg'
        ]
    ],
    
    // Callback URLs (automatically generated, but you can override)
    'callbacks' => [
        'success' => null, // Will use default: /payment_success.php
        'failure' => null, // Will use default: /payment_failure.php
        'webhook' => null  // Will use default: /payment_webhook.php
    ],
    
    // Currency Settings
    'currency' => [
        'code' => 'INR',
        'symbol' => '₹',
        'decimals' => 2
    ],
    
    // GST Configuration
    'gst' => [
        'enabled' => true,
        'rate' => 18, // GST percentage
        'included_in_price' => true // If true, GST is already included in displayed prices
    ],
    
    // Email Notifications
    'notifications' => [
        'payment_success' => true,
        'payment_failure' => true,
        'subscription_activated' => true,
        'subscription_expiring' => true,
        'subscription_expired' => true
    ],
    
    // Security Settings
    'security' => [
        'verify_signature' => true, // Always verify payment signatures
        'log_all_transactions' => true,
        'max_retry_attempts' => 3,
        'session_timeout' => 1800 // 30 minutes
    ],
    
    // Refund Settings
    'refunds' => [
        'enabled' => true,
        'auto_refund_on_cancellation' => false,
        'refund_processing_days' => 7
    ]
];

// Helper function to get payment config
function getPaymentConfig($key = null) {
    static $config = null;
    
    if ($config === null) {
        $config = require __DIR__ . '/payment_config.php';
    }
    
    if ($key === null) {
        return $config;
    }
    
    // Support dot notation: 'razorpay.test.key_id'
    $keys = explode('.', $key);
    $value = $config;
    
    foreach ($keys as $k) {
        if (!isset($value[$k])) {
            return null;
        }
        $value = $value[$k];
    }
    
    return $value;
}

// Helper function to get active gateway configuration
function getActiveGatewayConfig() {
    $config = getPaymentConfig();
    $gateway = $config['active_gateway'];
    $mode = $config[$gateway]['mode'];
    
    return [
        'gateway' => $gateway,
        'mode' => $mode,
        'config' => $config[$gateway][$mode]
    ];
}

// Helper function to format currency
function formatCurrency($amount) {
    $config = getPaymentConfig('currency');
    return $config['symbol'] . number_format($amount, $config['decimals']);
}

// Helper function to get callback URL
function getCallbackUrl($type = 'success') {
    $config = getPaymentConfig('callbacks');
    
    if ($config[$type]) {
        return $config[$type];
    }
    
    // Generate default callback URL
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    
    $urls = [
        'success' => '/payment_success.php',
        'failure' => '/payment_failure.php',
        'webhook' => '/payment_webhook.php'
    ];
    
    return $protocol . '://' . $host . $urls[$type];
}