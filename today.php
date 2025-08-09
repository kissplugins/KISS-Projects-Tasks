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
		<div class="ptt-today-header">
			<h1>Today</h1>
			<div class="ptt-today-user-filter">
				<label for="ptt-today-user-select">Show entries for:</label>
				<?php
				wp_dropdown_users( [
					'name'       => 'ptt-today-user-select',
					'id'         => 'ptt-today-user-select',
					'capability' => 'publish_posts',
					'selected'   => get_current_user_id(),
				] );
				?>
			</div>
		</div>


		<div class="ptt-today-entry-box">
			<div class="ptt-today-input-group">
				<input type="text" id="ptt-today-session-title" placeholder="What are you working on?">
				<select id="ptt-today-task-select" disabled>
					<option value="">-- Select an Assignee --</option>
				</select>
				<input type="text" id="ptt-today-project-display" placeholder="Project" readonly>
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
 * AJAX handler to get tasks for the Today page dropdown, filtered by assignee.
 * Fetches tasks that are "Not Started" or "In Progress" and sorts them by last modified.
 */
function ptt_get_tasks_for_today_page_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$assignee_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
	if ( ! $assignee_id ) {
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

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => 100,
		'post_status'    => 'publish',
		'orderby'        => 'modified', // LIFO
		'order'          => 'DESC',
		'tax_query'      => [
			[
				'taxonomy' => 'task_status',
				'field'    => 'term_id',
				'terms'    => $status_terms_to_include,
			],
		],
		'meta_query'     => [
			[
				'key'     => 'ptt_assignee',
				'value'   => $assignee_id,
				'compare' => '=',
			],
		],
	];

	$query = new WP_Query( $args );
	$tasks = [];
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$tasks[] = [
				'id'    => get_the_ID(),
				'title' => get_the_title(),
			];
		}
		wp_reset_postdata();
	}

	wp_send_json_success( $tasks );
}
add_action( 'wp_ajax_ptt_get_tasks_for_today_page', 'ptt_get_tasks_for_today_page_callback' );

/**
 * AJAX handler to get the project name for a given task.
 */
function ptt_get_project_for_task_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
	if ( ! $task_id ) {
		wp_send_json_success( [ 'project_name' => '' ] );
		return;
	}

	$project_terms = get_the_terms( $task_id, 'project' );
	$project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–';

	wp_send_json_success( [ 'project_name' => $project_name ] );
}
add_action( 'wp_ajax_ptt_get_project_for_task', 'ptt_get_project_for_task_callback' );


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

	if ( ! $post_id ) {
		wp_send_json_error( [ 'message' => 'Invalid Task ID.' ] );
	}

	// Stop any other running session for the current user first.
	$active_session = ptt_get_active_session_index_for_user( get_current_user_id() );
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

	wp_send_json_success( [
		'message'    => 'Timer started!',
		'post_id'    => $post_id,
		'row_index'  => $new_row_index - 1, // add_row returns 1-based index
		'start_time' => $new_session['session_start_time'],
	] );
}
add_action( 'wp_ajax_ptt_today_start_new_session', 'ptt_today_start_new_session_callback' );

/**
 * AJAX handler to get time entries for a specific day, filtered by assignee.
 */
function ptt_get_daily_entries_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$target_date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : date( 'Y-m-d' );
	$assignee_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;

	$all_entries         = [];
	$grand_total_seconds = 0;

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
	];

	if ( $assignee_id > 0 ) {
		$args['meta_query'] = [
			[
				'key'     => 'ptt_assignee',
				'value'   => $assignee_id,
				'compare' => '=',
			],
		];
	}

	$q = new WP_Query( $args );

	if ( $q->have_posts() ) {
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id  = get_the_ID();
			$sessions = get_field( 'sessions', $post_id );

			if ( ! empty( $sessions ) && is_array( $sessions ) ) {
				foreach ( $sessions as $session ) {
					$start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
					if ( $start_str && date( 'Y-m-d', strtotime( $start_str ) ) === $target_date ) {
						$stop_str         = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
						$duration_seconds = 0;

						if ( $start_str && $stop_str ) {
							$duration_seconds = strtotime( $stop_str ) - strtotime( $start_str );
						} elseif ( $start_str && ! $stop_str ) {
							// For running timers
							$duration_seconds = time() - strtotime( $start_str );
						}
						$grand_total_seconds += $duration_seconds;

						$project_terms = get_the_terms( $post_id, 'project' );
						$project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–';

						$all_entries[] = [
							'session_title' => $session['session_title'],
							'task_title'    => get_the_title(),
							'project_name'  => $project_name,
							'start_time'    => strtotime( $start_str ),
							'duration'      => $duration_seconds > 0 ? gmdate( 'H:i:s', $duration_seconds ) : 'Running',
							'is_running'    => empty( $stop_str ),
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

	$total_hours         = floor( $grand_total_seconds / 3600 );
	$total_minutes       = floor( ( $grand_total_seconds / 60 ) % 60 );
	$total_formatted     = sprintf( '%02d:%02d', $total_hours, $total_minutes );

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
	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'author'         => $user_id, // Check tasks created by the user
		'meta_query'     => [
			[
				'key'     => 'ptt_assignee', // Also check tasks assigned to the user
				'value'   => $user_id,
				'compare' => '=',
			],
			'relation' => 'OR',
		],
	];
	$query = new WP_Query( $args );
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id_to_check = get_the_ID();
			$sessions = get_field('sessions', $post_id_to_check);
			if (!empty($sessions) && is_array($sessions)) {
				foreach ($sessions as $index => $session) {
					// Check if session belongs to the current user and is running
					// Note: This assumes the person who started the session is the current user.
					// A more robust system would store user_id per session.
					if ( ! empty( $session['session_start_time'] ) && empty( $session['session_stop_time'] ) ) {
						// Found an active session, let's assume it's for the current user.
						wp_reset_postdata();
						return [ 'post_id' => $post_id_to_check, 'index' => $index ];
					}
				}
			}
		}
	}
	wp_reset_postdata();
	return false;
}