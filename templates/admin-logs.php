<?php
/**
 * Chat logs admin page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap mcb-admin-wrap">
    <h1><?php esc_html_e( 'Ιστορικό Συνομιλιών', 'medical-chatbot' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Πρόσφατες συνομιλίες με το chatbot (τελευταίες 100).', 'medical-chatbot' ); ?></p>

    <?php if ( empty( $logs ) ) : ?>
        <div class="mcb-no-logs">
            <p><?php esc_html_e( 'Δεν υπάρχουν συνομιλίες ακόμα. Οι συνομιλίες θα εμφανιστούν εδώ μόλις οι επισκέπτες αρχίσουν να χρησιμοποιούν το chatbot.', 'medical-chatbot' ); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:150px;"><?php esc_html_e( 'Ημερομηνία', 'medical-chatbot' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Συνεδρία', 'medical-chatbot' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Μοντέλο', 'medical-chatbot' ); ?></th>
                    <th><?php esc_html_e( 'Μήνυμα Χρήστη', 'medical-chatbot' ); ?></th>
                    <th><?php esc_html_e( 'Απάντηση Bot', 'medical-chatbot' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $logs as $log ) : ?>
                <tr>
                    <td><?php echo esc_html( $log->created_at ); ?></td>
                    <td><code><?php echo esc_html( substr( $log->session_id, 0, 8 ) ); ?>...</code></td>
                    <td><?php echo esc_html( $log->model_used ); ?></td>
                    <td><?php echo esc_html( wp_trim_words( $log->user_message, 20 ) ); ?></td>
                    <td><?php echo esc_html( wp_trim_words( $log->bot_response, 30 ) ); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
