<?php
/**
 * Database Fix Script - Run this once to update plugin options to UAT environment
 *
 * Instructions:
 * 1. Upload this file to your plugin directory
 * 2. Access it via browser: yoursite.com/wp-content/plugins/airducap-integration/fix-database-options.php
 * 3. This will force update all plugin settings to use UAT environment
 * 4. Delete this file after running
 */

// Try multiple paths to find WordPress
$wp_paths = array(
    '../../../../wp-load.php',
    '../../../../wp-config.php',
    '../../../wp-load.php',
    '../../../wp-config.php',
    '../../wp-load.php',
    '../../wp-config.php'
);

$wp_loaded = false;
foreach ($wp_paths as $path) {
    if (file_exists($path)) {
        try {
            require_once($path);
            $wp_loaded = true;
            break;
        } catch (Exception $e) {
            continue;
        }
    }
}

if (!$wp_loaded || !defined('ABSPATH')) {
    // Fallback: try to load via direct database connection
    echo "<h2>WordPress Load Failed - Using Direct Database Update</h2>";

    // Define the database updates needed
    $db_updates = array(
        'airducap_api_base_url' => 'https://uat-book.airducap.com',
        'airducap_api_username' => 'dev101@dev101.com',
        'airducap_api_password' => 'QgdYlFgTvAQTcCC',
        'airducap_default_currency' => 'ZAR',
        'airducap_enable_debug' => '1',
        'airducap_http_timeout' => '45',
        'airducap_ssl_verify' => '1'
    );

    echo "<p style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 4px;'>";
    echo "<strong>WordPress couldn't be loaded automatically.</strong><br><br>";
    echo "Please run these SQL commands in your database (phpMyAdmin, command line, etc.):<br><br>";

    foreach ($db_updates as $option => $value) {
        echo "<code style='background: #f8f9fa; padding: 2px 4px; margin: 2px 0; display: block;'>";
        echo "INSERT INTO wp_options (option_name, option_value) VALUES ('$option', '$value') ON DUPLICATE KEY UPDATE option_value = '$value';";
        echo "</code>";
    }

    echo "<br><strong>OR</strong> manually update via WordPress Admin:<br>";
    echo "Go to Settings → Air Du Cap API and set:<br>";
    echo "• API Base URL: https://uat-book.airducap.com<br>";
    echo "• Enable Debug Logging: Yes<br>";
    echo "• HTTP Timeout: 45 seconds<br>";
    echo "</p>";

    die();
}

// WordPress loaded successfully
echo "<h2>AirDuCap Plugin Database Fix</h2>";
echo "<p>WordPress loaded successfully. Updating plugin options to use UAT environment...</p>";

// Force update all plugin options to UAT environment
$updates = array(
    'airducap_api_username' => 'dev101@dev101.com',
    'airducap_api_password' => 'QgdYlFgTvAQTcCC',
    'airducap_api_base_url' => 'https://uat-book.airducap.com',
    'airducap_default_currency' => 'ZAR',
    'airducap_enable_debug' => '1',
    'airducap_http_timeout' => '45',
    'airducap_ssl_verify' => '1'
);

foreach ($updates as $option => $value) {
    $old_value = get_option($option, 'NOT_SET');
    update_option($option, $value);
    $new_value = get_option($option);

    echo "<div style='margin: 10px 0; padding: 10px; background: #f0f0f0; border-left: 4px solid #0073aa;'>";
    echo "<strong>$option:</strong><br>";
    echo "Old: " . ($old_value === 'NOT_SET' ? 'NOT_SET' : $old_value) . "<br>";
    echo "New: $new_value<br>";
    echo "</div>";
}

// Clear any cached data
echo "<h3>Clearing cached data...</h3>";
global $wpdb;
$cache_deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_airducap_%' OR option_name LIKE '_transient_timeout_airducap_%'");
echo "<p>Cleared $cache_deleted cached entries from database.</p>";

// Test the API connection with new settings
echo "<h3>Testing API Connection...</h3>";

$test_url = 'https://uat-book.airducap.com/airports/api/list/?q=cape&field_name=from_location';
$auth = base64_encode('dev101@dev101.com:QgdYlFgTvAQTcCC');

$response = wp_remote_get($test_url, array(
    'headers' => array(
        'Authorization' => 'Basic ' . $auth
    ),
    'timeout' => 30,
    'sslverify' => true,
));

if (is_wp_error($response)) {
    echo "<div style='color: red; background: #ffe6e6; padding: 10px; margin: 10px 0;'>";
    echo "<strong>ERROR:</strong> " . $response->get_error_message();
    echo "</div>";
} else {
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);

    echo "<div style='background: #e6ffe6; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Response Code:</strong> $code<br>";

    if ($code === 200) {
        $data = json_decode($body, true);
        if (is_array($data)) {
            echo "<strong>SUCCESS!</strong> Found " . count($data) . " airports.<br>";
            if (count($data) > 0) {
                echo "<strong>First airport:</strong> " . json_encode($data[0]) . "<br>";
            }
        } else {
            echo "<strong>Response:</strong> " . substr($body, 0, 200) . "<br>";
        }
    } else {
        echo "<strong>Error Response:</strong> " . substr($body, 0, 200) . "<br>";
    }
    echo "</div>";
}

echo "<hr>";
echo "<h3>✅ Database Update Complete!</h3>";
echo "<h4>Next Steps:</h4>";
echo "<ul>";
echo "<li>1. Delete this file for security: <code>fix-database-options.php</code></li>";
echo "<li>2. Test the airport search on your website frontend</li>";
echo "<li>3. Go to WordPress Admin → Settings → Air Du Cap API to verify settings</li>";
echo "<li>4. Use the admin testing dashboard to verify API connection</li>";
echo "</ul>";

echo "<div style='background: #e7f3ff; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa;'>";
echo "<h4>Manual Postman Test (if needed):</h4>";
echo "<strong>Working UAT Endpoint:</strong><br>";
echo "<code>GET https://uat-book.airducap.com/airports/api/list/?q=cape%20winelands&field_name=from_location</code><br>";
echo "<strong>Authorization:</strong> Basic Auth<br>";
echo "<strong>Username:</strong> dev101@dev101.com<br>";
echo "<strong>Password:</strong> QgdYlFgTvAQTcCC<br>";
echo "</div>";
?>
