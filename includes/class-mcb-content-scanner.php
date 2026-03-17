<?php
/**
 * Content Scanner - Scans and indexes website content for the chatbot.
 * Fetches the actual rendered HTML of each page to capture all visible content
 * regardless of which page builder (Elementor, WPBakery, etc.) was used.
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
     * Number of pages to process per batch.
     */
    const BATCH_SIZE = 5;

    /**
     * Run a full scan of all configured post types.
     * (Legacy method - kept for cron compatibility.)
     *
     * @return array Scan results with counts.
     */
    public function run_scan() {
        global $wpdb;

        $post_ids = $this->get_scannable_post_ids();
        $table_name = $wpdb->prefix . 'mcb_content_index';

        // Clear existing index
        $wpdb->query( "TRUNCATE TABLE $table_name" );

        $total_posts = 0;
        $total_chunks = 0;

        foreach ( $post_ids as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }
            $chunks = $this->index_post( $post );
            $total_chunks += $chunks;
            $total_posts++;
        }

        update_option( 'mcb_last_scan', current_time( 'mysql' ) );

        return array(
            'posts_scanned'  => $total_posts,
            'chunks_created' => $total_chunks,
            'scan_time'      => current_time( 'mysql' ),
        );
    }

    /**
     * Initialize a batch scan: get post IDs and clear the index.
     *
     * @return array Post IDs and total count.
     */
    public function batch_init() {
        global $wpdb;

        $post_ids = $this->get_scannable_post_ids();
        $table_name = $wpdb->prefix . 'mcb_content_index';

        // Clear existing index
        $wpdb->query( "TRUNCATE TABLE $table_name" );

        // Store the post IDs for batch processing
        update_option( 'mcb_scan_queue', $post_ids );
        update_option( 'mcb_scan_progress', 0 );

        return array(
            'total'      => count( $post_ids ),
            'batch_size' => self::BATCH_SIZE,
        );
    }

    /**
     * Process the next batch of posts.
     *
     * @param int $offset The offset to start from.
     * @return array Batch results.
     */
    public function batch_process( $offset = 0 ) {
        $post_ids = get_option( 'mcb_scan_queue', array() );
        $total = count( $post_ids );
        $batch = array_slice( $post_ids, $offset, self::BATCH_SIZE );

        $batch_chunks = 0;
        $batch_posts = 0;
        $batch_details = array();

        foreach ( $batch as $post_id ) {
            $post = get_post( $post_id );
            if ( ! $post ) {
                continue;
            }

            $chunks = $this->index_post( $post );
            $batch_chunks += $chunks;
            $batch_posts++;
            $batch_details[] = array(
                'title'  => $post->post_title,
                'chunks' => $chunks,
            );
        }

        $new_offset = $offset + self::BATCH_SIZE;
        $done = $new_offset >= $total;

        if ( $done ) {
            update_option( 'mcb_last_scan', current_time( 'mysql' ) );
            delete_option( 'mcb_scan_queue' );
            delete_option( 'mcb_scan_progress' );
        } else {
            update_option( 'mcb_scan_progress', $new_offset );
        }

        return array(
            'processed'    => min( $new_offset, $total ),
            'total'        => $total,
            'batch_chunks' => $batch_chunks,
            'batch_posts'  => $batch_posts,
            'done'         => $done,
            'details'      => $batch_details,
        );
    }

    /**
     * Get all post IDs that should be scanned.
     *
     * @return array Array of post IDs.
     */
    private function get_scannable_post_ids() {
        $post_types = get_option( 'mcb_post_types', array( 'post', 'page' ) );
        if ( ! is_array( $post_types ) ) {
            $post_types = array( 'post', 'page' );
        }

        $post_ids = get_posts( array(
            'post_type'      => $post_types,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );

        return $post_ids;
    }

    /**
     * Index a single post by fetching its rendered HTML.
     *
     * @param WP_Post $post The post to index.
     * @return int Number of chunks created.
     */
    private function index_post( $post ) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'mcb_content_index';
        $title = sanitize_text_field( $post->post_title );
        $url = get_permalink( $post->ID );

        // Fetch the actual rendered page HTML
        $content = $this->fetch_page_content( $url );

        // If fetch failed, fall back to database content
        if ( empty( $content ) ) {
            $content = $this->get_database_content( $post );
        }

        $content = trim( $content );

        if ( empty( $content ) ) {
            return 0;
        }

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
     * Fetch the rendered HTML of a page and extract its visible text content.
     *
     * @param string $url The page URL.
     * @return string The extracted text content.
     */
    private function fetch_page_content( $url ) {
        if ( empty( $url ) ) {
            return '';
        }

        $response = wp_remote_get( $url, array(
            'timeout'    => 15,
            'sslverify'  => false,
            'user-agent' => 'MCB-Content-Scanner/1.0 (internal)',
        ) );

        if ( is_wp_error( $response ) ) {
            return '';
        }

        $status = wp_remote_retrieve_response_code( $response );
        if ( 200 !== $status ) {
            return '';
        }

        $html = wp_remote_retrieve_body( $response );
        if ( empty( $html ) ) {
            return '';
        }

        return $this->extract_text_from_html( $html );
    }

    /**
     * Extract meaningful text content from HTML, removing navigation,
     * scripts, styles, footers, and other non-content elements.
     *
     * @param string $html Raw HTML.
     * @return string Cleaned text.
     */
    private function extract_text_from_html( $html ) {
        // Remove script and style tags with their content
        $html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
        $html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
        $html = preg_replace( '/<noscript\b[^>]*>.*?<\/noscript>/is', '', $html );

        // Remove common non-content areas by tag
        $html = preg_replace( '/<nav\b[^>]*>.*?<\/nav>/is', '', $html );
        $html = preg_replace( '/<header\b[^>]*>.*?<\/header>/is', '', $html );
        $html = preg_replace( '/<footer\b[^>]*>.*?<\/footer>/is', '', $html );

        // Remove elements by common non-content classes/IDs
        $html = preg_replace( '/<[^>]+(class|id)\s*=\s*["\'][^"\']*\b(menu|nav|sidebar|widget|cookie|popup|modal|banner|advertisement|social)[^"\']*["\'][^>]*>.*?<\/[a-z]+>/is', '', $html );

        // Remove HTML comments
        $html = preg_replace( '/<!--.*?-->/s', '', $html );

        // Remove all remaining HTML tags
        $text = wp_strip_all_tags( $html );

        // Decode HTML entities
        $text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );

        // Normalize whitespace
        $text = preg_replace( '/\s+/', ' ', $text );

        return trim( $text );
    }

    /**
     * Fallback: get content from database fields if HTTP fetch fails.
     *
     * @param WP_Post $post The post.
     * @return string Cleaned content.
     */
    private function get_database_content( $post ) {
        $content = '';

        // Render shortcodes in post_content
        $raw = do_shortcode( $post->post_content );
        $content .= wp_strip_all_tags( $raw );

        // Try Elementor data
        $elementor_data = get_post_meta( $post->ID, '_elementor_data', true );
        if ( ! empty( $elementor_data ) ) {
            if ( is_string( $elementor_data ) ) {
                $elementor_data = json_decode( $elementor_data, true );
            }
            if ( is_array( $elementor_data ) ) {
                $content .= ' ' . $this->extract_elementor_text( $elementor_data );
            }
        }

        $content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
        $content = preg_replace( '/\s+/', ' ', $content );

        return trim( $content );
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
            if ( ! empty( $element['settings'] ) ) {
                foreach ( $element['settings'] as $key => $value ) {
                    if ( is_string( $value ) && ! empty( $value ) ) {
                        if ( strpos( $key, 'text' ) !== false || strpos( $key, 'title' ) !== false || strpos( $key, 'description' ) !== false || strpos( $key, 'content' ) !== false || strpos( $key, 'editor' ) !== false || strpos( $key, 'heading' ) !== false || strpos( $key, 'caption' ) !== false ) {
                            $clean = wp_strip_all_tags( $value );
                            $clean = html_entity_decode( $clean, ENT_QUOTES, 'UTF-8' );
                            if ( mb_strlen( $clean, 'UTF-8' ) > 2 ) {
                                $text .= ' ' . $clean;
                            }
                        }
                    }
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
        if ( mb_strlen( $content, 'UTF-8' ) <= self::CHUNK_SIZE ) {
            return array( $content );
        }

        $chunks = array();
        // Split on sentence-ending punctuation (including Greek semicolon ';' used as question mark)
        $sentences = preg_split( '/(?<=[.!?;·])\s+/u', $content, -1, PREG_SPLIT_NO_EMPTY );
        $current_chunk = '';

        foreach ( $sentences as $sentence ) {
            if ( mb_strlen( $current_chunk, 'UTF-8' ) + mb_strlen( $sentence, 'UTF-8' ) + 1 > self::CHUNK_SIZE ) {
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
