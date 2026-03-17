<?php
/**
 * Content Scanner - Scans and indexes website content for the chatbot.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCB_Content_Scanner {

    /**
     * Maximum characters per content chunk.
     */
    const CHUNK_SIZE = 1500;

    /**
     * Run a full scan of all configured post types.
     *
     * @return array Scan results with counts.
     */
    public function run_scan() {
        global $wpdb;

        $post_types = get_option( 'mcb_post_types', array( 'post', 'page' ) );
        if ( ! is_array( $post_types ) ) {
            $post_types = array( 'post', 'page' );
        }

        $table_name = $wpdb->prefix . 'mcb_content_index';

        // Clear existing index
        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_posts = 0;
        $total_chunks = 0;

        foreach ( $post_types as $post_type ) {
            $posts = get_posts( array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
            ) );

            foreach ( $posts as $post ) {
                $chunks = $this->index_post( $post );
                $total_chunks += $chunks;
                $total_posts++;
            }
        }

        update_option( 'mcb_last_scan', current_time( 'mysql' ) );

        return array(
            'posts_scanned'  => $total_posts,
            'chunks_created' => $total_chunks,
            'scan_time'      => current_time( 'mysql' ),
        );
    }

    /**
     * Index a single post into chunks.
     *
     * @param WP_Post $post The post to index.
     * @return int Number of chunks created.
     */
    private function index_post( $post ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mcb_content_index';

        // Get content from post_content (render shortcodes first)
        $content = $this->clean_content( $post->post_content );

        // Also extract content from page builder meta fields (Elementor, WPBakery, etc.)
        $meta_content = $this->extract_meta_content( $post->ID );
        if ( ! empty( $meta_content ) ) {
            $content .= ' ' . $meta_content;
        }

        $content = trim( $content );
        $title = sanitize_text_field( $post->post_title );
        $url = get_permalink( $post->ID );

        // Split content into chunks
        $chunks = $this->split_into_chunks( $content );

        $chunk_index = 0;
        foreach ( $chunks as $chunk ) {
            $wpdb->insert(
                $table_name,
                array(
                    'post_id'      => $post->ID,
                    'post_type'    => $post->post_type,
                    'title'        => $title,
                    'content'      => $chunk,
                    'url'          => $url,
                    'chunk_index'  => $chunk_index,
                    'last_scanned' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s', '%s', '%s', '%d', '%s' )
            );
            $chunk_index++;
        }

        return $chunk_index;
    }

    /**
     * Clean post content by removing HTML and extra whitespace.
     * Renders shortcodes first to capture their output before stripping HTML.
     *
     * @param string $content Raw post content.
     * @return string Cleaned content.
     */
    private function clean_content( $content ) {
        // First render shortcodes to get their output (e.g., page builder elements)
        $content = do_shortcode( $content );

        // Remove HTML tags but keep text content
        $content = wp_strip_all_tags( $content );

        // Decode HTML entities
        $content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

        // Normalize whitespace
        $content = preg_replace( '/\s+/', ' ', $content );

        return trim( $content );
    }

    /**
     * Extract text content from page builder meta fields.
     * Supports Elementor, WPBakery, Beaver Builder, and generic custom fields.
     *
     * @param int $post_id The post ID.
     * @return string Extracted text content.
     */
    private function extract_meta_content( $post_id ) {
        $extra_content = '';

        // Elementor: extract text from serialized Elementor data
        $elementor_data = get_post_meta( $post_id, '_elementor_data', true );
        if ( ! empty( $elementor_data ) ) {
            if ( is_string( $elementor_data ) ) {
                $elementor_data = json_decode( $elementor_data, true );
            }
            if ( is_array( $elementor_data ) ) {
                $extra_content .= ' ' . $this->extract_elementor_text( $elementor_data );
            }
        }

        // WPBakery / Visual Composer: content is typically in post_content with shortcodes
        // (already handled by do_shortcode in clean_content)

        // ACF and generic custom fields: extract text from commonly used meta keys
        $text_meta_keys = apply_filters( 'mcb_extra_meta_keys', array() );
        foreach ( $text_meta_keys as $key ) {
            $value = get_post_meta( $post_id, $key, true );
            if ( ! empty( $value ) && is_string( $value ) ) {
                $extra_content .= ' ' . wp_strip_all_tags( $value );
            }
        }

        // Normalize whitespace
        $extra_content = preg_replace( '/\s+/', ' ', $extra_content );

        return trim( $extra_content );
    }

    /**
     * Recursively extract text from Elementor data structure.
     *
     * @param array $elements Elementor elements array.
     * @return string Extracted text.
     */
    private function extract_elementor_text( $elements ) {
        $text = '';

        foreach ( $elements as $element ) {
            // Extract from settings (where Elementor stores widget content)
            if ( ! empty( $element['settings'] ) ) {
                foreach ( $element['settings'] as $key => $value ) {
                    if ( is_string( $value ) && ! empty( $value ) ) {
                        // Skip CSS/styling properties, focus on content fields
                        $content_keys = array(
                            'title', 'editor', 'text', 'description', 'content',
                            'heading', 'subtitle', 'caption', 'label', 'inner_text',
                            'tab_title', 'tab_content', 'item_description', 'item_title',
                            'alert_title', 'alert_description', 'html', 'shortcode',
                            'testimonial_content', 'testimonial_name', 'testimonial_job',
                            'blockquote_content', 'author_name',
                            'title_text', 'description_text',
                        );
                        if ( in_array( $key, $content_keys, true ) || strpos( $key, 'text' ) !== false || strpos( $key, 'title' ) !== false || strpos( $key, 'description' ) !== false || strpos( $key, 'content' ) !== false ) {
                            $clean = wp_strip_all_tags( $value );
                            $clean = html_entity_decode( $clean, ENT_QUOTES, 'UTF-8' );
                            if ( mb_strlen( $clean, 'UTF-8' ) > 2 ) {
                                $text .= ' ' . $clean;
                            }
                        }
                    }
                    // Handle repeater fields (arrays of items with text)
                    if ( is_array( $value ) ) {
                        foreach ( $value as $item ) {
                            if ( is_array( $item ) ) {
                                foreach ( $item as $sub_key => $sub_value ) {
                                    if ( is_string( $sub_value ) && ! empty( $sub_value ) ) {
                                        if ( strpos( $sub_key, 'text' ) !== false || strpos( $sub_key, 'title' ) !== false || strpos( $sub_key, 'description' ) !== false || strpos( $sub_key, 'content' ) !== false ) {
                                            $clean = wp_strip_all_tags( $sub_value );
                                            $clean = html_entity_decode( $clean, ENT_QUOTES, 'UTF-8' );
                                            if ( mb_strlen( $clean, 'UTF-8' ) > 2 ) {
                                                $text .= ' ' . $clean;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Recurse into child elements
            if ( ! empty( $element['elements'] ) ) {
                $text .= ' ' . $this->extract_elementor_text( $element['elements'] );
            }
        }

        return $text;
    }

    /**
     * Split content into chunks at sentence boundaries.
     *
     * @param string $content The content to split.
     * @return array Array of content chunks.
     */
    private function split_into_chunks( $content ) {
        if ( strlen( $content ) <= self::CHUNK_SIZE ) {
            return array( $content );
        }

        $chunks = array();
        $sentences = preg_split( '/(?<=[.!?])\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
        $current_chunk = '';

        foreach ( $sentences as $sentence ) {
            if ( strlen( $current_chunk ) + strlen( $sentence ) + 1 > self::CHUNK_SIZE ) {
                if ( ! empty( $current_chunk ) ) {
                    $chunks[] = trim( $current_chunk );
                }
                $current_chunk = $sentence;
            } else {
                $current_chunk .= ( empty( $current_chunk ) ? '' : ' ' ) . $sentence;
            }
        }

        if ( ! empty( $current_chunk ) ) {
            $chunks[] = trim( $current_chunk );
        }

        return $chunks;
    }

    /**
     * Search the content index for relevant chunks.
     *
     * @param string $query The user's question.
     * @param int    $limit Maximum chunks to return.
     * @return array Matching content chunks with metadata.
     */
    public function search( $query, $limit = 5 ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mcb_content_index';

        // Extract meaningful keywords (remove common stop words)
        $keywords = $this->extract_keywords( $query );

        if ( empty( $keywords ) ) {
            // Fallback: return most recent content
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name ORDER BY last_scanned DESC LIMIT %d",
                    $limit
                )
            );
            return $results;
        }

        // Build a relevance-scored search query
        $where_clauses = array();
        $relevance_parts = array();
        $params = array();

        foreach ( $keywords as $keyword ) {
            $like = '%' . $wpdb->esc_like( $keyword ) . '%';
            $where_clauses[] = "(content LIKE %s OR title LIKE %s)";
            $params[] = $like;
            $params[] = $like;

            // Title matches score higher
            $relevance_parts[] = "(CASE WHEN title LIKE %s THEN 3 ELSE 0 END)";
            $params[] = $like;
            $relevance_parts[] = "(CASE WHEN content LIKE %s THEN 1 ELSE 0 END)";
            $params[] = $like;
        }

        $where_sql = implode( ' OR ', $where_clauses );
        $relevance_sql = implode( ' + ', $relevance_parts );

        $params[] = $limit;

        $sql = "SELECT *, ($relevance_sql) as relevance
                FROM $table_name
                WHERE $where_sql
                ORDER BY relevance DESC, last_scanned DESC
                LIMIT %d";

        $results = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );

        return $results;
    }

    /**
     * Extract meaningful keywords from a query.
     * Supports Greek, Latin, and other Unicode scripts.
     *
     * @param string $query The search query.
     * @return array Array of keywords.
     */
    private function extract_keywords( $query ) {
        // English stop words
        $stop_words = array(
            'a', 'an', 'the', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
            'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could',
            'should', 'may', 'might', 'shall', 'can', 'need', 'dare', 'ought',
            'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from',
            'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below',
            'between', 'out', 'off', 'over', 'under', 'again', 'further', 'then',
            'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each',
            'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no',
            'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very',
            'just', 'because', 'but', 'and', 'or', 'if', 'while', 'about',
            'what', 'which', 'who', 'whom', 'this', 'that', 'these', 'those',
            'i', 'me', 'my', 'we', 'our', 'you', 'your', 'he', 'him', 'his',
            'she', 'her', 'it', 'its', 'they', 'them', 'their', 'tell', 'know',
            'please', 'help', 'want', 'like', 'get', 'give', 'make', 'go',
        );

        // Greek stop words
        $greek_stop_words = array(
            'και', 'του', 'της', 'των', 'τον', 'την', 'το', 'τα', 'τις', 'τους',
            'ένα', 'μια', 'ένας', 'στο', 'στη', 'στα', 'στις', 'στον', 'στην', 'στους',
            'από', 'για', 'με', 'σε', 'ως', 'που', 'είναι', 'ήταν', 'θα', 'να',
            'δεν', 'μου', 'σου', 'σας', 'μας', 'τους', 'αυτό', 'αυτή', 'αυτός',
            'αυτά', 'αυτές', 'αυτοί', 'εγώ', 'εσύ', 'εμείς', 'εσείς', 'αυτοί',
            'πώς', 'πως', 'τι', 'ποιο', 'ποια', 'ποιος', 'ποιες', 'ποιοι', 'ποιων',
            'πότε', 'πού', 'γιατί', 'αν', 'ή', 'αλλά', 'όμως', 'ούτε', 'μόνο',
            'πολύ', 'πιο', 'πάνω', 'κάτω', 'μετά', 'πριν', 'όλα', 'κάθε',
            'κάνετε', 'κάνουν', 'κάνει', 'κάνω', 'κάνουμε', 'έχει', 'έχω',
            'έχουν', 'έχουμε', 'έχετε', 'είναι', 'είμαι', 'είσαι', 'είμαστε',
            'μπορεί', 'μπορώ', 'μπορούν', 'πρέπει', 'ποια', 'ποιες', 'ποιο',
        );

        $all_stop_words = array_merge( $stop_words, $greek_stop_words );

        $query = mb_strtolower( $query, 'UTF-8' );
        // Keep Unicode letters, numbers, and whitespace (supports Greek and all scripts)
        $query = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $query );
        $words = preg_split( '/\s+/', $query, -1, PREG_SPLIT_NO_EMPTY );
        $words = array_filter( $words, function( $word ) use ( $all_stop_words ) {
            return mb_strlen( $word, 'UTF-8' ) > 1 && ! in_array( $word, $all_stop_words, true );
        });

        return array_values( array_unique( $words ) );
    }

    /**
     * Get scan statistics.
     *
     * @return array Scan stats.
     */
    public function get_stats() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mcb_content_index';

        $total_chunks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table_name" );
        $total_posts = (int) $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM $table_name" );
        $last_scan = get_option( 'mcb_last_scan', '' );

        return array(
            'total_chunks' => $total_chunks,
            'total_posts'  => $total_posts,
            'last_scan'    => $last_scan,
        );
    }
}
