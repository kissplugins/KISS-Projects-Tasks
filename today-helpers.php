<?php
/**
 * ------------------------------------------------------------------
 * TODAY PAGE HELPERS via Claude
 * ------------------------------------------------------------------
 *
 * This file contains helper functions and classes for the Today page,
 * providing a modular approach for rendering and managing time entries.
 *
 * @since 1.9.1
 * ------------------------------------------------------------------
 */

// Block direct access.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Class PTT_Today_Entry_Renderer
 *
 * Handles the rendering of individual time entries for the Today page.
 * This class provides a flexible structure for future enhancements.
 */
class PTT_Today_Entry_Renderer {

	/**
	 * Renders a single time entry row.
	 *
	 * @param array $entry Entry data array.
	 * @return string HTML output.
	 */
	public static function render_entry( $entry ) {
		$running_class = ! empty( $entry['is_running'] ) ? 'running' : '';
		$entry_id = isset( $entry['entry_id'] ) ? $entry['entry_id'] : '';
		$post_id = isset( $entry['post_id'] ) ? $entry['post_id'] : '';
		$session_index = isset( $entry['session_index'] ) ? $entry['session_index'] : '';

		ob_start();
		?>
		<div class="ptt-today-entry <?php echo esc_attr( $running_class ); ?>"
		     data-entry-id="<?php echo esc_attr( $entry_id ); ?>"
		     data-post-id="<?php echo esc_attr( $post_id ); ?>"
		     data-session-index="<?php echo esc_attr( $session_index ); ?>">

			<?php echo self::render_entry_details( $entry ); ?>
			<?php echo self::render_entry_duration( $entry ); ?>

		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the details section of an entry.
	 *
	 * @param array $entry Entry data.
	 * @return string HTML output.
	 */
	private static function render_entry_details( $entry ) {
		ob_start();
		?>
		<div class="entry-details">
			<span class="entry-session-title" data-field="session_title">
				<?php echo esc_html( $entry['session_title'] ); ?>
			</span>
                       <span class="entry-meta">
                               <?php
                               $project_id   = $entry['project_id'] ?? 0;
                               $client_id    = $entry['client_id'] ?? 0;
                               $user_task_ids = ptt_get_tasks_for_user( get_current_user_id() );

                               $task_args = [
                                       'post_type'      => 'project_task',
                                       'posts_per_page' => 100,
                                       'post_status'    => 'publish',
                                       'orderby'        => 'date',
                                       'order'          => 'DESC',
                                       'post__in'       => $user_task_ids,
                                       'tax_query'      => [
                                               'relation' => 'AND',
                                               [
                                                       'taxonomy' => 'project',
                                                       'field'    => 'term_id',
                                                       'terms'    => $project_id,
                                               ],
                                       ],
                               ];

                               if ( $client_id ) {
                                       $task_args['tax_query'][] = [
                                               'taxonomy' => 'client',
                                               'field'    => 'term_id',
                                               'terms'    => $client_id,
                                       ];
                               }

                               $tasks_query = new WP_Query( $task_args );
                               ?>
                               <select class="ptt-entry-task-selector" data-original-task="<?php echo esc_attr( $entry['post_id'] ); ?>">
                                       <?php
                                       if ( $tasks_query->have_posts() ) {
                                               while ( $tasks_query->have_posts() ) {
                                                       $tasks_query->the_post();
                                                       echo '<option value="' . esc_attr( get_the_ID() ) . '"' . selected( get_the_ID(), $entry['post_id'], false ) . '>' . esc_html( get_the_title() ) . '</option>';
                                               }
                                               wp_reset_postdata();
                                       }
                                       ?>
                               </select>
                               <button type="button" class="button button-small ptt-move-session-btn" style="display:none;">Move</button>
                               <button type="button" class="button button-small ptt-cancel-move-btn" style="display:none;">Cancel</button>
                               &bull;
                               <span class="entry-project-name" data-field="project_name">
                                       <?php echo esc_html( $entry['project_name'] ); ?>
                               </span>
                               <?php if ( ! empty( $entry['client_name'] ) ) : ?>
                                       &bull;
                                       <span class="entry-client-name" data-field="client_name">
                                               <?php echo esc_html( $entry['client_name'] ); ?>
                                       </span>
                               <?php endif; ?>
                       </span>
			<?php if ( ! empty( $entry['session_notes'] ) ) : ?>
				<span class="entry-notes" data-field="session_notes">
					<?php echo esc_html( $entry['session_notes'] ); ?>
				</span>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the duration section of an entry.
	 *
	 * @param array $entry Entry data.
	 * @return string HTML output.
	 */
	private static function render_entry_duration( $entry ) {
		$duration_class = ! empty( $entry['is_running'] ) ? 'entry-duration-running' : '';
		$editable_attr = ! empty( $entry['is_running'] ) ? '' : 'data-editable="true"';

		ob_start();
		?>
		<div class="entry-duration <?php echo esc_attr( $duration_class ); ?>"
		     data-field="duration"
		     data-duration-seconds="<?php echo esc_attr( $entry['duration_seconds'] ?? 0 ); ?>"
		     <?php echo $editable_attr; ?>>
			<?php echo esc_html( $entry['duration'] ); ?>
		</div>
		<?php
		return ob_get_clean();
	}

}

/**
 * Class PTT_Today_Data_Provider
 *
 * Handles data fetching and processing for the Today page.
 */
class PTT_Today_Data_Provider {

	/**
	 * Gets time entries for a specific user and date.
	 *
	 * @param int    $user_id User ID.
	 * @param string $target_date Date in Y-m-d format.
	 * @param array  $filters Optional filters array.
	 * @return array Processed entries array.
	 */
	public static function get_daily_entries( $user_id, $target_date, $filters = [] ) {
		$all_entries = [];

		// Get all tasks for the current user
		$user_task_ids = ptt_get_tasks_for_user( $user_id );

		if ( empty( $user_task_ids ) ) {
			return $all_entries;
		}

		// Build query args
		$args = self::build_query_args( $user_task_ids, $filters );

		$query = new WP_Query( $args );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$post_id = get_the_ID();

				// Process sessions for this task
				$task_entries = self::process_task_sessions( $post_id, $target_date );
				$all_entries = array_merge( $all_entries, $task_entries );
			}
			wp_reset_postdata();
		}

		// Sort entries by start time descending
		usort( $all_entries, function( $a, $b ) {
			return $b['start_time'] <=> $a['start_time'];
		} );

		return $all_entries;
	}

	/**
	 * Builds WP_Query arguments based on filters.
	 *
	 * @param array $user_task_ids User's task IDs.
	 * @param array $filters Filter options.
	 * @return array Query arguments.
	 */
	private static function build_query_args( $user_task_ids, $filters ) {
		// Get Term IDs for statuses
		$status_terms_to_include = [];
		$status_names = ['Not Started', 'In Progress', 'Completed', 'Blocked'];

		foreach ( $status_names as $status_name ) {
			$term = get_term_by( 'name', $status_name, 'task_status' );
			if ( $term ) {
				$status_terms_to_include[] = $term->term_id;
			}
		}

		$args = [
			'post_type'      => 'project_task',
			'posts_per_page' => -1,
			'post_status'    => ['publish', 'private'],
			'post__in'       => $user_task_ids,
			'tax_query'      => [
				'relation' => 'AND',
			],
		];

		// Add status filter
		if ( ! empty( $status_terms_to_include ) ) {
			$args['tax_query'][] = [
				'taxonomy' => 'task_status',
				'field'    => 'term_id',
				'terms'    => $status_terms_to_include,
			];
		}

		// Add client filter if provided
		if ( ! empty( $filters['client_id'] ) ) {
			$args['tax_query'][] = [
				'taxonomy' => 'client',
				'field'    => 'term_id',
				'terms'    => $filters['client_id'],
			];
		}

		// Add project filter if provided
		if ( ! empty( $filters['project_id'] ) ) {
			$args['tax_query'][] = [
				'taxonomy' => 'project',
				'field'    => 'term_id',
				'terms'    => $filters['project_id'],
			];
		}

		return $args;
	}

	/**
	 * Processes sessions for a task and returns entries for the target date.
	 *
	 * @param int    $post_id Task post ID.
	 * @param string $target_date Target date in Y-m-d format.
	 * @return array Array of entry data.
	 */
	private static function process_task_sessions( $post_id, $target_date ) {
		$entries = [];
		$sessions = get_field( 'sessions', $post_id );

		if ( empty( $sessions ) || ! is_array( $sessions ) ) {
			return $entries;
		}

		// Get task metadata
		$task_title = get_the_title( $post_id );
               $project_terms = get_the_terms( $post_id, 'project' );
               $project_id = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->term_id : 0;
               $project_name = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–';
               $client_terms = get_the_terms( $post_id, 'client' );
               $client_id = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->term_id : 0;
               $client_name = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '';
               $edit_link = get_edit_post_link( $post_id );

		foreach ( $sessions as $index => $session ) {
			$start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
			if ( empty( $start_str ) ) {
				continue;
			}

			$start_ts = strtotime( $start_str );
			if ( ! $start_ts ) {
				continue;
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

				// If manual override is set, prefer manual duration over timestamps
				if ( ! empty( $session['session_manual_override'] ) ) {
					$manual_hours = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.0;
					if ( $manual_hours > 0 ) {
						$duration_seconds = (int) round( $manual_hours * 3600 );
					}
				}

				}

					// Ensure manual override always takes precedence over timestamp math,
					// even for non-running sessions (e.g., start==stop) where timestamp math yields 0.
					if ( ! empty( $session['session_manual_override'] ) ) {
						$manual_hours = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.0;
						if ( $manual_hours > 0 ) {
							$duration_seconds = (int) round( $manual_hours * 3600 );
						}
					}


				$entries[] = [
					'entry_id'         => $post_id . '_' . $index,
					'post_id'          => $post_id,
					'session_index'    => $index,
					'session_title'    => $session['session_title'] ?? '',
					'session_notes'    => $session['session_notes'] ?? '',
                                       'task_title'       => $task_title,
                                       'project_name'     => $project_name,
                                       'client_name'      => $client_name,
                                       'project_id'       => $project_id,
                                       'client_id'        => $client_id,
					'start_time'       => $start_ts,
					'stop_time'        => $stop_ts,
					'duration_seconds' => $duration_seconds,
						'is_manual'        => ! empty( $session['session_manual_override'] ),

					'duration'         => $duration_seconds > 0 ? gmdate( 'H:i:s', $duration_seconds ) : 'Running',
					'is_running'       => empty( $stop_str ),
					'edit_link'        => $edit_link,
				];
			}
		}

		return $entries;
	}

	/**
	 * Calculates total duration from an array of entries.
	 *
	 * @param array $entries Array of entry data.
	 * @return array Total time in seconds and formatted string.
	 */
	public static function calculate_total_duration( $entries ) {
		$total_seconds = 0;

		foreach ( $entries as $entry ) {
			if ( isset( $entry['duration_seconds'] ) ) {
				$total_seconds += $entry['duration_seconds'];
			}
		}

		$total_hours = floor( $total_seconds / 3600 );
		$total_minutes = floor( ( $total_seconds / 60 ) % 60 );
		$formatted = sprintf( '%02d:%02d', $total_hours, $total_minutes );

		return [
			'seconds'   => $total_seconds,
			'formatted' => $formatted,
		];
	}
}

/**
 * Class PTT_Today_Page_Manager
 *
 * Main manager class for the Today page functionality.
 */
class PTT_Today_Page_Manager {

	/**
	 * Renders the complete entries list HTML.
	 *
	 * @param int    $user_id User ID.
	 * @param string $target_date Target date.
	 * @param array  $filters Optional filters.
	 * @return array HTML and total duration.
	 */
	public static function render_entries_list( $user_id, $target_date, $filters = [] ) {
		$entries = PTT_Today_Data_Provider::get_daily_entries( $user_id, $target_date, $filters );
		$total = PTT_Today_Data_Provider::calculate_total_duration( $entries );

		ob_start();
		if ( empty( $entries ) ) {
			echo '<div class="ptt-today-no-entries">No time entries recorded for this day.</div>';
		} else {
			echo '<div class="ptt-today-entries-wrapper" data-date="' . esc_attr( $target_date ) . '">';
			foreach ( $entries as $entry ) {
				echo PTT_Today_Entry_Renderer::render_entry( $entry );
			}
			echo '</div>';
		}
		$html = ob_get_clean();

		return [
			'html'    => $html,
			'total'   => $total['formatted'],
			'entries' => $entries,
		];
	}

	/**
	 * Gets debug information for the Today page.
	 *
	 * @param int    $user_id User ID.
	 * @param string $target_date Target date.
	 * @param int    $matched_tasks Number of matched tasks.
	 * @param int    $matched_sessions Number of matched sessions.
	 * @return string HTML debug output.
	 */
	public static function get_debug_info( $user_id, $target_date, $matched_tasks, $matched_sessions, $entries = [] ) {
		$current_user = get_userdata( $user_id );
		$user_info = $current_user ? $current_user->user_login . ' (ID: ' . $current_user->ID . ')' : 'Unknown';

		ob_start();
		?>
		<ul>
			<li><strong>User:</strong> <?php echo esc_html( $user_info ); ?></li>
			<li><strong>Date:</strong> <?php echo esc_html( $target_date ); ?></li>
			<li><strong>Queried Statuses:</strong> Not Started, In Progress, Completed, Blocked</li>
			<li><strong>Tasks Found:</strong> <?php echo esc_html( $matched_tasks ); ?></li>
			<hr />
			<pre style="white-space:pre-wrap;word-wrap:break-word;max-height:360px;overflow:auto;border:1px dashed #ccc;padding:8px;background:#fff;">
<?php echo esc_html( print_r( $entries, true ) ); ?>
			</pre>
			<?php
			// NOTE: Keep expanded debug info in place until further notice; do not remove.
			?>
			<li><strong>Sessions on this Date:</strong> <?php echo esc_html( $matched_sessions ); ?></li>
			<li><strong>Task Query Rule:</strong> Tasks where the current user is the assignee.</li>
		</ul>
		<?php
		return ob_get_clean();
	}
}