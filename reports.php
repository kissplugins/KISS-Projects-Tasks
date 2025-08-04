<?php
/**
 * ------------------------------------------------------------------
 * 10.0 ADMIN PAGES & LINKS  (Reports)
 * ------------------------------------------------------------------
 *
 * This file registers the **Reports** sub‑page that appears under the
 * Tasks CPT and renders all report‑related markup/logic.
 *
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

/**
 * Retrieves the Assignee display name for a task.
 *
 * @param int $post_id Task ID.
 * @return string Assignee name or "No Assignee".
 */
function ptt_get_assignee_name( $post_id ) {
       $assignee_id = (int) get_post_meta( $post_id, 'ptt_assignee', true );
       if ( $assignee_id ) {
               return get_the_author_meta( 'display_name', $assignee_id );
       }
       return __( 'No Assignee', 'ptt' );
}

/*===================================================================
 * Register admin‑side “Reports” sub‑menu
 *==================================================================*/
function ptt_add_reports_page() {
	add_submenu_page(
		'edit.php?post_type=project_task',
		'Time Reports',
		'Reports',
		'edit_posts',
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

       if ( ! current_user_can( 'edit_posts' ) ) {
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

		<div class="notice notice-info inline">
			<p><strong>How the Date Filter Works:</strong></p>
			<ul style="list-style: disc; padding-left: 20px;">
				<li><strong>Classic &amp; Task Focused Views:</strong> The date range shows tasks that were either created or had work sessions logged within that period.</li>
				<li><strong>Single Day View:</strong> The single date picker shows all tasks that were created or had work sessions logged on that specific day.</li>
			</ul>
		</div>

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
                                               <th scope="row"><label for="assignee_id">Select Assignee</label></th>
                                               <td>
                                                       <?php
                                                       wp_dropdown_users(
                                                               [
                                                                       'name'            => 'assignee_id',
                                                                       'role__in'        => [ 'author', 'editor', 'administrator' ],
                                                                       'show_option_all' => 'All Assignees',
                                                                       'selected'        => isset( $_REQUEST['assignee_id'] ) ? intval( $_REQUEST['assignee_id'] ) : 0,
                                                               ]
                                                       );
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

       $assignee_id = isset( $_REQUEST['assignee_id'] ) ? intval( $_REQUEST['assignee_id'] ) : 0;
       $client_id   = isset( $_REQUEST['client_id'] )   ? intval( $_REQUEST['client_id'] )   : 0;
       $project_id  = isset( $_REQUEST['project_id'] )  ? intval( $_REQUEST['project_id'] )  : 0;
       $status_id   = isset( $_REQUEST['status_id'] )   ? intval( $_REQUEST['status_id'] )   : 0;
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
               'meta_key'       => 'ptt_assignee',
               'orderby'        => [ 'meta_value_num' => 'ASC', 'date' => 'ASC' ],
       ];

       $meta_query = [];
       if ( $assignee_id ) {
               $meta_query[] = [
                       'key'     => 'ptt_assignee',
                       'value'   => $assignee_id,
                       'compare' => '=',
                       'type'    => 'NUMERIC',
               ];
       }
       if ( ! empty( $meta_query ) ) {
               $args['meta_query'] = $meta_query;
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

	echo '<h2>Report Results</h2>';

	// =================================================================
	// VIEW MODE ROUTER
	// =================================================================
	if ( 'task_focused' === $view_mode ) {
		
		$q = new WP_Query( $args );
		
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
			echo '<h4>Final Status Order Map (<code>$status_order</code>):</h4>';
			echo '<p><em>Tasks are sorted based on the ascending value of their status ID in this map.</em></p>';
			echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' . esc_html( print_r( $status_order, true ) ) . '</pre>';
			echo '</div>';
		}

		/*----------------------------------------------------------
		 * TASK FOCUSED VIEW
		 *---------------------------------------------------------*/
                $task_list = [];
                if ( $q->have_posts() ) {
                        // Attn: LLM Maintainers do not refactor this date search code unless specifically directed.
                        $start_timestamp = $start_date ? strtotime( $start_date . ' 00:00:00' ) : 0;
                        $end_timestamp   = $end_date ? strtotime( $end_date . ' 23:59:59' ) : PHP_INT_MAX;

			while ( $q->have_posts() ) {
				$q->the_post();
				$post_id     = get_the_ID();
				$is_relevant = false;

				// Check 1: Creation date is within range
				$creation_timestamp = get_the_date( 'U', $post_id );
				if ( $creation_timestamp >= $start_timestamp && $creation_timestamp <= $end_timestamp ) {
					$is_relevant = true;
				}

				// Check 2: At least one session date is within range
				if ( ! $is_relevant ) {
					$sessions = get_field( 'sessions', $post_id );
					if ( ! empty( $sessions ) && is_array( $sessions ) ) {
						foreach ( $sessions as $session ) {
							if ( ! empty( $session['session_start_time'] ) ) {
								$session_timestamp = strtotime( $session['session_start_time'] );
								if ( $session_timestamp >= $start_timestamp && $session_timestamp <= $end_timestamp ) {
									$is_relevant = true;
									break;
								}
							}
						}
					}
				}

				if ( ! $is_relevant ) {
					continue;
				}

				// Get Last Entry Date
				$latest_session_timestamp = 0;
				$sessions                 = get_field( 'sessions', $post_id );
				if ( ! empty( $sessions ) && is_array( $sessions ) ) {
					foreach ( $sessions as $session ) {
						if ( ! empty( $session['session_start_time'] ) ) {
							$ts = strtotime( $session['session_start_time'] );
							if ( $ts > $latest_session_timestamp ) {
								$latest_session_timestamp = $ts;
							}
						}
					}
				}
				$last_entry_date = ( $latest_session_timestamp > 0 ) ? date( 'Y-m-d', $latest_session_timestamp ) : '–';

				$duration    = (float) get_field( 'calculated_duration', $post_id );
				$grand_total += $duration;

				$client_terms = get_the_terms( $post_id, 'client' );
				$project_terms = get_the_terms( $post_id, 'project' );

				$task_base = [
					'id'              => $post_id,
					'title'           => get_the_title(),
                                       'assignee_name'   => ptt_get_assignee_name( $post_id ),
					'client_name'     => ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '–',
					'project_name'    => ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–',
					'creation_date'   => get_the_date( 'Y-m-d', $post_id ),
					'last_entry_date' => $last_entry_date,
					'duration'        => $duration,
					'content'         => get_the_content(),
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
		}

		if ( empty( $task_list ) ) {
			echo '<p>No matching tasks found for the selected criteria.</p>';
			return;
		}

		// Sort the final flat list
		usort( $task_list, function( $a, $b ) use ( $status_order ) {
			$oa = isset( $status_order[ $a['status_id'] ] ) ? $status_order[ $a['status_id'] ] : PHP_INT_MAX;
			$ob = isset( $status_order[ $b['status_id'] ] ) ? $status_order[ $b['status_id'] ] : PHP_INT_MAX;
			if ( $oa === $ob ) {
				return strcmp( $b['last_entry_date'], $a['last_entry_date'] ); // Secondary sort: reverse chronological last entry
			}
			return $oa <=> $ob; // Primary sort: by status order map
		} );

		// Render the flat table
		echo '<div class="ptt-report-results">';
		echo '<table class="wp-list-table widefat fixed striped">';
		echo '<thead><tr>
				<th style="width:20%">Task Name</th>
				<th style="width:12%">Assignee</th>
				<th style="width:10%">Client</th>
				<th style="width:10%">Project</th>
				<th style="width:8%">Creation&nbsp;Date</th>
				<th style="width:8%">Last&nbsp;Entry</th>
				<th style="width:8%">Duration (Hrs)</th>
				<th style="width:16%">Notes</th>
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
			echo '<td>' . esc_html( $task['assignee_name'] ) . '</td>';
			echo '<td>' . esc_html( $task['client_name'] ) . '</td>';
			echo '<td>' . esc_html( $task['project_name'] ) . '</td>';
			echo '<td>' . esc_html( $task['creation_date'] ) . '</td>';
			echo '<td>' . esc_html( $task['last_entry_date'] ) . '</td>';
			echo '<td>' . number_format( $task['duration'], 2 ) . '</td>';
			echo '<td>' . ptt_format_task_notes( $task['content'] ) . '</td>';
			echo '<td>' . $status_dropdown . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<div class="grand-total"><strong>Grand Total: ' . number_format( $grand_total, 2 ) . '&nbsp;hours</strong></div>';
		echo '</div>'; // .ptt-report-results

	} elseif ( 'single_day' === $view_mode ) {

		$target_date_str = ! empty( $_REQUEST['start_date'] ) ? sanitize_text_field( $_REQUEST['start_date'] ) : date( 'Y-m-d' );
		unset( $args['date_query'] ); // Unset original date query
		$q = new WP_Query( $args );

		/*--------------------------------------------------------------
		 * Render Debug Output (if enabled)
		 *-------------------------------------------------------------*/
		if ( isset( $_REQUEST['debug_mode'] ) && '1' === $_REQUEST['debug_mode'] ) {
			echo '<div class="notice notice-info" style="padding: 15px; margin: 20px 0; border-left-color: #0073aa;">';
			echo '<h3><span class="dashicons dashicons-hammer" style="vertical-align: middle; margin-right: 5px;"></span> Debugging Information (Single Day View)</h3>';
			echo '<h4>Query & Filtering Logic:</h4>';
			echo '<p><strong>Target Date (<code>$target_date_str</code>):</strong> ' . esc_html( $target_date_str ) . '</p>';
			echo '<p><em>The query below fetches all candidate tasks based on non-date filters (User, Client, etc.). PHP logic then loops through these results to find tasks that were either created on the target date or had a time session on that date.</em></p>';
			echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; white-space: pre-wrap;">' . esc_html( print_r( $args, true ) ) . '</pre>';
			echo '</div>';
		}

		/*----------------------------------------------------------
		 * SINGLE DAY VIEW
		 *---------------------------------------------------------*/
		$daily_tasks       = [];
		$grand_total_today = 0.0;

		if ( $q->have_posts() ) {
			while ( $q->have_posts() ) {
				$q->the_post();
				$post_id          = get_the_ID();
				$sort_timestamp   = PHP_INT_MAX;
				$is_relevant      = false;
				$daily_duration   = 0.0;
				$sessions         = get_field( 'sessions', $post_id );

				// Determine relevance from creation date first
				if ( date( 'Y-m-d', get_the_date( 'U' ) ) === $target_date_str ) {
					$is_relevant    = true;
					$sort_timestamp = get_the_date( 'U' );
				}

				// If sessions exist, they are the primary source of truth for duration and can also determine relevance.
				if ( ! empty( $sessions ) && is_array( $sessions ) ) {
					$session_duration_today = 0.0;
					$has_session_today      = false;
					foreach ( $sessions as $session ) {
						$session_start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
						if ( $session_start_str && date( 'Y-m-d', strtotime( $session_start_str ) ) === $target_date_str ) {
							$has_session_today = true;
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
							$session_duration_today += $session_duration;
						}
					}

					if ( $has_session_today ) {
						$is_relevant    = true;
						$daily_duration = $session_duration_today;
					}
				} elseif ( $is_relevant ) {
					// No sessions, but relevant by post_date (for legacy manual entries).
					$daily_duration = (float) get_field( 'calculated_duration', $post_id );
				}


				if ( $is_relevant ) {
					$client_terms  = get_the_terms( $post_id, 'client' );
					$project_terms = get_the_terms( $post_id, 'project' );

					$daily_tasks[] = [
						'id'             => $post_id,
						'title'          => get_the_title(),
                                       'assignee_name'  => ptt_get_assignee_name( $post_id ),
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

		// Prepare date navigation links.
		$current_url_args = $_GET;
		$target_date_obj  = new DateTime( $target_date_str );
		
		$prev_date_obj                    = ( clone $target_date_obj )->modify( '-1 day' );
		$current_url_args['start_date']   = $prev_date_obj->format( 'Y-m-d' );
		$prev_link                        = add_query_arg( $current_url_args, admin_url( 'edit.php' ) );

		$next_date_obj                    = ( clone $target_date_obj )->modify( '+1 day' );
		$current_url_args['start_date']   = $next_date_obj->format( 'Y-m-d' );
		$next_link                        = add_query_arg( $current_url_args, admin_url( 'edit.php' ) );
		
		$today_str = current_time('Y-m-d');


		// Render the "Single Day" table.
		echo '<div class="ptt-report-results">';
		echo '<h3>';
		echo '<a href="' . esc_url( $prev_link ) . '" class="ptt-date-nav-arrow" title="Previous Day">&lt;</a> ';
		echo 'Report for ' . esc_html( date( 'F j, Y', strtotime( $target_date_str ) ) );
		if ( $target_date_str < $today_str ) {
			echo ' <a href="' . esc_url( $next_link ) . '" class="ptt-date-nav-arrow" title="Next Day">&gt;</a>';
		}
		echo '</h3>';

		if ( empty( $daily_tasks ) ) {
			echo '<p>No tasks were created or had time tracked on this day.</p>';
		} else {
			echo '<table class="wp-list-table widefat fixed striped">';
			echo '<thead><tr>
					<th style="width:10%">Time</th>
					<th style="width:20%">Task Name</th>
					<th style="width:15%">Assignee</th>
					<th style="width:15%">Client</th>
					<th style="width:15%">Project</th>
					<th style="width:10%">Duration (Hrs)</th>
					<th style="width:15%">Notes</th>
				  </tr></thead><tbody>';

			$deductible_keywords = [ 'break', 'personal time' ];
			foreach ( $daily_tasks as $task ) {
				// Check if the task is deductible to apply strikethrough formatting.
				$is_deductible = false;
				foreach ( $deductible_keywords as $keyword ) {
					if ( stripos( $task['title'], $keyword ) !== false || stripos( $task['project_name'], $keyword ) !== false ) {
						$is_deductible = true;
						break;
					}
				}
				$duration_cell_style = $is_deductible ? 'style="text-decoration: line-through;"' : '';

				echo '<tr>';
				echo '<td>' . esc_html( $task['date_display'] ) . '</td>';
				echo '<td><a href="' . get_edit_post_link( $task['id'] ) . '">' . esc_html( $task['title'] ) . '</a></td>';
				echo '<td>' . esc_html( $task['assignee_name'] ) . '</td>';
				echo '<td>' . esc_html( $task['client_name'] ) . '</td>';
				echo '<td>' . esc_html( $task['project_name'] ) . '</td>';
				echo '<td ' . $duration_cell_style . '>' . ( $task['daily_duration'] > 0 ? number_format( $task['daily_duration'], 2 ) : '–' ) . '</td>';
				echo '<td>' . ptt_format_task_notes( $task['content'] ) . '</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';

			// Calculate deductible time.
			$deductible_time = 0.0;
			foreach ( $daily_tasks as $task ) {
				$is_deductible = false;
				foreach ( $deductible_keywords as $keyword ) {
					if ( stripos( $task['title'], $keyword ) !== false || stripos( $task['project_name'], $keyword ) !== false ) {
						$is_deductible = true;
						break;
					}
				}
				if ( $is_deductible ) {
					$deductible_time += $task['daily_duration'];
				}
			}
			$net_total_today = $grand_total_today - $deductible_time;

			// Display the grand total.
			$total_display_text = '<strong>Total for Day: ' . number_format( $net_total_today, 2 ) . '&nbsp;hours</strong>';
			if ( $deductible_time > 0 ) {
				$total_display_text .= ' <span class="subtotal" style="font-weight:normal;">(Exc. any "Breaks" or "Personal Time")</span>';
			}
			echo '<div class="grand-total">' . $total_display_text . '</div>';
		}
		echo '</div>'; // .ptt-report-results

	} else {

		$q = new WP_Query( $args );
		
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
			echo '<h4>Final Status Order Map (<code>$status_order</code>):</h4>';
			echo '<p><em>Tasks are sorted based on the ascending value of their status ID in this map.</em></p>';
			echo '<pre style="background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' . esc_html( print_r( $status_order, true ) ) . '</pre>';
			echo '</div>';
		}

		/*----------------------------------------------------------
		 * CLASSIC (HIERARCHICAL) VIEW
		 *---------------------------------------------------------*/
                $report = [];
                if ( $q->have_posts() ) {
                        // Attn: LLM Maintainers do not refactor this date search code unless specifically directed.
                        $start_timestamp = $start_date ? strtotime( $start_date . ' 00:00:00' ) : 0;
                        $end_timestamp   = $end_date ? strtotime( $end_date . ' 23:59:59' ) : PHP_INT_MAX;

			while ( $q->have_posts() ) {
				$q->the_post();
				$post_id     = get_the_ID();
				$is_relevant = false;

				// Check 1: Creation date is within range
				$creation_timestamp = get_the_date( 'U', $post_id );
				if ( $creation_timestamp >= $start_timestamp && $creation_timestamp <= $end_timestamp ) {
					$is_relevant = true;
				}

				// Check 2: At least one session date is within range
				if ( ! $is_relevant ) {
					$sessions = get_field( 'sessions', $post_id );
					if ( ! empty( $sessions ) && is_array( $sessions ) ) {
						foreach ( $sessions as $session ) {
							if ( ! empty( $session['session_start_time'] ) ) {
								$session_timestamp = strtotime( $session['session_start_time'] );
								if ( $session_timestamp >= $start_timestamp && $session_timestamp <= $end_timestamp ) {
									$is_relevant = true;
									break;
								}
							}
						}
					}
				}

				if ( ! $is_relevant ) {
					continue;
				}

				// Get Last Entry Date
				$latest_session_timestamp = 0;
				$sessions                 = get_field( 'sessions', $post_id );
				if ( ! empty( $sessions ) && is_array( $sessions ) ) {
					foreach ( $sessions as $session ) {
						if ( ! empty( $session['session_start_time'] ) ) {
							$ts = strtotime( $session['session_start_time'] );
							if ( $ts > $latest_session_timestamp ) {
								$latest_session_timestamp = $ts;
							}
						}
					}
				}
				$last_entry_date = ( $latest_session_timestamp > 0 ) ? date( 'Y-m-d', $latest_session_timestamp ) : '–';

				$duration = (float) get_field( 'calculated_duration', $post_id );
				$grand_total += $duration;

                               $assignee_id = (int) get_post_meta( $post_id, 'ptt_assignee', true );
                               $display_name = $assignee_id ? get_the_author_meta( 'display_name', $assignee_id ) : __( 'No Assignee', 'ptt' );
                               $slack_user   = $assignee_id ? get_user_meta( $assignee_id, 'slack_username', true ) : '';
                               $slack_id     = $assignee_id ? get_user_meta( $assignee_id, 'slack_user_id', true ) : '';

                               $assignee_name = $display_name;
                               if ( $assignee_id && ! empty( $slack_user ) && ! empty( $slack_id ) ) {
                                       $assignee_name .= ' (@' . $slack_user . ' - ' . $slack_id . ')';
                               }

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
				
                               if ( ! isset( $report[ $assignee_id ] ) ) {
                                       $report[ $assignee_id ] = [ 'name' => $assignee_name, 'total' => 0, 'clients' => [] ];
                               }
                               if ( ! isset( $report[ $assignee_id ]['clients'][ $client_id_term ] ) ) {
                                       $report[ $assignee_id ]['clients'][ $client_id_term ] = [ 'name' => $client_name, 'total' => 0, 'projects' => [] ];
                               }
                               if ( ! isset( $report[ $assignee_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] ) ) {
                                       $report[ $assignee_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] = [ 'name' => $project_name, 'total' => 0, 'tasks' => [] ];
                               }

                               $report[ $assignee_id ]['total'] += $duration;
                               $report[ $assignee_id ]['clients'][ $client_id_term ]['total'] += $duration;
                               $report[ $assignee_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ]['total'] += $duration;
                               $report[ $assignee_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ]['tasks'][] = [
                                       'id'              => $post_id,
                                       'title'           => get_the_title(),
                                       'assignee_name'   => ptt_get_assignee_name( $post_id ),
                                       'creation_date'   => get_the_date( 'Y-m-d', $post_id ),
                                       'last_entry_date' => $last_entry_date,
                                       'duration'        => $duration,
                                       'content'         => get_the_content(),
                                       'task_budget'     => $task_budget,
                                       'project_budget'  => $project_budget,
                                       'status_id'       => $status_id_term,
                               ];
			}
			wp_reset_postdata();
		}
		
		if ( empty( $report ) ) {
			echo '<p>No matching tasks found for the selected criteria.</p>';
			return;
		}

		// Sort clients alphabetically, then projects alphabetically, then tasks by status
		foreach ( $report as &$author ) {
			uasort( $author['clients'], function( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
			foreach ( $author['clients'] as &$client ) {
				uasort( $client['projects'], function( $a, $b ) { return strcasecmp( $a['name'], $b['name'] ); } );
				foreach ( $client['projects'] as &$project ) {
					usort( $project['tasks'], function( $a, $b ) use ( $status_order ) {
						$oa = isset( $status_order[ $a['status_id'] ] ) ? $status_order[ $a['status_id'] ] : PHP_INT_MAX;
						$ob = isset( $status_order[ $b['status_id'] ] ) ? $status_order[ $b['status_id'] ] : PHP_INT_MAX;
						if ( $oa === $ob ) { return strcmp( $b['last_entry_date'], $a['last_entry_date'] ); }
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
							<th style="width:22%">Task Name</th>
							<th style="width:12%">Assignee</th>
							<th style="width:8%">Creation&nbsp;Date</th>
							<th style="width:8%">Last&nbsp;Entry</th>
							<th style="width:8%">Duration (Hrs)</th>
							<th style="width:8%">Orig.&nbsp;Budget</th>
							<th style="width:26%">Notes</th>
							<th style="width:8%">Status</th>
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
						echo '<td>' . esc_html( $task['assignee_name'] ) . '</td>';
						echo '<td>' . esc_html( $task['creation_date'] ) . '</td>';
						echo '<td>' . esc_html( $task['last_entry_date'] ) . '</td>';
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