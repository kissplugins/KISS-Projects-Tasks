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

		<div class="ptt-today-entry-box">
			<div class="ptt-today-input-group">
				<input type="text" id="ptt-today-session-title" placeholder="What are you working on?">
				<select id="ptt-today-task-select" disabled>
					<option value="">-- Select a Project First --</option>
				</select>
				<?php
				wp_dropdown_categories( [
					'taxonomy'        => 'project',
					'name'            => 'ptt-today-project-filter',
					'id'              => 'ptt-today-project-filter',
					'show_option_all' => 'Filter by Project...',
					'hide_empty'      => false,
					'hierarchical'    => true,
				] );
				?>
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

		<div id="ptt-today-debug-area" style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9;">
			<h3>Debug Info</h3>
			<div id="ptt-debug-content"></div>
		</div>

	</div>
	<?php
}

/**
 * AJAX handler to get tasks for the Today page dropdown.
 * Fetches tasks that are "Not Started" or "In Progress" for the current user and sorts them by last modified.
 */
function ptt_get_tasks_for_today_page_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$project_id = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
	$user_id    = get_current_user_id();

	// Get all tasks for the current user (author or assignee)
	$user_task_ids = ptt_get_tasks_for_user( $user_id );
	if ( empty( $user_task_ids ) ) {
		wp_send_json_success( [] ); // Send empty array if user has no tasks
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
		'post__in'       => $user_task_ids, // Only query user's tasks
		'tax_query'      => [
			'relation' => 'AND',
			[
				'taxonomy' => 'task_status',
				'field'    => 'term_id',
				'terms'    => $status_terms_to_include,
			],
		],
	];

	if ( $project_id > 0 ) {
		$args['tax_query'][] = [
			'taxonomy' => 'project',
			'field'    => 'term_id',
			'terms'    => $project_id,
		];
	}

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
 * AJAX handler to get time entries for a specific day for the current user.
 */
function ptt_get_daily_entries_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$user_id = get_current_user_id();
	$target_date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : date( 'Y-m-d' );
	$all_entries = [];
	$grand_total_seconds = 0;

	// Get all tasks for the current user to make the query more efficient.
	$user_task_ids = ptt_get_tasks_for_user( $user_id );

	// If the user has no tasks, we can stop right here.
	if ( empty( $user_task_ids ) ) {
		ob_start();
		echo '<div class="ptt-today-no-entries">No time entries recorded for this day.</div>';
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html, 'total' => '00:00' ] );
	}

	// Get Term IDs for statuses
	$status_terms_to_include = [];
	$status_names = ['Not Started', 'In Progress', 'Completed', 'Blocked'];
	foreach ($status_names as $status_name) {
		$term = get_term_by('name', $status_name, 'task_status');
		if ($term) {
			$status_terms_to_include[] = $term->term_id;
		}
	}

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post_status'    => ['publish', 'private'],
		'post__in'       => $user_task_ids, // The crucial filter
		'tax_query'      => [
			'relation' => 'AND',
			[
				'taxonomy' => 'task_status',
				'field'    => 'term_id',
				'terms'    => $status_terms_to_include,
			],
		],
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
					if ( empty( $start_str ) ) {
						continue;
					}

					// Validate strtotime before using it
					$start_ts = strtotime( $start_str );
					if ( ! $start_ts ) {
						continue; // Skip if start date is invalid
					}

					if ( date( 'Y-m-d', $start_ts ) === $target_date ) {
						$stop_str = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
						$duration_seconds = 0;
						$stop_ts = strtotime( $stop_str );

						if ( $start_ts && $stop_ts ) {
							$duration_seconds = $stop_ts - $start_ts;
						} elseif ( $start_ts && ! $stop_str ) {
							// For running timers
							$duration_seconds = time() - $start_ts;
						}
						$grand_total_seconds += $duration_seconds;

                                               $project_terms = get_the_terms( $post_id, 'project' );
                                               $project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–';

                                               $client_terms = get_the_terms( $post_id, 'client' );
                                               $client_name  = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '–';

                                               $all_entries[] = [
                                                       'session_title' => $session['session_title'],
                                                       'task_title'    => get_the_title(),
                                                       'task_id'       => $post_id,
                                                       'project_name'  => $project_name,
                                                       'client_name'   => $client_name,
                                                       'start_time'    => $start_ts,
                                                       'stop_time'     => $stop_ts, // Added stop_time
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
                                       <?php $edit_link = get_edit_post_link( $entry['task_id'] ); ?>
                                       <span class="entry-meta"><a href="<?php echo esc_url( $edit_link ); ?>"><?php echo esc_html( $entry['task_title'] ); ?></a> &bull; <?php echo esc_html( $entry['project_name'] ); ?> &bull; <?php echo esc_html( $entry['client_name'] ); ?></span>
                               </div>
                               <div class="entry-duration">
					<?php echo esc_html( wp_date( 'g:i:s A', $entry['start_time'] ) ); ?> |
					<?php echo $entry['is_running'] ? 'Now' : esc_html( wp_date( 'g:i:s A', $entry['stop_time'] ) ); ?> |
					SUB-TOTAL: <?php echo esc_html( $entry['duration'] ); ?>
				</div>
			</div>
			<?php
		}
	}
	$html = ob_get_clean();

	// Prepare Debug Info for Today page. WARNING: Do not modify or refactor unless specifically requested
	$debug_info = [];
	$current_user = wp_get_current_user();
	$debug_info['user'] = $current_user->user_login . ' (ID: ' . $current_user->ID . ')';
	$debug_info['date'] = $target_date;
	$debug_info['queried_statuses'] = implode(', ', $status_names);
	$debug_info['matched_tasks'] = $q->found_posts;
	$debug_info['matched_sessions'] = count( $all_entries );

	// Add timezone and time computations
	$wp_timezone_string = wp_timezone_string();
	$current_utc_time = current_time('mysql', 1);
	$current_local_time = current_time('mysql', 0);

	$debug_info['wp_timezone'] = $wp_timezone_string;
	$debug_info['current_utc_time'] = $current_utc_time;
	$debug_info['current_local_time'] = $current_local_time;

	ob_start();
	?>
	<ul>
		<li><strong>User:</strong> <?php echo esc_html( $debug_info['user'] ); ?></li>
		<li><strong>Date:</strong> <?php echo esc_html( $debug_info['date'] ); ?></li>
		<li><strong>Queried Statuses:</strong> <?php echo esc_html( $debug_info['queried_statuses'] ); ?></li>
		<li><strong>Tasks Found:</strong> <?php echo esc_html( $debug_info['matched_tasks'] ); ?></li>
		<li><strong>Sessions on this Date:</strong> <?php echo esc_html( $debug_info['matched_sessions'] ); ?></li>
		<li><strong>Task Query Rule:</strong> Tasks where the current user is the assignee.</li>
		<li><strong>WordPress Timezone:</strong> <?php echo esc_html( $debug_info['wp_timezone'] ); ?></li>
		<li><strong>Current UTC Time:</strong> <?php echo esc_html( $debug_info['current_utc_time'] ); ?></li>
		<li><strong>Current Local Time:</strong> <?php echo esc_html( $debug_info['current_local_time'] ); ?></li>
	</ul>
	<?php
	/**
	 * --- Recommendations for Future Debugging ---
	 * 1. To see the full WP_Query arguments, you could add:
	 *    <pre><?php print_r( $args ); ?></pre>
	 * 2. For more complex debugging, consider installing the "Query Monitor" plugin.
	 * 3. Use browser console logs by passing debug data to JavaScript and using console.log().
	 */
	$debug_html = ob_get_clean();

	$total_hours   = floor( $grand_total_seconds / 3600 );
	$total_minutes = floor( ( $grand_total_seconds / 60 ) % 60 );
	$total_seconds_remainder = $grand_total_seconds % 60; // Calculate remaining seconds
	$total_formatted = sprintf( '%02d:%02d:%02d', $total_hours, $total_minutes, $total_seconds_remainder ); // Include seconds

	wp_send_json_success( [ 'html' => $html, 'total' => $total_formatted, 'debug' => $debug_html ] );
}
add_action( 'wp_ajax_ptt_get_daily_entries', 'ptt_get_daily_entries_callback' );


/**
 * Helper to find any active session across all tasks for a specific user.
 *
 * @param int $user_id The user ID to check.
 * @return array|false An array with post_id and index of the active session, or false.
 */
function ptt_get_active_session_index_for_user( $user_id ) {
	$user_task_ids = ptt_get_tasks_for_user( $user_id );
	if ( empty( $user_task_ids ) ) {
		return false;
	}

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post__in'       => $user_task_ids,
		'fields'         => 'ids', // We only need the IDs
	];

	$query = new WP_Query( $args );

	if ( $query->have_posts() ) {
		foreach ( $query->posts as $post_id ) {
			$index = ptt_get_active_session_index( $post_id );
			if ( $index !== false ) {
				// No need to reset postdata as we are only using IDs
				return [ 'post_id' => $post_id, 'index' => $index ];
			}
		}
	}

	return false;
}