<?php
/**
 * Backward compatibility functions for today.php
 * 
 * This file provides procedural function wrappers around the PSR-4 TodayController class
 * to maintain backward compatibility with existing code.
 */

use KISS\PTT\Presentation\Today\TodayController;

// Block direct access
if (!defined('WPINC')) {
    die;
}

// Load helper classes compatibility
require_once PTT_PLUGIN_DIR . 'src/Presentation/Today/today-helpers-compat.php';

if (!function_exists('ptt_add_today_page')) {
    /**
     * Adds the "Today" link under the Tasks CPT menu
     * 
     * @return void
     */
    function ptt_add_today_page() {
        TodayController::addTodayPage();
    }
}

if (!function_exists('ptt_today_enqueue_font')) {
    /**
     * Ensures time display styles are applied on the Today page
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    function ptt_today_enqueue_font($hook) {
        TodayController::enqueueFont($hook);
    }
}

if (!function_exists('ptt_render_today_page_html')) {
    /**
     * Renders the Today page HTML
     * 
     * @return void
     */
    function ptt_render_today_page_html() {
        TodayController::renderTodayPageHtml();
    }
}

if (!function_exists('ptt_get_tasks_for_today_page_callback')) {
    /**
     * AJAX handler to get tasks for the Today page dropdown
     * 
     * @return void
     */
    function ptt_get_tasks_for_today_page_callback() {
        TodayController::getTasksForTodayPageCallback();
    }
}

if (!function_exists('ptt_today_start_new_session_callback')) {
    /**
     * AJAX handler to start a new session from the Today page
     * 
     * @return void
     */
    function ptt_today_start_new_session_callback() {
        TodayController::startNewSessionCallback();
    }
}

if (!function_exists('ptt_get_daily_entries_callback')) {
    /**
     * AJAX handler to get time entries for a specific day for the current user
     * 
     * @return void
     */
    function ptt_get_daily_entries_callback() {
        TodayController::getDailyEntriesCallback();
    }
}

if (!function_exists('ptt_update_session_duration_callback')) {
    /**
     * AJAX handler to update session duration
     * 
     * @return void
     */
    function ptt_update_session_duration_callback() {
        TodayController::updateSessionDurationCallback();
    }
}

if (!function_exists('ptt_update_session_field_callback')) {
    /**
     * AJAX handler to update session field
     * 
     * @return void
     */
    function ptt_update_session_field_callback() {
        TodayController::updateSessionFieldCallback();
    }
}

if (!function_exists('ptt_delete_session_callback')) {
    /**
     * AJAX handler to delete session
     * 
     * @return void
     */
    function ptt_delete_session_callback() {
        TodayController::deleteSessionCallback();
    }
}

if (!function_exists('ptt_move_session_to_task')) {
    /**
     * Move a session from one task to another
     * 
     * @param int $source_post_id Source task ID
     * @param int $session_index Session index
     * @param int $target_post_id Target task ID
     * @return bool Success status
     */
    function ptt_move_session_to_task($source_post_id, $session_index, $target_post_id) {
        return TodayController::moveSessionToTask($source_post_id, $session_index, $target_post_id);
    }
}

if (!function_exists('ptt_move_session_callback')) {
    /**
     * AJAX handler to move session to another task
     * 
     * @return void
     */
    function ptt_move_session_callback() {
        TodayController::moveSessionCallback();
    }
}

if (!function_exists('ptt_today_start_timer_callback')) {
    /**
     * AJAX handler to start a timer from the Today page
     * 
     * @return void
     */
    function ptt_today_start_timer_callback() {
        TodayController::startTimerCallback();
    }
}

if (!function_exists('ptt_today_quick_start_callback')) {
    /**
     * AJAX handler for Quick Start functionality
     * 
     * @return void
     */
    function ptt_today_quick_start_callback() {
        TodayController::quickStartCallback();
    }
}

if (!function_exists('ptt_get_or_create_quick_start_project')) {
    /**
     * Get or create the global placeholder Project
     * 
     * @return int Project term ID
     */
    function ptt_get_or_create_quick_start_project() {
        return TodayController::getOrCreateQuickStartProject();
    }
}

if (!function_exists('ptt_get_or_create_daily_quick_start_task')) {
    /**
     * Get or create the per-user placeholder Task under the placeholder project
     * 
     * @param int $user_id User ID
     * @param int $project_term_id Project term ID
     * @param int $client_term_id Client term ID
     * @return int Task post ID
     */
    function ptt_get_or_create_daily_quick_start_task($user_id, $project_term_id, $client_term_id) {
        return TodayController::getOrCreateDailyQuickStartTask($user_id, $project_term_id, $client_term_id);
    }
}

// Register hooks using the procedural functions for backward compatibility
add_action('admin_menu', 'ptt_add_today_page', 5);
add_action('admin_enqueue_scripts', 'ptt_today_enqueue_font', 20);

// Register AJAX handlers
add_action('wp_ajax_ptt_get_tasks_for_today_page', 'ptt_get_tasks_for_today_page_callback');
add_action('wp_ajax_ptt_today_start_new_session', 'ptt_today_start_new_session_callback');
add_action('wp_ajax_ptt_get_daily_entries', 'ptt_get_daily_entries_callback');
add_action('wp_ajax_ptt_update_session_duration', 'ptt_update_session_duration_callback');
add_action('wp_ajax_ptt_update_session_field', 'ptt_update_session_field_callback');
add_action('wp_ajax_ptt_delete_session', 'ptt_delete_session_callback');
add_action('wp_ajax_ptt_move_session', 'ptt_move_session_callback');
add_action('wp_ajax_ptt_today_start_timer', 'ptt_today_start_timer_callback');
add_action('wp_ajax_ptt_today_quick_start', 'ptt_today_quick_start_callback');
