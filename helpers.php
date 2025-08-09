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
 * Gets all task post IDs for a specific user.
 *
 * A task belongs to a user if they are the post_author OR if they
 * are the ptt_assignee meta value. This provides a single, efficient
 * way to retrieve all relevant tasks for a user.
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

	// Get posts where the user is the author
	$authored_posts_query = $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts}
		 WHERE post_author = %d AND post_type = 'project_task' AND post_status = 'publish'",
		$user_id
	);
	$authored_posts = $wpdb->get_col( $authored_posts_query );

	// Merge, remove duplicates, and ensure all values are integers
	$task_ids = array_map( 'intval', array_unique( array_merge( $assigned_posts, $authored_posts ) ) );

	return $task_ids;
}