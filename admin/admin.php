<?php
/**
 * Admin settings page for Air Du Cap Integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class AirDuCapAdmin {

    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_airducap_admin_test_api', array($this, 'ajax_admin_test_api'));
        add_action('wp_ajax_airducap_admin_search_airports', array($this, 'ajax_admin_search_airports'));
        add_action('wp_ajax_airducap_admin_test_flight_search', array($this, 'ajax_admin_test_flight_search'));
        add_action('wp_ajax_airducap_admin_force_update', array($this, 'ajax_admin_force_update'));
    }

    public function add_admin_menu() {
        add_options_page(
            'Air Du Cap Settings',
            'Air Du Cap API',
            'manage_options',
            'airducap-settings',
            array($this, 'admin_page')
        );
    }

    public function register_settings() {
        register_setting('airducap_settings', 'airducap_api_username');
        register_setting('airducap_settings', 'airducap_api_password');
        register_setting('airducap_settings', 'airducap_api_base_url');
        register_setting('airducap_settings', 'airducap_default_currency');
        register_setting('airducap_settings', 'airducap_enable_debug');
        // New settings for timeout and SSL
        register_setting('airducap_settings', 'airducap_http_timeout');
        register_setting('airducap_settings', 'airducap_ssl_verify');
    }

    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>Air Du Cap API Settings & Testing</h1>

            <div style="display: flex; gap: 20px;">
                <!-- Settings Form -->
                <div style="flex: 1; max-width: 600px;">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('airducap_settings');
                        do_settings_sections('airducap_settings');
                        ?>

                        <table class="form-table">
                            <tr>
                                <th scope="row">API Username</th>
                                <td>
                                    <input type="text" name="airducap_api_username"
                                           value="<?php echo esc_attr(get_option('airducap_api_username', 'dev101@dev101.com')); ?>"
                                           class="regular-text"/>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">API Password</th>
                                <td>
                                    <input type="password" name="airducap_api_password"
                                           value="<?php echo esc_attr(get_option('airducap_api_password', 'QgdYlFgTvAQTcCC')); ?>"
                                           class="regular-text"/>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">API Base URL</th>
                                <td>
                                    <input type="url" name="airducap_api_base_url"
                                           value="<?php echo esc_attr(get_option('airducap_api_base_url', 'https://uat-book.airducap.com')); ?>"
                                           class="regular-text"/>
                                    <p class="description">
                                        <strong>UAT (Working):</strong> https://uat-book.airducap.com<br>
                                        <strong>Production (404 Errors):</strong> https://book.airducap.com<br>
                                        <em style="color: red;">Use UAT environment until production is fixed</em>
                                    </p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Default Currency</th>
                                <td>
                                    <select name="airducap_default_currency">
                                        <option value="ZAR" <?php selected(get_option('airducap_default_currency', 'ZAR'), 'ZAR'); ?>>ZAR (South African Rand)</option>
                                        <option value="USD" <?php selected(get_option('airducap_default_currency'), 'USD'); ?>>USD (US Dollar)</option>
                                        <option value="EUR" <?php selected(get_option('airducap_default_currency'), 'EUR'); ?>>EUR (Euro)</option>
                                        <option value="GBP" <?php selected(get_option('airducap_default_currency'), 'GBP'); ?>>GBP (British Pound)</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">HTTP Timeout (seconds)</th>
                                <td>
                                    <input type="number" min="5" max="120" name="airducap_http_timeout" value="<?php echo esc_attr(intval(get_option('airducap_http_timeout', 30))); ?>" />
                                    <p class="description">Increase if you see cURL error 28 (timeout). Defaults to 30s.</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Verify SSL Certificates</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="airducap_ssl_verify" value="1" <?php checked(get_option('airducap_ssl_verify', '1'), '1'); ?> />
                                        Enable SSL verification (recommended). Disable only if your server has outdated CA bundle and API calls fail.
                                    </label>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Enable Debug Logging</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="airducap_enable_debug" value="1"
                                               <?php checked(get_option('airducap_enable_debug', '0'), '1'); ?> />
                                        Enable debug logging to PHP error log
                                    </label>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button(); ?>
                    </form>
                </div>

                <!-- API Testing Panel -->
                <div style="flex: 1; max-width: 600px;">
                    <div style="background: #f9f9f9; padding: 20px; border-radius: 8px;">
                        <h2>API Testing Dashboard</h2>

                        <!-- Force Database Update -->
                        <div style="margin-bottom: 30px; background: #fff3cd; padding: 15px; border-left: 4px solid #ffc107;">
                            <h3>⚠️ Fix Database Settings</h3>
                            <p>If you're getting 404 errors, click this button to force update all settings to UAT environment:</p>
                            <button type="button" class="button button-secondary" id="force-update-settings">
                                Force Update to UAT Environment
                            </button>
                            <div id="force-update-result" style="margin-top: 10px;"></div>
                        </div>

                        <!-- Connection Test -->
                        <div style="margin-bottom: 30px;">
                            <h3>1. Connection Test</h3>
                            <button type="button" class="button button-primary" id="test-connection">
                                Test API Connection
                            </button>
                            <div id="connection-result" style="margin-top: 10px;"></div>
                        </div>

                        <!-- Airport Search Test -->
                        <div style="margin-bottom: 30px;">
                            <h3>2. Airport Search Test</h3>
                            <p>Test different search terms to see available airports:</p>
                            <input type="text" id="airport-search-term" placeholder="Enter search term (e.g., london, virginia, new york)" style="width: 300px;"/>
                            <button type="button" class="button" id="test-airport-search">Search Airports</button>
                            <div id="airport-search-result" style="margin-top: 10px; max-height: 300px; overflow-y: auto;"></div>
                        </div>

                        <!-- Flight Search Test -->
                        <div style="margin-bottom: 30px;">
                            <h3>3. Flight Search Test</h3>
                            <p>Test flight search with sample data:</p>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                                <input type="number" id="test-from" placeholder="From Airport ID"/>
                                <input type="number" id="test-to" placeholder="To Airport ID"/>
                                <input type="text" id="test-date" placeholder="Date (DD/MM/YYYY)"/>
                                <input type="number" id="test-adults" placeholder="Adults" value="1"/>
                            </div>
                            <button type="button" class="button" id="test-flight-search">Search Flights</button>
                            <div id="flight-search-result" style="margin-top: 10px; max-height: 300px; overflow-y: auto;"></div>
                        </div>

                        <!-- API Endpoints -->
                        <div>
                            <h3>4. Available API Endpoints</h3>
                            <ul style="list-style-type: disc; margin-left: 20px;">
                                <li><code>/airports/api/list/?q=search_term&field_name=from_location</code> - Search airports</li>
                                <li><code>/flights/api/search/?from_location=ID&to_location=ID&date_of_travel=DD/MM/YYYY&adults=1</code> - Search flights</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Force update settings
            $('#force-update-settings').click(function() {
                var button = $(this);
                var result = $('#force-update-result');

                button.prop('disabled', true).text('Updating...');
                result.html('<p style="color: #666;">Updating settings to UAT environment...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'airducap_admin_force_update',
                        nonce: '<?php echo wp_create_nonce('airducap_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Force Update to UAT Environment');
                        if (response.success) {
                            result.html('<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px;">' + response.data + '</div>');
                        } else {
                            result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">Error: ' + response.data + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        button.prop('disabled', false).text('Force Update to UAT Environment');
                        result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">AJAX Error: ' + error + '</div>');
                    }
                });
            });

            // Test connection
            $('#test-connection').click(function() {
                var button = $(this);
                var result = $('#connection-result');

                button.prop('disabled', true).text('Testing...');
                result.html('<p style="color: #666;">Testing API connection...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'airducap_admin_test_api',
                        nonce: '<?php echo wp_create_nonce('airducap_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Test API Connection');
                        if (response.success) {
                            result.html('<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px;">' + response.data + '</div>');
                        } else {
                            result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">Error: ' + response.data + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        button.prop('disabled', false).text('Test API Connection');
                        result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">AJAX Error: ' + error + '</div>');
                    }
                });
            });

            // Test airport search
            $('#test-airport-search').click(function() {
                var button = $(this);
                var result = $('#airport-search-result');
                var searchTerm = $('#airport-search-term').val();

                if (!searchTerm) {
                    alert('Please enter a search term');
                    return;
                }

                button.prop('disabled', true).text('Searching...');
                result.html('<p style="color: #666;">Searching airports for: ' + searchTerm + '</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'airducap_admin_search_airports',
                        search_term: searchTerm,
                        nonce: '<?php echo wp_create_nonce('airducap_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Search Airports');
                        if (response.success) {
                            var airports = response.data;
                            var html = '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 10px;">Found ' + airports.length + ' airports</div>';
                            if (airports.length > 0) {
                                html += '<table style="width: 100%; border-collapse: collapse;">';
                                html += '<tr style="background: #f8f9fa;"><th style="text-align: left; padding: 5px; border: 1px solid #ddd;">ID</th><th style="text-align: left; padding: 5px; border: 1px solid #ddd;">Name</th></tr>';
                                airports.forEach(function(airport) {
                                    var id = airport.id || airport.pk || 'N/A';
                                    var name = airport.name || airport.title || airport.label || 'N/A';
                                    html += '<tr><td style="padding: 5px; border: 1px solid #ddd;">' + id + '</td><td style="padding: 5px; border: 1px solid #ddd;">' + name + '</td></tr>';
                                });
                                html += '</table>';
                            }
                            result.html(html);
                        } else {
                            result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">Error: ' + response.data + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        button.prop('disabled', false).text('Search Airports');
                        result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">AJAX Error: ' + error + '</div>');
                    }
                });
            });

            // Test flight search
            $('#test-flight-search').click(function() {
                var button = $(this);
                var result = $('#flight-search-result');
                var fromId = $('#test-from').val();
                var toId = $('#test-to').val();
                var date = $('#test-date').val();
                var adults = $('#test-adults').val() || 1;

                if (!fromId || !toId || !date) {
                    alert('Please fill in From Airport ID, To Airport ID, and Date');
                    return;
                }

                button.prop('disabled', true).text('Searching...');
                result.html('<p style="color: #666;">Searching flights...</p>');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'airducap_admin_test_flight_search',
                        from_location: fromId,
                        to_location: toId,
                        date_of_travel: date,
                        adults: adults,
                        nonce: '<?php echo wp_create_nonce('airducap_admin_nonce'); ?>'
                    },
                    success: function(response) {
                        button.prop('disabled', false).text('Search Flights');
                        if (response.success) {
                            var data = response.data;
                            var html = '<div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 4px; margin-bottom: 10px;">Flight search successful!</div>';
                            html += '<pre style="background: #f8f9fa; padding: 10px; border-radius: 4px; font-size: 12px; overflow: auto;">' + JSON.stringify(data, null, 2) + '</pre>';
                            result.html(html);
                        } else {
                            result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">Error: ' + response.data + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        button.prop('disabled', false).text('Search Flights');
                        result.html('<div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 4px;">AJAX Error: ' + error + '</div>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // AJAX handlers for admin testing
    public function ajax_admin_test_api() {
        check_ajax_referer('airducap_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Get current settings and clean the URL
        $api_url = rtrim(get_option('airducap_api_base_url', 'https://uat-book.airducap.com'), '/');
        $username = get_option('airducap_api_username', 'dev101@dev101.com');
        $password = get_option('airducap_api_password', 'QgdYlFgTvAQTcCC');
        $timeout = intval(get_option('airducap_http_timeout', 30)) ?: 30;
        $ssl_verify = get_option('airducap_ssl_verify', '1') === '1';

        // Test with a simple search term to verify API connectivity
        $test_term = 'cape';
        $url = $api_url . '/airports/api/list/?q=' . urlencode($test_term) . '&field_name=from_location';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ),
            'timeout' => $timeout,
            'sslverify' => $ssl_verify,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Connection error for URL: ' . $url . '. Error: ' . $response->get_error_message());
            return;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            wp_send_json_error('API returned status code: ' . $code . ' for URL: ' . $url . '. Response: ' . substr($body, 0, 200));
            return;
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            wp_send_json_error('Invalid response format - expected array, got: ' . gettype($data) . '. Raw response: ' . substr($body, 0, 200));
            return;
        }

        // Success response with actual API data
        $message = "✅ API Connection Successful!\n";
        $message .= "Tested with search term '" . $test_term . "' and found " . count($data) . " airports.\n";
        $message .= "API URL: " . $url . "\n";
        $message .= "Response time: " . $timeout . "s timeout configured.\n\n";

        if (count($data) > 0) {
            $message .= "Sample airport data:\n";
            $first_airport = $data[0];
            $message .= "ID: " . ($first_airport['id'] ?? 'N/A') . ", Name: " . ($first_airport['name'] ?? 'N/A');
        }

        wp_send_json_success($message);
    }

    public function ajax_admin_search_airports() {
        check_ajax_referer('airducap_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $search_term = sanitize_text_field($_POST['search_term']);
        $timeout = intval(get_option('airducap_http_timeout', 30)) ?: 30;
        $ssl_verify = get_option('airducap_ssl_verify', '1') === '1';

        // Clean the URL and ensure proper format
        $api_url = rtrim(get_option('airducap_api_base_url', 'https://uat-book.airducap.com'), '/');
        $username = get_option('airducap_api_username', 'dev101@dev101.com');
        $password = get_option('airducap_api_password', 'QgdYlFgTvAQTcCC');

        $url = $api_url . '/airports/api/list/?q=' . urlencode($search_term) . '&field_name=from_location';

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ),
            'timeout' => $timeout,
            'sslverify' => $ssl_verify,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Connection error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            wp_send_json_error('API returned status code: ' . $code . ' for URL: ' . $url . '. Response: ' + substr($body, 0, 200));
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            wp_send_json_error('Invalid response format - expected array, got: ' . gettype($data) . '. Raw response: ' + substr($body, 0, 200));
        }

        wp_send_json_success($data);
    }

    public function ajax_admin_test_flight_search() {
        check_ajax_referer('airducap_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $timeout = intval(get_option('airducap_http_timeout', 30)) ?: 30;
        $ssl_verify = get_option('airducap_ssl_verify', '1') === '1';

        // Clean the URL and ensure proper format
        $api_url = rtrim(get_option('airducap_api_base_url', 'https://uat-book.airducap.com'), '/');
        $username = get_option('airducap_api_username', 'dev101@dev101.com');
        $password = get_option('airducap_api_password', 'QgdYlFgTvAQTcCC');

        $params = array(
            'from_location' => intval($_POST['from_location']),
            'to_location' => intval($_POST['to_location']),
            'date_of_travel' => sanitize_text_field($_POST['date_of_travel']),
            'adults' => intval($_POST['adults']),
            'ip_address' => $_SERVER['REMOTE_ADDR'],
            'currency' => get_option('airducap_default_currency', 'ZAR')
        );

        $url = $api_url . '/flights/api/search/?' + http_build_query($params);

        $response = wp_remote_get($url, array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($username . ':' . $password)
            ),
            'timeout' => $timeout,
            'sslverify' => $ssl_verify,
        ));

        if (is_wp_error($response)) {
            wp_send_json_error('Connection error: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            wp_send_json_error('API returned status code: ' . $code . ' for URL: ' . $url . '. Response: ' + substr($body, 0, 200));
        }

        $data = json_decode($body, true);

        wp_send_json_success($data);
    }

    public function ajax_admin_force_update() {
        check_ajax_referer('airducap_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        // Force update all settings to UAT environment
        $updates = array(
            'airducap_api_base_url' => 'https://uat-book.airducap.com',
            'airducap_api_username' => 'dev101@dev101.com',
            'airducap_api_password' => 'QgdYlFgTvAQTcCC',
            'airducap_default_currency' => 'ZAR',
            'airducap_enable_debug' => '1',
            'airducap_http_timeout' => '45',
            'airducap_ssl_verify' => '1'
        );

        $results = array();
        foreach ($updates as $option => $value) {
            $old_value = get_option($option, 'NOT_SET');
            update_option($option, $value);
            $new_value = get_option($option);
            $results[] = "$option: $old_value → $new_value";
        }

        // Clear cached data
        global $wpdb;
        $cache_deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_airducap_%' OR option_name LIKE '_transient_timeout_airducap_%'");

        $message = "✅ Settings forcefully updated to UAT environment!\n\n";
        $message .= "Updated options:\n" . implode("\n", $results);
        $message .= "\n\nCleared $cache_deleted cached entries.";
        $message .= "\n\nNow test the API connection below.";

        wp_send_json_success($message);
    }
}
