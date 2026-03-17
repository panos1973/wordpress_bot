/**
 * Medical Chatbot Admin Scripts
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Scan Now button
        $('#mcb-scan-btn').on('click', function () {
            var $btn = $(this);
            var $status = $('#mcb-scan-status');

            $btn.prop('disabled', true).text('Scanning...');
            $status.text('');

            $.ajax({
                url: mcbAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mcb_run_scan',
                    nonce: mcbAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;
                        $status.text(
                            'Scan complete! ' + data.posts_scanned + ' posts scanned, ' +
                            data.chunks_created + ' content chunks created.'
                        ).css('color', '#00a32a');

                        // Update the stats display
                        setTimeout(function () {
                            location.reload();
                        }, 2000);
                    } else {
                        $status.text('Scan failed: ' + response.data).css('color', '#d63638');
                    }
                },
                error: function () {
                    $status.text('Scan failed due to a server error.').css('color', '#d63638');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Scan Now');
                }
            });
        });

        // Test Chat
        $('#mcb-test-btn').on('click', function () {
            var message = $('#mcb-test-input').val().trim();
            if (!message) return;

            var $btn = $(this);
            var $result = $('#mcb-test-result');

            $btn.prop('disabled', true).text('Testing...');
            $result.show().text('Sending request...');

            $.ajax({
                url: mcbAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mcb_test_chat',
                    nonce: mcbAdmin.nonce,
                    message: message
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;
                        var output = 'Model: ' + (data.model || 'N/A') + '\n\n';
                        output += 'Response:\n' + data.message;

                        if (data.sources && data.sources.length > 0) {
                            output += '\n\nSources:';
                            for (var i = 0; i < data.sources.length; i++) {
                                output += '\n- ' + data.sources[i].title + ' (' + data.sources[i].url + ')';
                            }
                        }

                        $result.text(output);
                    } else {
                        $result.text('Error: ' + (response.data || 'Unknown error'));
                    }
                },
                error: function () {
                    $result.text('Request failed. Check your server logs.');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Test');
                }
            });
        });

        // Allow Enter key in test input
        $('#mcb-test-input').on('keypress', function (e) {
            if (e.which === 13) {
                e.preventDefault();
                $('#mcb-test-btn').click();
            }
        });

        // Reschedule cron when frequency changes
        $('#mcb_scan_frequency').on('change', function () {
            // The cron will be rescheduled when settings are saved
            // Add a visual hint
            $(this).closest('td').find('.description').text(
                'Save settings to apply the new scan schedule.'
            ).css('color', '#d63638');
        });
    });
})(jQuery);
