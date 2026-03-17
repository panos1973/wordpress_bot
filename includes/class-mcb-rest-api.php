<?php
/**
 * REST API endpoints for the chatbot frontend.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCB_REST_API {

    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
    }

    public function register_routes() {
        register_rest_route( 'medical-chatbot/v1', '/chat', array(
            'methods'             => 'POST',
            'callback'            => array( $this, 'handle_chat' ),
            'permission_callback' => '__return_true', // Public endpoint
            'args'                => array(
                'message' => array(
                    'required'          => true,
                    'type'              => 'string',
                    'sanitize_callback' => 'sanitize_text_field',
                    'validate_callback' => function( $param ) {
                        return ! empty( $param ) && strlen( $param ) <= 1000;
                    },
                ),
                'history' => array(
                    'required' => false,
                    'type'     => 'array',
                    'default'  => array(),
                ),
            ),
        ) );

        register_rest_route( 'medical-chatbot/v1', '/status', array(
            'methods'             => 'GET',
            'callback'            => array( $this, 'handle_status' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public function handle_chat( $request ) {
        $message = $request->get_param( 'message' );
        $history = $request->get_param( 'history' );

        // Basic rate limiting by IP
        $ip = $this->get_client_ip();
        $transient_key = 'mcb_rate_' . md5( $ip );
        $request_count = (int) get_transient( $transient_key );

        if ( $request_count > 30 ) {
            return new WP_REST_Response(
                array(
                    'success' => false,
                    'message' => 'Too many requests. Please wait a moment before trying again.',
                ),
                429
            );
        }

        set_transient( $transient_key, $request_count + 1, MINUTE_IN_SECONDS );

        // Sanitize history
        $clean_history = array();
        if ( is_array( $history ) ) {
            foreach ( $history as $entry ) {
                if ( isset( $entry['user'] ) && isset( $entry['assistant'] ) ) {
                    $clean_history[] = array(
                        'user'      => sanitize_text_field( $entry['user'] ),
                        'assistant' => sanitize_textarea_field( $entry['assistant'] ),
                    );
                }
            }
            // Keep only last 5 exchanges for context
            $clean_history = array_slice( $clean_history, -5 );
        }

        $engine = new MCB_Chat_Engine();
        $response = $engine->get_response( $message, $clean_history );

        return new WP_REST_Response( $response, 200 );
    }

    public function handle_status( $request ) {
        $scanner = new MCB_Content_Scanner();
        $stats = $scanner->get_stats();

        return new WP_REST_Response( array(
            'active'    => true,
            'indexed'   => $stats['total_posts'] > 0,
            'last_scan' => $stats['last_scan'],
        ), 200 );
    }

    private function get_client_ip() {
        $ip_keys = array( 'HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR' );
        foreach ( $ip_keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
                // Handle comma-separated IPs (X-Forwarded-For)
                if ( strpos( $ip, ',' ) !== false ) {
                    $ip = trim( explode( ',', $ip )[0] );
                }
                return $ip;
            }
        }
        return '127.0.0.1';
    }
}
