<?php
/**
 * Backward compatibility functions for time-functions.php
 * 
 * This file provides procedural function wrappers around the PSR-4 TimeFunctions class
 * to maintain backward compatibility with existing code.
 */

use KISS\PTT\Time\TimeFunctions;

// Block direct access
if (!defined('WPINC')) {
    die;
}

if (!function_exists('ptt_calculate_and_save_duration')) {
    /**
     * Calculate and save total duration for a task from all sessions
     * 
     * @param int $post_id The task post ID
     * @return string Formatted duration (e.g., "1.50")
     */
    function ptt_calculate_and_save_duration($post_id) {
        return TimeFunctions::calculateAndSaveDuration($post_id);
    }
}

if (!function_exists('ptt_get_active_session_index')) {
    /**
     * Get the index of the currently active session for a task
     * 
     * @param int $post_id The task post ID
     * @return int|false Session index (0-based) or false if no active session
     */
    function ptt_get_active_session_index($post_id) {
        return TimeFunctions::getActiveSessionIndex($post_id);
    }
}

if (!function_exists('ptt_calculate_session_duration')) {
    /**
     * Calculate duration for a specific session
     * 
     * @param int $post_id The task post ID
     * @param int $index The session index (0-based)
     * @return string Formatted duration (e.g., "1.50")
     */
    function ptt_calculate_session_duration($post_id, $index) {
        return TimeFunctions::calculateSessionDuration($post_id, $index);
    }
}

if (!function_exists('ptt_ensure_manual_session_timestamps')) {
    /**
     * Ensure manual sessions have proper timestamps
     * 
     * @param int $post_id The task post ID
     * @return void
     */
    function ptt_ensure_manual_session_timestamps($post_id) {
        TimeFunctions::ensureManualSessionTimestamps($post_id);
    }
}

if (!function_exists('ptt_get_total_sessions_duration')) {
    /**
     * Get total duration from all sessions for a task
     * 
     * @param int $post_id The task post ID
     * @return float Total duration in hours
     */
    function ptt_get_total_sessions_duration($post_id) {
        return TimeFunctions::getTotalSessionsDuration($post_id);
    }
}
