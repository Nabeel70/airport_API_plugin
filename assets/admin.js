jQuery(document).ready(function($) {
    // Test API Connection on main settings page
    $('#test-api-connection').on('click', function() {
        var button = $(this);
        var statusSpan = $('#api-test-status');

        button.prop('disabled', true).text('Testing...');
        statusSpan.text('');

        $.ajax({
            url: airducap_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'airducap_test_connection',
                nonce: airducap_admin_ajax.nonce
            },
            success: function(response) {
                if (response.success) {
                    statusSpan.css('color', 'green').text('✅ ' + response.data);
                } else {
                    statusSpan.css('color', 'red').text('❌ ' + response.data);
                }
            },
            error: function(xhr, status, error) {
                statusSpan.css('color', 'red').text('❌ AJAX request failed: ' + error);
            },
            complete: function() {
                button.prop('disabled', false).text('Test Connection');
            }
        });
    });
});

