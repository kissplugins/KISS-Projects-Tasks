<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

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
                <label for="ptt_project">Select Project <span id="ptt-project-budget-display" class="ptt-project-budget-label" style="display: none;"></span></label>
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
    
    // Get project budget to return with tasks
    $project_budget = get_field('project_max_budget', 'project_' . $project_id);

    wp_send_json_success(['tasks' => $tasks, 'budget' => $project_budget]);
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
    
    wp_send_json_success([
        'task_budget' => $task_budget,
    ]);
}
add_action('wp_ajax_ptt_get_task_details', 'ptt_get_task_details_callback');