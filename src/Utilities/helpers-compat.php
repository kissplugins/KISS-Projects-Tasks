<?php
/**
 * Backward compatibility functions for helpers.php
 * 
 * This file provides procedural function wrappers around the PSR-4 Helpers class
 * to maintain backward compatibility with existing code.
 */

use KISS\PTT\Utilities\Helpers;

// Block direct access
if (!defined('WPINC')) {
    die;
}

if (!function_exists('ptt_get_tasks_for_user')) {
    /**
     * Gets all task post IDs assigned to a specific user
     * 
     * A task belongs to a user if they are the ptt_assignee meta value.
     * This ensures users only see tasks that are actually assigned to them,
     * not tasks they created for others.
     * 
     * @param int $user_id The ID of the user
     * @return array An array of task post IDs. Returns an empty array if no tasks are found.
     */
    function ptt_get_tasks_for_user($user_id) {
        return Helpers::getTasksForUser($user_id);
    }
}

if (!function_exists('ptt_get_active_session_index_for_user')) {
    /**
     * Find the currently running session for a user, if any
     * 
     * @param int $user_id The user ID to check
     * @return array|false An array with post_id and index of the active session, or false if none
     */
    function ptt_get_active_session_index_for_user($user_id) {
        return Helpers::getActiveSessionIndexForUser($user_id);
    }
}
