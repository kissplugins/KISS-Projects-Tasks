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
			<?php echo self::render_entry_actions( $entry ); ?>

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
				<?php if ( ! empty( $entry['is_quick_start'] ) ) : ?>
					<span class="ptt-badge-quick-start" title="Quick Start Task">Quick Start</span>
				<?php endif; ?>
			</span>
                       <span class="entry-meta">
                               <?php
                               $session_index = $entry['session_index'] ?? 0;
                               $is_task_level_entry = ( $session_index === -1 );

                               if ( ! $is_task_level_entry ) {
                                       // Only show task selector for session-level entries
                                       $project_id   = $entry['project_id'] ?? 0;
                                       $client_id    = $entry['client_id'] ?? 0;
                                       $user_task_ids = ptt_get_tasks_for_user( get_current_user_id() );

							$__is_quick_start = ! empty( $entry['is_quick_start'] );
							$__tax_query = [ 'relation' => 'AND' ];
							if ( $__is_quick_start && $client_id ) {
								$__tax_query[] = [ 'taxonomy' => 'client', 'field' => 'term_id', 'terms' => $client_id ];
							} else {
								$__tax_query[] = [ 'taxonomy' => 'project', 'field' => 'term_id', 'terms' => $project_id ];
								if ( $client_id ) { $__tax_query[] = [ 'taxonomy' => 'client', 'field' => 'term_id', 'terms' => $client_id ]; }
							}


                                       $task_args = [
                                               'post_type'      => 'project_task',
                                               'posts_per_page' => 100,
                                               'post_status'    => 'publish',
                                               'orderby'        => 'date',
                                               'order'          => 'DESC',
                                               'post__in'       => $user_task_ids,
								'tax_query'      => $__tax_query,
							];

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
							<?php if ( $__is_quick_start ) : ?>
								<div class="ptt-qs-hint" style="margin-top:6px;color:#555;font-size:12px;">
									Quick Start: showing tasks for client (project not restricted)
								</div>
							<?php endif; ?>

							<button type="button" class="button button-small ptt-move-session-btn" style="display:none;">Move</button>
							<button type="button" class="button button-small ptt-cancel-move-btn" style="display:none;">Cancel</button>
							&bull;
							<?php } else { ?>
								<em>Task-level entry</em> &bull;
							<?php } ?>
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
			<span class="ptt-time-display"><?php echo esc_html( $entry['duration'] ); ?></span>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Renders the actions section of an entry.
	 *
	 * @param array $entry Entry data.
	 * @return string HTML output.
	 */
	private static function render_entry_actions( $entry ) {
		$session_index = $entry['session_index'] ?? 0;
		$is_task_level_entry = ( $session_index === -1 );
		$post_id = $entry['post_id'] ?? 0;
		$edit_link = $entry['edit_link'] ?? '';
		$is_running = ! empty( $entry['is_running'] );

		ob_start();
		?>
		<div class="entry-actions">
			<?php if ( $is_task_level_entry && ! $is_running ) : ?>
				<!-- Start Timer button for task-level entries without active sessions -->
				<button type="button"
				        class="button button-small ptt-start-timer-btn"
				        data-post-id="<?php echo esc_attr( $post_id ); ?>"
				        data-action="start-timer">
					<span class="dashicons dashicons-controls-play"></span> Start Timer
				</button>
			<?php elseif ( ! $is_task_level_entry && ! $is_running ) : ?>
				<!-- Add Another Session button for completed session entries -->
				<button type="button"
				        class="button button-small ptt-add-session-btn"
				        data-post-id="<?php echo esc_attr( $post_id ); ?>"
				        data-task-title="<?php echo esc_attr( $entry['task_title'] ?? '' ); ?>"
				        data-project-name="<?php echo esc_attr( $entry['project_name'] ?? '' ); ?>"
				        data-project-id="<?php echo esc_attr( $entry['project_id'] ?? '' ); ?>"
				        data-action="add-session">
					<span class="dashicons dashicons-plus-alt"></span> Add Another Session
				</button>
			<?php endif; ?>

			<!-- Edit Task button for all entries -->
			<?php if ( $edit_link ) : ?>
				<a href="<?php echo esc_url( $edit_link ); ?>"
				   class="button button-small ptt-edit-task-btn"
				   target="_blank"
				   title="Edit Task">
					<span class="dashicons dashicons-edit"></span> Edit Task
				</a>
			<?php endif; ?>
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

				// Process this task for the target date
				$task_entries = self::process_task_for_date( $post_id, $target_date );
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
	 * Processes a task for the target date and returns entries.
	 *
	 * This method checks for three scenarios:
	 * 1. Task created/published on the target date
	 * 2. Parent-level time tracking on the target date (start_time)
	 * 3. Session-level time tracking on the target date (session_start_time)
	 *
	 * @param int    $post_id Task post ID.
	 * @param string $target_date Target date in Y-m-d format.
	 * @return array Array of entry data.
	 */
	private static function process_task_for_date( $post_id, $target_date ) {
		$entries = [];

		// Get task metadata (used by all entry types)
		$task_title = get_the_title( $post_id );
		$project_terms = get_the_terms( $post_id, 'project' );
		$project_id = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->term_id : 0;
		$project_name = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '–';
		$client_terms = get_the_terms( $post_id, 'client' );
		$client_id = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->term_id : 0;
		$client_name = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '';
		$edit_link = get_edit_post_link( $post_id );

		// 1. Check if task was created/published on target date
		$post_date = get_the_date( 'Y-m-d', $post_id );
		$task_created_on_date = ( $post_date === $target_date );

		// 2. Check parent-level time tracking
		$parent_start_time = get_field( 'start_time', $post_id );
		$parent_matches_date = false;
		if ( $parent_start_time ) {
			$parent_start_ts = strtotime( $parent_start_time );
			$parent_matches_date = ( $parent_start_ts && date( 'Y-m-d', $parent_start_ts ) === $target_date );
		}

		// 3. Process session-level time tracking
		$sessions = get_field( 'sessions', $post_id );
		$session_entries = [];
		if ( ! empty( $sessions ) && is_array( $sessions ) ) {
			$session_entries = self::process_task_sessions( $post_id, $target_date, $task_title, $project_name, $client_name, $project_id, $client_id, $edit_link );
		}

		$has_session_for_date = ! empty( $session_entries );

		// If task was created on date OR has parent-level time tracking, create a task-level entry
		// BUT suppress this if a session exists on the same date (avoid duplicate visual entries)
		if ( ( $task_created_on_date || $parent_matches_date ) && ! $has_session_for_date ) {
			$entry_type = [];
			if ( $task_created_on_date ) {
				$entry_type[] = 'created';
			}
			if ( $parent_matches_date ) {
				$entry_type[] = 'parent_time';
			}

			// Calculate parent-level duration
			$duration_seconds = 0;
			$start_ts = 0;
			$stop_ts = 0;
			$is_running = false;

			if ( $parent_matches_date ) {
				$start_ts = strtotime( $parent_start_time );
				$parent_stop_time = get_field( 'stop_time', $post_id );
				$manual_override = get_field( 'manual_override', $post_id );

				if ( $manual_override ) {
					$manual_duration = get_field( 'manual_duration', $post_id );
					$duration_seconds = $manual_duration ? (int) round( floatval( $manual_duration ) * 3600 ) : 0;
					$stop_ts = $start_ts; // For manual entries, use same timestamp
				} elseif ( $parent_stop_time ) {
					$stop_ts = strtotime( $parent_stop_time );
					if ( $start_ts && $stop_ts ) {
						$duration_seconds = $stop_ts - $start_ts;
					}
				} else {
					// Running timer
					$duration_seconds = time() - $start_ts;
					$is_running = true;
				}
			} else {
				// Task created on date but no time tracking - use post date
				$start_ts = strtotime( $post_date . ' 00:00:00' );
			}

			$entries[] = [
				'entry_id'         => $post_id . '_task',
				'post_id'          => $post_id,
				'session_index'    => -1, // Indicates this is a task-level entry
				'session_title'    => implode( ', ', $entry_type ) . ': ' . $task_title,
				'session_notes'    => $task_created_on_date ? 'Task created on this date' : 'Parent-level time tracking',
				'task_title'       => $task_title,
				'project_name'     => $project_name,
				'client_name'      => $client_name,
				'project_id'       => $project_id,
				'client_id'        => $client_id,
				'is_quick_start'   => ( $project_name === 'Quick Start' ),
				'start_time'       => $start_ts,
				'stop_time'        => $stop_ts,
				'duration_seconds' => $duration_seconds,
				'is_manual'        => $parent_matches_date && get_field( 'manual_override', $post_id ),
				'duration'         => $duration_seconds > 0 ? gmdate( 'H:i:s', $duration_seconds ) : ( $is_running ? 'Running' : '00:00:00' ),
				'is_running'       => $is_running,
				'edit_link'        => $edit_link,
				'entry_type'       => $entry_type,
			];
		}

		// Add session entries
		$entries = array_merge( $entries, $session_entries );

		return $entries;
	}

	/**
	 * Processes sessions for a task and returns entries for the target date.
	 *
	 * @param int    $post_id Task post ID.
	 * @param string $target_date Target date in Y-m-d format.
	 * @param string $task_title Task title.
	 * @param string $project_name Project name.
	 * @param string $client_name Client name.
	 * @param int    $project_id Project ID.
	 * @param int    $client_id Client ID.
	 * @param string $edit_link Edit link.
	 * @return array Array of session entry data.
	 */
	private static function process_task_sessions( $post_id, $target_date, $task_title, $project_name, $client_name, $project_id, $client_id, $edit_link ) {
		$entries = [];
		$sessions = get_field( 'sessions', $post_id );

		if ( empty( $sessions ) || ! is_array( $sessions ) ) {
			return $entries;
		}

		foreach ( $sessions as $index => $session ) {
			$start_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
			if ( empty( $start_str ) ) {
				continue;
			}

			$start_ts = \KISS\PTT\Plugin::$acf->toUtcTimestamp( $start_str );
			if ( ! $start_ts ) {
				continue;
			}

			// Compare as local date by converting UTC start_ts to local Y-m-d
			if ( wp_date( 'Y-m-d', $start_ts ) === $target_date ) {
				$stop_str = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
				$duration_seconds = 0;
				$stop_ts = $stop_str ? \KISS\PTT\Plugin::$acf->toUtcTimestamp( $stop_str ) : null;

				if ( $start_ts && $stop_ts ) {
					$duration_seconds = $stop_ts - $start_ts;
				} elseif ( $start_ts && ! $stop_str ) {
					// For running timers
					$duration_seconds = time() - $start_ts;
				}

				// Manual override always takes precedence
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
					'is_quick_start'   => ( $project_name === 'Quick Start' ),
					'start_time'       => $start_ts,
					'stop_time'        => $stop_ts,
					'duration_seconds' => $duration_seconds,
					'is_manual'        => ! empty( $session['session_manual_override'] ),
					'duration'         => $duration_seconds > 0 ? gmdate( 'H:i:s', $duration_seconds ) : 'Running',
					'is_running'       => empty( $stop_str ),
					'edit_link'        => $edit_link,
					'entry_type'       => ['session'],
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
			echo '<div class="ptt-today-no-entries">No tasks or time entries found for this day.</div>';
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
	 * @param int    $matched_entries Number of matched entries.
	 * @param array  $entries Array of entries for detailed debugging.
	 * @return string HTML debug output.
	 */
	public static function get_debug_info( $user_id, $target_date, $matched_tasks, $matched_entries, $entries = [] ) {
		$current_user = get_userdata( $user_id );
		$user_info = $current_user ? $current_user->user_login . ' (ID: ' . $current_user->ID . ')' : 'Unknown';

		// Analyze entry types
		$entry_types = [
			'created' => 0,
			'parent_time' => 0,
			'session' => 0,
		];

		foreach ( $entries as $entry ) {
			if ( isset( $entry['entry_type'] ) && is_array( $entry['entry_type'] ) ) {
				foreach ( $entry['entry_type'] as $type ) {
					if ( isset( $entry_types[ $type ] ) ) {
						$entry_types[ $type ]++;
					}
				}
			}
		}

		ob_start();
		?>
		<ul>
			<li><strong>User:</strong> <?php echo esc_html( $user_info ); ?></li>
			<li><strong>Date:</strong> <?php echo esc_html( $target_date ); ?></li>
			<li><strong>Queried Statuses:</strong> Not Started, In Progress, Completed, Blocked</li>
			<li><strong>Tasks Found:</strong> <?php echo esc_html( $matched_tasks ); ?></li>
			<li><strong>Total Entries:</strong> <?php echo esc_html( $matched_entries ); ?></li>
			<li><strong>Entry Types:</strong>
				<ul>
					<li>Tasks Created on Date: <?php echo esc_html( $entry_types['created'] ); ?></li>
					<li>Parent-Level Time Tracking: <?php echo esc_html( $entry_types['parent_time'] ); ?></li>
					<li>Session-Level Time Tracking: <?php echo esc_html( $entry_types['session'] ); ?></li>
				</ul>
			</li>
			<li><strong>Task Query Rules:</strong>
				<ul>
					<li><strong>User Filter:</strong> Only tasks where the current user is the assignee (ptt_assignee)</li>
					<li><strong>Date Scenarios:</strong> Tasks that match ANY of the following for the target date:
						<ul>
							<li><strong>Tasks Created:</strong> Tasks created/published on the target date</li>
							<li><strong>Parent-Level Time:</strong> Tasks with legacy parent-level time tracking (start_time) on the target date
								<br><em>Note: Parent-level timer fields are hidden in UI but preserved for calculations</em></li>
							<li><strong>Session-Level Time:</strong> Tasks with session-level time tracking (session_start_time) on the target date</li>
						</ul>
					</li>
					<li><strong>Workflow:</strong> Use action buttons (Start Timer, Add Another Session, Edit Task) for seamless time tracking</li>
					<li><strong>Data Access:</strong> Tasks created by the user but assigned to others are NOT shown (use Reports or All Tasks for broader data)</li>
					<li><strong>Quick Start & Reassignment Rules:</strong>
						<ul>
							<li><strong>Quick Start Project:</strong> Daily Quick Start tasks are created under the <em>"Quick Start"</em> project and tagged with the selected Client.</li>
							<li><strong>Task Selector (Move Session):</strong>
								<ul>
									<li><strong>Quick Start entries:</strong> Lists tasks assigned to the current user for the <em>same Client only</em> (Project is ignored for reassignment).</li>
									<li><strong>Non-Quick-Start entries:</strong> Lists tasks assigned to the current user that match the entry’s <em>Project</em>; if the entry has a Client, tasks must also match that <em>Client</em>.</li>
								</ul>
							</li>
							<li><strong>Implication:</strong> For Quick Start sessions, you can now reassign directly to any of your tasks for that Client, regardless of Project.</li>
						</ul>
					</li>
				</ul>
			</li>
			<hr />
			<pre style="white-space:pre-wrap;word-wrap:break-word;max-height:360px;overflow:auto;border:1px dashed #ccc;padding:8px;background:#fff;">
<?php echo esc_html( print_r( $entries, true ) ); ?>
			</pre>
		</ul>
		<?php
		return ob_get_clean();
	}
}