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
