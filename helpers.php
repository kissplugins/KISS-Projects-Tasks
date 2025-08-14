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
	global $wpdb;

	if ( ! $user_id ) {
		return [];
	}

	// Get posts where the user is the assignee
	$assigned_posts_query = $wpdb->prepare(
		"SELECT post_id FROM {$wpdb->postmeta}
		 WHERE meta_key = 'ptt_assignee' AND meta_value = %d",
		$user_id
	);
	$assigned_posts = $wpdb->get_col( $assigned_posts_query );

	// Merge, remove duplicates, and ensure all values are integers
	$task_ids = array_map( 'intval', array_unique( $assigned_posts ) );

	return $task_ids;
}

/**
 * Validates whether a specific user (by ID) is the assignee of a task.
 * Pure check: no reliance on the current logged-in user or capabilities.
 */
function ptt_validate_task_access( int $post_id, int $user_id ): bool {
    if ( ! $post_id || ! $user_id ) return false;
    if ( get_post_type( $post_id ) !== 'project_task' ) return false;
    $assignee = (int) get_post_meta( $post_id, 'ptt_assignee', true );
    return $assignee === (int) $user_id;
}


/** Input validation helpers (centralized) */

/**
 * Validate and normalize a WordPress ID (post, term, user).
 */
function ptt_validate_id( $val ): int {
    $id = absint( $val );
    return $id > 0 ? $id : 0;
}

/**
 * Validate a date string in YYYY-MM-DD format; returns empty string if invalid.
 */
function ptt_validate_date( $val ): string {
    $s = is_string( $val ) ? trim( $val ) : '';
    if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $s ) ) {
        return '';
    }
    // Extra check for real calendar date
    [$y, $m, $d] = array_map( 'intval', explode( '-', $s ) );
    return checkdate( $m, $d, $y ) ? $s : '';
}

/**
 * Validate session index against a count; returns -1 if invalid.
 */
function ptt_validate_session_index( $val, int $count ): int {
    $idx = absint( $val );
    return ( $idx >= 0 && $idx < $count ) ? $idx : -1;
}

/**
 * Validate duration in hours. Accepts decimal or HH:MM[:SS]. Clamps to [0, 48].
 */
function ptt_validate_duration( $val ): float {
    if ( is_string( $val ) && strpos( $val, ':' ) !== false ) {
        $parts = explode( ':', $val );
        $h = isset( $parts[0] ) ? absint( $parts[0] ) : 0;
        $m = isset( $parts[1] ) ? absint( $parts[1] ) : 0;
        $s = isset( $parts[2] ) ? absint( $parts[2] ) : 0;
        $hours = $h + ($m / 60) + ($s / 3600);
    } else {
        $hours = (float) $val;
    }
    if ( ! is_finite( $hours ) ) { $hours = 0.0; }
    return max( 0.0, min( 48.0, $hours ) );
}
