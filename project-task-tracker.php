<?php
/**
 * Plugin Name:       KISS - Project & Task Time Tracker
 * Plugin URI:        https://kissplugins.com
 * Description:       A robust system for WordPress users to track time spent on client projects and individual tasks. Requires ACF Pro.
 * Version:           1.11.15
 * Author:            KISS Plugins
 * Author URI:        https://kissplugins.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       ptt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'PTT_VERSION', '1.11.15' );
define( 'PTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );



// Load plugin modules
require_once PTT_PLUGIN_DIR . 'helpers.php';
require_once PTT_PLUGIN_DIR . 'shortcodes.php';
require_once PTT_PLUGIN_DIR . 'self-test.php';
require_once PTT_PLUGIN_DIR . 'reports.php';
require_once PTT_PLUGIN_DIR . 'kanban.php';
require_once PTT_PLUGIN_DIR . 'today.php';

// =================================================================
// 1.0 PLUGIN ACTIVATION & DEACTIVATION
// =================================================================

/**
 * Runs on plugin activation.
 */
function ptt_activate() {
    // Register CPT and Taxonomies to make them available.
    ptt_register_post_type();
    ptt_register_taxonomies();

    // Ensure default Task Status terms exist
    $default_statuses = [ 'Not Started', 'In Progress', 'Completed', 'Paused' ];
    foreach ( $default_statuses as $status ) {
        if ( ! term_exists( $status, 'task_status' ) ) {
            wp_insert_term( $status, 'task_status' );
        }
    }

    // Create a baseline test post.
    if ( ! get_page_by_title( 'Test First Task', OBJECT, 'project_task' ) ) {
        $post_data = array(
            'post_title'   => 'Test First Task',
            'post_content' => 'This is a sample task created on plugin activation for testing purposes.',
            'post_status'  => 'publish',
            'post_author'  => get_current_user_id(),
            'post_type'    => 'project_task',
        );
        wp_insert_post( $post_data );
    }

    // Flush rewrite rules.
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ptt_activate' );

/**
 * Runs on plugin deactivation.
 */
function ptt_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ptt_deactivate' );


// =================================================================
// 2.0 DEPENDENCY CHECKS (ACF Pro)
// =================================================================

/**
 * Checks if ACF Pro is active and displays a notice if not.
 */
function ptt_check_dependencies() {
    if ( ! class_exists( 'ACF' ) || ! function_exists('acf_get_field_groups') ) {
        ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>Project & Task Time Tracker:</strong> This plugin requires Advanced Custom Fields (ACF) Pro to be installed and activated. Please install ACF Pro.</p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'ptt_check_dependencies' );


// =================================================================
// 3.0 CPT & TAXONOMY REGISTRATION
// =================================================================

/**
 * Registers the Custom Post Type for "Projects & Tasks".
 */
function ptt_register_post_type() {
    $labels = [
        'name'               => _x( 'Tasks', 'post type general name', 'ptt' ),
        'singular_name'      => _x( 'Task', 'post type singular name', 'ptt' ),
        'menu_name'          => _x( 'Tasks', 'admin menu', 'ptt' ),
        'name_admin_bar'     => _x( 'Task', 'add new on admin bar', 'ptt' ),
        'add_new'            => _x( 'Add New Task', 'book', 'ptt' ),
        'add_new_item'       => __( 'Add New Task', 'ptt' ),
        'new_item'           => __( 'New Task', 'ptt' ),
        'edit_item'          => __( 'Edit Task', 'ptt' ),
        'view_item'          => __( 'View Task', 'ptt' ),
        'all_items'          => __( 'All Tasks', 'ptt' ),
        'search_items'       => __( 'Search Tasks', 'ptt' ),
        'parent_item_colon'  => __( 'Parent Tasks:', 'ptt' ),
        'not_found'          => __( 'No tasks found.', 'ptt' ),
        'not_found_in_trash' => __( 'No tasks found in Trash.', 'ptt' ),
    ];

    $args = [
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => [ 'slug' => 'project_task' ],
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-clock',
        'supports'           => [ 'title', 'editor', 'author', 'revisions' ],
        'taxonomies'         => [ 'client', 'project', 'task_status', 'post_tag' ],
    ];

    register_post_type( 'project_task', $args );
}
add_action( 'init', 'ptt_register_post_type' );

/**
 * Adds the post ID to Task permalinks for uniqueness and easier referencing.
 *
 * @param string   $post_link The generated permalink.
 * @param WP_Post  $post      The current post object.
 * @return string  Modified permalink with post ID appended.
 */
function ptt_task_id_permalink( $post_link, $post ) {
    if ( 'project_task' === get_post_type( $post ) ) {
        return home_url( 'project_task/' . $post->post_name . '-' . $post->ID . '/' );
    }
    return $post_link;
}
add_filter( 'post_type_link', 'ptt_task_id_permalink', 10, 2 );

/**
 * Registers rewrite rule to parse the custom Task permalink structure.
 */
function ptt_task_id_rewrite() {
    add_rewrite_rule( '^project_task/[^/]+-([0-9]+)/?$', 'index.php?post_type=project_task&p=$matches[1]', 'top' );
}
add_action( 'init', 'ptt_task_id_rewrite' );

/**
 * Registers custom taxonomies "Clients" and "Projects".
 */
function ptt_register_taxonomies() {
    // Clients Taxonomy
    $client_labels = [
        'name'              => _x( 'Clients', 'taxonomy general name', 'ptt' ),
        'singular_name'     => _x( 'Client', 'taxonomy singular name', 'ptt' ),
        'search_items'      => __( 'Search Clients', 'ptt' ),
        'all_items'         => __( 'All Clients', 'ptt' ),
        'parent_item'       => __( 'Parent Client', 'ptt' ),
        'parent_item_colon' => __( 'Parent Client:', 'ptt' ),
        'edit_item'         => __( 'Edit Client', 'ptt' ),
        'update_item'       => __( 'Update Client', 'ptt' ),
        'add_new_item'      => __( 'Add New Client', 'ptt' ),
        'new_item_name'     => __( 'New Client Name', 'ptt' ),
        'menu_name'         => __( 'Clients', 'ptt' ),
    ];
    $client_args = [
        'hierarchical'      => true,
        'labels'            => $client_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'client' ],
        'show_in_menu'      => 'edit.php?post_type=project_task',
    ];
    register_taxonomy( 'client', [ 'project_task' ], $client_args );

    // Projects Taxonomy
    $project_labels = [
        'name'              => _x( 'Projects', 'taxonomy general name', 'ptt' ),
        'singular_name'     => _x( 'Project', 'taxonomy singular name', 'ptt' ),
        'search_items'      => __( 'Search Projects', 'ptt' ),
        'all_items'         => __( 'All Projects', 'ptt' ),
        'parent_item'       => __( 'Parent Project', 'ptt' ),
        'parent_item_colon' => __( 'Parent Project:', 'ptt' ),
        'edit_item'         => __( 'Edit Project', 'ptt' ),
        'update_item'       => __( 'Update Project', 'ptt' ),
        'add_new_item'      => __( 'Add New Project', 'ptt' ),
        'new_item_name'     => __( 'New Project Name', 'ptt' ),
        'menu_name'         => __( 'Projects', 'ptt' ),
    ];
    $project_args = [
        'hierarchical'      => true,
        'labels'            => $project_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'project' ],
        'show_in_menu'      => 'edit.php?post_type=project_task',
    ];
    register_taxonomy( 'project', [ 'project_task' ], $project_args );

    // Task Status Taxonomy
    $status_labels = [
        'name'              => _x( 'Status', 'taxonomy general name', 'ptt' ),
        'singular_name'     => _x( 'Status', 'taxonomy singular name', 'ptt' ),
        'search_items'      => __( 'Search Statuses', 'ptt' ),
        'all_items'         => __( 'All Statuses', 'ptt' ),
        'edit_item'         => __( 'Edit Status', 'ptt' ),
        'update_item'       => __( 'Update Status', 'ptt' ),
        'add_new_item'      => __( 'Add New Status', 'ptt' ),
        'new_item_name'     => __( 'New Status Name', 'ptt' ),
        'menu_name'         => __( 'Task Status', 'ptt' ),
    ];
    $status_args = [
        'hierarchical'      => true, // This makes it a single-choice UI (radio buttons)
        'labels'            => $status_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'task_status' ],
        'show_in_menu'      => 'edit.php?post_type=project_task',
        'default_term'      => [
            'name' => 'Not Started',
            'slug' => 'not-started',
        ],
    ];
    register_taxonomy( 'task_status', [ 'project_task' ], $status_args );
}
add_action( 'init', 'ptt_register_taxonomies' );


// =================================================================
// 4.0 ACF FIELD REGISTRATION
// =================================================================

/**
 * Registers the necessary ACF fields programmatically.
 */
function ptt_register_acf_fields() {
    if ( ! function_exists( 'acf_add_local_field_group' ) ) {
        return;
    }

    // Field Group for Tasks (Post Type)
    acf_add_local_field_group( array(
        'key' => 'group_ptt_task_fields',
        'title' => 'Task Details',
        'fields' => array(
            array(
                'key' => 'field_ptt_task_max_budget',
                'label' => 'Maximum Budget (Hours)',
                'name' => 'task_max_budget',
                'type' => 'number',
                'instructions' => 'Set a time budget for this specific task.',
                'step' => '0.01',
            ),
             array(
                'key' => 'field_ptt_task_deadline',
                'label' => 'Task Deadline',
                'name' => 'task_deadline',
                'type' => 'date_picker',
                'display_format' => 'F j, Y',
                'return_format' => 'Y-m-d',
            ),
            array(
                'key' => 'field_ptt_start_time',
                'label' => 'Start Time',
                'name' => 'start_time',
                'type' => 'date_time_picker',
                'instructions' => 'The date and time the task was started.',
                'display_format' => 'Y-m-d H:i:s',
                'return_format' => 'Y-m-d H:i:s',
            ),
            array(
                'key' => 'field_ptt_stop_time',
                'label' => 'Stop Time',
                'name' => 'stop_time',
                'type' => 'date_time_picker',
                'instructions' => 'The date and time the task was stopped.',
                'display_format' => 'Y-m-d H:i:s',
                'return_format' => 'Y-m-d H:i:s',
            ),
            array(
                'key' => 'field_ptt_calculated_duration',
                'label' => 'Calculated Duration (Hours)',
                'name' => 'calculated_duration',
                'type' => 'number',
                'instructions' => 'This value is calculated automatically. (e.g., 1.5 = 1 hour 30 mins)',
                'readonly' => 1,
                'step' => '0.01',
            ),
            array(
                'key' => 'field_ptt_manual_override',
                'label' => 'Manual Time Entry',
                'name' => 'manual_override',
                'type' => 'true_false',
                'instructions' => 'Check this to manually enter time instead of using the timer.',
                'ui' => 1,
                'ui_on_text' => 'Manual',
                'ui_off_text' => 'Timer',
            ),
            array(
                'key' => 'field_ptt_manual_duration',
                'label' => 'Manual Duration (Hours)',
                'name' => 'manual_duration',
                'type' => 'number',
                'instructions' => 'Enter the time spent in decimal hours (e.g., 1.5 = 1 hour 30 mins)',
                'step' => '0.01',
                'min' => '0',
                'conditional_logic' => array(
                    array(
                        array(
                            'field' => 'field_ptt_manual_override',
                            'operator' => '==',
                            'value' => '1',
                        ),
                    ),
                ),
            ),
            array(
                'key' => 'field_ptt_sessions',
                'label' => 'Sessions',
                'name' => 'sessions',
                'type' => 'repeater',
                'instructions' => 'Track multiple work sessions for this task.',
                'button_label' => 'Add Session',
                'sub_fields' => array(
                    array(
                        'key' => 'field_ptt_session_title',
                        'label' => 'Session Title',
                        'name' => 'session_title',
                        'type' => 'text',
                    ),
                    array(
                        'key' => 'field_ptt_session_notes',
                        'label' => 'Notes',
                        'name' => 'session_notes',
                        'type' => 'textarea',
                    ),
                    array(
                        'key' => 'field_ptt_session_start_time',
                        'label' => 'Start Time',
                        'name' => 'session_start_time',
                        'type' => 'date_time_picker',
                        'display_format' => 'Y-m-d H:i:s',
                        'return_format' => 'Y-m-d H:i:s',
                    ),
                    array(
                        'key' => 'field_ptt_session_stop_time',
                        'label' => 'Stop Time',
                        'name' => 'session_stop_time',
                        'type' => 'date_time_picker',
                        'display_format' => 'Y-m-d H:i:s',
                        'return_format' => 'Y-m-d H:i:s',
                    ),
                    array(
                        'key' => 'field_ptt_session_manual_override',
                        'label' => 'Manual Time Entry',
                        'name' => 'session_manual_override',
                        'type' => 'true_false',
                        'ui' => 1,
                        'ui_on_text' => 'Manual',
                        'ui_off_text' => 'Timer',
                    ),
                    array(
                        'key' => 'field_ptt_session_manual_duration',
                        'label' => 'Manual Duration (Hours)',
                        'name' => 'session_manual_duration',
                        'type' => 'number',
                        'step' => '0.01',
                        'min' => '0',
                        'conditional_logic' => array(
                            array(
                                array(
                                    'field' => 'field_ptt_session_manual_override',
                                    'operator' => '==',
                                    'value' => '1',
                                ),
                            ),
                        ),
                    ),
                    array(
                        'key' => 'field_ptt_session_calculated_duration',
                        'label' => 'Calculated Duration (Hours)',
                        'name' => 'session_calculated_duration',
                        'type' => 'number',
                        'readonly' => 1,
                        'step' => '0.01',
                    ),
                    array(
                        'key' => 'field_ptt_session_timer_controls',
                        'label' => 'Timer',
                        'name' => 'session_timer_controls',
                        'type' => 'message',
                        'wrapper' => array('class' => 'ptt-session-timer'),
                        'escape_html' => 0,
                    ),
                ),
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'post_type',
                    'operator' => '==',
                    'value' => 'project_task',
                ),
            ),
        ),
        'menu_order' => 0,
        'position' => 'normal',
        'style' => 'default',
        'label_placement' => 'top',
        'instruction_placement' => 'label',
    ) );

    // Field Group for Projects (Taxonomy)
    acf_add_local_field_group(array(
        'key' => 'group_ptt_project_fields',
        'title' => 'Project Details',
        'fields' => array(
            array(
                'key' => 'field_ptt_project_max_budget',
                'label' => 'Maximum Budget (Hours)',
                'name' => 'project_max_budget',
                'type' => 'number',
                'instructions' => 'Set a total time budget for the entire project.',
                'step' => '0.01',
            ),
            array(
                'key' => 'field_ptt_project_deadline',
                'label' => 'Project Deadline',
                'name' => 'project_deadline',
                'type' => 'date_picker',
                'display_format' => 'F j, Y',
                'return_format' => 'Y-m-d',
            ),
        ),
        'location' => array(
            array(
                array(
                    'param' => 'taxonomy',
                    'operator' => '==',
                    'value' => 'project',
                ),
            ),
        ),
    ));
}
add_action( 'acf/init', 'ptt_register_acf_fields' );

/**
 * Automatically timestamps manual sessions that don't have a start time.
 * Hooks into the ACF update process to modify the value before saving.
 *
 * @param array $value   The new repeater value.
 * @param int   $post_id The post ID.
 * @param array $field   The field object.
 * @return array The modified repeater value.
 */
function ptt_timestamp_manual_sessions( $value, $post_id, $field ) {
	if ( ! empty( $value ) && is_array( $value ) ) {
		$current_time = null; // Lazy-load time

		foreach ( $value as $i => &$row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			// Check for manual override - ACF can pass either field keys or field names
			$is_manual = ! empty( $row['field_ptt_session_manual_override'] )
					|| ! empty( $row['session_manual_override'] );

			// Check if start time already exists
			$has_start_time = ! empty( $row['field_ptt_session_start_time'] )
					|| ! empty( $row['session_start_time'] );

			if ( $is_manual && ! $has_start_time ) {
				if ( null === $current_time ) {
					$current_time = current_time( 'mysql', 1 ); // UTC
				}
				// Set both field keys and names to ensure compatibility
				$row['field_ptt_session_start_time'] = $current_time;
				$row['field_ptt_session_stop_time']  = $current_time;
				$row['session_start_time']           = $current_time;
				$row['session_stop_time']            = $current_time;
			}
		}
	}
	return $value;
}
add_filter( 'acf/update_value/key=field_ptt_sessions', 'ptt_timestamp_manual_sessions', 10, 3 );
// Also hook by field name to cover cases where field keys differ (e.g., ACF UI vs local JSON)
add_filter( 'acf/update_value/name=sessions', 'ptt_timestamp_manual_sessions', 10, 3 );



function ptt_activate_kanban_additions() {
    // Existing activation code...

    // Add rewrite rules for Kanban
    ptt_kanban_rewrite_rule();

    // Flush rewrite rules (this should already be in your activation)
    flush_rewrite_rules();
}


// =================================================================
// 5.0 ENQUEUE SCRIPTS & STYLES
// =================================================================

/**
 * Enqueues scripts and styles for admin and front-end.
 */
function ptt_enqueue_assets() {
    // Main CSS file (now in root)
    wp_enqueue_style( 'ptt-styles', PTT_PLUGIN_URL . 'styles.css', [], PTT_VERSION );

    $deps = [ 'jquery' ];
    if ( is_admin() ) {
        $deps[] = 'jquery-ui-dialog';
        wp_enqueue_style( 'wp-jquery-ui-dialog' );
    }

    // Main JS file (now in root)
    wp_enqueue_script( 'ptt-scripts', PTT_PLUGIN_URL . 'scripts.js', $deps, PTT_VERSION, true );

    // Localize script to pass data like nonces and AJAX URL
    wp_localize_script( 'ptt-scripts', 'ptt_ajax_object', [
        'ajax_url'              => admin_url( 'admin-ajax.php' ),
        'nonce'                 => wp_create_nonce( 'ptt_ajax_nonce' ),
        'confirm_stop'          => __('Are you sure you want to stop the timer?', 'ptt'),
        'concurrent_error'      => __('You have another task running. Please stop it before starting a new one.', 'ptt'),
        'edit_post_link'        => admin_url('post.php?action=edit&post='),
        'todays_date_formatted' => date_i18n( get_option( 'date_format' ), current_time( 'timestamp' ) ),
        'sync_authors_confirm'  => __( 'Are you sure you want to synchronize Authors to Assignee?', 'ptt' ),
        'sync_authors_title'    => __( 'Confirm Synchronization', 'ptt' ),
    ] );
}
// Enqueue for admin screens
add_action( 'admin_enqueue_scripts', 'ptt_enqueue_assets' );
// Enqueue for front-end (where the shortcode might be)
add_action( 'wp_enqueue_scripts', 'ptt_enqueue_assets' );


// =================================================================
// 6.0 CORE TIME CALCULATION LOGIC
// =================================================================

/**
 * Calculates duration between start and stop time and saves it to a custom field.
 *
 * @param int $post_id The ID of the post to calculate.
 * @return float The calculated duration in decimal hours.
 */
function ptt_calculate_and_save_duration( $post_id ) {
    $sessions = get_field( 'sessions', $post_id );
    $duration = 0.00;

    if ( ! empty( $sessions ) ) {
        $duration = ptt_get_total_sessions_duration( $post_id );
    } else {
        $manual_override = get_field( 'manual_override', $post_id );

        if ( $manual_override ) {
            $manual_duration = get_field( 'manual_duration', $post_id );
            $duration        = $manual_duration ? (float) $manual_duration : 0.00;
        } else {
            $start_time_str = get_field( 'start_time', $post_id );
            $stop_time_str  = get_field( 'stop_time', $post_id );

            if ( $start_time_str && $stop_time_str ) {
                try {
                    // Always use UTC for calculations to avoid timezone issues.
                    $start_time = new DateTime( $start_time_str, new DateTimeZone('UTC') );
                    $stop_time  = new DateTime( $stop_time_str, new DateTimeZone('UTC') );

                    if ( $stop_time > $start_time ) {
                        $diff_seconds   = $stop_time->getTimestamp() - $start_time->getTimestamp();
                        $duration_hours = $diff_seconds / 3600;
                        $duration       = ceil( $duration_hours * 100 ) / 100;
                    }
                } catch ( Exception $e ) {
                    $duration = 0.00;
                }
            }
        }
    }

    $formatted_duration = number_format( (float) $duration, 2, '.', '' );
    update_field( 'calculated_duration', $formatted_duration, $post_id );

    return $formatted_duration;
}

/**
 * Returns the index of any active session for a task.
 *
 * @param int $post_id The task ID.
 * @return int|false The active session index or false if none.
 */
function ptt_get_active_session_index( $post_id ) {
    $sessions = get_field( 'sessions', $post_id );
    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
        foreach ( $sessions as $index => $session ) {
            if ( ! empty( $session['session_start_time'] ) && empty( $session['session_stop_time'] ) ) {
                return $index;
            }
        }
    }
    return false;
}

/**
 * Calculates and saves duration for a specific session row.
 *
 * @param int $post_id The task ID.
 * @param int $index   Session index (0 based).
 * @return string      Formatted duration hours.
 */
function ptt_calculate_session_duration( $post_id, $index ) {
    $sessions = get_field( 'sessions', $post_id );
    if ( empty( $sessions ) || ! isset( $sessions[ $index ] ) ) {
        return '0.00';
    }

    $session = $sessions[ $index ];

    if ( ! empty( $session['session_manual_override'] ) ) {
        $duration = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.00;
    } else {
        $start_time_str = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
        $stop_time_str  = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
        $duration       = 0.00;

        if ( $start_time_str && $stop_time_str ) {
            try {
                // Always use UTC for calculations
                $start_time = new DateTime( $start_time_str, new DateTimeZone('UTC') );
                $stop_time  = new DateTime( $stop_time_str, new DateTimeZone('UTC') );

                if ( $stop_time > $start_time ) {
                    $diff_seconds   = $stop_time->getTimestamp() - $start_time->getTimestamp();
                    $duration_hours = $diff_seconds / 3600;
                    $duration       = ceil( $duration_hours * 100 ) / 100;
                }
            } catch ( Exception $e ) {
                $duration = 0.00;
            }
        }
    }

    $formatted = number_format( (float) $duration, 2, '.', '' );
    update_sub_field( array( 'sessions', $index + 1, 'session_calculated_duration' ), $formatted, $post_id );
    return $formatted;
}

/**
 * Calculates the total duration of all sessions for a task.
 *
 * @param int $post_id Task ID.
 * @return float Total hours rounded to two decimals.
 */
function ptt_get_total_sessions_duration( $post_id ) {
    $sessions = get_field( 'sessions', $post_id );
    $total    = 0.0;

    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
        foreach ( $sessions as $session ) {
            if ( ! empty( $session['session_manual_override'] ) ) {
                $dur = isset( $session['session_manual_duration'] ) ? floatval( $session['session_manual_duration'] ) : 0.0;
            } else {
                $start = isset( $session['session_start_time'] ) ? $session['session_start_time'] : '';
                $stop  = isset( $session['session_stop_time'] ) ? $session['session_stop_time'] : '';
                $dur   = 0.0;

                if ( $start && $stop ) {
                    try {
                        $start_time = new DateTime( $start, new DateTimeZone('UTC') );
                        $stop_time  = new DateTime( $stop, new DateTimeZone('UTC') );
                        if ( $stop_time > $start_time ) {
                            $diff_seconds = $stop_time->getTimestamp() - $start_time->getTimestamp();
                            $dur          = $diff_seconds / 3600;
                        }
                    } catch ( Exception $e ) {
                        $dur = 0.0;
                    }
                }
            }

            $total += $dur;
        }
    }

    $total = ceil( $total * 100 ) / 100;
    return $total;
}


/**
 * Ensure manual sessions without a start time get start/stop timestamps set to now (UTC).
 * Safe to call multiple times; only fills missing start times.
 *
 * @param int $post_id
 */
function ptt_ensure_manual_session_timestamps( $post_id ) {
    if ( get_post_type( $post_id ) !== 'project_task' ) {
        return;
    }

    $sessions = get_field( 'sessions', $post_id );
    if ( empty( $sessions ) || ! is_array( $sessions ) ) {
        return;
    }

    $now = null; // lazy load
    $did_update = false;

    foreach ( $sessions as $i => $session ) {
        $is_manual = ! empty( $session['session_manual_override'] );
        $has_start = ! empty( $session['session_start_time'] );

        if ( $is_manual && ! $has_start ) {
            if ( null === $now ) {
                $now = current_time( 'mysql', 1 ); // UTC
            }
            // Update via keys (best effort)
            $row_index = $i + 1;
            update_sub_field( array( 'field_ptt_sessions', $row_index, 'field_ptt_session_start_time' ), $now, $post_id );
            update_sub_field( array( 'field_ptt_sessions', $row_index, 'field_ptt_session_stop_time' ),  $now, $post_id );
            // Update in-memory (names) so we can persist via update_field as a fallback
            $sessions[ $i ]['session_start_time'] = $now;
            $sessions[ $i ]['session_stop_time']  = $now;
            $did_update = true;
        }
    }

    // Fallback/persistence: write the modified structure back to ACF
    if ( $did_update ) {
        update_field( 'sessions', $sessions, $post_id );
    }
}

/**
 * Recalculates duration whenever a task post is saved.
 * Hooks into ACF's save_post action for reliability.
 *
 * @param int $post_id The post ID.
 */
function ptt_recalculate_on_save( $post_id ) {
    if ( get_post_type( $post_id ) === 'project_task' ) {
        // Safety net: ensure manual sessions get timestamps when saving in admin
        ptt_ensure_manual_session_timestamps( $post_id );

        $sessions = get_field( 'sessions', $post_id );
        if ( ! empty( $sessions ) ) {
            foreach ( array_keys( $sessions ) as $index ) {
                ptt_calculate_session_duration( $post_id, $index );
            }
        }
        ptt_calculate_and_save_duration( $post_id );
    }
}
add_action( 'acf/save_post', 'ptt_recalculate_on_save', 20 );


// =================================================================
// 7.0 ADMIN UI (CPT EDITOR)
// =================================================================

/**
 * Adds a custom "Assignee" meta box on the Task editor.
 */
function ptt_add_assignee_meta_box_setup() {
    add_meta_box(
        'ptt-assignee',
        __( 'Assignee', 'ptt' ),
        'ptt_render_assignee_meta_box',
        'project_task',
        'side',
        'default'
    );
}
add_action( 'add_meta_boxes_project_task', 'ptt_add_assignee_meta_box_setup' );

/**
 * Renders the Assignee meta box content.
 *
 * @param WP_Post $post Current post object.
 */
function ptt_render_assignee_meta_box( $post ) {
    $assignee = (int) get_post_meta( $post->ID, 'ptt_assignee', true );
    wp_nonce_field( 'ptt_save_assignee_nonce', 'ptt_assignee_nonce' );

    echo '<p>';
    echo '<label for="ptt_assignee">' . esc_html__( 'Assignee', 'ptt' ) . '</label><br />';
    wp_dropdown_users(
        [
            'name'             => 'ptt_assignee',
            'id'               => 'ptt_assignee',
            'capability'       => 'publish_posts',
            'selected'         => $assignee,
            'show_option_none' => __( 'No Assignee', 'ptt' ),
        ]
    );
    echo '</p>';
}

/**
 * Saves the Assignee custom field.
 *
 * @param int $post_id Post ID.
 */
function ptt_save_assignee_meta( $post_id ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! isset( $_POST['ptt_assignee_nonce'] ) || ! wp_verify_nonce( $_POST['ptt_assignee_nonce'], 'ptt_save_assignee_nonce' ) ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    // Handle saving the assignee
    $assignee = isset( $_POST['ptt_assignee'] ) ? absint( $_POST['ptt_assignee'] ) : 0;
    update_post_meta( $post_id, 'ptt_assignee', $assignee );
}
add_action( 'save_post_project_task', 'ptt_save_assignee_meta' );


/**
 * Adds Assignee column to the Tasks list table.
 *
 * @param array $columns Existing columns.
 * @return array Modified columns.
 */
function ptt_add_assignee_column( $columns ) {
    $new = [];
    foreach ( $columns as $key => $label ) {
        $new[ $key ] = $label;
        if ( 'author' === $key ) {
            $new['ptt_assignee'] = __( 'Assignee', 'ptt' );
        }
    }
    return $new;
}
add_filter( 'manage_project_task_posts_columns', 'ptt_add_assignee_column' );

/**
 * Renders the Assignee column content.
 *
 * @param string $column  Column name.
 * @param int    $post_id Post ID.
 */
function ptt_render_assignee_column( $column, $post_id ) {
    if ( 'ptt_assignee' === $column ) {
        $assignee_id = (int) get_post_meta( $post_id, 'ptt_assignee', true );
        $name        = $assignee_id ? get_the_author_meta( 'display_name', $assignee_id ) : __( 'No Assignee', 'ptt' );
        echo esc_html( $name );
    }
}
add_action( 'manage_project_task_posts_custom_column', 'ptt_render_assignee_column', 10, 2 );


// =================================================================
// 7.5 USER PROFILE INTEGRATION
// =================================================================

/**
 * Displays custom user profile fields for Slack integration.
 *
 * @param WP_User $user The current user object.
 */
function ptt_add_user_profile_fields( $user ) {
    ?>
    <h3>KISS PTT - Sleuth Integration</h3>
    <table class="form-table">
        <tr>
            <th><label for="slack_username">Slack Username</label></th>
            <td>
                <input type="text" name="slack_username" id="slack_username" value="<?php echo esc_attr( get_the_author_meta( 'slack_username', $user->ID ) ); ?>" class="regular-text" /><br />
                <span class="description">Enter the user's Slack username (e.g., noelsaw).</span>
            </td>
        </tr>
        <tr>
            <th><label for="slack_user_id">Slack User ID</label></th>
            <td>
                <input type="text" name="slack_user_id" id="slack_user_id" value="<?php echo esc_attr( get_the_author_meta( 'slack_user_id', $user->ID ) ); ?>" class="regular-text" /><br />
                <span class="description">Enter the user's Slack User ID (e.g., U1234567890).</span>
            </td>
        </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'ptt_add_user_profile_fields' );
add_action( 'edit_user_profile', 'ptt_add_user_profile_fields' );


/**
 * Saves the custom user profile fields.
 *
 * @param int $user_id The ID of the user being saved.
 * @return bool
 */
function ptt_save_user_profile_fields( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    if ( isset( $_POST['slack_username'] ) ) {
        update_user_meta( $user_id, 'slack_username', sanitize_text_field( $_POST['slack_username'] ) );
    }

    if ( isset( $_POST['slack_user_id'] ) ) {
        update_user_meta( $user_id, 'slack_user_id', sanitize_text_field( $_POST['slack_user_id'] ) );
    }
    return true;
}
add_action( 'personal_options_update', 'ptt_save_user_profile_fields' );
add_action( 'edit_user_profile_update', 'ptt_save_user_profile_fields' );


// =================================================================
// 8.0 AJAX HANDLERS
// =================================================================

/**
 * Checks if a user has any task that is currently running (has start time but no stop time).
 *
 * @param int $user_id The user ID to check.
 * @param int $exclude_post_id A post ID to exclude from the check (optional).
 * @return int The ID of the active post, or 0 if none.
 */
function ptt_has_active_task( $user_id, $exclude_post_id = 0 ) {
    $args = [
        'post_type'      => 'project_task',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'fields'         => 'ids', // Only get post IDs
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'start_time',
                'compare' => 'EXISTS',
            ],
            [
                'key'     => 'start_time',
                'value'   => '',
                'compare' => '!=',
            ],
            [
                'key'     => 'stop_time',
                'compare' => 'NOT EXISTS',
            ],
             [
                'key'     => 'stop_time',
                'value'   => '',
                'compare' => '=',
            ]
        ],
    ];

    if ($exclude_post_id) {
        $args['post__not_in'] = [$exclude_post_id];
    }

    $query = new WP_Query( $args );

    if ( $query->have_posts() ) {
        return $query->posts[0];
    }

    return 0;
}


/**
 * AJAX handler to start the timer. This is used by both Admin and Frontend.
 */
function ptt_start_timer_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Invalid Post ID.' ] );
    }

    // Check for concurrent tasks
    $user_id = get_current_user_id();
    $active_task_id = ptt_has_active_task( $user_id, $post_id );
    if ( $active_task_id > 0 ) {
        wp_send_json_error( [
            'message' => 'You have another task running. Please stop it before starting a new one.',
            'active_task_id' => $active_task_id
        ] );
    }

    $current_time = current_time( 'mysql', 1 ); // Use UTC time
    update_field( 'start_time', $current_time, $post_id );
    update_field( 'stop_time', '', $post_id ); // Clear any previous stop time
    update_field( 'calculated_duration', '0.00', $post_id ); // Reset duration

    wp_update_post( ['ID' => $post_id, 'post_author' => $user_id] );

    $status_terms = get_the_terms( $post_id, 'task_status' );
    $status_name  = ! is_wp_error( $status_terms ) && $status_terms ? $status_terms[0]->name : '';

    wp_send_json_success( [
        'message'     => 'Timer started!',
        'start_time'  => $current_time,
        'post_id'     => $post_id,
        'task_status' => $status_name,
    ] );
}
add_action( 'wp_ajax_ptt_start_timer', 'ptt_start_timer_callback' );

/**
 * AJAX handler to get info about the currently active task.
 */
function ptt_get_active_task_info_callback() {
    check_ajax_referer('ptt_ajax_nonce', 'nonce');

    if ( !is_user_logged_in() || !current_user_can('edit_posts') ) {
        wp_send_json_error(['message' => 'Permission denied.']);
    }

    $user_id = get_current_user_id();
    $active_task_id = ptt_has_active_task($user_id);

    if ( !$active_task_id ) {
        wp_send_json_error(['message' => 'No active task found.']);
    }

    $start_time    = get_field( 'start_time', $active_task_id );
    $status_terms  = get_the_terms( $active_task_id, 'task_status' );
    $status_name   = ! is_wp_error( $status_terms ) && $status_terms ? $status_terms[0]->name : '';

    wp_send_json_success([
        'post_id'     => $active_task_id,
        'task_name'   => get_the_title( $active_task_id ),
        'start_time'  => $start_time,
        'task_status' => $status_name,
    ]);
}
add_action('wp_ajax_ptt_get_active_task_info', 'ptt_get_active_task_info_callback');


/**
 * AJAX handler to stop the timer.
 */
function ptt_stop_timer_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Invalid Post ID.' ] );
    }

    $current_time = current_time( 'mysql', 1 ); // Use UTC time
    update_field( 'stop_time', $current_time, $post_id );

    $duration = ptt_calculate_and_save_duration( $post_id );

    wp_send_json_success( [
        'message' => 'Timer stopped! Duration: ' . $duration . ' hours.',
        'stop_time' => $current_time,
        'duration' => $duration
    ] );
}
add_action( 'wp_ajax_ptt_stop_timer', 'ptt_stop_timer_callback' );

/**
 * AJAX handler to force-stop a timer (admin/recovery function).
 */
function ptt_force_stop_timer_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Invalid Post ID.' ] );
    }

    $current_time = current_time( 'mysql', 1 ); // Use UTC time
    update_field( 'stop_time', $current_time, $post_id );

    $duration = ptt_calculate_and_save_duration( $post_id );

    wp_send_json_success( [
        'message' => 'Timer force-stopped! Duration: ' . $duration . ' hours.',
        'stop_time' => $current_time,
        'duration' => $duration,
        'forced' => true
    ] );
}
add_action( 'wp_ajax_ptt_force_stop_timer', 'ptt_force_stop_timer_callback' );

/**
 * AJAX handler for manual time entry.
 */
function ptt_save_manual_time_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $manual_hours = isset( $_POST['manual_hours'] ) ? floatval( $_POST['manual_hours'] ) : 0;

    if ( ! $post_id || $manual_hours <= 0 ) {
         wp_send_json_error( [ 'message' => 'Invalid data.' ] );
    }

    update_field( 'manual_override', true, $post_id );
    update_field( 'manual_duration', $manual_hours, $post_id );

    $start_time = get_field( 'start_time', $post_id );
    if ( ! $start_time ) {
        $start_time = current_time( 'mysql', 1 ); // Use UTC time
        update_field( 'start_time', $start_time, $post_id );
    }

    $duration = ptt_calculate_and_save_duration( $post_id );

    wp_send_json_success( [
        'message' => 'Manual time saved! Duration: ' . $duration . ' hours.',
        'duration' => $duration
    ] );
}
add_action( 'wp_ajax_ptt_save_manual_time', 'ptt_save_manual_time_callback' );

/**
 * Disable parent-level timer AJAX handlers.
 * These are hidden from the UI but we disable the handlers for security.
 */
function ptt_disable_parent_level_timer_handlers() {
    // Remove parent-level timer actions
    remove_action( 'wp_ajax_ptt_start_timer', 'ptt_start_timer_callback' );
    remove_action( 'wp_ajax_ptt_stop_timer', 'ptt_stop_timer_callback' );
    remove_action( 'wp_ajax_ptt_save_manual_time', 'ptt_save_manual_time_callback' );
    remove_action( 'wp_ajax_ptt_force_stop_timer', 'ptt_force_stop_timer_callback' );
}
// Uncomment the line below to disable parent-level timer handlers
// add_action( 'init', 'ptt_disable_parent_level_timer_handlers', 20 );

/**
 * AJAX handler to start a session timer.
 */
function ptt_start_session_timer_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $row_index = isset( $_POST['row_index'] ) ? intval( $_POST['row_index'] ) : -1;
    if ( ! $post_id || $row_index < 0 ) {
        wp_send_json_error( [ 'message' => 'Invalid data.' ] );
    }

    $active = ptt_get_active_session_index( $post_id );
    if ( $active !== false && $active !== $row_index ) {
        ptt_stop_session( $post_id, $active );
    }

    $current_time = current_time( 'mysql', 1 ); // Use UTC time
    update_sub_field( array( 'sessions', $row_index + 1, 'session_start_time' ), $current_time, $post_id );
    update_sub_field( array( 'sessions', $row_index + 1, 'session_stop_time' ), '', $post_id );
    update_sub_field( array( 'sessions', $row_index + 1, 'session_calculated_duration' ), '0.00', $post_id );

    wp_send_json_success( [ 'message' => 'Session started!', 'start_time' => $current_time ] );
}
add_action( 'wp_ajax_ptt_start_session_timer', 'ptt_start_session_timer_callback' );

/**
 * Stops a session timer and calculates duration.
 */
function ptt_stop_session( $post_id, $index ) {
    $current_time = current_time( 'mysql', 1 ); // Use UTC time
    update_sub_field( array( 'sessions', $index + 1, 'session_stop_time' ), $current_time, $post_id );
    $duration = ptt_calculate_session_duration( $post_id, $index );
    ptt_calculate_and_save_duration( $post_id );
    return $duration;
}

/**
 * AJAX handler to stop a session timer.
 */
function ptt_stop_session_timer_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $row_index = isset( $_POST['row_index'] ) ? intval( $_POST['row_index'] ) : -1;
     if ( ! $post_id || $row_index < 0 ) {
        wp_send_json_error( [ 'message' => 'Invalid data.' ] );
    }

    $duration = ptt_stop_session( $post_id, $row_index );

    wp_send_json_success( [
        'message'   => 'Session stopped! Duration: ' . $duration . ' hours.',
        'stop_time' => current_time( 'mysql', 1 ),
        'duration'  => $duration,
    ] );
}
add_action( 'wp_ajax_ptt_stop_session_timer', 'ptt_stop_session_timer_callback' );


/**
 * AJAX handler to update a task's status from the reports page.
 */
function ptt_update_task_status_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $post_id   = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
    $status_id = isset( $_POST['status_id'] ) ? intval( $_POST['status_id'] ) : 0;

    if ( ! $post_id || ! $status_id ) {
        wp_send_json_error( [ 'message' => 'Invalid data provided.' ] );
    }

    $result = wp_set_object_terms( $post_id, $status_id, 'task_status', false );

    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => 'Failed to update status.' ] );
    }

    wp_send_json_success( [ 'message' => 'Status updated successfully.' ] );
}
add_action( 'wp_ajax_ptt_update_task_status', 'ptt_update_task_status_callback' );


/**
 * Adds a "Settings" link to the plugin's action links on the plugins page.
 *
 * @param array $links An array of plugin action links.
 * @return array An array of plugin action links.
 */
function ptt_add_settings_link( $links ) {
    $settings_link = '<a href="' . admin_url( 'edit.php?post_type=project_task&page=ptt-self-test' ) . '">Settings</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'ptt_add_settings_link' );

