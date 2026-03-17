/**
 * Medical Chatbot Admin Scripts
 */
(function ($) {
    'use strict';

    $(document).ready(function () {
        // Scan Now button - batch scanning with progress
        $('#mcb-scan-btn').on('click', function () {
            var $btn = $(this);
            var $status = $('#mcb-scan-status');

            $btn.prop('disabled', true).text('Αρχικοποίηση...');
            $status.html('').css('color', '');

            // Step 1: Initialize the scan
            $.ajax({
                url: mcbAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'mcb_scan_init',
                    nonce: mcbAdmin.nonce
                },
                success: function (response) {
                    if (response.success) {
                        var total = response.data.total;
                        $status.html(
                            '<div class="mcb-progress-wrap">' +
                            '<div class="mcb-progress-bar"><div class="mcb-progress-fill" style="width:0%"></div></div>' +
                            '<div class="mcb-progress-text">Σάρωση 0 / ' + total + ' σελίδων...</div>' +
                            '</div>'
                        ).css('color', '');

                        // Step 2: Process batches
                        processBatch(0, total, 0);
                    } else {
                        $status.text('Η σάρωση απέτυχε: ' + response.data).css('color', '#d63638');
                        $btn.prop('disabled', false).text('Σάρωση Τώρα');
                    }
                },
                error: function () {
                    $status.text('Η σάρωση απέτυχε λόγω σφάλματος διακομιστή.').css('color', '#d63638');
                    $btn.prop('disabled', false).text('Σάρωση Τώρα');
                }
            });
        });

        function processBatch(offset, total, totalChunks) {
            var $btn = $('#mcb-scan-btn');
            var $fill = $('.mcb-progress-fill');
            var $text = $('.mcb-progress-text');
            var pct = Math.round((offset / total) * 100);

            $fill.css('width', pct + '%');
            $btn.text('Σάρωση... ' + pct + '%');

            $.ajax({
                url: mcbAdmin.ajaxUrl,
                type: 'POST',
                timeout: 120000,
                data: {
                    action: 'mcb_scan_batch',
                    nonce: mcbAdmin.nonce,
                    offset: offset
                },
                success: function (response) {
                    if (response.success) {
                        var data = response.data;
                        totalChunks += data.batch_chunks;
                        var processed = data.processed;
                        var newPct = Math.round((processed / total) * 100);

                        $fill.css('width', newPct + '%');
                        $text.text('Σάρωση ' + processed + ' / ' + total + ' σελίδων... (' + totalChunks + ' τμήματα)');

                        if (data.done) {
                            // Scan complete
                            $fill.css('width', '100%');
                            $text.html(
                                '<strong>Η σάρωση ολοκληρώθηκε!</strong> ' +
                                total + ' σελίδες, ' + totalChunks + ' τμήματα περιεχομένου.'
                            );
                            $text.css('color', '#00a32a');
                            $btn.prop('disabled', false).text('Σάρωση Τώρα');

                            setTimeout(function () {
                                location.reload();
                            }, 3000);
                        } else {
                            // Process next batch
                            processBatch(data.processed, total, totalChunks);
                        }
                    } else {
                        $text.text('Η σάρωση απέτυχε: ' + response.data).css('color', '#d63638');
                        $btn.prop('disabled', false).text('Σάρωση Τώρα');
                    }
                },
                error: function () {
                    $text.text('Σφάλμα κατά τη σάρωση. Δοκιμάστε ξανά.').css('color', '#d63638');
                    $btn.prop('disabled', false).text('Σάρωση Τώρα');
                }
            });
        }

        // Test Chat
        $('#mcb-test-btn').on('click', function () {
            var message = $('#mcb-test-input').val().trim();
            if (!message) return;

            var $btn = $(this);
            var $result = $('#mcb-test-result');

            $btn.prop('disabled', true).text('Δοκιμή...');
            $result.show().text('Αποστολή αιτήματος...');

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
                        var output = 'Μοντέλο: ' + (data.model || 'N/A') + '\n\n';
                        output += 'Απάντηση:\n' + data.message;

                        if (data.sources && data.sources.length > 0) {
                            output += '\n\nΠηγές:';
                            for (var i = 0; i < data.sources.length; i++) {
                                output += '\n- ' + data.sources[i].title + ' (' + data.sources[i].url + ')';
                            }
                        }

                        $result.text(output);
                    } else {
                        $result.text('Σφάλμα: ' + (response.data || 'Άγνωστο σφάλμα'));
                    }
                },
                error: function () {
                    $result.text('Το αίτημα απέτυχε. Ελέγξτε τα logs του server.');
                },
                complete: function () {
                    $btn.prop('disabled', false).text('Δοκιμή');
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
            $(this).closest('td').find('.description').text(
                'Αποθηκεύστε τις ρυθμίσεις για να εφαρμοστεί το νέο πρόγραμμα σάρωσης.'
            ).css('color', '#d63638');
        });
    });
})(jQuery);
