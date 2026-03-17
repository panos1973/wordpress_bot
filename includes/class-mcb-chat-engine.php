<?php
/**
 * Chat Engine - Handles AI interactions with Claude and Gemini.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCB_Chat_Engine {

    /**
     * Process a user message and return a bot response.
     *
     * @param string $message  The user's message.
     * @param array  $history  Previous conversation messages.
     * @return array Response with message and metadata.
     */
    public function get_response( $message, $history = array() ) {
        $provider = get_option( 'mcb_ai_provider', 'claude' );

        // Search for relevant content
        $scanner = new MCB_Content_Scanner();
        $max_chunks = (int) get_option( 'mcb_max_context_chunks', 10 );
        $relevant_content = $scanner->search( $message, $max_chunks );

        // Build context from relevant content
        $context = $this->build_context( $relevant_content );

        // Build the prompt
        $system_prompt = $this->build_system_prompt( $context );

        // Call the appropriate AI provider
        if ( 'gemini' === $provider ) {
            $response = $this->call_gemini( $system_prompt, $message, $history );
        } else {
            $response = $this->call_claude( $system_prompt, $message, $history );
        }

        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();

            // Log the error for admin debugging
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( 'Medical Chatbot API Error (' . $provider . '): ' . $error_msg );
            }

            // Log failed conversation so admin can see it in Logs tab
            $this->log_conversation( $message, '[ERROR] ' . $error_msg, $provider );

            return array(
                'success' => false,
                'message' => 'Ζητούμε συγγνώμη, αλλά δεν μπορούμε να επεξεργαστούμε το αίτημά σας αυτή τη στιγμή. Παρακαλώ δοκιμάστε ξανά αργότερα.',
                'error'   => $error_msg,
            );
        }

        // Append the disclaimer
        $disclaimer = "\n\n---\n*⚕️ Αποποίηση ευθύνης: Αυτό δεν αποτελεί ιατρική συμβουλή. Οι πληροφορίες βασίζονται στο περιεχόμενο της ιστοσελίδας μας. Συμβουλευτείτε πάντα έναν εξειδικευμένο επαγγελματία υγείας για ιατρικά θέματα.*";
        $response_text = $response . $disclaimer;

        // Log the conversation
        $this->log_conversation( $message, $response_text, $provider );

        return array(
            'success' => true,
            'message' => $response_text,
            'model'   => $provider,
            'sources' => $this->extract_sources( $relevant_content ),
        );
    }

    /**
     * Build context string from content chunks.
     *
     * @param array $chunks Content chunks from search.
     * @return string Formatted context.
     */
    private function build_context( $chunks ) {
        if ( empty( $chunks ) ) {
            $phone1 = get_option( 'mcb_phone_number', '' );
            $phone2 = get_option( 'mcb_phone_number_2', '' );

            $phone_info = '';
            if ( ! empty( $phone1 ) && ! empty( $phone2 ) ) {
                $phone_info = ' στα τηλέφωνα ' . $phone1 . ' ή ' . $phone2;
            } elseif ( ! empty( $phone1 ) ) {
                $phone_info = ' στο τηλέφωνο ' . $phone1;
            } elseif ( ! empty( $phone2 ) ) {
                $phone_info = ' στο τηλέφωνο ' . $phone2;
            }

            return 'Δεν βρέθηκε σχετικό περιεχόμενο στην ιστοσελίδα για αυτό το ερώτημα. ΣΗΜΑΝΤΙΚΟ: Ενημέρωσε τον επισκέπτη ότι δεν είσαι σίγουρος για την απάντηση και ότι θα ήταν καλύτερα να καλέσει το ιατρείο' . $phone_info . ' για βοήθεια και καθοδήγηση.';
        }

        $context_parts = array();
        $seen_posts = array();

        foreach ( $chunks as $chunk ) {
            $post_key = $chunk->post_id . '-' . $chunk->chunk_index;
            if ( in_array( $post_key, $seen_posts, true ) ) {
                continue;
            }
            $seen_posts[] = $post_key;

            $context_parts[] = sprintf(
                "--- Source: \"%s\" (%s) ---\n%s",
                $chunk->title,
                $chunk->url,
                $chunk->content
            );
        }

        return implode( "\n\n", $context_parts );
    }

    /**
     * Build the system prompt with website context.
     *
     * @param string $context The website content context.
     * @return string The system prompt.
     */
    private function build_system_prompt( $context ) {
        $site_name = get_bloginfo( 'name' );

        return "Είσαι ο ψηφιακός βοηθός της ιστοσελίδας \"{$site_name}\". Ο ρόλος σου είναι να υποδέχεσαι τους επισκέπτες μας με ευγένεια και να τους βοηθάς να βρουν εύκολα αυτό που ψάχνουν μέσα στο site μας. Θέλουμε ο λόγος σου να είναι φιλικός, άμεσος και προσιτός – σαν να μιλάει ένας ευγενικός υπάλληλος στην υποδοχή του ιατρείου.

ΒΑΣΙΚΟΙ ΚΑΝΟΝΕΣ ΣΥΜΠΕΡΙΦΟΡΑΣ:
* Φιλικό Ύφος: Χρησιμοποίησε απλά, καθημερινά Ελληνικά. Απόφυγε τις πολύ επίσημες ή «ξύλινες» εκφράσεις. Αντί για \"Δεν διαθέτω αυτή την πληροφορία\", προτίμησε το \"Δυστυχώς δεν βλέπω κάτι σχετικό εδώ, αλλά...\"
* Περιορισμός Γνώσης: Απάντα αποκλειστικά με βάση τις πληροφορίες που υπάρχουν παρακάτω. Μην προσθέτεις δικές σου γνώσεις, ακόμα κι αν τις θεωρείς σωστές.
* Ιατρικό Απόρρητο & Συμβουλές: Αν κάποιος ζητήσει διάγνωση ή ιατρική συμβουλή, εξήγησε με γλυκό τρόπο ότι μόνο ο γιατρός μπορεί να το κάνει αυτό. Μην κάνεις διαγνώσεις.
* Επικοινωνία: Αν δεν βρίσκεις την απάντηση στο κείμενο, μην πεις απλά \"δεν ξέρω\". Πρότεινε στον επισκέπτη να πάρει τηλέφωνο στο ιατρείο ή να στείλει ένα μήνυμα για να τον βοηθήσει ο γιατρός προσωπικά.
* Πηγή Πληροφορίας: Αν η απάντηση βρίσκεται σε συγκεκριμένο άρθρο, πες το με φυσικό τρόπο (π.χ. \"Όπως διαβάζω στην ενότητα των υπηρεσιών μας...\").
* Συντομία: Μην κουράζεις με τεράστιες παραγράφους. Δώσε την ουσία και ρώτα αν χρειάζονται κάτι άλλο.

ΠΕΡΙΕΧΟΜΕΝΟ ΙΣΤΟΣΕΛΙΔΑΣ:
{$context}";
    }

    /**
     * Call Claude Haiku 4.5 API.
     *
     * @param string $system_prompt The system prompt.
     * @param string $message       The user message.
     * @param array  $history       Conversation history.
     * @return string|WP_Error The AI response or error.
     */
    private function call_claude( $system_prompt, $message, $history = array() ) {
        $api_key = get_option( 'mcb_claude_api_key', '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'Claude API key is not configured.' );
        }

        $messages = array();

        // Add conversation history
        foreach ( $history as $entry ) {
            $messages[] = array(
                'role'    => 'user',
                'content' => $entry['user'],
            );
            $messages[] = array(
                'role'    => 'assistant',
                'content' => $entry['assistant'],
            );
        }

        // Add current message
        $messages[] = array(
            'role'    => 'user',
            'content' => $message,
        );

        $response = wp_remote_post(
            'https://api.anthropic.com/v1/messages',
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type'      => 'application/json',
                    'x-api-key'         => $api_key,
                    'anthropic-version' => '2023-06-01',
                ),
                'body'    => wp_json_encode( array(
                    'model'      => 'claude-haiku-4-5-20251001',
                    'max_tokens' => 2048,
                    'system'     => $system_prompt,
                    'messages'   => $messages,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status_code ) {
            $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error';
            return new WP_Error( 'api_error', 'Claude API error: ' . $error_msg );
        }

        if ( isset( $body['content'][0]['text'] ) ) {
            return $body['content'][0]['text'];
        }

        return new WP_Error( 'parse_error', 'Could not parse Claude API response.' );
    }

    /**
     * Call Gemini API.
     *
     * @param string $system_prompt The system prompt.
     * @param string $message       The user message.
     * @param array  $history       Conversation history.
     * @return string|WP_Error The AI response or error.
     */
    private function call_gemini( $system_prompt, $message, $history = array() ) {
        $api_key = get_option( 'mcb_gemini_api_key', '' );

        if ( empty( $api_key ) ) {
            return new WP_Error( 'no_api_key', 'Gemini API key is not configured.' );
        }

        $contents = array();

        // Add conversation history
        foreach ( $history as $entry ) {
            $contents[] = array(
                'role'  => 'user',
                'parts' => array( array( 'text' => $entry['user'] ) ),
            );
            $contents[] = array(
                'role'  => 'model',
                'parts' => array( array( 'text' => $entry['assistant'] ) ),
            );
        }

        // Add current message
        $contents[] = array(
            'role'  => 'user',
            'parts' => array( array( 'text' => $message ) ),
        );

        $model = get_option( 'mcb_gemini_model', 'gemini-3-flash-preview' );
        $url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;

        $response = wp_remote_post(
            $url,
            array(
                'timeout' => 30,
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'system_instruction' => array(
                        'parts' => array( array( 'text' => $system_prompt ) ),
                    ),
                    'contents'           => $contents,
                    'generationConfig'   => array(
                        'maxOutputTokens' => 2048,
                        'temperature'     => 0.3,
                    ),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $status_code = wp_remote_retrieve_response_code( $response );

        if ( 200 !== $status_code ) {
            $error_msg = isset( $body['error']['message'] ) ? $body['error']['message'] : 'Unknown API error';
            return new WP_Error( 'api_error', 'Gemini API error: ' . $error_msg );
        }

        if ( isset( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
            return $body['candidates'][0]['content']['parts'][0]['text'];
        }

        return new WP_Error( 'parse_error', 'Could not parse Gemini API response.' );
    }

    /**
     * Extract source references from content chunks.
     *
     * @param array $chunks Content chunks.
     * @return array Source references.
     */
    private function extract_sources( $chunks ) {
        $sources = array();
        $seen = array();

        foreach ( $chunks as $chunk ) {
            if ( in_array( $chunk->post_id, $seen, true ) ) {
                continue;
            }
            $seen[] = $chunk->post_id;
            $sources[] = array(
                'title' => $chunk->title,
                'url'   => $chunk->url,
            );
        }

        return $sources;
    }

    /**
     * Log a conversation exchange.
     *
     * @param string $user_message The user's message.
     * @param string $bot_response The bot's response.
     * @param string $model        The model used.
     */
    private function log_conversation( $user_message, $bot_response, $model ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mcb_chat_logs';

        // Check if the table exists before inserting
        $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" );
        if ( $table_exists ) {
            $wpdb->insert(
                $table_name,
                array(
                    'session_id'   => isset( $_COOKIE['mcb_session_id'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['mcb_session_id'] ) ) : wp_generate_uuid4(),
                    'user_message' => sanitize_textarea_field( $user_message ),
                    'bot_response' => $bot_response,
                    'model_used'   => sanitize_text_field( $model ),
                    'created_at'   => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s', '%s' )
            );
        }
    }
}
