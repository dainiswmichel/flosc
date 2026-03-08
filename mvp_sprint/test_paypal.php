<?php
// Temporary test script for PayPal plan creation
$client_id = get_option('flosc_paypal_client_id');
$secret = get_option('flosc_paypal_secret');

echo "Calling PayPal sandbox OAuth...\n";
$resp = wp_remote_post('https://api-m.sandbox.paypal.com/v1/oauth2/token', [
    'headers' => [
        'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $secret),
        'Content-Type' => 'application/x-www-form-urlencoded',
    ],
    'body' => 'grant_type=client_credentials',
    'timeout' => 30,
]);

if (is_wp_error($resp)) {
    echo "ERROR: " . $resp->get_error_message() . "\n";
    exit(1);
}

$code = wp_remote_retrieve_response_code($resp);
$body = json_decode(wp_remote_retrieve_body($resp), true);
echo "OAuth HTTP $code\n";

if (empty($body['access_token'])) {
    echo "Token error: " . json_encode($body) . "\n";
    exit(1);
}

$token = $body['access_token'];
echo "Got token: " . substr($token, 0, 20) . "...\n";

// Create product
echo "Creating product...\n";
$prod_resp = wp_remote_post('https://api-m.sandbox.paypal.com/v1/catalogs/products', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode([
        'name' => 'LeSAEp Pronunciation Course',
        'description' => 'Learn Excellent Standard American English Pronunciation',
        'type' => 'DIGITAL',
        'category' => 'EDUCATIONAL_AND_TEXTBOOKS',
    ]),
    'timeout' => 30,
]);

if (is_wp_error($prod_resp)) {
    echo "Product ERROR: " . $prod_resp->get_error_message() . "\n";
    exit(1);
}

$prod_code = wp_remote_retrieve_response_code($prod_resp);
$prod_body = json_decode(wp_remote_retrieve_body($prod_resp), true);
echo "Product HTTP $prod_code\n";

if (empty($prod_body['id'])) {
    echo "Product failed: " . json_encode($prod_body) . "\n";
    exit(1);
}

$product_id = $prod_body['id'];
echo "Product ID: $product_id\n";

// Create monthly plan
echo "Creating monthly plan...\n";
$monthly_resp = wp_remote_post('https://api-m.sandbox.paypal.com/v1/billing/plans', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode([
        'product_id' => $product_id,
        'name' => 'LeSAEp Monthly — $10/month',
        'status' => 'ACTIVE',
        'billing_cycles' => [[
            'frequency' => ['interval_unit' => 'MONTH', 'interval_count' => 1],
            'tenure_type' => 'REGULAR',
            'sequence' => 1,
            'total_cycles' => 0,
            'pricing_scheme' => ['fixed_price' => ['value' => '10.00', 'currency_code' => 'USD']],
        ]],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'payment_failure_threshold' => 3,
        ],
    ]),
    'timeout' => 30,
]);

$monthly_code = wp_remote_retrieve_response_code($monthly_resp);
$monthly_body = json_decode(wp_remote_retrieve_body($monthly_resp), true);
echo "Monthly plan HTTP $monthly_code\n";

if (empty($monthly_body['id'])) {
    echo "Monthly plan failed: " . json_encode($monthly_body) . "\n";
    exit(1);
}

echo "Monthly plan ID: " . $monthly_body['id'] . "\n";

// Create yearly plan
echo "Creating yearly plan...\n";
$yearly_resp = wp_remote_post('https://api-m.sandbox.paypal.com/v1/billing/plans', [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Content-Type' => 'application/json',
    ],
    'body' => json_encode([
        'product_id' => $product_id,
        'name' => 'LeSAEp Yearly — $100/year',
        'status' => 'ACTIVE',
        'billing_cycles' => [[
            'frequency' => ['interval_unit' => 'YEAR', 'interval_count' => 1],
            'tenure_type' => 'REGULAR',
            'sequence' => 1,
            'total_cycles' => 0,
            'pricing_scheme' => ['fixed_price' => ['value' => '100.00', 'currency_code' => 'USD']],
        ]],
        'payment_preferences' => [
            'auto_bill_outstanding' => true,
            'payment_failure_threshold' => 3,
        ],
    ]),
    'timeout' => 30,
]);

$yearly_code = wp_remote_retrieve_response_code($yearly_resp);
$yearly_body = json_decode(wp_remote_retrieve_body($yearly_resp), true);
echo "Yearly plan HTTP $yearly_code\n";

if (empty($yearly_body['id'])) {
    echo "Yearly plan failed: " . json_encode($yearly_body) . "\n";
    exit(1);
}

echo "Yearly plan ID: " . $yearly_body['id'] . "\n";

// Save to WP options
$plans = [
    'product_id' => $product_id,
    'monthly_plan_id' => $monthly_body['id'],
    'yearly_plan_id' => $yearly_body['id'],
];
update_option('flosc_paypal_plans', $plans);
echo "\nSaved plans to DB: " . json_encode($plans) . "\n";
echo "DONE\n";
