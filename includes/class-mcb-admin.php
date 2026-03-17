<?php
/**
 * Admin Settings Page for Medical Chatbot.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCB_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'wp_ajax_mcb_run_scan', array( $this, 'ajax_run_scan' ) );
        add_action( 'wp_ajax_mcb_scan_init', array( $this, 'ajax_scan_init' ) );
        add_action( 'wp_ajax_mcb_scan_batch', array( $this, 'ajax_scan_batch' ) );
        add_action( 'wp_ajax_mcb_test_chat', array( $this, 'ajax_test_chat' ) );
    }

    public function add_admin_menu() {
        add_menu_page(
            __( 'Medical Chatbot', 'medical-chatbot' ),
            __( 'Medical Chatbot', 'medical-chatbot' ),
            'manage_options',
            'medical-chatbot',
            array( $this, 'render_settings_page' ),
            'dashicons-format-chat',
            80
        );

        add_submenu_page(
            'medical-chatbot',
            __( 'Settings', 'medical-chatbot' ),
            __( 'Settings', 'medical-chatbot' ),
            'manage_options',
            'medical-chatbot',
            array( $this, 'render_settings_page' )
        );

        add_submenu_page(
            'medical-chatbot',
            __( 'Chat Logs', 'medical-chatbot' ),
            __( 'Chat Logs', 'medical-chatbot' ),
            'manage_options',
            'medical-chatbot-logs',
            array( $this, 'render_logs_page' )
        );
    }

    public function register_settings() {
        // API Settings
        register_setting( 'mcb_settings', 'mcb_ai_provider', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'mcb_settings', 'mcb_claude_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'mcb_settings', 'mcb_gemini_api_key', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );

        // Scan Settings
        register_setting( 'mcb_settings', 'mcb_scan_frequency', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'mcb_settings', 'mcb_post_types', array(
            'sanitize_callback' => array( $this, 'sanitize_post_types' ),
        ) );

        // Chat Settings
        register_setting( 'mcb_settings', 'mcb_chat_title', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'mcb_settings', 'mcb_welcome_message', array(
            'sanitize_callback' => 'sanitize_textarea_field',
        ) );
        register_setting( 'mcb_settings', 'mcb_primary_color', array(
            'sanitize_callback' => 'sanitize_hex_color',
        ) );
        register_setting( 'mcb_settings', 'mcb_max_context_chunks', array(
            'sanitize_callback' => 'absint',
        ) );
        register_setting( 'mcb_settings', 'mcb_phone_number', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
        register_setting( 'mcb_settings', 'mcb_phone_number_2', array(
            'sanitize_callback' => 'sanitize_text_field',
        ) );
    }

    public function sanitize_post_types( $input ) {
        if ( ! is_array( $input ) ) {
            return array( 'post', 'page' );
        }
        return array_map( 'sanitize_text_field', $input );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( false === strpos( $hook, 'medical-chatbot' ) ) {
            return;
        }

        wp_enqueue_style(
            'mcb-admin',
            MCB_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            MCB_VERSION
        );

        wp_enqueue_script(
            'mcb-admin',
            MCB_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery' ),
            MCB_VERSION,
            true
        );

        wp_localize_script( 'mcb-admin', 'mcbAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'mcb_admin_nonce' ),
        ) );
    }

    public function render_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $scanner = new MCB_Content_Scanner();
        $stats = $scanner->get_stats();

        include MCB_PLUGIN_DIR . 'templates/admin-settings.php';
    }

    public function render_logs_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'mcb_chat_logs';
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );

        $logs = array();
        if ( $table_exists ) {
            $logs = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100"
            );
        }

        include MCB_PLUGIN_DIR . 'templates/admin-logs.php';
    }

    public function ajax_run_scan() {
        check_ajax_referer( 'mcb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $scanner = new MCB_Content_Scanner();
        $results = $scanner->run_scan();

        wp_send_json_success( $results );
    }

    public function ajax_scan_init() {
        check_ajax_referer( 'mcb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $scanner = new MCB_Content_Scanner();
        $results = $scanner->batch_init();

        wp_send_json_success( $results );
    }

    public function ajax_scan_batch() {
        check_ajax_referer( 'mcb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;

        $scanner = new MCB_Content_Scanner();
        $results = $scanner->batch_process( $offset );

        wp_send_json_success( $results );
    }

    public function ajax_test_chat() {
        check_ajax_referer( 'mcb_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $message = isset( $_POST['message'] ) ? sanitize_text_field( wp_unslash( $_POST['message'] ) ) : '';

        if ( empty( $message ) ) {
            wp_send_json_error( 'Message is required.' );
        }

        $engine = new MCB_Chat_Engine();
        $response = $engine->get_response( $message );

        wp_send_json_success( $response );
    }
}
