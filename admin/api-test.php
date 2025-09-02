<?php
/**
 * Air Du Cap API Test Page
 */

if (!defined('ABSPATH')) {
    exit;
}

class AirDuCapAPITest {

    public function __construct() {
        // Can be used for future AJAX handlers if needed
    }

    public function render_test_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $api_base_url = get_option('airducap_api_base_url', 'https://book.airducap.com');
        $api_username = get_option('airducap_api_username', 'dev101@dev101.com');
        $api_password = get_option('airducap_api_password', 'QgdYlFgTvAQTcCC');

        $response = wp_remote_get($api_base_url . '/api/airports', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($api_username . ':' . $api_password),
            ],
        ]);

        $data = is_wp_error($response) ? $response->get_error_message() : json_decode(wp_remote_retrieve_body($response), true);

        ?>
        <div class="wrap">
            <h1>Air Du Cap - API Test Dashboard</h1>

            <div class="notice notice-info">
                <p>This dashboard helps you test the connection to the Air Du Cap API and inspect the raw data returned.</p>
            </div>

            <div class="card">
                <h2>API Response</h2>
                <pre><?php echo esc_html(print_r($data, true)); ?></pre>
            </div>
        </div>
        <?php
    }
}
