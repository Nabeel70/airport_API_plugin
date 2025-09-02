<?php
/**
 * Plugin Name: Air Du Cap API Integration
 * Plugin URI: https://airducap.com
 * Description: Integration with Air Du Cap API for flight booking and airport search functionality
 * Version: 1.0.3
 * Author: Mr. Developer
 * License: GPL v2 or later
 * Text Domain: airducap-integration
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('AIRDUCAP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('AIRDUCAP_PLUGIN_PATH', plugin_dir_path(__FILE__));
// Point to UAT environment since production is giving 404s
define('AIRDUCAP_API_BASE_URL', 'https://uat-book.airducap.com');

class AirDuCapIntegration {
    
    private $api_username;
    private $api_password;
    private $api_base_url;
    private $default_currency;
    private $http_timeout;
    private $ssl_verify;

    public function __construct() {
        // Get settings from database or use defaults
        $this->api_username = get_option('airducap_api_username', 'dev101@dev101.com');
        $this->api_password = get_option('airducap_api_password', 'QgdYlFgTvAQTcCC');
        // Use UAT as the fallback default since production gives 404s
        $this->api_base_url = get_option('airducap_api_base_url', 'https://uat-book.airducap.com');
        $this->default_currency = get_option('airducap_default_currency', 'ZAR');
        $this->http_timeout = intval(get_option('airducap_http_timeout', 45)) ?: 45; // Increased default timeout
        $this->ssl_verify = get_option('airducap_ssl_verify', '1') === '1';

        add_action('init', array($this, 'init'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_airducap_search_airports', array($this, 'ajax_search_airports'));
        add_action('wp_ajax_nopriv_airducap_search_airports', array($this, 'ajax_search_airports'));
        add_action('wp_ajax_airducap_search_flights', array($this, 'ajax_search_flights'));
        add_action('wp_ajax_nopriv_airducap_search_flights', array($this, 'ajax_search_flights'));
        add_action('wp_ajax_airducap_test_connection', array($this, 'ajax_test_connection'));
        // Proxy for Google Static Map fallback
        add_action('wp_ajax_airducap_proxy_map', array($this, 'ajax_proxy_map'));
        add_action('wp_ajax_nopriv_airducap_proxy_map', array($this, 'ajax_proxy_map'));
        // Network diagnostic tool
        add_action('wp_ajax_airducap_network_test', array($this, 'ajax_network_test'));
        add_shortcode('airducap_search_form', array($this, 'render_search_form'));
        add_shortcode('airducap_flight_results', array($this, 'render_flight_results'));
        
        // Include admin functionality
        if (is_admin()) {
            include_once AIRDUCAP_PLUGIN_PATH . 'admin/admin.php';
            new AirDuCapAdmin();
            include_once AIRDUCAP_PLUGIN_PATH . 'admin/api-test.php';
        }
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'activate'));
    }
    
    public function activate() {
        // Set default options
        add_option('airducap_api_username', 'dev101@dev101.com');
        add_option('airducap_api_password', 'QgdYlFgTvAQTcCC');
        // Default to UAT API since production gives 404s
        add_option('airducap_api_base_url', 'https://uat-book.airducap.com');
        add_option('airducap_default_currency', 'ZAR');
        add_option('airducap_enable_debug', '1'); // Enable debug by default to troubleshoot
        add_option('airducap_http_timeout', 45); // Increased default
        add_option('airducap_ssl_verify', '1');
    }
    
    public function init() {
        // Initialize plugin
        load_plugin_textdomain('airducap-integration', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_scripts() {
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-datepicker');
        wp_enqueue_style('jquery-ui-datepicker', 'https://code.jquery.com/ui/1.12.1/themes/ui-lightness/jquery-ui.css');
        wp_enqueue_script('airducap-js', AIRDUCAP_PLUGIN_URL . 'assets/airducap.js', array('jquery'), '1.0.3', true);
        wp_enqueue_style('airducap-css', AIRDUCAP_PLUGIN_URL . 'assets/airducap.css', array(), '1.0.2');

        // Localize script for AJAX
        wp_localize_script('airducap-js', 'airducap_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('airducap_nonce'),
            // Expose defaults to the frontend so requests match live
            'default_currency' => $this->default_currency,
            'api_base_url' => $this->api_base_url,
        ));
    }
    
    /**
     * Make API request with authentication with retry support
     */
    private function make_api_request($endpoint, $params = array()) {
        // Clean the base URL and ensure proper formatting
        $base_url = rtrim($this->api_base_url, '/');
        $url = $base_url . $endpoint;

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_username . ':' . $this->api_password),
                'Content-Type' => 'application/json',
                'User-Agent' => 'AirDuCap-WordPress-Plugin/1.0',
            ),
            'timeout' => $this->http_timeout,
            'redirection' => 5,
            'sslverify' => $this->ssl_verify,
            'httpversion' => '1.1',
            'decompress' => true,
        );
        
        // Debug logging
        if (get_option('airducap_enable_debug', '0') === '1') {
            error_log('AirDuCap API Request URL: ' . $url);
            error_log('AirDuCap API Auth: ' . base64_encode($this->api_username . ':' . $this->api_password));
            error_log('AirDuCap API Request Timeout: ' . $this->http_timeout . 's');
        }
        
        $attempts = 0;
        $max_attempts = 2; // 1 retry on transient network timeouts
        do {
            $attempts++;
            $response = wp_remote_get($url, $args);

            if (is_wp_error($response)) {
                $error = $response->get_error_message();
                if (get_option('airducap_enable_debug', '0') === '1') {
                    error_log('AirDuCap API Error (attempt ' . $attempts . '): ' . $error);
                }
                // Retry only on timeouts
                if ($attempts < $max_attempts && (stripos($error, 'timed out') !== false || stripos($error, 'cURL error 28') !== false)) {
                    // brief backoff
                    usleep(500000); // 500ms
                    continue;
                }
                return array('error' => $error);
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            if (get_option('airducap_enable_debug', '0') === '1') {
                error_log('AirDuCap API Response Code: ' . $response_code);
                error_log('AirDuCap API Response Body (truncated): ' . substr($body, 0, 500));
            }

            if ($response_code !== 200) {
                // Retry on 429/5xx once
                if ($attempts < $max_attempts && in_array($response_code, array(429, 500, 502, 503, 504), true)) {
                    usleep(500000);
                    continue;
                }
                return array('error' => 'API returned status code: ' . $response_code . '. Response: ' . $body);
            }

            $data = json_decode($body, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return array('error' => 'Invalid JSON response: ' . json_last_error_msg());
            }

            return $data;
        } while ($attempts < $max_attempts);

        return array('error' => 'Unknown error');
    }
    
    /**
     * Normalize date to dd/mm/YYYY regardless of input format
     */
    private function normalize_date($date) {
        if (empty($date)) return '';
        $date = trim($date);
        // If already dd/mm/yyyy
        if (preg_match('#^\d{2}/\d{2}/\d{4}$#', $date)) {
            return $date;
        }
        // Try common formats
        $formats = ['Y-m-d', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            $dt = DateTime::createFromFormat($fmt, $date);
            if ($dt && $dt->format($fmt) === $date) {
                return $dt->format('d/m/Y');
            }
        }
        // Fallback: attempt strtotime
        $ts = strtotime($date);
        if ($ts) {
            return date('d/m/Y', $ts);
        }
        return $date;
    }

    /**
     * AJAX handler for API connection test
     */
    public function ajax_test_connection() {
        check_ajax_referer('airducap_nonce', 'nonce');

        // Test with a simple API call - no hardcoded data
        $test_params = array(
            'q' => 'cape',
            'field_name' => 'from_location'
        );

        $result = $this->make_api_request('/airports/api/list/', $test_params);

        if (isset($result['error'])) {
            wp_send_json_error('Connection failed: ' . $result['error']);
        } else {
            $count = is_array($result) ? count($result) : 0;
            wp_send_json_success("✅ API connection successful! Found $count airports with test search. API is responding correctly.");
        }
    }

    /**
     * AJAX handler for airport search (with caching and better errors)
     */
    public function ajax_search_airports() {
        check_ajax_referer('airducap_nonce', 'nonce');

        $search_term = isset($_POST['q']) ? sanitize_text_field(wp_unslash($_POST['q'])) : '';
        $field_name = isset($_POST['field_name']) ? sanitize_text_field(wp_unslash($_POST['field_name'])) : '';
        $from_location = isset($_POST['from_location']) ? intval($_POST['from_location']) : 0;
        $to_location = isset($_POST['to_location']) ? intval($_POST['to_location']) : 0;

        if (empty($search_term) || strlen($search_term) < 2) {
            wp_send_json_error(['message' => 'Search term must be at least 2 characters long.']);
        }

        $cache_key = 'airducap_airports_' . md5(implode('|', array($this->api_base_url, $field_name, $from_location, $to_location, strtolower($search_term))));
        $cached = get_transient($cache_key);
        if ($cached) {
            wp_send_json_success(['data' => $cached, 'cached' => true]);
        }

        // Use the documented endpoint
        $params = array(
            'q' => $search_term,
            'field_name' => $field_name,
        );
        if ($from_location) { $params['from_location'] = $from_location; }
        if ($to_location) { $params['to_location'] = $to_location; }

        $response = $this->make_api_request('/airports/api/list/', $params);

        // Force debug logging for this specific call
        error_log('AIRPORT SEARCH DEBUG - Search Term: ' . $search_term);
        error_log('AIRPORT SEARCH DEBUG - Timeout: ' . $this->http_timeout . 's');
        error_log('AIRPORT SEARCH DEBUG - Raw API Response: ' . print_r($response, true));

        if (isset($response['error'])) {
            error_log('AIRPORT SEARCH DEBUG - API Error: ' . $response['error']);
            $msg = $response['error'];
            // Normalize common cURL timeout message for UX
            if (stripos($msg, 'cURL error 28') !== false || stripos($msg, 'timed out') !== false) {
                $msg = 'Connection to the API timed out. Please try again in a moment.';
            }
            wp_send_json_error(['message' => 'API request failed.', 'error' => $msg]);
        }

        // Handle the response - per API docs, it's an array of airports
        if (empty($response)) {
            error_log('AIRPORT SEARCH DEBUG - Empty response from API');
            wp_send_json_error(['message' => 'No airports found - empty response.']);
        }

        if (!is_array($response)) {
            error_log('AIRPORT SEARCH DEBUG - Response is not array, type: ' . gettype($response));
            wp_send_json_error(['message' => 'Invalid response format - not an array.']);
        }

        if (count($response) === 0) {
            error_log('AIRPORT SEARCH DEBUG - Response is empty array');
            wp_send_json_error(['message' => 'No airports found - empty array.']);
        }

        // Cache for 30 minutes to improve UX and reduce API pressure
        set_transient($cache_key, $response, 30 * MINUTE_IN_SECONDS);

        error_log('AIRPORT SEARCH DEBUG - Success! Found ' . count($response) . ' airports');
        error_log('AIRPORT SEARCH DEBUG - First airport: ' . print_r($response[0] ?? 'none', true));

        wp_send_json_success(['data' => $response]);
    }
    
    /**
     * AJAX handler for flight search
     */
    public function ajax_search_flights() {
        check_ajax_referer('airducap_nonce', 'nonce');
        
        $from_location = intval($_POST['from_location']);
        $to_location = intval($_POST['to_location']);
        $raw_date_of_travel = isset($_POST['date_of_travel']) ? wp_unslash($_POST['date_of_travel']) : '';
        $date_of_travel = sanitize_text_field($this->normalize_date($raw_date_of_travel));
        $adults = intval($_POST['adults']);
        
        if (empty($from_location) || empty($to_location) || empty($date_of_travel) || empty($adults)) {
            wp_send_json_error('Missing required parameters: from_location, to_location, date_of_travel, and adults are required');
            return;
        }
        
        $params = array(
            'from_location' => $from_location,
            'to_location' => $to_location,
            'date_of_travel' => $date_of_travel,
            'adults' => $adults,
            'ip_address' => $_SERVER['REMOTE_ADDR']
        );
        
        // Optional parameters
        if (!empty($_POST['date_of_return'])) {
            $raw_return = wp_unslash($_POST['date_of_return']);
            $params['date_of_return'] = sanitize_text_field($this->normalize_date($raw_return));
        }
        if (!empty($_POST['children'])) {
            $params['children'] = intval($_POST['children']);
        }
        if (!empty($_POST['infants'])) {
            $params['infants'] = intval($_POST['infants']);
        }
        // Always provide currency: posted value wins; otherwise use default from settings
        if (!empty($_POST['currency'])) {
            $params['currency'] = sanitize_text_field($_POST['currency']);
        } else {
            $params['currency'] = $this->default_currency ?: 'ZAR';
        }
        
        // Debug logging for final params
        if (get_option('airducap_enable_debug', '0') === '1') {
            error_log('AirDuCap Flights Search Params: ' . print_r($params, true));
        }

        $flights = $this->make_api_request('/flights/api/search/', $params);
        
        if (isset($flights['error'])) {
            wp_send_json_error($flights['error']);
        } else {
            wp_send_json_success($flights);
        }
    }
    
    /**
     * Render search form shortcode
     */
    public function render_search_form($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default'
        ), $atts);
        
        ob_start();
        ?>
        <div class="airducap-search-form">
            <h2>Find Your Perfect Flight</h2>

            <div class="flight-search-tabs">
                <button type="button" class="tab-button active">Flight Search</button>
                <button type="button" class="tab-button">Round Trip</button>
            </div>

            <form id="airducap-flight-search" class="airducap-form">
                <?php wp_nonce_field('airducap_nonce', 'airducap_nonce'); ?>

                <div class="form-row">
                    <div class="form-group origin">
                        <label for="origin">Origin</label>
                        <input type="text" id="origin" name="origin" class="airport-search"
                               data-field="from_location" placeholder="Enter origin airport" required>
                        <input type="hidden" id="from_location" name="from_location">
                        <div class="airport-suggestions" id="origin-suggestions"></div>
                    </div>

                    <div class="form-group destination">
                        <label for="destination">Destination</label>
                        <input type="text" id="destination" name="destination" class="airport-search"
                               data-field="to_location" placeholder="Enter destination airport" required>
                        <input type="hidden" id="to_location" name="to_location">
                        <div class="airport-suggestions" id="destination-suggestions"></div>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group date-group">
                        <label for="depart_date">Departure Date</label>
                        <input type="text" id="depart_date" name="depart_date" class="date-input" placeholder="dd/mm/yyyy" required>
                    </div>

                    <div class="form-group date-group">
                        <label for="return_date">Return Date (Optional)</label>
                        <input type="text" id="return_date" name="return_date" class="date-input" placeholder="dd/mm/yyyy">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group passenger-group">
                        <label for="adults">Adults</label>
                        <input type="number" id="adults" name="adults" min="1" max="9" value="1" required>
                    </div>

                    <div class="form-group">
                        <label for="children">Children</label>
                        <input type="number" id="children" name="children" min="0" max="9" value="0">
                    </div>

                    <div class="form-group">
                        <label for="infants">Infants</label>
                        <input type="number" id="infants" name="infants" min="0" max="9" value="0">
                    </div>
                </div>

                <div class="search-button-container">
                    <button type="submit" class="btn-search">Search Flights</button>
                </div>
            </form>

            <div id="flight-results"></div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render flight results shortcode
     */
    public function render_flight_results($atts) {
        return '<div id="airducap-flight-results-container"></div>';
    }

    /**
     * Test API connection
     */
    public function test_api_connection() {
        $test_params = array(
            'q' => 'new',
            'field_name' => 'from_location'
        );

        $result = $this->make_api_request('/airports/api/list/', $test_params);

        if (isset($result['error'])) {
            return array('status' => 'error', 'message' => $result['error']);
        } else {
            return array('status' => 'success', 'message' => 'Connected successfully');
        }
    }

    /**
     * Proxy Google Static Map URL to bypass referrer restrictions when needed
     */
    public function ajax_proxy_map() {
        // nonce passed as GET param because images load via GET
        check_ajax_referer('airducap_nonce', 'nonce');

        $url = isset($_GET['url']) ? esc_url_raw(wp_unslash($_GET['url'])) : '';
        if (!$url || stripos($url, 'https://maps.googleapis.com/maps/api/staticmap') !== 0) {
            status_header(400);
            echo 'Invalid URL';
            wp_die();
        }

        $response = wp_remote_get($url, array(
            'timeout' => min($this->http_timeout, 20),
            'sslverify' => true,
        ));

        if (is_wp_error($response)) {
            status_header(502);
            echo 'Map fetch error';
            wp_die();
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $ctype = wp_remote_retrieve_header($response, 'content-type');

        if ($code !== 200 || empty($body)) {
            status_header(502);
            echo 'Map not available';
            wp_die();
        }

        if (!$ctype) { $ctype = 'image/png'; }
        header('Content-Type: ' . $ctype);
        header('Cache-Control: max-age=3600');
        echo $body;
        wp_die();
    }

    /**
     * Network diagnostic tool
     */
    public function ajax_network_test() {
        check_ajax_referer('airducap_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $diagnostics = array();

        // Test basic connectivity to API hosts
        $hosts = array(
            'Production' => 'https://book.airducap.com',
            'UAT' => 'https://uat-book.airducap.com'
        );

        foreach ($hosts as $name => $host) {
            $start_time = microtime(true);
            $response = wp_remote_get($host . '/airports/api/list/?q=test&field_name=from_location', array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($this->api_username . ':' . $this->api_password)
                ),
                'timeout' => 15,
                'sslverify' => $this->ssl_verify
            ));
            $end_time = microtime(true);
            $duration = round(($end_time - $start_time) * 1000, 2);

            if (is_wp_error($response)) {
                $diagnostics[$name] = array(
                    'status' => 'ERROR',
                    'error' => $response->get_error_message(),
                    'duration' => $duration . 'ms'
                );
            } else {
                $code = wp_remote_retrieve_response_code($response);
                $diagnostics[$name] = array(
                    'status' => $code === 200 ? 'OK' : 'HTTP ' . $code,
                    'duration' => $duration . 'ms'
                );
            }
        }

        wp_send_json_success($diagnostics);
    }
}

// Initialize the plugin
new AirDuCapIntegration();
