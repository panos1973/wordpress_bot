<?php
/**
 * Cron scheduling for automatic content scanning.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MCB_Cron {

    const HOOK_NAME = 'mcb_scheduled_scan';

    public function __construct() {
        add_action( self::HOOK_NAME, array( $this, 'run_scheduled_scan' ) );
        add_filter( 'cron_schedules', array( $this, 'add_custom_schedules' ) );
    }

    /**
     * Add custom cron schedules.
     */
    public function add_custom_schedules( $schedules ) {
        $schedules['mcb_monthly'] = array(
            'interval' => 30 * DAY_IN_SECONDS,
            'display'  => __( 'Once Monthly', 'medical-chatbot' ),
        );

        $schedules['mcb_weekly'] = array(
            'interval' => 7 * DAY_IN_SECONDS,
            'display'  => __( 'Once Weekly', 'medical-chatbot' ),
        );

        $schedules['mcb_daily'] = array(
            'interval' => DAY_IN_SECONDS,
            'display'  => __( 'Once Daily', 'medical-chatbot' ),
        );

        return $schedules;
    }

    /**
     * Schedule the content scan.
     */
    public static function schedule_scan() {
        if ( ! wp_next_scheduled( self::HOOK_NAME ) ) {
            $frequency = get_option( 'mcb_scan_frequency', 'monthly' );
            $schedule = 'mcb_' . $frequency;

            // Validate schedule exists
            $valid_schedules = array( 'mcb_monthly', 'mcb_weekly', 'mcb_daily' );
            if ( ! in_array( $schedule, $valid_schedules, true ) ) {
                $schedule = 'mcb_monthly';
            }

            wp_schedule_event( time(), $schedule, self::HOOK_NAME );
        }
    }

    /**
     * Unschedule the content scan.
     */
    public static function unschedule_scan() {
        $timestamp = wp_next_scheduled( self::HOOK_NAME );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, self::HOOK_NAME );
        }
    }

    /**
     * Reschedule with updated frequency.
     */
    public static function reschedule_scan() {
        self::unschedule_scan();
        self::schedule_scan();
    }

    /**
     * Run the scheduled content scan.
     */
    public function run_scheduled_scan() {
        $scanner = new MCB_Content_Scanner();
        $results = $scanner->run_scan();

        // Log the scan result
        error_log( sprintf(
            '[Medical Chatbot] Scheduled scan completed: %d posts scanned, %d chunks created at %s',
            $results['posts_scanned'],
            $results['chunks_created'],
            $results['scan_time']
        ) );
    }
}
