<?php
/**
 * Chat logs admin page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap mcb-admin-wrap">
    <h1><?php esc_html_e( 'Chat Logs', 'medical-chatbot' ); ?></h1>
    <p class="description"><?php esc_html_e( 'Recent conversations with the chatbot (latest 100).', 'medical-chatbot' ); ?></p>

    <?php if ( empty( $logs ) ) : ?>
        <div class="mcb-no-logs">
            <p><?php esc_html_e( 'No chat logs yet. Conversations will appear here once visitors start using the chatbot.', 'medical-chatbot' ); ?></p>
        </div>
    <?php else : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:150px;"><?php esc_html_e( 'Date', 'medical-chatbot' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Session', 'medical-chatbot' ); ?></th>
                    <th style="width:100px;"><?php esc_html_e( 'Model', 'medical-chatbot' ); ?></th>
                    <th><?php esc_html_e( 'User Message', 'medical-chatbot' ); ?></th>
                    <th><?php esc_html_e( 'Bot Response', 'medical-chatbot' ); ?></th>
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
