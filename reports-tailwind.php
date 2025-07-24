<?php
/**
 * ------------------------------------------------------------------
 * 12.0 ADMIN PAGES & LINKS (Tailwind Reports)
 * ------------------------------------------------------------------
 *
 * This file registers the **Tailwind Reports** sub-page and renders
 * the Tailwind CSS styled version of the single day report.
 *
 * ------------------------------------------------------------------
 */

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Enqueues the Tailwind CSS script specifically for the Tailwind Reports page.
 */
function ptt_enqueue_tailwind_assets( $hook ) {
	// Only load on our specific admin page.
	if ( isset( $_GET['page'] ) && $_GET['page'] === 'ptt-tailwind-reports' ) {
		// Note: For production use, it is highly recommended to compile your CSS
		// and enqueue a local file instead of using the live CDN.
		wp_enqueue_script( 'ptt-tailwind-cdn', 'https://cdn.tailwindcss.com', [], PTT_VERSION, false );
	}
}
add_action( 'admin_enqueue_scripts', 'ptt_enqueue_tailwind_assets' );

/**
 * Register the admin-side "Tailwind Reports" sub-menu.
 */
function ptt_add_tailwind_reports_page() {
	add_submenu_page(
		'edit.php?post_type=project_task',
		'Tailwind Reports',
		'Tailwind Reports',
		'manage_options',
		'ptt-tailwind-reports',
		'ptt_tailwind_reports_page_html'
	);
}
add_action( 'admin_menu', 'ptt_add_tailwind_reports_page' );

/**
 * Reports page HTML (filter form + results container) for Tailwind.
 */
function ptt_tailwind_reports_page_html() {
	?>
	<div class="wrap">
		<h1 class="text-2xl font-bold mb-4">Project & Task Time Reports (Tailwind)</h1>

		<form method="get" action="">
			<?php wp_nonce_field( 'ptt_run_report_nonce', 'ptt-tailwind-reports' ); ?>
			<input type="hidden" name="post_type" value="project_task">
			<input type="hidden" name="page"      value="ptt-tailwind-reports">

			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row"><label for="user_id">Select User</label></th>
						<td>
							<?php
							wp_dropdown_users( [
								'name'            => 'user_id',
								'show_option_all' => 'All Users',
								'selected'        => isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : 0,
							] );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="start_date">Select Day</label></th>
						<td>
							<input type="date" id="start_date" name="start_date"
								   value="<?php echo isset( $_REQUEST['start_date'] ) ? esc_attr( $_REQUEST['start_date'] ) : date('Y-m-d'); ?>">
						</td>
					</tr>
				</tbody>
			</table>

			<p class="submit">
				<input type="submit" name="run_report" class="button button-primary" value="Run Report">
			</p>
		</form>

		<?php
		if ( isset( $_REQUEST['run_report'] ) ) {
			check_admin_referer( 'ptt_run_report_nonce', 'ptt-tailwind-reports' );
			ptt_display_tailwind_report_results();
		}
		?>
	</div>
	<?php
}

/**
 * Core: Query & Display Tailwind report results.
 */
function ptt_display_tailwind_report_results() {

	$user_id     = isset( $_REQUEST['user_id'] ) ? intval( $_REQUEST['user_id'] ) : 0;
	$target_date_str = ! empty( $_REQUEST['start_date'] ) ? sanitize_text_field( $_REQUEST['start_date'] ) : date( 'Y-m-d' );

	$args = [
		'post_type'      => 'project_task',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'orderby'        => [ 'author' => 'ASC', 'date' => 'ASC' ],
	];

	if ( $user_id ) {
		$args['author'] = $user_id;
	}

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
			$sessions         = get_field( 'sessions', $post_id );

			if ( date( 'Y-m-d', get_the_date( 'U' ) ) === $target_date_str ) {
				$is_relevant    = true;
				$sort_timestamp = get_the_date( 'U' );
			}

			if ( ! empty( $sessions ) && is_array( $sessions ) ) {
				$session_duration_today = 0.0;
				$has_session_today      = false;
				foreach ( $sessions as $session ) {
					$session_start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
					if ( $session_start_str && date( 'Y-m-d', strtotime( $session_start_str ) ) === $target_date_str ) {
						$has_session_today = true;
						$sort_timestamp = min( $sort_timestamp, strtotime( $session_start_str ) );
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
				$daily_duration = (float) get_field( 'calculated_duration', $post_id );
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

	usort( $daily_tasks, function( $a, $b ) {
		return $a['sort_timestamp'] <=> $b['sort_timestamp'];
	} );

	$current_url_args = $_GET;
	$target_date_obj  = new DateTime( $target_date_str );

	$prev_date_obj                    = ( clone $target_date_obj )->modify( '-1 day' );
	$current_url_args['start_date']   = $prev_date_obj->format( 'Y-m-d' );
	$prev_link                        = add_query_arg( $current_url_args, admin_url( 'edit.php' ) );

	$next_date_obj                    = ( clone $target_date_obj )->modify( '+1 day' );
	$current_url_args['start_date']   = $next_date_obj->format( 'Y-m-d' );
	$next_link                        = add_query_arg( $current_url_args, admin_url( 'edit.php' ) );

	$today_str = current_time('Y-m-d');

	echo '<div class="mt-8">';
	echo '<div class="flex justify-between items-center mb-4">';
	echo '  <a href="' . esc_url( $prev_link ) . '" class="text-blue-500 hover:text-blue-700">&lt; Previous Day</a>';
	echo '  <h2 class="text-xl font-bold">Report for ' . esc_html( date( 'F j, Y', strtotime( $target_date_str ) ) ) . '</h2>';
	if ( $target_date_str < $today_str ) {
		echo '  <a href="' . esc_url( $next_link ) . '" class="text-blue-500 hover:text-blue-700">Next Day &gt;</a>';
	}
	echo '</div>';

	if ( empty( $daily_tasks ) ) {
		echo '<p class="text-gray-500">No tasks were created or had time tracked on this day.</p>';
	} else {
		echo '<div class="shadow overflow-hidden border-b border-gray-200 sm:rounded-lg">';
		echo '<table class="min-w-full divide-y divide-gray-200">';
		echo '  <thead class="bg-gray-50">';
		echo '    <tr>';
		echo '      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>';
		echo '      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Task Name</th>';
		echo '      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>';
		echo '      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>';
		echo '      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Duration (Hrs)</th>';
		echo '      <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notes</th>';
		echo '    </tr>';
		echo '  </thead>';
		echo '  <tbody class="bg-white divide-y divide-gray-200">';

		$deductible_keywords = [ 'break', 'personal time' ];
		foreach ( $daily_tasks as $task ) {
			$is_deductible = false;
			foreach ( $deductible_keywords as $keyword ) {
				if ( stripos( $task['title'], $keyword ) !== false || stripos( $task['project_name'], $keyword ) !== false ) {
					$is_deductible = true;
					break;
				}
			}
			$duration_cell_class = $is_deductible ? 'line-through' : '';

			echo '<tr>';
			echo '  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html( $task['date_display'] ) . '</td>';
			echo '  <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><a href="' . get_edit_post_link( $task['id'] ) . '" class="text-indigo-600 hover:text-indigo-900">' . esc_html( $task['title'] ) . '</a></td>';
			echo '  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html( $task['client_name'] ) . '</td>';
			echo '  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">' . esc_html( $task['project_name'] ) . '</td>';
			echo '  <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 ' . $duration_cell_class . '">' . ( $task['daily_duration'] > 0 ? number_format( $task['daily_duration'], 2 ) : '–' ) . '</td>';
			echo '  <td class="px-6 py-4 text-sm text-gray-500">' . ptt_format_task_notes( $task['content'] ) . '</td>';
			echo '</tr>';
		}
		echo '  </tbody>';
		echo '</table>';
		echo '</div>';

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

		$total_display_text = '<strong>Total for Day: ' . number_format( $net_total_today, 2 ) . '&nbsp;hours</strong>';
		if ( $deductible_time > 0 ) {
			$total_display_text .= ' <span class="font-normal">(Exc. any "Breaks" or "Personal Time")</span>';
		}
		echo '<div class="mt-4 p-4 bg-gray-800 text-white text-right font-bold rounded-lg">' . $total_display_text . '</div>';
	}
	echo '</div>';
}