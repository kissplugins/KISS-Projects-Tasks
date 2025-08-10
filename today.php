<?php
/**
 * ------------------------------------------------------------------
 * 12.0 ADMIN PAGE & LINKS (Today) via CLAUDE
 * ------------------------------------------------------------------
 *
 * This file registers the "Today" page and renders its markup and
 * logic for a daily time-tracking dashboard view.
 *
 * Version: 1.10.4
 * ------------------------------------------------------------------
 */

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

// Load helper classes
require_once PTT_PLUGIN_DIR . 'today-helpers.php';

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
 * Enqueues the Silkscreen font and time display styles for the Today page.
 *
 * @param string $hook Current admin page hook.
 */
function ptt_today_enqueue_font( $hook ) {
    if ( 'project_task_page_ptt-today' !== $hook ) {
        return;
    }

    wp_enqueue_style( 'ptt-silkscreen-font', 'https://fonts.googleapis.com/css2?family=Silkscreen&display=swap', [], null );
    wp_add_inline_style(
        'ptt-styles',
        '.ptt-time-display{font-family:"Silkscreen",monospace;font-weight:400;}.entry-duration{font-family:inherit;font-weight:400;}'
    );
}
add_action( 'admin_enqueue_scripts', 'ptt_today_enqueue_font', 20 );

/**
 * Renders the Today page HTML.
 */
function ptt_render_today_page_html() {
	?>
	<div class="wrap" id="ptt-today-page-container">
		<h1>Today</h1>

		<!-- Timer Entry Box -->
		<div class="ptt-today-entry-box" data-module="timer-entry">
			<div class="ptt-today-input-group">
				<input type="text" 
				       id="ptt-today-session-title" 
				       placeholder="What are you working on?"
				       data-field="session-title">
				
				<select id="ptt-today-task-select" 
				        disabled
				        data-field="task-selector">
					<option value="">-- Select a Project First --</option>
				</select>
				
				<?php
				// Project filter dropdown
				wp_dropdown_categories( [
					'taxonomy'        => 'project',
					'name'            => 'ptt-today-project-filter',
					'id'              => 'ptt-today-project-filter',
					'show_option_all' => 'Filter by Project...',
					'hide_empty'      => false,
					'hierarchical'    => true,
				] );
				?>
				
				<!-- Placeholder for future client filter -->
				<div id="ptt-today-client-filter-container" style="display: none;">
					<?php
					// Ready for future implementation
					wp_dropdown_categories( [
						'taxonomy'        => 'client',
						'name'            => 'ptt-today-client-filter',
						'id'              => 'ptt-today-client-filter',
						'show_option_all' => 'All Clients',
						'hide_empty'      => false,
						'hierarchical'    => true,
						'show'            => 0, // Hidden by default
					] );
					?>
				</div>
			</div>
			
			<div class="ptt-today-timer-controls">
				<div class="ptt-today-timer-display" data-timer="main">00:00:00</div>
				<button type="button" 
				        id="ptt-today-start-stop-btn" 
				        class="button button-primary"
				        data-action="toggle-timer">Start</button>
			</div>
		</div>

		<!-- Time Entries Area -->
		<div class="ptt-today-entries-area" data-module="entries-list">
			<div class="ptt-today-entries-header">
				<h2>Time Entries</h2>
				<div class="ptt-today-controls-wrapper">
					<!-- Date Switcher -->
					<div class="ptt-today-date-switcher">
						<button class="ptt-date-nav" data-direction="prev" title="Previous day">
							<span class="dashicons dashicons-arrow-left-alt2"></span>
						</button>
						
						<select id="ptt-today-date-select" data-field="date-selector">
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
						
						<button class="ptt-date-nav" data-direction="next" title="Next day">
							<span class="dashicons dashicons-arrow-right-alt2"></span>
						</button>
					</div>
					
					<!-- Total Display -->
                                        <span id="ptt-today-total" data-field="total-display">
                                                Total: <strong class="ptt-time-display">00:00:00</strong>
                                        </span>
					
					<!-- View Options (Future) -->
					<div class="ptt-today-view-options" style="display: none;">
						<button class="button button-small" data-view="compact">Compact</button>
						<button class="button button-small" data-view="detailed">Detailed</button>
					</div>
				</div>
			</div>
			
			<!-- Entries List Container -->
			<div id="ptt-today-entries-list" data-container="entries">
				<div class="ptt-ajax-spinner"></div>
			</div>
			
			<!-- Entry Template (Hidden, for JS cloning) -->
                       <template id="ptt-today-entry-template">
                               <div class="ptt-today-entry" data-entry-id="" data-post-id="" data-session-index="">
                                       <div class="entry-details">
                                               <span class="entry-session-title" data-field="session_title"></span>
                                               <span class="entry-meta">
                                                       <select class="ptt-entry-task-selector" data-original-task=""></select>
                                                       <button type="button" class="button button-small ptt-move-session-btn" style="display:none;">Move</button>
                                                       <button type="button" class="button button-small ptt-cancel-move-btn" style="display:none;">Cancel</button>
                                                       &bull;
                                                       <span class="entry-project-name" data-field="project_name"></span>
                                                       <span class="entry-client-wrapper" data-client-wrapper style="display:none;">
                                                               &bull;
                                                               <span class="entry-client-name" data-field="client_name"></span>
                                                       </span>
                                               </span>
                                       </div>
                                       <div class="entry-duration" data-field="duration">
                                               Start: <span class="ptt-time-display" data-start></span> |
                                               End: <span class="ptt-time-display" data-end></span> |
                                               Sub-total: <span class="ptt-time-display" data-subtotal></span>
                                       </div>
                               </div>
                       </template>
               </div>

		<!-- Debug Area -->
		<div id="ptt-today-debug-area" 
		     style="margin-top: 20px; padding: 10px; border: 1px solid #ccc; background: #f9f9f9;"
		     data-module="debug">
			<h3>Debug Info</h3>
			<div id="ptt-debug-content" data-container="debug-content"></div>
		</div>

		<!-- Hidden Data Storage for JS -->
		<div id="ptt-today-data-storage" style="display: none;">
			<input type="hidden" id="ptt-current-user-id" value="<?php echo get_current_user_id(); ?>">
			<input type="hidden" id="ptt-ajax-nonce" value="<?php echo wp_create_nonce( 'ptt_ajax_nonce' ); ?>">
			<input type="hidden" id="ptt-ajax-url" value="<?php echo admin_url( 'admin-ajax.php' ); ?>">
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
	$client_id  = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
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
	
	if ( $client_id > 0 ) {
		$args['tax_query'][] = [
			'taxonomy' => 'client',
			'field'    => 'term_id',
			'terms'    => $client_id,
		];
	}

	$query = new WP_Query( $args );
	$tasks = [];
	if ( $query->have_posts() ) {
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			
			// Get additional metadata for richer dropdown display
			$project_terms = get_the_terms( $post_id, 'project' );
			$project_name = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '';
			
			$tasks[] = [
				'id'           => $post_id,
				'title'        => get_the_title(),
				'project_name' => $project_name,
				'edit_link'    => get_edit_post_link( $post_id ),
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
	$session_notes = isset( $_POST['session_notes'] ) ? sanitize_textarea_field( $_POST['session_notes'] ) : '';

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
		'session_notes'      => $session_notes,
		'session_start_time' => current_time( 'mysql', 1 ), // UTC
	];

	$new_row_index = add_row( 'sessions', $new_session, $post_id );

	if ( ! $new_row_index ) {
		wp_send_json_error( [ 'message' => 'Failed to create new session.' ] );
	}

        // Get task metadata for response
        $task_title   = get_the_title( $post_id );
        $project_terms = get_the_terms( $post_id, 'project' );
        $project_name  = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '';
        $client_terms  = get_the_terms( $post_id, 'client' );
        $client_name   = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '';

        wp_send_json_success( [
                'message'      => 'Timer started!',
                'post_id'      => $post_id,
                'row_index'    => $new_row_index - 1, // add_row returns 1-based index
                'start_time'   => $new_session['session_start_time'],
                'task_title'   => $task_title,
                'project_name' => $project_name,
                'client_name'  => $client_name,
                'session_data' => [
                        'title' => $session_title,
                        'notes' => $session_notes,
                ],
        ] );
}
add_action( 'wp_ajax_ptt_today_start_new_session', 'ptt_today_start_new_session_callback' );

/**
 * AJAX handler to get time entries for a specific day for the current user.
 * Now uses the modular helper classes for better flexibility.
 */
function ptt_get_daily_entries_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error();
	}

	$user_id = get_current_user_id();
	$target_date = isset( $_POST['date'] ) ? sanitize_text_field( $_POST['date'] ) : date( 'Y-m-d' );
	$client_id = isset( $_POST['client_id'] ) ? intval( $_POST['client_id'] ) : 0;
	$project_id = isset( $_POST['project_id'] ) ? intval( $_POST['project_id'] ) : 0;
	
	// Build filters array
	$filters = [];
	if ( $client_id > 0 ) {
		$filters['client_id'] = $client_id;
	}
	if ( $project_id > 0 ) {
		$filters['project_id'] = $project_id;
	}
	
        // Fetch entries and build custom HTML with start/end times
        $entries = PTT_Today_Data_Provider::get_daily_entries( $user_id, $target_date, $filters );
        $total   = PTT_Today_Data_Provider::calculate_total_duration( $entries );

        ob_start();
        if ( empty( $entries ) ) {
                echo '<div class="ptt-today-no-entries">No time entries recorded for this day.</div>';
        } else {
                echo '<div class="ptt-today-entries-wrapper" data-date="' . esc_attr( $target_date ) . '">';
                foreach ( $entries as $entry ) {
                        $entry_html = PTT_Today_Entry_Renderer::render_entry( $entry );

                        $duration_class = ! empty( $entry['is_running'] ) ? 'entry-duration-running' : '';
                        $editable_attr  = ! empty( $entry['is_running'] ) ? '' : 'data-editable="true"';

                        $start_num  = $entry['start_time'] ? wp_date( 'h:i:s', $entry['start_time'] ) : '--:--:--';
                        $start_ampm = $entry['start_time'] ? wp_date( 'A', $entry['start_time'] ) : '';

                        $end_num    = $entry['stop_time'] ? wp_date( 'h:i:s', $entry['stop_time'] ) : '--:--:--';
                        $end_ampm   = $entry['stop_time'] ? wp_date( 'A', $entry['stop_time'] ) : '';

                        $subtotal   = gmdate( 'H:i:s', $entry['duration_seconds'] ?? 0 );

                        ob_start();
                        ?>
                        <div class="entry-duration <?php echo esc_attr( $duration_class ); ?>"
                             data-field="duration"
                             data-duration-seconds="<?php echo esc_attr( $entry['duration_seconds'] ?? 0 ); ?>"
                             <?php echo $editable_attr; ?>>
                                Start: <span class="ptt-time-display"><?php echo esc_html( $start_num ); ?></span> <?php echo esc_html( $start_ampm ); ?> |
                                End: <span class="ptt-time-display"><?php echo esc_html( $end_num ); ?></span> <?php echo esc_html( $end_ampm ); ?> |
                                Sub-total: <span class="ptt-time-display"><?php echo esc_html( $subtotal ); ?></span>
                        </div>
                        <?php
                        $duration_div = ob_get_clean();
                        $entry_html   = preg_replace( '#<div class="entry-duration[^>]*>.*?</div>#s', $duration_div, $entry_html );
                        echo $entry_html;
                }
                echo '</div>';
        }
        $html = ob_get_clean();

        // Get debug info
        $entries_count = count( $entries );
        $tasks_count   = count( array_unique( array_column( $entries, 'post_id' ) ) );
        $debug_html    = PTT_Today_Page_Manager::get_debug_info( $user_id, $target_date, $tasks_count, $entries_count );

        wp_send_json_success(
                [
                        'html'    => $html,
                        'total'   => gmdate( 'H:i:s', $total['seconds'] ),
                        'debug'   => $debug_html,
                        'entries' => $entries, // Include raw data for JS manipulation
                ]
        );
}
add_action( 'wp_ajax_ptt_get_daily_entries', 'ptt_get_daily_entries_callback' );

/**
 * AJAX handler to update a session's duration (for inline editing).
 * This is a new handler for future live editing functionality.
 */
function ptt_update_session_duration_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$session_index = isset( $_POST['session_index'] ) ? intval( $_POST['session_index'] ) : -1;
	$new_duration = isset( $_POST['duration'] ) ? sanitize_text_field( $_POST['duration'] ) : '';

	if ( ! $post_id || $session_index < 0 || empty( $new_duration ) ) {
		wp_send_json_error( [ 'message' => 'Invalid data provided.' ] );
	}

	// Parse duration (expects format like "1.5" for hours or "01:30:00" for time format)
	$duration_hours = 0;
	if ( strpos( $new_duration, ':' ) !== false ) {
		// Time format (HH:MM:SS or HH:MM)
		$parts = explode( ':', $new_duration );
		$hours = intval( $parts[0] );
		$minutes = isset( $parts[1] ) ? intval( $parts[1] ) : 0;
		$seconds = isset( $parts[2] ) ? intval( $parts[2] ) : 0;
		$duration_hours = $hours + ( $minutes / 60 ) + ( $seconds / 3600 );
	} else {
		// Decimal hours format
		$duration_hours = floatval( $new_duration );
	}

	// Update the session's manual duration
	$updated = update_sub_field( 
		array( 'sessions', $session_index + 1, 'session_manual_override' ), 
		true, 
		$post_id 
	);
	
	if ( $updated ) {
		update_sub_field( 
			array( 'sessions', $session_index + 1, 'session_manual_duration' ), 
			$duration_hours, 
			$post_id 
		);
		
		// Recalculate total duration
		ptt_calculate_and_save_duration( $post_id );
		
		wp_send_json_success( [
			'message' => 'Duration updated successfully.',
			'duration_hours' => $duration_hours,
			'formatted' => number_format( $duration_hours, 2 ) . ' hrs',
		] );
	} else {
		wp_send_json_error( [ 'message' => 'Failed to update duration.' ] );
	}
}
add_action( 'wp_ajax_ptt_update_session_duration', 'ptt_update_session_duration_callback' );

/**
 * AJAX handler to update a session's title or notes (for inline editing).
 * This is a new handler for future live editing functionality.
 */
function ptt_update_session_field_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$session_index = isset( $_POST['session_index'] ) ? intval( $_POST['session_index'] ) : -1;
	$field_name = isset( $_POST['field_name'] ) ? sanitize_text_field( $_POST['field_name'] ) : '';
	$field_value = isset( $_POST['field_value'] ) ? sanitize_text_field( $_POST['field_value'] ) : '';

	if ( ! $post_id || $session_index < 0 || empty( $field_name ) ) {
		wp_send_json_error( [ 'message' => 'Invalid data provided.' ] );
	}

	// Map field names to ACF field keys
	$field_map = [
		'session_title' => 'session_title',
		'session_notes' => 'session_notes',
	];

	if ( ! isset( $field_map[ $field_name ] ) ) {
		wp_send_json_error( [ 'message' => 'Invalid field name.' ] );
	}

	$acf_field = $field_map[ $field_name ];
	
	// Update the field
	$updated = update_sub_field( 
		array( 'sessions', $session_index + 1, $acf_field ), 
		$field_value, 
		$post_id 
	);
	
	if ( $updated ) {
		wp_send_json_success( [
			'message' => 'Field updated successfully.',
			'field_name' => $field_name,
			'field_value' => $field_value,
		] );
	} else {
		wp_send_json_error( [ 'message' => 'Failed to update field.' ] );
	}
}
add_action( 'wp_ajax_ptt_update_session_field', 'ptt_update_session_field_callback' );

/**
 * AJAX handler to delete a session.
 * This is a new handler for future functionality.
 */
function ptt_delete_session_callback() {
	check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( [ 'message' => 'Permission denied.' ] );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	$session_index = isset( $_POST['session_index'] ) ? intval( $_POST['session_index'] ) : -1;

	if ( ! $post_id || $session_index < 0 ) {
		wp_send_json_error( [ 'message' => 'Invalid data provided.' ] );
	}

	// Delete the row
	$deleted = delete_row( 'sessions', $session_index + 1, $post_id );
	
	if ( $deleted ) {
		// Recalculate total duration
		ptt_calculate_and_save_duration( $post_id );
		
		wp_send_json_success( [
			'message' => 'Session deleted successfully.',
		] );
	} else {
		wp_send_json_error( [ 'message' => 'Failed to delete session.' ] );
	}
}
add_action( 'wp_ajax_ptt_delete_session', 'ptt_delete_session_callback' );

/**
 * Moves a session from one task to another.
 *
 * @param int $source_post_id Source task ID.
 * @param int $session_index  Index of the session to move (0 based).
 * @param int $target_post_id Target task ID.
 * @return int|false          New session index on target task, or false on failure.
 */
function ptt_move_session_to_task( $source_post_id, $session_index, $target_post_id ) {
        if ( ! $source_post_id || ! $target_post_id || $session_index < 0 ) {
                return false;
        }

        $sessions = get_field( 'sessions', $source_post_id );
        if ( empty( $sessions ) || ! isset( $sessions[ $session_index ] ) ) {
                return false;
        }

        $session = $sessions[ $session_index ];
        if ( isset( $session['session_timer_controls'] ) ) {
                unset( $session['session_timer_controls'] );
        }

        $new_row_index = add_row( 'sessions', $session, $target_post_id );
        if ( ! $new_row_index ) {
                return false;
        }

        delete_row( 'sessions', $session_index + 1, $source_post_id );

        ptt_calculate_and_save_duration( $source_post_id );
        ptt_calculate_and_save_duration( $target_post_id );

        return (int) $new_row_index - 1;
}

/**
 * AJAX handler to move a session between tasks.
 */
function ptt_move_session_callback() {
        check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'edit_posts' ) ) {
                wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }

        $post_id        = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        $session_index  = isset( $_POST['session_index'] ) ? intval( $_POST['session_index'] ) : -1;
        $target_post_id = isset( $_POST['target_post_id'] ) ? intval( $_POST['target_post_id'] ) : 0;

        if ( ! $post_id || $session_index < 0 || ! $target_post_id ) {
                wp_send_json_error( [ 'message' => 'Invalid data provided.' ] );
        }

        $new_index = ptt_move_session_to_task( $post_id, $session_index, $target_post_id );

        if ( $new_index !== false ) {
                wp_send_json_success( [
                        'message'   => 'Session moved successfully.',
                        'new_index' => $new_index,
                ] );
        } else {
                wp_send_json_error( [ 'message' => 'Failed to move session.' ] );
        }
}
add_action( 'wp_ajax_ptt_move_session', 'ptt_move_session_callback' );

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