<?php
/**
 * Plugin Name: Medical Website Chatbot
 * Plugin URI: https://github.com/panos1973/wordpress_bot
 * Description: AI-powered chatbot for medical websites that answers visitor questions based on website content. Supports Claude Haiku 4.5 and Gemini models.
 * Version: 1.4.5
 * Author: Publisize
 * License: GPL v2 or later
 * Text Domain: medical-chatbot
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'MCB_VERSION', '1.4.5' );
define( 'MCB_DB_VERSION', '1.4.5' );
define( 'MCB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MCB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'MCB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoload classes
require_once MCB_PLUGIN_DIR . 'includes/class-mcb-content-scanner.php';
require_once MCB_PLUGIN_DIR . 'includes/class-mcb-chat-engine.php';
require_once MCB_PLUGIN_DIR . 'includes/class-mcb-admin.php';
require_once MCB_PLUGIN_DIR . 'includes/class-mcb-rest-api.php';
require_once MCB_PLUGIN_DIR . 'includes/class-mcb-frontend.php';
require_once MCB_PLUGIN_DIR . 'includes/class-mcb-cron.php';

/**
 * Main plugin class.
 */
final class Medical_Chatbot {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        register_activation_hook( __FILE__, array( $this, 'activate' ) );
        register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );

        add_action( 'init', array( $this, 'init' ) );
        add_action( 'plugins_loaded', array( $this, 'check_upgrade' ) );
    }

    /**
     * Plugin activation — runs on fresh install AND when re-activating after an upgrade.
     */
    public function activate() {
        $this->create_tables();
        $this->set_default_options();

        // Store the current version
        update_option( 'mcb_version', MCB_VERSION );
        update_option( 'mcb_db_version', MCB_DB_VERSION );

        // Schedule cron
        MCB_Cron::schedule_scan();

        flush_rewrite_rules();
    }

    /**
     * Check if the plugin was upgraded and run migrations if needed.
     * This runs on every page load via plugins_loaded, but the version
     * comparison ensures migration code only executes once per upgrade.
     */
    public function check_upgrade() {
        $installed_version = get_option( 'mcb_version', '0.0.0' );

        if ( version_compare( $installed_version, MCB_VERSION, '<' ) ) {
            $this->run_upgrade( $installed_version );
        }
    }

    /**
     * Run upgrade routines from the installed version to the current version.
     *
     * @param string $from_version The previously installed version.
     */
    private function run_upgrade( $from_version ) {
        // Always ensure tables are up to date (dbDelta handles schema changes)
        $this->create_tables();

        // Version-specific migrations — add new blocks as the plugin evolves.
        // Example for a future version:
        // if ( version_compare( $from_version, '1.1.0', '<' ) ) {
        //     // Migration code for 1.1.0
        // }

        // Reschedule cron in case frequency options changed
        MCB_Cron::reschedule_scan();

        // Update stored version so this migration won't run again
        update_option( 'mcb_version', MCB_VERSION );
        update_option( 'mcb_db_version', MCB_DB_VERSION );

        flush_rewrite_rules();
    }

    /**
     * Create or update database tables using dbDelta.
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $table_name = $wpdb->prefix . 'mcb_content_index';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            post_type varchar(50) NOT NULL DEFAULT '',
            title text NOT NULL,
            content longtext NOT NULL,
            url varchar(500) NOT NULL DEFAULT '',
            chunk_index int(11) NOT NULL DEFAULT 0,
            last_scanned datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
            PRIMARY KEY  (id),
            KEY post_id (post_id),
            KEY post_type (post_type)
        ) $charset_collate;";

        dbDelta( $sql );

        $chat_log_table = $wpdb->prefix . 'mcb_chat_logs';
        $sql2 = "CREATE TABLE $chat_log_table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL DEFAULT '',
            user_message text NOT NULL,
            bot_response text NOT NULL,
            model_used varchar(50) NOT NULL DEFAULT '',
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY session_id (session_id)
        ) $charset_collate;";

        dbDelta( $sql2 );
    }

    /**
     * Set default option values (only if not already set — preserves user config on upgrade).
     */
    private function set_default_options() {
        $defaults = array(
            'mcb_ai_provider'        => 'claude',
            'mcb_claude_api_key'     => '',
            'mcb_gemini_api_key'     => '',
            'mcb_gemini_model'       => 'gemini-3-flash-preview',
            'mcb_custom_prompt'      => '',
            'mcb_scan_frequency'     => 'monthly',
            'mcb_post_types'         => array( 'post', 'page' ),
            'mcb_chat_title'         => 'Medical Assistant',
            'mcb_welcome_message'    => 'Hello! I can help you find information from our website. How can I assist you today?',
            'mcb_primary_color'      => '#0073aa',
            'mcb_max_context_chunks' => 5,
            'mcb_last_scan'          => '',
        );

        foreach ( $defaults as $key => $value ) {
            if ( false === get_option( $key ) ) {
                add_option( $key, $value );
            }
        }
    }

    public function deactivate() {
        MCB_Cron::unschedule_scan();
        flush_rewrite_rules();
    }

    public function init() {
        // Initialize components
        new MCB_Admin();
        new MCB_REST_API();
        new MCB_Frontend();
        new MCB_Cron();
    }
}

Medical_Chatbot::get_instance();
