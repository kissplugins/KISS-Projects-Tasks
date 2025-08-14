<?php
/**
 * ------------------------------------------------------------------
 * HELPERS
 * ------------------------------------------------------------------
 *
 * This file contains reusable helper functions for use across
 * various plugin modules.
 *
 * ------------------------------------------------------------------
 */

use KISS\PTT\Helpers\TaskHelper;

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Gets all task post IDs assigned to a specific user.
 *
 * A task belongs to a user if they are the ptt_assignee meta value.
 * This ensures users only see tasks that are actually assigned to them,
 * not tasks they created for others.
 *
 * @param int $user_id The ID of the user.
 * @return array An array of task post IDs. Returns an empty array if no tasks are found.
 */
function ptt_get_tasks_for_user( $user_id ) {
    return TaskHelper::get_tasks_for_user( $user_id );
}


/**
 * Find the currently running session for a user, if any.
 * Returns array like ['post_id' => int, 'index' => int] or false if none.
 */
function ptt_get_active_session_index_for_user( $user_id ) {
    if ( ! $user_id ) { return false; }
    if ( ! function_exists('get_field') ) { return false; }
    // Get tasks assigned to user
    $task_ids = function_exists('ptt_get_tasks_for_user') ? ptt_get_tasks_for_user($user_id) : [];
    if ( empty($task_ids) ) { return false; }
    foreach ( $task_ids as $task_id ) {
        $sessions = get_field( 'sessions', $task_id );
        if ( empty($sessions) || !is_array($sessions) ) { continue; }
        foreach ( $sessions as $idx => $s ) {
            $has_start = ! empty( $s['session_start_time'] );
            $has_stop  = ! empty( $s['session_stop_time'] );
            $is_running = $has_start && ! $has_stop;
            if ( $is_running ) {
                return [ 'post_id' => (int) $task_id, 'index' => (int) $idx ];
            }
        }
    }
    return false;
}
