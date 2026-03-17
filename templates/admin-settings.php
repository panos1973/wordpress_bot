<?php
/**
 * Admin settings page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap mcb-admin-wrap">
    <h1><?php esc_html_e( 'Medical Chatbot Settings', 'medical-chatbot' ); ?></h1>

    <!-- Status Dashboard -->
    <div class="mcb-dashboard">
        <div class="mcb-status-cards">
            <div class="mcb-card">
                <h3><?php esc_html_e( 'Content Index', 'medical-chatbot' ); ?></h3>
                <div class="mcb-stat">
                    <span class="mcb-stat-number"><?php echo esc_html( $stats['total_posts'] ); ?></span>
                    <span class="mcb-stat-label"><?php esc_html_e( 'Pages Indexed', 'medical-chatbot' ); ?></span>
                </div>
                <div class="mcb-stat">
                    <span class="mcb-stat-number"><?php echo esc_html( $stats['total_chunks'] ); ?></span>
                    <span class="mcb-stat-label"><?php esc_html_e( 'Content Chunks', 'medical-chatbot' ); ?></span>
                </div>
            </div>
            <div class="mcb-card">
                <h3><?php esc_html_e( 'Last Scan', 'medical-chatbot' ); ?></h3>
                <p class="mcb-last-scan">
                    <?php
                    if ( ! empty( $stats['last_scan'] ) ) {
                        echo esc_html( $stats['last_scan'] );
                    } else {
                        esc_html_e( 'Never scanned', 'medical-chatbot' );
                    }
                    ?>
                </p>
                <button id="mcb-scan-btn" class="button button-primary">
                    <?php esc_html_e( 'Scan Now', 'medical-chatbot' ); ?>
                </button>
                <span id="mcb-scan-status" class="mcb-status-msg"></span>
            </div>
            <div class="mcb-card">
                <h3><?php esc_html_e( 'AI Provider', 'medical-chatbot' ); ?></h3>
                <p class="mcb-provider-status">
                    <?php
                    $provider = get_option( 'mcb_ai_provider', 'claude' );
                    if ( 'claude' === $provider ) {
                        $key = get_option( 'mcb_claude_api_key', '' );
                        echo '<strong>Claude Haiku 4.5</strong><br>';
                        echo $key ? '<span class="mcb-status-ok">API Key Set</span>' : '<span class="mcb-status-error">API Key Missing</span>';
                    } else {
                        $key = get_option( 'mcb_gemini_api_key', '' );
                        echo '<strong>Gemini 2.0 Flash</strong><br>';
                        echo $key ? '<span class="mcb-status-ok">API Key Set</span>' : '<span class="mcb-status-error">API Key Missing</span>';
                    }
                    ?>
                </p>
            </div>
        </div>
    </div>

    <!-- Settings Form -->
    <form method="post" action="options.php">
        <?php settings_fields( 'mcb_settings' ); ?>

        <div class="mcb-settings-section">
            <h2><?php esc_html_e( 'AI Provider Settings', 'medical-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mcb_ai_provider"><?php esc_html_e( 'AI Model', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <select name="mcb_ai_provider" id="mcb_ai_provider">
                            <option value="claude" <?php selected( get_option( 'mcb_ai_provider' ), 'claude' ); ?>>
                                Claude Haiku 4.5 (Anthropic)
                            </option>
                            <option value="gemini" <?php selected( get_option( 'mcb_ai_provider' ), 'gemini' ); ?>>
                                Gemini 2.0 Flash (Google)
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Select which AI model to use for chat responses.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_claude_api_key"><?php esc_html_e( 'Claude API Key', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="mcb_claude_api_key" id="mcb_claude_api_key"
                               value="<?php echo esc_attr( get_option( 'mcb_claude_api_key' ) ); ?>"
                               class="regular-text" autocomplete="off">
                        <p class="description"><?php esc_html_e( 'Your Anthropic API key for Claude Haiku 4.5.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_gemini_api_key"><?php esc_html_e( 'Gemini API Key', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="mcb_gemini_api_key" id="mcb_gemini_api_key"
                               value="<?php echo esc_attr( get_option( 'mcb_gemini_api_key' ) ); ?>"
                               class="regular-text" autocomplete="off">
                        <p class="description"><?php esc_html_e( 'Your Google AI API key for Gemini 2.0 Flash.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mcb-settings-section">
            <h2><?php esc_html_e( 'Content Scanning', 'medical-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mcb_scan_frequency"><?php esc_html_e( 'Scan Frequency', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <select name="mcb_scan_frequency" id="mcb_scan_frequency">
                            <option value="daily" <?php selected( get_option( 'mcb_scan_frequency' ), 'daily' ); ?>>
                                <?php esc_html_e( 'Daily', 'medical-chatbot' ); ?>
                            </option>
                            <option value="weekly" <?php selected( get_option( 'mcb_scan_frequency' ), 'weekly' ); ?>>
                                <?php esc_html_e( 'Weekly', 'medical-chatbot' ); ?>
                            </option>
                            <option value="monthly" <?php selected( get_option( 'mcb_scan_frequency' ), 'monthly' ); ?>>
                                <?php esc_html_e( 'Monthly', 'medical-chatbot' ); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'How often the bot should re-scan your website content.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Content Types to Scan', 'medical-chatbot' ); ?></th>
                    <td>
                        <?php
                        $selected_types = get_option( 'mcb_post_types', array( 'post', 'page' ) );
                        $post_types = get_post_types( array( 'public' => true ), 'objects' );
                        foreach ( $post_types as $pt ) :
                            if ( 'attachment' === $pt->name ) continue;
                        ?>
                            <label style="display:block; margin-bottom:5px;">
                                <input type="checkbox" name="mcb_post_types[]"
                                       value="<?php echo esc_attr( $pt->name ); ?>"
                                       <?php checked( in_array( $pt->name, (array) $selected_types, true ) ); ?>>
                                <?php echo esc_html( $pt->labels->name ); ?>
                            </label>
                        <?php endforeach; ?>
                        <p class="description"><?php esc_html_e( 'Select which content types the bot should index and use for answers.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_max_context_chunks"><?php esc_html_e( 'Max Context Chunks', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mcb_max_context_chunks" id="mcb_max_context_chunks"
                               value="<?php echo esc_attr( get_option( 'mcb_max_context_chunks', 5 ) ); ?>"
                               min="1" max="20" class="small-text">
                        <p class="description"><?php esc_html_e( 'Maximum number of content chunks to send as context to the AI (higher = more context but higher cost).', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mcb-settings-section">
            <h2><?php esc_html_e( 'Chat Widget Appearance', 'medical-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mcb_chat_title"><?php esc_html_e( 'Chat Title', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="mcb_chat_title" id="mcb_chat_title"
                               value="<?php echo esc_attr( get_option( 'mcb_chat_title', 'Medical Assistant' ) ); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_welcome_message"><?php esc_html_e( 'Welcome Message', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <textarea name="mcb_welcome_message" id="mcb_welcome_message" rows="3" class="large-text"><?php
                            echo esc_textarea( get_option( 'mcb_welcome_message', 'Hello! I can help you find information from our website. How can I assist you today?' ) );
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_primary_color"><?php esc_html_e( 'Primary Color', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="color" name="mcb_primary_color" id="mcb_primary_color"
                               value="<?php echo esc_attr( get_option( 'mcb_primary_color', '#0073aa' ) ); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>

    <!-- Test Chat Section -->
    <div class="mcb-settings-section">
        <h2><?php esc_html_e( 'Test Chat', 'medical-chatbot' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Test the chatbot with a sample question to make sure everything works.', 'medical-chatbot' ); ?></p>
        <div class="mcb-test-chat">
            <input type="text" id="mcb-test-input" class="regular-text" placeholder="<?php esc_attr_e( 'Type a test question...', 'medical-chatbot' ); ?>">
            <button id="mcb-test-btn" class="button button-secondary"><?php esc_html_e( 'Test', 'medical-chatbot' ); ?></button>
        </div>
        <div id="mcb-test-result" class="mcb-test-result" style="display:none;"></div>
    </div>
</div>
