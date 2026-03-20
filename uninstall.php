<?php
/**
 * Uninstall handler - cleans up plugin data when deleted.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// Remove database tables
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcb_content_index" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}mcb_chat_logs" );

// Remove options
$options = array(
    'mcb_ai_provider',
    'mcb_claude_api_key',
    'mcb_gemini_api_key',
    'mcb_gemini_model',
    'mcb_custom_prompt',
    'mcb_scan_frequency',
    'mcb_post_types',
    'mcb_chat_title',
    'mcb_welcome_message',
    'mcb_primary_color',
    'mcb_max_context_chunks',
    'mcb_last_scan',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Clear scheduled cron
wp_clear_scheduled_hook( 'mcb_scheduled_scan' );
