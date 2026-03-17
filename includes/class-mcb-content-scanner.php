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

        // Strip shortcodes and HTML, get clean text
        $content = $this->clean_content( $post->post_content );
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
     * Clean post content by removing HTML, shortcodes, and extra whitespace.
     *
     * @param string $content Raw post content.
     * @return string Cleaned content.
     */
    private function clean_content( $content ) {
        // Remove shortcodes
        $content = strip_shortcodes( $content );

        // Process any remaining shortcodes
        $content = do_shortcode( $content );

        // Remove HTML tags
        $content = wp_strip_all_tags( $content );

        // Decode HTML entities
        $content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );

        // Normalize whitespace
        $content = preg_replace( '/\s+/', ' ', $content );

        return trim( $content );
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
     *
     * @param string $query The search query.
     * @return array Array of keywords.
     */
    private function extract_keywords( $query ) {
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

        $query = strtolower( $query );
        $query = preg_replace( '/[^a-z0-9\s]/', '', $query );
        $words = explode( ' ', $query );
        $words = array_filter( $words, function( $word ) use ( $stop_words ) {
            return strlen( $word ) > 2 && ! in_array( $word, $stop_words, true );
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
