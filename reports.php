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
 * 1.7.9 – 2025-07-23
 * • Dev: Expanded self-tests with additional AJAX and reporting checks.
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
 * Reports page HTML (filter form + results container)
 *==================================================================*/
function ptt_reports_page_html() {
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
								   value="<?php echo isset( $_REQUEST['start_date'] ) ? esc_attr( $_REQUEST['start_date'] ) : ''; ?>">
							to
							<input type="date" id="end_date" name="end_date"
								   value="<?php echo isset( $_REQUEST['end_date'] ) ? esc_attr( $_REQUEST['end_date'] ) : ''; ?>">

							<button type="button" id="set-this-week" class="button">This Week (Sun‑Sat)</button>
							<button type="button" id="set-last-week" class="button">Last Week</button>
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

	$user_id    = isset( $_REQUEST['user_id'] )    ? intval( $_REQUEST['user_id'] )    : 0;
	$client_id  = isset( $_REQUEST['client_id'] )  ? intval( $_REQUEST['client_id'] )  : 0;
	$project_id = isset( $_REQUEST['project_id'] ) ? intval( $_REQUEST['project_id'] ) : 0;
	$status_id  = isset( $_REQUEST['status_id'] )  ? intval( $_REQUEST['status_id'] )  : 0;
	$start_date = ! empty( $_REQUEST['start_date'] ) ? sanitize_text_field( $_REQUEST['start_date'] ) : null;
	$end_date   = ! empty( $_REQUEST['end_date'] )   ? sanitize_text_field( $_REQUEST['end_date'] )   : null;

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

	if ( ! $q->have_posts() ) {
		echo '<p>No matching tasks found for the selected criteria.</p>';
		return;
	}

	/*--------------------------------------------------------------
	 * Transform into hierarchical array → User > Client > Project
	 *-------------------------------------------------------------*/
	$report      = [];
	$grand_total = 0.0;

	while ( $q->have_posts() ) {
		$q->the_post();
		$post_id = get_the_ID();

		// -------- Meta & taxonomy look‑ups --------
		$author_id   = get_the_author_meta( 'ID' );
		$author_name = get_the_author_meta( 'display_name' );

		$client_terms   = get_the_terms( $post_id, 'client' );
		$client_id_term = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->term_id : 0;
		$client_name    = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : 'Uncategorized';

		$project_terms   = get_the_terms( $post_id, 'project' );
		$project_id_term = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->term_id : 0;
		$project_name    = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : 'Uncategorized';

		$status_terms = get_the_terms( $post_id, 'task_status' );
		$status_id_term = ! is_wp_error( $status_terms ) && $status_terms ? $status_terms[0]->term_id : 0;
		$status_name  = ! is_wp_error( $status_terms ) && $status_terms ? $status_terms[0]->name : '';

		// -------- Duration / Budget --------
		$duration       = (float) get_field( 'calculated_duration', $post_id );
		$task_budget    = get_field( 'task_max_budget', $post_id );
		$project_budget = $project_id_term ? get_field( 'project_max_budget', 'project_' . $project_id_term ) : 0;

		// -------- Start date (fallback) --------
		$start_time = get_field( 'start_time', $post_id );
		if ( ! $start_time ) {
			$start_time = get_the_date( 'Y-m-d H:i:s', $post_id );
		}

		// -------- Build array --------
		if ( ! isset( $report[ $author_id ] ) ) {
			$report[ $author_id ] = [
				'name'    => $author_name,
				'total'   => 0,
				'clients' => [],
			];
		}
		if ( ! isset( $report[ $author_id ]['clients'][ $client_id_term ] ) ) {
			$report[ $author_id ]['clients'][ $client_id_term ] = [
				'name'     => $client_name,
				'total'    => 0,
				'projects' => [],
			];
		}
		if ( ! isset( $report[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] ) ) {
			$report[ $author_id ]['clients'][ $client_id_term ]['projects'][ $project_id_term ] = [
				'name'  => $project_name,
				'total' => 0,
				'tasks' => [],
			];
		}

		$report[ $author_id ]['total']                                                             += $duration;
		$report[ $author_id ]['clients'][ $client_id_term ]['total']                               += $duration;
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
			'status_name'    => $status_name,
		];

		$grand_total += $duration;
	}
	wp_reset_postdata();

	/*--------------------------------------------------------------
	 * Render HTML
	 *-------------------------------------------------------------*/
	echo '<h2>Report Results</h2><div class="ptt-report-results">';

	// Get all status terms once to build dropdowns
	$all_statuses = get_terms( [ 'taxonomy' => 'task_status', 'hide_empty' => false ] );

	foreach ( $report as $author ) {

		echo '<h3>User: ' . esc_html( $author['name'] ) .
			 ' <span class="subtotal">(User&nbsp;Total: ' . number_format( $author['total'], 2 ) . '&nbsp;hrs)</span></h3>';

		foreach ( $author['clients'] as $client ) {

			echo '<div class="client-group">';
			echo '<h4>Client: ' . esc_html( $client['name'] ) .
				 ' <span class="subtotal">(Client&nbsp;Total: ' . number_format( $client['total'], 2 ) . '&nbsp;hrs)</span></h4>';

			foreach ( $client['projects'] as $project ) {

				echo '<div class="project-group">';
				echo '<h5>Project: ' . esc_html( $project['name'] ) .
					 ' <span class="subtotal">(Project&nbsp;Total: ' . number_format( $project['total'], 2 ) . '&nbsp;hrs)</span></h5>';

				echo '<table class="wp-list-table widefat fixed striped">';
				echo '<thead><tr>
						<th>Task Name</th><th>Date</th><th>Duration (Hours)</th>
						<th>Orig.&nbsp;Budget</th><th>Notes</th><th>Status</th>
					  </tr></thead><tbody>';

				foreach ( $project['tasks'] as $task ) {

					// --- Budget column display & color logic ---
					$effective_budget = 0;
					$budget_display   = '–';

					if ( ! empty( $task['task_budget'] ) && (float) $task['task_budget'] > 0 ) {
						$effective_budget = (float) $task['task_budget'];
						$budget_display   = number_format( $effective_budget, 2 ) . '&nbsp;(Task)';
					} elseif ( ! empty( $task['project_budget'] ) && (float) $task['project_budget'] > 0 ) {
						$effective_budget = (float) $task['project_budget'];
						$budget_display   = number_format( $effective_budget, 2 ) . '&nbsp;(Project)';
					}

					$budget_td_style = '';
					if ( $effective_budget > 0 && (float) $task['duration'] > $effective_budget ) {
						$budget_td_style = 'style="color: #f44336; font-weight: bold;"';
					}

					// --- Status column dropdown ---
					$status_dropdown  = '<div class="ptt-status-control">';
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

				echo '</tbody></table></div>'; // .project-group
			}

			echo '</div>'; // .client-group
		}
	}

	echo '<div class="grand-total"><strong>Grand Total: ' .
		 number_format( $grand_total, 2 ) . '&nbsp;hours</strong></div>';
	echo '</div>'; // .ptt-report-results
}