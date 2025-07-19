<?php
/**
 * Plugin Name:       KISS - Project & Task Time Tracker
 * Plugin URI:        https://kissplugins.com
 * Description:       A robust system for WordPress users to track time spent on client projects and individual tasks. Requires ACF Pro.
 * Version:           1.3.3
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

define( 'PTT_VERSION', '1.3.3' );
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
 * 9.0  FRONT-END SHORTCODE [task-enter]
 * 10.0 ADMIN PAGES & LINKS
 * 11.0 SELF-TEST MODULE
 *
 * =================================================================
 */

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
        'concurrent_error' => __('You have another task running. Please stop it before starting a new one.', 'ptt')
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
 * Adds Start/Stop buttons to the 'Publish' meta box on the task editor screen.
 */
function ptt_add_start_stop_buttons() {
    global $post;
    if ( get_post_type( $post ) !== 'project_task' ) {
        return;
    }

    $post_id    = $post->ID;
    $start_time = get_field( 'start_time', $post_id );
    $stop_time  = get_field( 'stop_time', $post_id );

    // Button container
    echo '<div id="ptt-timer-controls" class="misc-pub-section" data-postid="' . esc_attr( $post_id ) . '">';

    // Show START button if not started
    if ( ! $start_time ) {
         echo '<button type="button" id="ptt-start-timer" class="button button-primary button-large ptt-start-button">Start Timer</button>';
    }

    // Show STOP button if started but not stopped
    if ( $start_time && ! $stop_time ) {
        echo '<button type="button" id="ptt-stop-timer" class="button button-large ptt-stop-button">Stop Timer</button>';
    }

    echo '<div class="ptt-ajax-spinner"></div>';
    echo '<div class="ptt-ajax-message"></div>';
    echo '</div>';
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
 * @return bool True if an active task exists, false otherwise.
 */
function ptt_has_active_task( $user_id, $exclude_post_id = 0 ) {
    $args = [
        'post_type'      => 'project_task',
        'author'         => $user_id,
        'posts_per_page' => 1,
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
        ],
    ];

    if ($exclude_post_id) {
        $args['post__not_in'] = [$exclude_post_id];
    }

    $query = new WP_Query( $args );
    return $query->have_posts();
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
    if ( ptt_has_active_task( $user_id, $post_id ) ) {
        wp_send_json_error( [ 'message' => 'You have another task running. Please stop it before starting a new one.' ] );
    }

    $current_time = current_time( 'Y-m-d H:i:s' );
    update_field( 'start_time', $current_time, $post_id );
    update_field( 'stop_time', '', $post_id ); // Clear any previous stop time
    update_field( 'calculated_duration', '0.00', $post_id ); // Reset duration
    
    // Associate the current user as the author if they aren't already
    // This is useful if an admin creates a task and a developer starts it
    wp_update_post( ['ID' => $post_id, 'post_author' => $user_id] );

    wp_send_json_success( [ 'message' => 'Timer started!', 'start_time' => $current_time ] );
}
add_action( 'wp_ajax_ptt_start_timer', 'ptt_start_timer_callback' );

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
 * AJAX handler for creating a NEW task from the front-end shortcode.
 */
function ptt_frontend_start_task_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );

    if ( ! is_user_logged_in() || ! current_user_can('edit_posts') ) {
        wp_send_json_error(['message' => 'You do not have permission to perform this action.']);
    }

    $user_id = get_current_user_id();

    // Check for concurrent tasks
    if ( ptt_has_active_task( $user_id ) ) {
        wp_send_json_error( [ 'message' => 'You have another task running. Please stop it before starting a new one.' ] );
    }

    // Sanitize inputs
    $task_name = isset($_POST['task_name']) ? sanitize_text_field($_POST['task_name']) : '';
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : '';
    $client_id = isset($_POST['client']) ? intval($_POST['client']) : 0;
    $project_id = isset($_POST['project']) ? intval($_POST['project']) : 0;
    
    if (empty($task_name) || empty($client_id) || empty($project_id)) {
        wp_send_json_error(['message' => 'Please select a Client, a Project, and enter a Task Name.']);
    }

    $post_data = [
        'post_title'   => $task_name,
        'post_content' => $notes,
        'post_status'  => 'publish',
        'post_author'  => $user_id,
        'post_type'    => 'project_task',
    ];

    $post_id = wp_insert_post($post_data);

    if (is_wp_error($post_id)) {
        wp_send_json_error(['message' => 'Failed to create task.']);
    }

    // Set taxonomies
    wp_set_object_terms($post_id, $client_id, 'client', false);
    wp_set_object_terms($post_id, $project_id, 'project', false);

    // Start timer
    $current_time = current_time('Y-m-d H:i:s');
    update_field('start_time', $current_time, $post_id);
    update_field('calculated_duration', '0.00', $post_id);

    wp_send_json_success([
        'message' => 'New task created and timer started!',
        'post_id' => $post_id,
        'task_name' => get_the_title($post_id),
    ]);
}
add_action('wp_ajax_ptt_frontend_start_task', 'ptt_frontend_start_task_callback');

/**
 * AJAX handler to get available tasks for a project.
 */
function ptt_get_tasks_for_project_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    $project_id = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
    
    if (!$project_id || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Invalid project or permissions.']);
    }

    $args = [
        'post_type' => 'project_task',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
        'tax_query' => [
            [
                'taxonomy' => 'project',
                'field' => 'term_id',
                'terms' => $project_id,
            ],
        ],
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => 'start_time',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => 'start_time',
                'value' => '',
                'compare' => '=',
            ]
        ],
    ];

    $query = new WP_Query($args);
    $tasks = [];
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $tasks[] = [
                'id' => get_the_ID(),
                'title' => get_the_title(),
            ];
        }
    }
    wp_reset_postdata();
    wp_send_json_success($tasks);
}
add_action('wp_ajax_ptt_get_tasks_for_project', 'ptt_get_tasks_for_project_callback');

/**
 * AJAX handler to get task details (like budget).
 */
function ptt_get_task_details_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    $task_id = isset($_POST['task_id']) ? intval($_POST['task_id']) : 0;

    if (!$task_id || !current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Invalid task or permissions.']);
    }
    
    $task_budget = get_field('task_max_budget', $task_id);
    $project_budget = 0;

    // Get the project associated with the task
    $projects = wp_get_post_terms($task_id, 'project');
    if (!is_wp_error($projects) && !empty($projects)) {
        $project_id = $projects[0]->term_id;
        // ACF requires the term ID to be prefixed with the taxonomy slug
        $project_budget = get_field('project_max_budget', 'project_' . $project_id);
    }
    
    wp_send_json_success([
        'task_budget' => $task_budget,
        'project_budget' => $project_budget,
    ]);
}
add_action('wp_ajax_ptt_get_task_details', 'ptt_get_task_details_callback');


// =================================================================
// 9.0 FRONT-END SHORTCODE [task-enter]
// =================================================================

/**
 * Renders the front-end time entry form via shortcode.
 */
function ptt_task_enter_shortcode() {
    if ( ! is_user_logged_in() || ! current_user_can('edit_posts') ) {
        return '<p>You must be logged in to track time.</p>';
    }

    // Get taxonomy terms for dropdowns
    $clients = get_terms( [ 'taxonomy' => 'client', 'hide_empty' => false ] );
    $projects = get_terms( [ 'taxonomy' => 'project', 'hide_empty' => false ] );

    ob_start();
    ?>
    <div id="ptt-frontend-tracker" class="ptt-frontend-container">
        
        <div id="ptt-active-task-display" style="display: none;">
            <h3>Active Task</h3>
            <p><strong>Task:</strong> <span id="ptt-active-task-name"></span></p>
            <button id="ptt-frontend-stop-btn" class="button ptt-stop-button" data-postid="">Stop Timer</button>
            <div class="ptt-ajax-spinner"></div>
        </div>

        <form id="ptt-new-task-form">
            <h3>Start a Task</h3>
            
            <div class="ptt-form-field">
                <label for="ptt_client">Select Client</label>
                <select name="ptt_client" id="ptt_client">
                    <option value="">-- Select Client --</option>
                    <?php foreach ( $clients as $client ) : ?>
                        <option value="<?php echo esc_attr( $client->term_id ); ?>"><?php echo esc_html( $client->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ptt-form-field">
                <label for="ptt_project">Select Project</label>
                <select name="ptt_project" id="ptt_project">
                    <option value="">-- Select Project --</option>
                    <?php foreach ( $projects as $project ) : ?>
                        <option value="<?php echo esc_attr( $project->term_id ); ?>"><?php echo esc_html( $project->name ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="ptt-form-field">
                <label for="ptt_task">Select Task</label>
                <select name="ptt_task" id="ptt_task">
                    <option value="">-- Select Project First --</option>
                </select>
            </div>
            
            <div id="ptt-project-budget-display" class="ptt-project-budget" style="display: none;"></div>
            <div id="ptt-task-budget-display" class="ptt-task-budget" style="display: none;"></div>

            <div id="ptt-create-new-fields" style="display:none;">
                <div class="ptt-form-field">
                    <label for="ptt_task_name">New Task Name</label>
                    <input type="text" name="ptt_task_name" id="ptt_task_name">
                </div>

                <div class="ptt-form-field">
                    <label for="ptt_notes">Notes</label>
                    <textarea name="ptt_notes" id="ptt_notes" rows="3"></textarea>
                </div>
            </div>
            
            <div class="ptt-form-field">
                <button type="submit" id="ptt-frontend-start-btn" class="button ptt-start-button">Start Timer</button>
                <div class="ptt-ajax-spinner"></div>
            </div>
        </form>
        <div id="ptt-frontend-message" class="ptt-ajax-message"></div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode( 'task-enter', 'ptt_task_enter_shortcode' );


// =================================================================
// 10.0 ADMIN PAGES & LINKS
// =================================================================

/**
 * Adds the top-level "Reports" menu page.
 */
function ptt_add_reports_page() {
    add_menu_page(
        'Time Reports',
        'Reports',
        'manage_options',
        'ptt-reports',
        'ptt_reports_page_html',
        'dashicons-chart-bar',
        30
    );
}
add_action( 'admin_menu', 'ptt_add_reports_page' );

/**
 * Adds the "Settings" link under the Tasks CPT menu.
 */
function ptt_add_settings_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=project_task', // Parent slug
        'Tracker Settings',                // Page title
        'Settings',                        // Menu title
        'manage_options',                  // Capability
        'ptt-self-test',                   // Menu slug
        'ptt_self_test_page_html'          // Function
    );
}
add_action('admin_menu', 'ptt_add_settings_submenu_page');

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

/**
 * Renders the HTML for the reports page.
 */
function ptt_reports_page_html() {
    ?>
    <div class="wrap">
        <h1>Project & Task Time Reports</h1>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'ptt_run_report_nonce' ); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><label for="user_id">Select User</label></th>
                        <td>
                            <?php wp_dropdown_users( [
                                'name' => 'user_id',
                                'show_option_all' => 'All Users',
                                'selected' => isset($_POST['user_id']) ? intval($_POST['user_id']) : 0
                            ] ); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="start_date">Date Range</label></th>
                        <td>
                            <input type="date" id="start_date" name="start_date" value="<?php echo isset($_POST['start_date']) ? esc_attr($_POST['start_date']) : ''; ?>">
                            to
                            <input type="date" id="end_date" name="end_date" value="<?php echo isset($_POST['end_date']) ? esc_attr($_POST['end_date']) : ''; ?>">
                             <button type="button" id="set-this-week" class="button">This Week</button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p class="submit">
                <input type="submit" name="run_report" class="button button-primary" value="Run Report">
            </p>
        </form>

        <?php
        if ( isset( $_POST['run_report'] ) ) {
            check_admin_referer( 'ptt_run_report_nonce' );
            ptt_display_report_results();
        }
        ?>
    </div>
    <?php
}

/**
 * Queries and displays the report results.
 */
function ptt_display_report_results() {
    $user_id = isset( $_POST['user_id'] ) ? intval( $_POST['user_id'] ) : 0;
    $start_date = isset( $_POST['start_date'] ) && $_POST['start_date'] ? sanitize_text_field( $_POST['start_date'] ) : null;
    $end_date = isset( $_POST['end_date'] ) && $_POST['end_date'] ? sanitize_text_field( $_POST['end_date'] ) : null;

    $args = [
        'post_type' => 'project_task',
        'posts_per_page' => -1,
        'post_status' => 'publish',
        'orderby' => 'author',
        'order' => 'ASC',
        'meta_query' => [
            'relation' => 'AND',
            [ // Only include tasks that have been completed
                'key' => 'stop_time',
                'compare' => 'EXISTS'
            ],
            [
                'key' => 'stop_time',
                'value' => '',
                'compare' => '!='
            ]
        ]
    ];
    
    if ( $user_id ) {
        $args['author'] = $user_id;
    }

    if ( $start_date && $end_date ) {
        $args['meta_query'][] = [
            'key' => 'start_time',
            'value' => [ $start_date . ' 00:00:00', $end_date . ' 23:59:59' ],
            'compare' => 'BETWEEN',
            'type' => 'DATETIME'
        ];
    }

    $query = new WP_Query( $args );

    if ( ! $query->have_posts() ) {
        echo '<p>No completed tasks found for the selected criteria.</p>';
        return;
    }

    // Process and group results
    $report_data = [];
    $grand_total = 0.00;

    while ( $query->have_posts() ) {
        $query->the_post();
        $post_id = get_the_ID();
        $author_id = get_the_author_meta('ID');
        $author_name = get_the_author_meta('display_name');

        $clients = get_the_terms($post_id, 'client');
        $client_name = !is_wp_error($clients) && !empty($clients) ? $clients[0]->name : 'Uncategorized';
        $client_id = !is_wp_error($clients) && !empty($clients) ? $clients[0]->term_id : 0;
        
        $projects = get_the_terms($post_id, 'project');
        $project_name = !is_wp_error($projects) && !empty($projects) ? $projects[0]->name : 'Uncategorized';
        $project_id = !is_wp_error($projects) && !empty($projects) ? $projects[0]->term_id : 0;
        
        $duration = (float) get_field('calculated_duration', $post_id);
        $grand_total += $duration;

        if (!isset($report_data[$author_id])) {
            $report_data[$author_id] = ['name' => $author_name, 'clients' => [], 'total' => 0];
        }

        if (!isset($report_data[$author_id]['clients'][$client_id])) {
            $report_data[$author_id]['clients'][$client_id] = ['name' => $client_name, 'projects' => [], 'total' => 0];
        }

        if (!isset($report_data[$author_id]['clients'][$client_id]['projects'][$project_id])) {
            $report_data[$author_id]['clients'][$client_id]['projects'][$project_id] = ['name' => $project_name, 'tasks' => [], 'total' => 0];
        }

        $report_data[$author_id]['total'] += $duration;
        $report_data[$author_id]['clients'][$client_id]['total'] += $duration;
        $report_data[$author_id]['clients'][$client_id]['projects'][$project_id]['total'] += $duration;
        $report_data[$author_id]['clients'][$client_id]['projects'][$project_id]['tasks'][] = [
            'id' => $post_id,
            'title' => get_the_title(),
            'date' => get_field('start_time', $post_id),
            'duration' => $duration
        ];
    }
    wp_reset_postdata();

    // Display results
    echo '<h2>Report Results</h2>';
    echo '<div class="ptt-report-results">';
    
    foreach ($report_data as $author) {
        if (!$user_id) { // Only show user header if report is for all users
             echo '<h3>User: ' . esc_html($author['name']) . '</h3>';
        }
       
        foreach ($author['clients'] as $client) {
            echo '<div class="client-group">';
            echo '<h4>Client: ' . esc_html($client['name']) . ' <span class="subtotal">(Client Total: ' . number_format($client['total'], 2) . ' hrs)</span></h4>';
            foreach ($client['projects'] as $project) {
                echo '<div class="project-group">';
                echo '<h5>Project: ' . esc_html($project['name']) . ' <span class="subtotal">(Project Total: ' . number_format($project['total'], 2) . ' hrs)</span></h5>';
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead><tr><th>Task Name</th><th>Date</th><th>Duration (Hours)</th></tr></thead>';
                echo '<tbody>';
                foreach ($project['tasks'] as $task) {
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link($task['id']) . '">' . esc_html($task['title']) . '</a></td>';
                    echo '<td>' . esc_html(date('Y-m-d', strtotime($task['date']))) . '</td>';
                    echo '<td>' . number_format($task['duration'], 2) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
                echo '</div>'; // .project-group
            }
             echo '</div>'; // .client-group
        }
    }
    
    echo '<div class="grand-total"><strong>Grand Total: ' . number_format($grand_total, 2) . ' hours</strong></div>';
    echo '</div>'; // .ptt-report-results
}


// =================================================================
// 11.0 SELF-TEST MODULE
// =================================================================

/**
 * Renders the Self Test page HTML.
 */
function ptt_self_test_page_html() {
    ?>
    <div class="wrap">
        <h1>Plugin Settings & Self Test</h1>
        <p>This module helps verify core plugin functionality. It creates and then immediately deletes test data.</p>
        <button id="ptt-run-self-tests" class="button button-primary">Run All Tests</button>
        <div id="ptt-test-results-container" style="margin-top: 20px;">
             <div class="ptt-ajax-spinner" style="display:none;"></div>
        </div>
    </div>
    <?php
}

/**
 * AJAX handler to run all self-tests.
 */
function ptt_run_self_tests_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $results = [];

    // Test 1: Task Post Save
    $test_post_id = wp_insert_post(['post_type' => 'project_task', 'post_title' => 'SELF TEST POST', 'post_status' => 'trash']);
    if ($test_post_id && !is_wp_error($test_post_id)) {
        $results[] = ['name' => 'Task Post Save', 'status' => 'Pass', 'message' => 'Successfully created and trashed test post.'];
        wp_delete_post($test_post_id, true); // force delete
    } else {
        $results[] = ['name' => 'Task Post Save', 'status' => 'Fail', 'message' => 'Failed to create test post.'];
    }

    // Test 2: Create Client + Project
    $client_term = wp_insert_term('SELF TEST CLIENT', 'client');
    $project_term = wp_insert_term('SELF TEST PROJECT', 'project');
    if (!is_wp_error($client_term) && !is_wp_error($project_term)) {
        $results[] = ['name' => 'Create Client + Project', 'status' => 'Pass', 'message' => 'Successfully created test taxonomies.'];
        wp_delete_term($client_term['term_id'], 'client');
        wp_delete_term($project_term['term_id'], 'project');
    } else {
        $results[] = ['name' => 'Create Client + Project', 'status' => 'Fail', 'message' => 'Failed to create test taxonomies.'];
    }

    // Test 3: Calculate Total Time
    $calc_test_post_id = wp_insert_post(['post_type' => 'project_task', 'post_title' => 'CALC TEST POST', 'post_status' => 'publish']);
    if ($calc_test_post_id && !is_wp_error($calc_test_post_id)) {
        update_field('start_time', '2025-07-19 10:00:00', $calc_test_post_id);
        update_field('stop_time', '2025-07-19 11:30:00', $calc_test_post_id); // 1.5 hours
        $duration = ptt_calculate_and_save_duration($calc_test_post_id);
        
        if ($duration == '1.50') {
            $results[] = ['name' => 'Calculate Total Time (Basic)', 'status' => 'Pass', 'message' => 'Correctly calculated 1.50 hours.'];
        } else {
            $results[] = ['name' => 'Calculate Total Time (Basic)', 'status' => 'Fail', 'message' => 'Calculation incorrect. Expected 1.50, got ' . $duration];
        }

        // Test with rounding up
        update_field('start_time', '2025-07-19 12:00:00', $calc_test_post_id);
        update_field('stop_time', '2025-07-19 12:01:00', $calc_test_post_id); // 1 min = 0.01666 hours, should round up to 0.02
        $duration_rounding = ptt_calculate_and_save_duration($calc_test_post_id);

        if ($duration_rounding == '0.02') {
             $results[] = ['name' => 'Calculate Total Time (Rounding)', 'status' => 'Pass', 'message' => 'Correctly rounded up to 0.02 hours.'];
        } else {
            $results[] = ['name' => 'Calculate Total Time (Rounding)', 'status' => 'Fail', 'message' => 'Rounding incorrect. Expected 0.02, got ' . $duration_rounding];
        }

        wp_delete_post($calc_test_post_id, true);
    } else {
         $results[] = ['name' => 'Calculate Total Time', 'status' => 'Fail', 'message' => 'Could not create post for calculation test.'];
    }
    
    wp_send_json_success($results);
}
add_action( 'wp_ajax_ptt_run_self_tests', 'ptt_run_self_tests_callback' );