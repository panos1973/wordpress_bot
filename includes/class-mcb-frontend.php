<?php
/**
 * Frontend chatbot widget.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCB_Frontend {

    public function __construct() {
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_chatbot_widget' ) );
    }

    public function enqueue_assets() {
        // Don't load in admin
        if ( is_admin() ) {
            return;
        }

        wp_enqueue_style(
            'mcb-chatbot',
            MCB_PLUGIN_URL . 'assets/css/chatbot.css',
            array(),
            MCB_VERSION
        );

        wp_enqueue_script(
            'mcb-chatbot',
            MCB_PLUGIN_URL . 'assets/js/chatbot.js',
            array(),
            MCB_VERSION,
            true
        );

        $primary_color = get_option( 'mcb_primary_color', '#0073aa' );
        $icon_color    = get_option( 'mcb_icon_color', '#ffffff' );

        wp_localize_script( 'mcb-chatbot', 'mcbChat', array(
            'restUrl'        => esc_url_raw( rest_url( 'medical-chatbot/v1' ) ),
            'nonce'          => wp_create_nonce( 'wp_rest' ),
            'chatTitle'      => esc_html( get_option( 'mcb_chat_title', 'Βοηθός' ) ),
            'welcomeMessage' => esc_html( get_option( 'mcb_welcome_message', 'Πώς μπορώ να σας βοηθήσω;' ) ),
            'primaryColor'   => sanitize_hex_color( $primary_color ),
            'iconColor'      => sanitize_hex_color( $icon_color ),
        ) );

        // Inject custom colours as CSS variables
        wp_add_inline_style( 'mcb-chatbot', ':root { --mcb-primary: ' . sanitize_hex_color( $primary_color ) . '; --mcb-icon: ' . sanitize_hex_color( $icon_color ) . '; }' );
    }

    public function render_chatbot_widget() {
        if ( is_admin() ) {
            return;
        }
        ?>
        <div id="mcb-chatbot-container" aria-label="<?php esc_attr_e( 'Συνομιλία με τον βοηθό μας', 'medical-chatbot' ); ?>">
            <!-- Toggle Button -->
            <button id="mcb-chat-toggle" aria-label="<?php esc_attr_e( 'Άνοιγμα συνομιλίας', 'medical-chatbot' ); ?>">
                <svg id="mcb-icon-chat" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
                <svg id="mcb-icon-close" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none;">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>

            <!-- Chat Window -->
            <div id="mcb-chat-window" style="display:none;" role="dialog" aria-label="<?php esc_attr_e( 'Παράθυρο συνομιλίας', 'medical-chatbot' ); ?>">
                <div id="mcb-chat-header">
                    <span id="mcb-chat-title"></span>
                    <button id="mcb-chat-minimize" aria-label="<?php esc_attr_e( 'Ελαχιστοποίηση', 'medical-chatbot' ); ?>">&minus;</button>
                </div>
                <div id="mcb-chat-messages" role="log" aria-live="polite"></div>
                <div id="mcb-chat-input-area">
                    <textarea id="mcb-chat-input" placeholder="<?php esc_attr_e( 'Γράψτε την ερώτησή σας...', 'medical-chatbot' ); ?>" rows="1" maxlength="1000" aria-label="<?php esc_attr_e( 'Το μήνυμά σας', 'medical-chatbot' ); ?>"></textarea>
                    <button id="mcb-chat-send" aria-label="<?php esc_attr_e( 'Αποστολή μηνύματος', 'medical-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
