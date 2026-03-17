<?php
/**
 * Admin settings page template.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="wrap mcb-admin-wrap">
    <h1><?php esc_html_e( 'Ρυθμίσεις Medical Chatbot', 'medical-chatbot' ); ?> <small style="font-size:13px;color:#646970;">v<?php echo esc_html( MCB_VERSION ); ?></small></h1>

    <!-- Status Dashboard -->
    <div class="mcb-dashboard">
        <div class="mcb-status-cards">
            <div class="mcb-card">
                <h3><?php esc_html_e( 'Ευρετήριο Περιεχομένου', 'medical-chatbot' ); ?></h3>
                <div class="mcb-stat">
                    <span class="mcb-stat-number"><?php echo esc_html( $stats['total_posts'] ); ?></span>
                    <span class="mcb-stat-label"><?php esc_html_e( 'Σελίδες', 'medical-chatbot' ); ?></span>
                </div>
                <div class="mcb-stat">
                    <span class="mcb-stat-number"><?php echo esc_html( $stats['total_chunks'] ); ?></span>
                    <span class="mcb-stat-label"><?php esc_html_e( 'Τμήματα Περιεχομένου', 'medical-chatbot' ); ?></span>
                </div>
            </div>
            <div class="mcb-card">
                <h3><?php esc_html_e( 'Τελευταία Σάρωση', 'medical-chatbot' ); ?></h3>
                <p class="mcb-last-scan">
                    <?php
                    if ( ! empty( $stats['last_scan'] ) ) {
                        echo esc_html( $stats['last_scan'] );
                    } else {
                        esc_html_e( 'Δεν έχει γίνει σάρωση', 'medical-chatbot' );
                    }
                    ?>
                </p>
                <button id="mcb-scan-btn" class="button button-primary">
                    <?php esc_html_e( 'Σάρωση Τώρα', 'medical-chatbot' ); ?>
                </button>
                <span id="mcb-scan-status" class="mcb-status-msg"></span>
            </div>
            <div class="mcb-card">
                <h3><?php esc_html_e( 'Πάροχος AI', 'medical-chatbot' ); ?></h3>
                <p class="mcb-provider-status">
                    <?php
                    $provider = get_option( 'mcb_ai_provider', 'claude' );
                    if ( 'claude' === $provider ) {
                        $key = get_option( 'mcb_claude_api_key', '' );
                        echo '<strong>Claude Haiku 4.5</strong><br>';
                        echo $key ? '<span class="mcb-status-ok">Κλειδί API Ενεργό</span>' : '<span class="mcb-status-error">Λείπει Κλειδί API</span>';
                    } else {
                        $key = get_option( 'mcb_gemini_api_key', '' );
                        echo '<strong>Gemini 3.0 Flash</strong><br>';
                        echo $key ? '<span class="mcb-status-ok">Κλειδί API Ενεργό</span>' : '<span class="mcb-status-error">Λείπει Κλειδί API</span>';
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
            <h2><?php esc_html_e( 'Ρυθμίσεις Παρόχου AI', 'medical-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mcb_ai_provider"><?php esc_html_e( 'Μοντέλο AI', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <select name="mcb_ai_provider" id="mcb_ai_provider">
                            <option value="claude" <?php selected( get_option( 'mcb_ai_provider' ), 'claude' ); ?>>
                                Claude Haiku 4.5 (Anthropic)
                            </option>
                            <option value="gemini" <?php selected( get_option( 'mcb_ai_provider' ), 'gemini' ); ?>>
                                Gemini 3.0 Flash (Google)
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Επιλέξτε ποιο μοντέλο AI θα χρησιμοποιηθεί για τις απαντήσεις.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_claude_api_key"><?php esc_html_e( 'Κλειδί API Claude', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="mcb_claude_api_key" id="mcb_claude_api_key"
                               value="<?php echo esc_attr( get_option( 'mcb_claude_api_key' ) ); ?>"
                               class="regular-text" autocomplete="off">
                        <p class="description"><?php esc_html_e( 'Το κλειδί API της Anthropic για Claude Haiku 4.5.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_gemini_api_key"><?php esc_html_e( 'Κλειδί API Gemini', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="password" name="mcb_gemini_api_key" id="mcb_gemini_api_key"
                               value="<?php echo esc_attr( get_option( 'mcb_gemini_api_key' ) ); ?>"
                               class="regular-text" autocomplete="off">
                        <p class="description"><?php esc_html_e( 'Το κλειδί Google AI API για Gemini 3.0 Flash.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mcb-settings-section">
            <h2><?php esc_html_e( 'Σάρωση Περιεχομένου', 'medical-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mcb_scan_frequency"><?php esc_html_e( 'Συχνότητα Σάρωσης', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <select name="mcb_scan_frequency" id="mcb_scan_frequency">
                            <option value="daily" <?php selected( get_option( 'mcb_scan_frequency' ), 'daily' ); ?>>
                                <?php esc_html_e( 'Καθημερινά', 'medical-chatbot' ); ?>
                            </option>
                            <option value="weekly" <?php selected( get_option( 'mcb_scan_frequency' ), 'weekly' ); ?>>
                                <?php esc_html_e( 'Εβδομαδιαία', 'medical-chatbot' ); ?>
                            </option>
                            <option value="monthly" <?php selected( get_option( 'mcb_scan_frequency' ), 'monthly' ); ?>>
                                <?php esc_html_e( 'Μηνιαία', 'medical-chatbot' ); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e( 'Πόσο συχνά θα σαρώνεται εκ νέου το περιεχόμενο της ιστοσελίδας.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Τύποι Περιεχομένου', 'medical-chatbot' ); ?></th>
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
                        <p class="description"><?php esc_html_e( 'Επιλέξτε ποιους τύπους περιεχομένου θα σαρώνει και θα χρησιμοποιεί το chatbot.', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_max_context_chunks"><?php esc_html_e( 'Μέγιστα Τμήματα', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="number" name="mcb_max_context_chunks" id="mcb_max_context_chunks"
                               value="<?php echo esc_attr( get_option( 'mcb_max_context_chunks', 10 ) ); ?>"
                               min="1" max="20" class="small-text">
                        <p class="description"><?php esc_html_e( 'Μέγιστος αριθμός τμημάτων περιεχομένου που αποστέλλονται στο AI (περισσότερα = πιο πλήρεις απαντήσεις αλλά υψηλότερο κόστος).', 'medical-chatbot' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="mcb-settings-section">
            <h2><?php esc_html_e( 'Εμφάνιση Chat Widget', 'medical-chatbot' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="mcb_chat_title"><?php esc_html_e( 'Τίτλος Chat', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="text" name="mcb_chat_title" id="mcb_chat_title"
                               value="<?php echo esc_attr( get_option( 'mcb_chat_title', 'Βοηθός' ) ); ?>"
                               class="regular-text">
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_welcome_message"><?php esc_html_e( 'Μήνυμα Καλωσορίσματος', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <textarea name="mcb_welcome_message" id="mcb_welcome_message" rows="3" class="large-text"><?php
                            echo esc_textarea( get_option( 'mcb_welcome_message', 'Πώς μπορώ να σας βοηθήσω;' ) );
                        ?></textarea>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="mcb_primary_color"><?php esc_html_e( 'Κύριο Χρώμα', 'medical-chatbot' ); ?></label>
                    </th>
                    <td>
                        <input type="color" name="mcb_primary_color" id="mcb_primary_color"
                               value="<?php echo esc_attr( get_option( 'mcb_primary_color', '#0073aa' ) ); ?>">
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button( 'Αποθήκευση Αλλαγών' ); ?>
    </form>

    <!-- Test Chat Section -->
    <div class="mcb-settings-section">
        <h2><?php esc_html_e( 'Δοκιμή Chat', 'medical-chatbot' ); ?></h2>
        <p class="description"><?php esc_html_e( 'Δοκιμάστε το chatbot με μια ερώτηση για να βεβαιωθείτε ότι λειτουργεί σωστά.', 'medical-chatbot' ); ?></p>
        <div class="mcb-test-chat">
            <input type="text" id="mcb-test-input" class="regular-text" placeholder="<?php esc_attr_e( 'Γράψτε μια δοκιμαστική ερώτηση...', 'medical-chatbot' ); ?>">
            <button id="mcb-test-btn" class="button button-secondary"><?php esc_html_e( 'Δοκιμή', 'medical-chatbot' ); ?></button>
        </div>
        <div id="mcb-test-result" class="mcb-test-result" style="display:none;"></div>
    </div>
</div>
