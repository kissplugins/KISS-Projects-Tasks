<?php
/**
 * Plugin Name:       KISS - Project & Task Time Tracker
 * Plugin URI:        https://kissplugins.com
 * Description:       A robust system for WordPress users to track time spent on client projects and individual tasks. Requires ACF Pro.
 * Version:           1.6.6
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

define( 'PTT_VERSION', '1.6.6' );
define( 'PTT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PTT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * =================================================================
 * TABLE OF CONTENTS
 * =================================================================
 *
 * 1.0  PLUGIN ACTIVATION & DEACTIVATION
 * 2.0  DEPENDENCY CHECKS (ACF Pro)
 * 3.0  CPT & TAXONOMY REGISTRATION
 * 4.0  ACF FIELD REGISTRATION
 * 5.0  ENQUEUE SCRIPTS & STYLES
 * 6.0  CORE TIME CALCULATION LOGIC
 * 7.0  ADMIN UI (CPT EDITOR)
 * 8.0  AJAX HANDLERS
 * 9.0  FRONT-END SHORTCODE [task-enter] (see shortcodes.php)
 * 10.0 ADMIN PAGES & LINKS (see reports.php)
 * 11.0 SELF-TEST MODULE (see self-test.php)
 *
 * =================================================================
 */

// Load plugin modules
require_once PTT_PLUGIN_DIR . 'shortcodes.php';
require_once PTT_PLUGIN_DIR . 'self-test.php';
require_once PTT_PLUGIN_DIR . 'reports.php';

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
        'taxonomies'         => [ 'client', 'project', 'post_tag' ],
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
    ];
    register_taxonomy( 'project', [ 'project_task' ], $project_args );
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
                'step' => '0.1',
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
                'step' => '0.1',
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


// =================================================================
// 5.0 ENQUEUE SCRIPTS & STYLES
// =================================================================

/**
 * Enqueues scripts and styles for admin and front-end.
 */
function ptt_enqueue_assets() {
    // Main CSS file (now in root)
    wp_enqueue_style( 'ptt-styles', PTT_PLUGIN_URL . 'styles.css', [], PTT_VERSION );

    // Main JS file (now in root)
    wp_enqueue_script( 'ptt-scripts', PTT_PLUGIN_URL . 'scripts.js', [ 'jquery' ], PTT_VERSION, true );
    
    // Localize script to pass data like nonces and AJAX URL
    wp_localize_script( 'ptt-scripts', 'ptt_ajax_object', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ptt_ajax_nonce' ),
        'confirm_stop' => __('Are you sure you want to stop the timer?', 'ptt'),
        'concurrent_error' => __('You have another task running. Please stop it before starting a new one.', 'ptt'),
        'edit_post_link' => admin_url('post.php?action=edit&post='), // Add this line
    ]);
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
    // Check if manual override is enabled
    $manual_override = get_field( 'manual_override', $post_id );
    
    if ( $manual_override ) {
        // Use manual duration if override is enabled
        $manual_duration = get_field( 'manual_duration', $post_id );
        $duration = $manual_duration ? (float) $manual_duration : 0.00;
    } else {
        // Calculate duration from start/stop times
        $start_time_str = get_field( 'start_time', $post_id );
        $stop_time_str  = get_field( 'stop_time', $post_id );
        $duration       = 0.00;

        if ( $start_time_str && $stop_time_str ) {
            try {
                $timezone   = wp_timezone();
                $start_time = new DateTime( $start_time_str, $timezone );
                $stop_time  = new DateTime( $stop_time_str, $timezone );

                if ( $stop_time > $start_time ) {
                    $diff_seconds = $stop_time->getTimestamp() - $start_time->getTimestamp();
                    $duration_hours = $diff_seconds / 3600;
                    // Round up to two decimal places
                    $duration = ceil( $duration_hours * 100 ) / 100;
                }
            } catch ( Exception $e ) {
                // Handle potential DateTime errors silently
                $duration = 0.00;
            }
        }
    }
    
    // Ensure format is always two decimal places
    $formatted_duration = number_format( (float) $duration, 2, '.', '' );
    
    // Update the custom field
    update_field( 'calculated_duration', $formatted_duration, $post_id );

    return $formatted_duration;
}

/**
 * Recalculates duration whenever a task post is saved.
 * Hooks into ACF's save_post action for reliability.
 *
 * @param int $post_id The post ID.
 */
function ptt_recalculate_on_save( $post_id ) {
    if ( get_post_type( $post_id ) === 'project_task' ) {
        ptt_calculate_and_save_duration( $post_id );
    }
}
add_action( 'acf/save_post', 'ptt_recalculate_on_save', 20 );


// =================================================================
// 7.0 ADMIN UI (CPT EDITOR)
// =================================================================

/**
 * Returns the timer controls HTML for a given task ID.
 *
 * @param int $post_id The task ID.
 * @return string HTML markup for timer controls.
 */
function ptt_get_timer_controls_html( $post_id ) {
    $start_time = get_field( 'start_time', $post_id );
    $stop_time  = get_field( 'stop_time', $post_id );

    ob_start();
    echo '<div id="ptt-timer-controls" class="misc-pub-section" data-postid="' . esc_attr( $post_id ) . '">';

    if ( ! $start_time ) {
        echo '<button type="button" id="ptt-start-timer" class="button button-primary button-large ptt-start-button">Start Timer</button>';
    }

    if ( $start_time && ! $stop_time ) {
        echo '<button type="button" id="ptt-stop-timer" class="button button-large ptt-stop-button">Stop Timer</button>';
    }

    echo '<button type="button" id="ptt-manual-entry-toggle" class="button button-small" style="margin-top: 8px;">Manual Entry</button>';

    echo '<div id="ptt-manual-entry-form" style="display: none; margin-top: 10px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 3px;">';
    echo '<label for="ptt-manual-hours" style="display: block; margin-bottom: 5px;">Enter time in decimal hours:</label>';
    echo '<input type="number" id="ptt-manual-hours" min="0" step="0.01" placeholder="e.g., 1.5" style="width: 100%; margin-bottom: 8px;">';
    echo '<div style="font-size: 12px; color: #666; margin-bottom: 8px;">Examples: 1.5 = 1h 30m, 0.25 = 15m, 2.75 = 2h 45m</div>';
    echo '<button type="button" id="ptt-save-manual-time" class="button button-primary">Save Manual Time</button>';
    echo '<button type="button" id="ptt-cancel-manual-time" class="button" style="margin-left: 5px;">Cancel</button>';
    echo '</div>';

    echo '<div class="ptt-ajax-spinner"></div>';
    echo '<div class="ptt-ajax-message"></div>';
    echo '</div>';

    return ob_get_clean();
}

/**
 * Forces the plugin's template for single Task views.
 *
 * @param string $template Path to the template.
 * @return string Modified template path when viewing a Task.
 */
function ptt_task_single_template( $template ) {
    if ( is_singular( 'project_task' ) ) {
        $custom = PTT_PLUGIN_DIR . 'templates/single-project_task.php';
        if ( file_exists( $custom ) ) {
            return $custom;
        }
    }
    return $template;
}
add_filter( 'template_include', 'ptt_task_single_template' );

/**
 * Adds Start/Stop buttons to the 'Publish' meta box on the task editor screen.
 */
function ptt_add_start_stop_buttons() {
    global $post;
    if ( get_post_type( $post ) !== 'project_task' ) {
        return;
    }

    $post_id = $post->ID;
    echo ptt_get_timer_controls_html( $post_id );
}
add_action( 'post_submitbox_misc_actions', 'ptt_add_start_stop_buttons' );


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

    $current_time = current_time( 'Y-m-d H:i:s' );
    update_field( 'start_time', $current_time, $post_id );
    update_field( 'stop_time', '', $post_id ); // Clear any previous stop time
    update_field( 'calculated_duration', '0.00', $post_id ); // Reset duration
    
    // Associate the current user as the author if they aren't already
    // This is useful if an admin creates a a task and a developer starts it
    wp_update_post( ['ID' => $post_id, 'post_author' => $user_id] );

    wp_send_json_success( [ 'message' => 'Timer started!', 'start_time' => $current_time, 'post_id' => $post_id ] );
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

    $start_time = get_field('start_time', $active_task_id);

    wp_send_json_success([
        'post_id'    => $active_task_id,
        'task_name'  => get_the_title($active_task_id),
        'start_time' => $start_time,
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

    // Verify the post exists and is a project_task
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'project_task' ) {
        wp_send_json_error( [ 'message' => 'Task not found.' ] );
    }

    // Check if the task is actually running
    $start_time = get_field( 'start_time', $post_id );
    $stop_time = get_field( 'stop_time', $post_id );
    
    if ( ! $start_time ) {
        wp_send_json_error( [ 'message' => 'This task has not been started.' ] );
    }
    
    if ( $stop_time ) {
        wp_send_json_error( [ 'message' => 'This task has already been stopped.' ] );
    }

    // Verify the current user is the one who started the task (or is an admin)
    $user_id = get_current_user_id();
    if ( $post->post_author != $user_id && ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'You can only stop tasks that you started.' ] );
    }

    $current_time = current_time( 'Y-m-d H:i:s' );
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

    // Get the post
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'project_task' ) {
        wp_send_json_error( [ 'message' => 'Task not found.' ] );
    }

    // For force stop, we just need to ensure there's a start time
    $start_time = get_field( 'start_time', $post_id );
    if ( ! $start_time ) {
        wp_send_json_error( [ 'message' => 'Cannot stop a task that was never started.' ] );
    }

    // Force stop the timer
    $current_time = current_time( 'Y-m-d H:i:s' );
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

    if ( ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Invalid Post ID.' ] );
    }

    if ( $manual_hours < 0 ) {
        wp_send_json_error( [ 'message' => 'Duration cannot be negative.' ] );
    }

    if ( $manual_hours > 24 ) {
        wp_send_json_error( [ 'message' => 'Duration cannot exceed 24 hours for a single entry.' ] );
    }

    // Get the post
    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'project_task' ) {
        wp_send_json_error( [ 'message' => 'Task not found.' ] );
    }

    // Set manual override and duration
    update_field( 'manual_override', true, $post_id );
    update_field( 'manual_duration', $manual_hours, $post_id );

    $start_time = get_field( 'start_time', $post_id );
    $stop_time  = get_field( 'stop_time', $post_id );

    // If no start time exists, use current time
    if ( ! $start_time ) {
        $start_time = current_time( 'Y-m-d H:i:s' );
        update_field( 'start_time', $start_time, $post_id );
    }

    // Ensure a stop time is recorded so the task appears in reports
    if ( ! $stop_time ) {
        try {
            $timezone = wp_timezone();
            $start_dt = new DateTime( $start_time, $timezone );
            $seconds  = $manual_hours * 3600;
            $start_dt->modify( '+' . $seconds . ' seconds' );
            $stop_time = $start_dt->format( 'Y-m-d H:i:s' );
        } catch ( Exception $e ) {
            $stop_time = $start_time;
        }
        update_field( 'stop_time', $stop_time, $post_id );
    }
    
    // Calculate and save duration
    $duration = ptt_calculate_and_save_duration( $post_id );

    wp_send_json_success( [
        'message' => 'Manual time saved! Duration: ' . $duration . ' hours.',
        'duration' => $duration
    ] );
}
add_action( 'wp_ajax_ptt_save_manual_time', 'ptt_save_manual_time_callback' );

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