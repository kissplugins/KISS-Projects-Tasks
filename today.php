<?php
/**
 * ------------------------------------------------------------------
 * 12.0 ADMIN PAGE & LINKS (Today)
 * ------------------------------------------------------------------
 *
 * This file registers the "Today" page and renders its markup and
 * logic for a daily time-tracking dashboard view.
 *
 * ------------------------------------------------------------------
 */

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Adds the "Today" link under the Tasks CPT menu.
 */
function ptt_add_today_page() {
	add_submenu_page(
		'edit.php?post_type=project_task', // Parent slug
		'Today View',                      // Page title
		'Today',                           // Menu title
		'edit_posts',                      // Capability
		'ptt-today',                       // Menu slug
		'ptt_render_today_page_html'       // Callback
	);
}
add_action( 'admin_menu', 'ptt_add_today_page', 5 ); // High priority to appear early

/**
 * Renders the Today page HTML.
 */
function ptt_render_today_page_html() {
	?>
	<div class="wrap" id="ptt-today-page-container">
		<h1>Today</h1>

		<div class="ptt-today-filters">
			<label for="ptt-today-user-filter">User:</label>
			<?php
			wp_dropdown_users( [
				'name'             => 'ptt-today-user-filter',
				'id'               => 'ptt-today-user-filter',
				'capability'       => 'edit_posts',
				'selected'         => get_current_user_id(),
				'show_option_none' => 'Select a User...',
				'option_none_value' => '0',
			] );
			?>
		</div>

		<div class="ptt-today-entry-box">
			<div class="ptt-today-input-group">
				<input type="text" id="ptt-today-session-title" placeholder="What are you working on?">
				<select id="ptt-today-task-select" disabled>
					<option value="">-- Select a User First --</option>
				</select>
				<div id="ptt-today-project-display" class="ptt-today-project-display">-- Project will appear here --</div>
			</div>
			<div class="ptt-today-timer-controls">
				<div class="ptt-today-timer-display">00:00:00</div>
				<button type="button" id="ptt-today-start-stop-btn" class="button button-primary">Start</button>
			</div>
		</div>

		<div class="ptt-today-entries-area">
			<div class="ptt-today-entries-header">
				<h2>Time Entries</h2>
				<div class="ptt-today-date-switcher">
					<select id="ptt-today-date-select">
						<?php
						for ( $i = 0; $i < 10; $i++ ) {
							$date_val  = date( 'Y-m-d', strtotime( "-$i days" ) );
							$date_text = '';
							if ( $i === 0 ) {
								$date_text = 'Today';
							} elseif ( $i === 1 ) {
								$date_text = 'Yesterday';
							} else {
								$date_text = date( 'l, M j', strtotime( "-$i days" ) );
							}
							echo '<option value="' . esc_attr( $date_val ) . '">' . esc_html( $date_text ) . '</option>';
						}
						?>
					</select>
					<span id="ptt-today-total">Total: <strong>00:00</strong></span>
				</div>
			</div>
			<div id="ptt-today-entries-list">
				<div class="ptt-ajax-spinner"></div>
			</div>
		</div>

	</div>
	<?php
}

/**
 * AJAX handler to get the project for a selected task.
 */
function ptt_get_project_for_task_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
	if ( ! $task_id ) {
		wp_send_json_error( [ 'message' => 'Invalid task ID.' ] );
	}

	$project_terms = get_the_terms( $task_id, 'project' );
	$project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : 'No Project';

	wp_send_json_success( [ 'project_name' => $project_name ] );
}
add_action( 'wp_ajax_ptt_get_project_for_task', 'ptt_get_project_for_task_callback' );

/**
 * AJAX handler to get tasks for the Today page dropdown.
 * Fetches tasks that are "Not Started" or "In Progress" and sorts them by last modified.
 */
function ptt_get_tasks_for_today_page_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
	
	// If no valid user selected, return empty
	if ( ! $user_id ) {
		wp_send_json_success( [] );
		return;
	}

	// Get Term IDs for "Not Started" and "In Progress"
	$status_terms_to_include = [];
	$not_started = get_term_by( 'name', 'Not Started', 'task_status' );
	$in_progress = get_term_by( 'name', 'In Progress', 'task_status' );
	if ( $not_started ) $status_terms_to_include[] = $not_started->term_id;
	if ( $in_progress ) $status_terms_to_include[] = $in_progress->term_id;

	if ( empty( $status_terms_to_include ) ) {
		wp_send_json_error( [ 'message' => 'Required task statuses not found.' ] );
	}

	// Get all tasks where the user is either the author or the assignee
	$author_tasks = get_posts( [
		'post_type'      => 'project_task',
		'author'         => $user_id,
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$assignee_tasks = get_posts( [
		'post_type'      => 'project_task',
		'meta_key'       => 'ptt_assignee',
		'meta_value'     => $user_id,
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$all_task_ids = array_unique( array_merge( $author_tasks, $assignee_tasks ) );

	if ( empty( $all_task_ids ) ) {
		wp_send_json_success( [] );
		return;
	}

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => 100,
		'post__in'       => $all_task_ids,
		'orderby'        => 'modified', // LIFO
		'order'          => 'DESC',
		'tax_query'      => [
			[
				'taxonomy' => 'task_status',
				'field'    => 'term_id',
				'terms'    => $status_terms_to_include,
			],
		],
	];

	$query = new WP_Query( $args );
	$tasks = [];
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();

			$project_terms = get_the_terms( $post_id, 'project' );
			$project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : 'N/A';

			$tasks[] = [
				'id'           => $post_id,
				'title'        => get_the_title(),
				'project_name' => $project_name,
			];
		}
		wp_reset_postdata();
	}

	wp_send_json_success( $tasks );
}
add_action( 'wp_ajax_ptt_get_tasks_for_today_page', 'ptt_get_tasks_for_today_page_callback' );

/**
 * AJAX handler to check if a user has an active session running.
 */
function ptt_check_active_session_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
	if ( ! $user_id ) {
		wp_send_json_error( [ 'has_active' => false ] );
		return;
	}

	$active_session = ptt_get_active_session_index_for_user( $user_id );
	if ( $active_session ) {
		$sessions = get_field( 'sessions', $active_session['post_id'] );
		$session = $sessions[ $active_session['index'] ];
		
		wp_send_json_success( [
			'has_active'     => true,
			'post_id'        => $active_session['post_id'],
			'row_index'      => $active_session['index'],
			'task_title'     => get_the_title( $active_session['post_id'] ),
			'session_title'  => $session['session_title'],
			'start_time'     => $session['session_start_time'],
		] );
	} else {
		wp_send_json_success( [ 'has_active' => false ] );
	}
}
add_action( 'wp_ajax_ptt_check_active_session', 'ptt_check_active_session_callback' );

/**
 * AJAX handler to start a new session from the Today page.
 * This adds a new row to the session repeater and starts the timer.
 */
function ptt_today_start_new_session_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$post_id       = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$session_title = isset( $_POST['session_title'] ) ? sanitize_text_field( $_POST['session_title'] ) : 'New Session';
	$user_id       = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : get_current_user_id();

	if ( ! $post_id ) {
		wp_send_json_error( [ 'message' => 'Invalid Task ID.' ] );
	}

	if ( ! $user_id ) {
		wp_send_json_error( [ 'message' => 'Invalid User ID.' ] );
	}

	// Stop any other running session for the selected user first.
	$active_session = ptt_get_active_session_index_for_user( $user_id );
	if ( $active_session ) {
		ptt_stop_session( $active_session['post_id'], $active_session['index'] );
	}

	$new_session = [
		'session_title'      => $session_title,
		'session_start_time' => current_time( 'mysql', 1 ), // UTC
	];

	$new_row_index = add_row( 'sessions', $new_session, $post_id );

	if ( ! $new_row_index ) {
		wp_send_json_error( [ 'message' => 'Failed to create new session.' ] );
	}

	// Ensure the task is assigned to the user who is tracking time.
	update_post_meta( $post_id, 'ptt_assignee', $user_id );

	// Set the task status to "In Progress".
	$in_progress_term = get_term_by( 'name', 'In Progress', 'task_status' );
	if ( $in_progress_term && ! is_wp_error( $in_progress_term ) ) {
		wp_set_object_terms( $post_id, $in_progress_term->term_id, 'task_status' );
	}

	wp_send_json_success( [
		'message'    => 'Timer started!',
		'post_id'    => $post_id,
		'row_index'  => $new_row_index - 1, // add_row returns 1-based index
		'start_time' => $new_session['session_start_time'],
	] );
}
add_action( 'wp_ajax_ptt_today_start_new_session', 'ptt_today_start_new_session_callback' );

/**
 * AJAX handler to get time entries for a specific day.
 */
function ptt_get_daily_entries_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$target_date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : date( 'Y-m-d' );
	$user_id     = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
	
	// If no valid user selected, show no entries
	if ( ! $user_id ) {
		ob_start();
		echo '<div class="ptt-today-no-entries">Please select a user to view time entries.</div>';
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html, 'total' => '00:00' ] );
		return;
	}
	
	$all_entries = [];
	$grand_total_seconds = 0;

	// Get all tasks where the user is either the author or the assignee
	$author_tasks = get_posts( [
		'post_type'      => 'project_task',
		'author'         => $user_id,
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$assignee_tasks = get_posts( [
		'post_type'      => 'project_task',
		'meta_key'       => 'ptt_assignee',
		'meta_value'     => $user_id,
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$all_task_ids = array_unique( array_merge( $author_tasks, $assignee_tasks ) );

	if ( empty( $all_task_ids ) ) {
		ob_start();
		echo '<div class="ptt-today-no-entries">No time entries recorded for this day.</div>';
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html, 'total' => '00:00' ] );
		return;
	}

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'post__in'       => $all_task_ids,
	];

	$q = new WP_Query( $args );

	if ( $q->have_posts() ) {
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();
			$sessions = get_field( 'sessions', $post_id );

			if ( ! empty( $sessions ) && is_array( $sessions ) ) {
				foreach ( $sessions as $session ) {
					$start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
					if ( $start_str && date( 'Y-m-d', strtotime( $start_str ) ) === $target_date ) {
						$stop_str = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
						$duration_seconds = 0;

						if ( $start_str && $stop_str ) {
							$duration_seconds = strtotime( $stop_str ) - strtotime( $start_str );
						} elseif ( $start_str && ! $stop_str ) {
							// For running timers
							$duration_seconds = time() - strtotime( $start_str . 'Z' ); // Treat stored UTC time correctly
						}
						$grand_total_seconds += $duration_seconds;

						$project_terms = get_the_terms( $post_id, 'project' );
						$project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–';

						$all_entries[] = [
							'session_title'  => $session['session_title'],
							'task_title'     => get_the_title(),
							'project_name'   => $project_name,
							'start_time'     => strtotime( $start_str ),
							'duration'       => $duration_seconds > 0 ? gmdate( 'H:i:s', $duration_seconds ) : 'Running',
							'is_running'     => empty( $stop_str ),
						];
					}
				}
			}
		}
		wp_reset_postdata();
	}

	// Sort entries by start time descending
	usort( $all_entries, function( $a, $b ) {
		return $b['start_time'] <=> $a['start_time'];
	} );

	// Prepare HTML
	ob_start();
	if ( empty( $all_entries ) ) {
		echo '<div class="ptt-today-no-entries">No time entries recorded for this day.</div>';
	} else {
		foreach ( $all_entries as $entry ) {
			$running_class = $entry['is_running'] ? 'running' : '';
			?>
			<div class="ptt-today-entry <?php echo $running_class; ?>">
				<div class="entry-details">
					<span class="entry-session-title"><?php echo esc_html( $entry['session_title'] ); ?></span>
					<span class="entry-meta"><?php echo esc_html( $entry['task_title'] ); ?> &bull; <?php echo esc_html( $entry['project_name'] ); ?></span>
				</div>
				<div class="entry-duration">
					<?php echo esc_html( $entry['duration'] ); ?>
				</div>
			</div>
			<?php
		}
	}
	$html = ob_get_clean();

	$total_hours   = floor( $grand_total_seconds / 3600 );
	$total_minutes = floor( ( $grand_total_seconds / 60 ) % 60 );
	$total_formatted = sprintf( '%02d:%02d', $total_hours, $total_minutes );

	wp_send_json_success( [ 'html' => $html, 'total' => $total_formatted ] );
}
add_action( 'wp_ajax_ptt_get_daily_entries', 'ptt_get_daily_entries_callback' );


/**
 * Helper to find any active session across all tasks for a specific user.
 *
 * @param int $user_id The user ID to check.
 * @return array|false An array with post_id and index of the active session, or false.
 */
function ptt_get_active_session_index_for_user( $user_id ) {
	// Get all tasks where the user is either the author or the assignee
	$author_tasks = get_posts( [
		'post_type'      => 'project_task',
		'author'         => $user_id,
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$assignee_tasks = get_posts( [
		'post_type'      => 'project_task',
		'meta_key'       => 'ptt_assignee',
		'meta_value'     => $user_id,
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	] );
	$all_task_ids = array_unique( array_merge( $author_tasks, $assignee_tasks ) );

	if ( empty( $all_task_ids ) ) {
		return false;
	}

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post__in'       => $all_task_ids,
	];
	$query = new WP_Query( $args );
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$index = ptt_get_active_session_index( $post_id );
			if ( $index !== false ) {
				wp_reset_postdata();
				return [ 'post_id' => $post_id, 'index' => $index ];
			}
		}
	}
	wp_reset_postdata();
	return false;
}