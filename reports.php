<?php
/**
 * ------------------------------------------------------------------
 * 10.0 ADMIN PAGES & LINKS  (Reports)
 * ------------------------------------------------------------------
 *
 * This file registers the **Reports** sub‑page that appears under the
 * Tasks CPT and renders all report‑related markup/logic.
 *
 * CHANGELOG (excerpt)
 * ------------------------------------------------------------------
 * 1.7.18 – 2025-07-23
 * • Feature: Added "Single Day" view mode to reports to show all tasks created or modified on a specific day.
 * • Feature: In Single Day view, the report only calculates and displays session durations for the selected day.
 * • Improved: Date picker on reports page now switches to a single date selector when "Single Day" view is active.
 *
 * 1.7.17 – 2025-07-23
 * • Feature: Added "Task Focused" list view mode to reports, with a custom toggle switch UI.
 * • Feature: In Task Focused view, tasks with multiple statuses appear as a line item for each status.
 *
 * 1.7.16 – 2025-07-23
 * • Fixed: A PHP syntax error on the reports page caused by a missing '$' in an unset() call.
 * * 1.7.15 – 2025-07-23
 * • Feature: Added a debug toggle to the reports screen to show query and sorting logic.
 * • Improved: "Sort by Status" now correctly re-sorts all tasks based on the selection.
 * • Improved: Clients and Projects within the report are now sorted alphabetically.
 *
 * 1.7.10 – 2025-07-23
 * • Dev: Self tests now cover status update callbacks and reporting queries.
 *
 * 1.7.8 – 2025-07-22
 * • Feature: Added an inline-editable dropdown to the Status column on the reports page, allowing for quick task status updates.
 * • Improved: The new status dropdown saves changes instantly via AJAX without a page reload.
 *
 * 1.6.7 – 2025-07-22
 * • Fixed: Reports now include all tasks (including "Not Started") that match the filters, not just those with logged time.
 * • Improved: Report query now uses the task's publish date for date-range filtering, making it more reliable.
 * • Improved: Tasks within the report are now sorted chronologically by author, then date.
 *
 * 1.6.6 – 2025‑07‑20
 * • Fixed: Manual time entries (manual_override = 1) were excluded
 * from reports because they do not always have a stop_time.
 * • Improved: When a task has no start_time (e.g. imported manual
 * entries) we now fall back to the post’s publish date so the
 * report never shows “1970‑01‑01”.
 * ------------------------------------------------------------------
 */

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/*===================================================================
 * Helper: Format Task Notes (URLs → links, truncate > 200 chars)
 *==================================================================*/
function ptt_format_task_notes( $content, $max_length = 200 ) {
	$content = wp_strip_all_tags( $content );
	$content = trim( $content );

	if ( empty( $content ) ) {
		return '';
	}

	$truncated = false;
	if ( strlen( $content ) > $max_length ) {
		$content   = substr( $content, 0, $max_length - 3 );
		$truncated = true;
	}

	$content = esc_html( $content );

	$url_pattern = '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i';
	$content     = preg_replace_callback(
		$url_pattern,
		function ( $m ) {
			$url         = $m[1];
			$display_url = strlen( $url ) > 50 ? substr( $url, 0, 47 ) . '…' : $url;
			return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $display_url . '</a>';
		},
		$content
	);

	if ( $truncated ) {
		$content .= '…';
	}

	return $content;
}

/*===================================================================
 * Register admin‑side “Reports” sub‑menu
 *==================================================================*/
function ptt_add_reports_page() {
	add_submenu_page(
		'edit.php?post_type=project_task',
		'Time Reports',
		'Reports',
		'manage_options',
		'ptt-reports',
		'ptt_reports_page_html'
	);
}
add_action( 'admin_menu', 'ptt_add_reports_page' );

/*===================================================================
 * Handle sort-by-status cookie early
 *==================================================================*/
function ptt_handle_sort_status_cookie() {
       if ( ! isset( $_GET['page'] ) || 'ptt-reports' !== $_GET['page'] ) {
               return;
       }

       if ( ! current_user_can( 'manage_options' ) ) {
               return;
       }

       if ( isset( $_GET['run_report'] ) ) {
               $sort_pref = isset( $_GET['sort_status'] ) ? sanitize_text_field( $_GET['sort_status'] ) : 'default';
               if ( isset( $_GET['remember_sort'] ) && '1' === $_GET['remember_sort'] ) {
                       setcookie( 'ptt_sort_status', $sort_pref, time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN );
               } else {
                       setcookie( 'ptt_sort_status', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN );
               }
       }
}
add_action( 'admin_init', 'ptt_handle_sort_status_cookie' );

/*===================================================================
 * Reports page HTML (filter form + results container)
 *==================================================================*/
function ptt_reports_page_html() {

		$saved_sort = isset( $_REQUEST['sort_status'] )
			? sanitize_text_field( $_REQUEST['sort_status'] )
			: ( isset( $_COOKIE['ptt_sort_status'] ) ? sanitize_text_field( $_COOKIE['ptt_sort_status'] ) : 'default' );

		$view_mode = isset( $_REQUEST['view_mode'] ) ? sanitize_text_field( $_REQUEST['view_mode'] ) : 'classic';
		?>
		<div class="wrap">
				<h1>Project &amp; Task Time Reports</h1>

		<form method="get" action="">
			<?php wp_nonce_field( 'ptt_run_report_nonce' ); ?>
			<input type="hidden" name="post_type" value="project_task">
			<input type="hidden" name="page"      value="ptt-reports">

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">View Mode</th>
						<td>
							<div class="ptt-toggle-control">
								<input type="radio" id="view_mode_classic" name="view_mode" value="classic" <?php checked( $view_mode, 'classic' ); ?>>
								<label for="view_mode_classic">Classic</label>

								<input type="radio" id="view_mode_task_focused" name="view_mode" value="task_focused" <?php checked( $view_mode, 'task_focused' ); ?>>
								<label for="view_mode_task_focused">Task Focused</label>

								<input type="radio" id="view_mode_single_day" name="view_mode" value="single_day" <?php checked( $view_mode, 'single_day' ); ?>>
								<label for="view_mode_single_day">Single Day</label>
							</div>
							<p class="description">"Classic" groups tasks by Client/Project. "Task Focused" shows a flat list of all tasks. "Single Day" shows all work for one day.</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="user_id">Select User</label></th>
						<td>
							<?php
							wp_dropdown_users( [
								'name'            => 'user_id',
								'show_option_all' => 'All Users',
								'selected'        => isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : 0,
														] );
														?>
												</td>
										</tr>

									   <tr>
											   <th scope="row"><label for="status_id">Select&nbsp;Status</label></th>
											   <td>
													   <?php
													   wp_dropdown_categories([
															   'taxonomy'        => 'task_status',
															   'name'            => 'status_id',
															   'show_option_all' => 'Show All',
															   'hide_empty'      => false,
															   'selected'        => isset( $_REQUEST['status_id'] ) ? intval( $_REQUEST['status_id'] ) : 0,
															   'hierarchical'    => false,
															   'class'           => '',
													   ] );
													   ?>
											   </td>
									   </tr>

									   <tr>
											   <th scope="row"><label for="sort_status">Sort&nbsp;by&nbsp;Status</label></th>
											   <td>
													   <select name="sort_status" id="sort_status">
															   <option value="default" <?php selected( $saved_sort, 'default' ); ?>>Default</option>
															   <?php
															   $sort_terms = get_terms( [ 'taxonomy' => 'task_status', 'hide_empty' => false ] );
															   foreach ( $sort_terms as $term ) {
																	   echo '<option value="' . esc_attr( $term->term_id ) . '" ' . selected( $saved_sort, (string) $term->term_id, false ) . '>' . esc_html( $term->name ) . '</option>';
															   }
															   ?>
													   </select>
													   <label style="margin-left:10px;">
															   <input type="checkbox" name="remember_sort" id="remember_sort" value="1" <?php checked( isset( $_COOKIE['ptt_sort_status'] ) ); ?>>
															   Remember this setting for me
													   </label>
											   </td>
									   </tr>

					<tr>
						<th scope="row"><label for="client_id">Select Client</label></th>
						<td>
							<?php
							wp_dropdown_categories( [
								'taxonomy'        => 'client',
								'name'            => 'client_id',
								'show_option_all' => 'All Clients',
								'hide_empty'      => false,
								'selected'        => isset( $_REQUEST['client_id'] ) ? intval( $_REQUEST['client_id'] ) : 0,
								'hierarchical'    => true,
								'class'           => '',
							] );
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="project_id">Select Project</label></th>
						<td>
							<?php
							wp_dropdown_categories( [
								'taxonomy'        => 'project',
								'name'            => 'project_id',
								'show_option_all' => 'All Projects',
								'hide_empty'      => false,
								'selected'        => isset( $_REQUEST['project_id'] ) ? intval( $_REQUEST['project_id'] ) : 0,
								'hierarchical'    => true,
								'class'           => '',
							] );
							?>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="start_date">Date Range</label></th>
						<td>
							<input type="date" id="start_date" name="start_date"
								   value="<?php echo isset( $_REQUEST['start_date'] ) ? esc_attr( $_REQUEST['start_date'] ) : date('Y-m-d'); ?>">
							<span class="date-range-separator"> to </span>
							<input type="date" id="end_date" name="end_date"
								   value="<?php echo isset( $_REQUEST['end_date'] ) ? esc_attr( $_REQUEST['end_date'] ) : ''; ?>">

							<button type="button" id="set-this-week" class="button">This Week (Sun‑Sat)</button>
							<button type="button" id="set-last-week" class="button">Last Week</button>
						</td>
					</tr>

					<tr>
						<th scope="row"><label for="debug_mode">Debugging</label></th>
						<td>
							<label>
								<input type="checkbox" name="debug_mode" id="debug_mode" value="1" <?php checked( isset( $_REQUEST['debug_mode'] ) && '1' === $_REQUEST['debug_mode'] ); ?>>
								Show sorting & query logic
							</label>
							<p class="description">If checked, this will display the raw query arguments and sorting arrays used to generate the report.</p>
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="run_report" class="button button-primary" value="Run Report">
			</p>
		</form>

		<?php
		if ( isset( $_REQUEST['run_report'] ) ) {
			check_admin_referer( 'ptt_run_report_nonce' );
			ptt_display_report_results();
		}
		?>
	</div>
	<?php
}

/*===================================================================
 * Core: Query & Display report results
 *==================================================================*/
function ptt_display_report_results() {

	$user_id     = isset( $_REQUEST['user_id'] )    ? intval( $_REQUEST['user_id'] )    : 0;
	$client_id   = isset( $_REQUEST['client_id'] )  ? intval( $_REQUEST['client_id'] )  : 0;
	$project_id  = isset( $_REQUEST['project_id'] ) ? intval( $_REQUEST['project_id'] ) : 0;
	$status_id   = isset( $_REQUEST['status_id'] )  ? intval( $_REQUEST['status_id'] )  : 0;
	$start_date  = ! empty( $_REQUEST['start_date'] ) ? sanitize_text_field( $_REQUEST['start_date'] ) : null;
	$end_date    = ! empty( $_REQUEST['end_date'] )   ? sanitize_text_field( $_REQUEST['end_date'] )   : null;
	$sort_status = isset( $_REQUEST['sort_status'] ) ? sanitize_text_field( $_REQUEST['sort_status'] ) : ( isset( $_COOKIE['ptt_sort_status'] ) ? sanitize_text_field( $_COOKIE['ptt_sort_status'] ) : 'default' );
	$view_mode   = isset( $_REQUEST['view_mode'] )   ? sanitize_text_field( $_REQUEST['view_mode'] )   : 'classic';

	/*--------------------------------------------------------------
	 * Build WP_Query arguments
	 *-------------------------------------------------------------*/
	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => [ 'author' => 'ASC', 'date' => 'ASC' ], // Group by user, then sort tasks chronologically
	];

	if ( $user_id ) {
		$args['author'] = $user_id;
	}

	/* Taxonomy filtering */
	$tax_query = [ 'relation' => 'AND' ];
	if ( $client_id ) {
		$tax_query[] = [
			'taxonomy' => 'client',
			'field'    => 'term_id',
			'terms'    => $client_id,
		];
	}
	if ( $project_id ) {
		$tax_query[] = [
			'taxonomy' => 'project',
			'field'    => 'term_id',
			'terms'    => $project_id,
		];
	}
	if ( $status_id ) {
		$tax_query[] = [
			'taxonomy' => 'task_status',
			'field'    => 'term_id',
			'terms'    => $status_id,
		];
	}
	if ( count( $tax_query ) > 1 ) {
		$args['tax_query'] = $tax_query;
	}

	/* Date-range filtering (now using post_date via date_query to include all tasks) */
	if ( $start_date && $end_date ) {
		$args['date_query'] = [
			[
				'after'     => $start_date . ' 00:00:00',
				'before'    => $end_date . ' 23:59:59',
				'inclusive' => true,
			],
		];
	}

	/*--------------------------------------------------------------
	 * Execute query
	 *-------------------------------------------------------------*/
	$q = new WP_Query( $args );

	if ( ! $q->have_posts() && 'single_day' !== $view_mode ) {
		echo '<p>No matching tasks found for the selected criteria.</p>';
		return;
	}

	// --------------------------------------------------------------
	// Build Status Sorting Map
	// --------------------------------------------------------------
	$status_terms          = get_terms( [ 'taxonomy' => 'task_status', 'hide_empty' => false ] );
	$default_order_names   = [ 'In Progress', 'Not Started', 'Blocked', 'Paused', 'Completed' ]; // 'Deferred' was removed for task-focused view
	$status_order          = [];
	$index                 = 1;

	// Build the status order map based on user preference
	if ( 'default' !== $sort_status && term_exists( (int) $sort_status, 'task_status' ) ) {
		$status_order[ intval( $sort_status ) ] = $index++;
	}

	// Add the default statuses to the map if they aren't already there
	foreach ( $default_order_names as $name ) {
		foreach ( $status_terms as $term ) {
			if ( ! isset( $status_order[ $term->term_id ] ) && strcasecmp( $term->name, $name ) === 0 ) {
				$status_order[ $term->term_id ] = $index++;
			}
		}
	}
	// Add any remaining statuses not in the default list
	foreach ( $status_terms as $term ) {
		if ( ! isset( $status_order[ $term->term_id ] ) ) {
			$status_order[ $term->term_id ] = $index++;
		}
	}

	$all_statuses = get_terms( [ 'taxonomy' => 'task_status', 'hide_empty' => false ] );
	$grand_total  = 0.0;

	/*--------------------------------------------------------------
	 * Render Debug Output (if enabled)
	 *-------------------------------------------------------------*/
	if ( isset( $_REQUEST['debug_mode'] ) && '1' === $_REQUEST['debug_mode'] ) {
		echo '<div class="notice notice-info" style="padding: 15px; margin: 20px 0; border-left-color: #0073aa;">';
		echo '<h3><span class="dashicons dashicons-hammer" style="vertical-align: middle; margin-right: 5px;"></span> Debugging Information</h3>';

		echo '<h4>Initial WP_Query Arguments:</h4>';
		echo '<p><em>This is the main query sent to the database to fetch all matching tasks before they are grouped and sorted.</em></p>';
		echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap;">' . esc_html( print_r( $args, true ) ) . '</pre>';

		echo '<h4>Task Sorting Logic:</h4>';
		echo '<p><strong>Selected View Mode (<code>$view_mode</code>):</strong> ' . esc_html( $view_mode ) . '</p>';
		echo '<p><strong>Selected Sort Preference (<code>$sort_status</code>):</strong> ' . esc_html( $sort_status ) . '</p>';
		echo '<p><strong>Default Status Order (Hardcoded):</strong></p>';
		echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' . esc_html( print_r( $default_order_names, true ) ) . '</pre>';

		echo '<h4>Final Status Order Map (<code>$status_order</code>):</h4>';
		echo '<p><em>Tasks are sorted based on the ascending value of their status ID in this map. The selected status (if any) is assigned a value of `1` to give it top priority.</em></p>';
		echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' . esc_html( print_r( $status_order, true ) ) . '</pre>';

		echo '</div>';
	}

	echo '<h2>Report Results</h2>';

	// =================================================================
	// VIEW MODE ROUTER
	// =================================================================
	if ( 'task_focused' === $view_mode ) {

		/*----------------------------------------------------------
		 * TASK FOCUSED VIEW
		 *---------------------------------------------------------*/
		$task_list = [];
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id     = get_the_ID();
			$duration    = (float) get_field( 'calculated_duration', $post_id );
			$grand_total += $duration;

			$client_terms = get_the_terms( $post_id, 'client' );
			$project_terms = get_the_terms( $post_id, 'project' );
			$start_time = get_field( 'start_time', $post_id ) ?: get_the_date( 'Y-m-d H:i:s', $post_id );

			$task_base = [
				'id'             => $post_id,
				'title'          => get_the_title(),
				'client_name'    => ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '–',
				'project_name'   => ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–',
				'date'           => $start_time,
				'duration'       => $duration,
				'content'        => get_the_content(),
			];

			$current_statuses = get_the_terms( $post_id, 'task_status' );
			if ( ! is_wp_error( $current_statuses ) && ! empty( $current_statuses ) ) {
				foreach ( $current_statuses as $status ) {
					$task_list[] = array_merge( $task_base, [
						'status_id'   => $status->term_id,
						'status_name' => $status->name,
					] );
				}
			} else {
				$task_list[] = array_merge( $task_base, [
					'status_id'   => 0,
					'status_name' => '',
				] );
			}
		}
		wp_reset_postdata();

		// Sort the final flat list
		usort( $task_list, function( $a, $b ) use ( $status_order ) {
			$oa = isset( $status_order[ $a['status_id'] ] ) ? $status_order[ $a['status_id'] ] : PHP_INT_MAX;
			$ob = isset( $status_order[ $b['status_id'] ] ) ? $status_order[ $b['status_id'] ] : PHP_INT_MAX;
			if ( $oa === $ob ) {
				return strcmp( $a['date'], $b['date'] ); // Secondary sort: chronological
			}
			return $oa <=> $ob; // Primary sort: by status order map
		} );

		// Render the flat table
		echo '<div class="ptt-report-results">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>
				<th style="width:25%">Task Name</th>
				<th style="width:15%">Client</th>
				<th style="width:15%">Project</th>
				<th style="width:8%">Date</th>
				<th style="width:8%">Duration (Hrs)</th>
				<th style="width:21%">Notes</th>
				<th style="width:8%">Status</th>
			  </tr></thead><tbody>';

		foreach ( $task_list as $task ) {
			// Status column dropdown
			$status_dropdown  = '<div class="ptt-status-control">';
			$status_dropdown .= '<select class="ptt-report-status-select" data-postid="' . esc_attr( $task['id'] ) . '">';
			foreach ( $all_statuses as $status ) {
				$selected         = selected( $task['status_id'], $status->term_id, false );
				$status_dropdown .= '<option value="' . esc_attr( $status->term_id ) . '" ' . $selected . '>' . esc_html( $status->name ) . '</option>';
			}
			$status_dropdown .= '</select>';
			$status_dropdown .= '<div class="ptt-ajax-spinner" style="display:none;"></div>';
			$status_dropdown .= '</div>';

			echo '<tr>';
			echo '<td><a href="' . get_edit_post_link( $task['id'] ) . '">' . esc_html( $task['title'] ) . '</a></td>';
			echo '<td>' . esc_html( $task['client_name'] ) . '</td>';
			echo '<td>' . esc_html( $task['project_name'] ) . '</td>';
			echo '<td>' . esc_html( date( 'Y-m-d', strtotime( $task['date'] ) ) ) . '</td>';
			echo '<td>' . number_format( $task['duration'], 2 ) . '</td>';
			echo '<td>' . ptt_format_task_notes( $task['content'] ) . '</td>';
			echo '<td>' . $status_dropdown . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<div class="grand-total"><strong>Grand Total: ' . number_format( $grand_total, 2 ) . '&nbsp;hours</strong></div>';
		echo '</div>'; // .ptt-report-results

	} elseif ( 'single_day' === $view_mode ) {

		/*----------------------------------------------------------
		 * SINGLE DAY VIEW
		 *---------------------------------------------------------*/
		$target_date_str = ! empty( $_REQUEST['start_date'] ) ? sanitize_text_field( $_REQUEST['start_date'] ) : date( 'Y-m-d' );
		unset( $args['date_query'] ); // Unset original date query

		$q = new WP_Query( $args );

		$daily_tasks       = [];
		$grand_total_today = 0.0;

		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$post_id          = get_the_ID();
				$sort_timestamp   = PHP_INT_MAX;
				$is_relevant      = false;
				$daily_duration   = 0.0;

				// Condition 1: Check if the post was created on the target day.
				if ( date( 'Y-m-d', get_the_date( 'U' ) ) === $target_date_str ) {
					$is_relevant    = true;
					$sort_timestamp = min( $sort_timestamp, get_the_date( 'U' ) );
				}

				// Condition 2: Check if any session was tracked on the target day.
				$sessions = get_field( 'sessions', $post_id );
				if ( ! empty( $sessions ) && is_array( $sessions ) ) {
					foreach ( $sessions as $session ) {
						$session_start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';

						if ( $session_start_str && date( 'Y-m-d', strtotime( $session_start_str ) ) === $target_date_str ) {
							$is_relevant = true;
							$sort_timestamp = min( $sort_timestamp, strtotime( $session_start_str ) );

							// Calculate duration for this specific session.
							$session_duration = 0.0;
							if ( ! empty( $session['session_manual_override'] ) ) {
								$session_duration = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.0;
							} else {
								$start = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
								$stop  = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
								if ( $start && $stop ) {
									try {
										$start_time = new DateTime( $start, new DateTimeZone('UTC') );
										$stop_time  = new DateTime( $stop, new DateTimeZone('UTC') );
										if ( $stop_time > $start_time ) {
											$diff_seconds     = $stop_time->getTimestamp() - $start_time->getTimestamp();
											$duration_hours   = $diff_seconds / 3600;
											$session_duration = ceil( $duration_hours * 100 ) / 100;
										}
									} catch ( Exception $e ) {
										$session_duration = 0.0;
									}
								}
							}
							$daily_duration += $session_duration;
						}
					}
				}

				if ( $is_relevant ) {
					$client_terms  = get_the_terms( $post_id, 'client' );
					$project_terms = get_the_terms( $post_id, 'project' );

					$daily_tasks[] = [
						'id'             => $post_id,
						'title'          => get_the_title(),
						'client_name'    => ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '–',
						'project_name'   => ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–',
						'sort_timestamp' => $sort_timestamp,
						'daily_duration' => $daily_duration,
						'content'        => get_the_content(),
						'date_display'   => date( 'h:i A', $sort_timestamp ),
					];
					$grand_total_today += $daily_duration;
				}
			}
			wp_reset_postdata();
		}

		// Sort the final list chronologically by the earliest event of the day.
		usort( $daily_tasks, function( $a, $b ) {
			return $a['sort_timestamp'] <=> $b['sort_timestamp'];
		} );

		// Render the "Single Day" table.
		echo '<div class="ptt-report-results">';
		echo '<h3>Report for ' . esc_html( date( 'F j, Y', strtotime( $target_date_str ) ) ) . '</h3>';

		if ( empty( $daily_tasks ) ) {
			echo '<p>No tasks were created or had time tracked on this day.</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>
					<th style="width:10%">Time</th>
					<th style="width:25%">Task Name</th>
					<th style="width:15%">Client</th>
					<th style="width:15%">Project</th>
					<th style="width:10%">Duration (Hrs)</th>
					<th style="width:25%">Notes</th>
				  </tr></thead><tbody>';

			foreach ( $daily_tasks as $task ) {
				echo '<tr>';
				echo '<td>' . esc_html( $task['date_display'] ) . '</td>';
				echo '<td><a href="' . get_edit_post_link( $task['id'] ) . '">' . esc_html( $task['title'] ) . '</a></td>';
				echo '<td>' . esc_html( $task['client_name'] ) . '</td>';
				echo '<td>' . esc_html( $task['project_name'] ) . '</td>';
				echo '<td>' . ( $task['daily_duration'] > 0 ? number_format( $task['daily_duration'], 2 ) : '–' ) . '</td>';
				echo '<td>' . ptt_format_task_notes( $task['content'] ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
			echo '<div class="grand-total"><strong>Total for Day: ' . number_format( $grand_total_today, 2 ) . '&nbsp;hours</strong></div>';
		}
		echo '</div>'; // .ptt-report-results

	} else {

		/*----------------------------------------------------------
		 * CLASSIC (HIERARCHICAL) VIEW
		 *---------------------------------------------------------*/
		$report = [];
		while ( $q->have_posts() ) {
			$q->the_post();
			$post_id = get_the_ID();
			$duration = (float) get_field( 'calculated_duration', $post_id );
			$grand_total += $duration;

			$author_id   = get_the_author_meta( 'ID' );
			$author_name = get_the_author_meta( 'display_name' );

			$client_terms   = get_the_terms( $post_id, 'client' );
			$client_id_term = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->term_id : 0;
			$client_name    = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : 'Uncategorized';

			$project_terms   = get_the_terms( $post_id, 'project' );
			$project_id_term = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->term_id : 0;
			$project_name    = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : 'Uncategorized';

			$status_terms_obj = get_the_terms( $post_id, 'task_status' );
			$status_id_term = ! is_wp_error( $status_terms_obj ) && $status_terms_obj ? $status_terms_obj[0]->term_id : 0;

			$task_budget    = get_field( 'task_max_budget', $post_id );
			$project_budget = $project_id_term ? get_field( 'project_max_budget', 'project_' . $project_id_term ) : 0;
			$start_time = get_field( 'start_time', $post_id ) ?: get_the_date( 'Y-m-d H:i:s', $post_id );

			if ( ! isset( $report[ $author_id ] ) ) {
				$report[ $author_id ] = [ 'name' => $author_name, 'total' => 0, 'clients' => [] ];
			}
			if ( ! isset( $report[ $author_id ]['clients'][ $client_id_term ] ) ) {
				$report[ $author_id ]['clients'][ $client_id_term ] = [ 'name' => $client_name, 'total' => 0, 'projects' => [] ];
			}
			if ( ! isset( $report[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] ) ) {
				$report[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] = [ 'name' => $project_name, 'total' => 0, 'tasks' => [] ];
			}

			$report[ $author_id ]['total'] += $duration;
			$report[ $author_id ]['clients'][ $client_id_term ]['total'] += $duration;
			$report[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ]['total'] += $duration;
			$report[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ]['tasks'][] = [
				'id'             => $post_id,
				'title'          => get_the_title(),
				'date'           => $start_time,
				'duration'       => $duration,
				'content'        => get_the_content(),
				'task_budget'    => $task_budget,
				'project_budget' => $project_budget,
				'status_id'      => $status_id_term,
			];
		}
		wp_reset_postdata();

		// Sort clients alphabetically, then projects alphabetically, then tasks by status
		foreach ( $report as &$author ) {
			uasort( $author['clients'], function( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
			foreach ( $author['clients'] as &$client ) {
				uasort( $client['projects'], function( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
				foreach ( $client['projects'] as &$project ) {
					usort( $project['tasks'], function( $a, $b ) use ( $status_order ) {
						$oa = isset( $status_order[ $a['status_id'] ] ) ? $status_order[ $a['status_id'] ] : PHP_INT_MAX;
						$ob = isset( $status_order[ $b['status_id'] ] ) ? $status_order[ $b['status_id'] ] : PHP_INT_MAX;
						if ( $oa === $ob ) { return strcmp( $a['date'], $b['date'] ); }
						return $oa <=> $ob;
					} );
				}
			}
		}
		unset( $author, $client, $project );

		// Render the hierarchical table
		echo '<div class="ptt-report-results">';
		foreach ( $report as $author ) {
			echo '<h3>User: ' . esc_html( $author['name'] ) . ' <span class="subtotal">(User&nbsp;Total: ' . number_format( $author['total'], 2 ) . '&nbsp;hrs)</span></h3>';
			foreach ( $author['clients'] as $client ) {
				echo '<div class="client-group">';
				echo '<h4>Client: ' . esc_html( $client['name'] ) . ' <span class="subtotal">(Client&nbsp;Total: ' . number_format( $client['total'], 2 ) . '&nbsp;hrs)</span></h4>';
				foreach ( $client['projects'] as $project ) {
					echo '<div class="project-group">';
					echo '<h5>Project: ' . esc_html( $project['name'] ) . ' <span class="subtotal">(Project&nbsp;Total: ' . number_format( $project['total'], 2 ) . '&nbsp;hrs)</span></h5>';
					echo '<table class="wp-list-table widefat fixed striped">';
					echo '<thead><tr>
							<th>Task Name</th><th>Date</th><th>Duration (Hours)</th>
							<th>Orig.&nbsp;Budget</th><th>Notes</th><th>Status</th>
						  </tr></thead><tbody>';

					foreach ( $project['tasks'] as $task ) {
						$effective_budget = 0;
						$budget_display = '–';
						if ( ! empty( $task['task_budget'] ) && (float) $task['task_budget'] > 0 ) {
							$effective_budget = (float) $task['task_budget'];
							$budget_display = number_format( $effective_budget, 2 ) . '&nbsp;(Task)';
						} elseif ( ! empty( $task['project_budget'] ) && (float) $task['project_budget'] > 0 ) {
							$effective_budget = (float) $task['project_budget'];
							$budget_display = number_format( $effective_budget, 2 ) . '&nbsp;(Project)';
						}
						$budget_td_style = '';
						if ( $effective_budget > 0 && (float) $task['duration'] > $effective_budget ) {
							$budget_td_style = 'style="color: #f44336; font-weight: bold;"';
						}

						$status_dropdown = '<div class="ptt-status-control">';
						$status_dropdown .= '<select class="ptt-report-status-select" data-postid="' . esc_attr( $task['id'] ) . '">';
						foreach ( $all_statuses as $status ) {
							$selected = selected( $task['status_id'], $status->term_id, false );
							$status_dropdown .= '<option value="' . esc_attr( $status->term_id ) . '" ' . $selected . '>' . esc_html( $status->name ) . '</option>';
						}
						$status_dropdown .= '</select>';
						$status_dropdown .= '<div class="ptt-ajax-spinner" style="display:none;"></div>';
						$status_dropdown .= '</div>';

						echo '<tr>';
						echo '<td><a href="' . get_edit_post_link( $task['id'] ) . '">' . esc_html( $task['title'] ) . '</a></td>';
						echo '<td>' . esc_html( date( 'Y-m-d', strtotime( $task['date'] ) ) ) . '</td>';
						echo '<td>' . number_format( $task['duration'], 2 ) . '</td>';
						echo '<td ' . $budget_td_style . '>' . $budget_display . '</td>';
						echo '<td>' . ptt_format_task_notes( $task['content'] ) . '</td>';
						echo '<td>' . $status_dropdown . '</td>';
						echo '</tr>';
					}
					echo '</tbody></table></div>';
				}
				echo '</div>';
			}
		}
		echo '<div class="grand-total"><strong>Grand Total: ' . number_format( $grand_total, 2 ) . '&nbsp;hours</strong></div>';
		echo '</div>'; // .ptt-report-results
	}
}