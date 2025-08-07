<?php
/**
 * Kanban Board Module for Project & Task Time Tracker
 * 
 * @package PTT
 * @since 1.8.0
 */

// Block direct access
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Register the Kanban Board submenu page
 */
function ptt_add_kanban_page() {
    add_submenu_page(
        'edit.php?post_type=project_task',
        'Kanban Board',
        'Kanban Board',
        'edit_posts',
        'ptt-kanban',
        'ptt_kanban_page_html'
    );
}
add_action( 'admin_menu', 'ptt_add_kanban_page', 50 );

/**
 * Register the /kanban rewrite rule
 */
function ptt_kanban_rewrite_rule() {
    add_rewrite_rule(
        '^kanban/?$',
        'index.php?post_type=project_task&kanban=1',
        'top'
    );
}
add_action( 'init', 'ptt_kanban_rewrite_rule' );

/**
 * Add kanban query var
 */
function ptt_kanban_query_vars( $vars ) {
    $vars[] = 'kanban';
    return $vars;
}
add_filter( 'query_vars', 'ptt_kanban_query_vars' );

/**
 * Handle kanban template redirect
 */
function ptt_kanban_template_redirect() {
    if ( get_query_var( 'kanban' ) ) {
        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_die( __( 'You do not have permission to access this page.', 'ptt' ) );
        }
        ptt_kanban_page_html();
        exit;
    }
}
add_action( 'template_redirect', 'ptt_kanban_template_redirect' );

/**
 * Enqueue Kanban-specific scripts and styles
 */
function ptt_enqueue_kanban_assets( $hook ) {
    // Only load on Kanban page
    if ( 'project_task_page_ptt-kanban' !== $hook && ! get_query_var( 'kanban' ) ) {
        return;
    }
    
    // Enqueue Kanban JS
    wp_enqueue_script( 
        'ptt-kanban', 
        PTT_PLUGIN_URL . 'kanban.js', 
        [ 'jquery', 'jquery-ui-sortable' ], 
        PTT_VERSION, 
        true 
    );
    
    // Localize script for AJAX and data
    wp_localize_script( 'ptt-kanban', 'ptt_kanban', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'ptt_ajax_nonce' ),
        'messages' => [
            'drag_error' => __( 'Failed to update task status. Please try again.', 'ptt' ),
            'filter_error' => __( 'Failed to apply filters. Please try again.', 'ptt' ),
            'loading' => __( 'Loading...', 'ptt' ),
            'no_tasks' => __( 'No tasks found matching your filters.', 'ptt' ),
        ]
    ] );
}
add_action( 'admin_enqueue_scripts', 'ptt_enqueue_kanban_assets' );
add_action( 'wp_enqueue_scripts', 'ptt_enqueue_kanban_assets' );

/**
 * Save Kanban filter preferences in cookies
 */
function ptt_save_kanban_filter_preferences() {
    if ( ! isset( $_GET['page'] ) || 'ptt-kanban' !== $_GET['page'] ) {
        return;
    }
    
    if ( ! current_user_can( 'edit_posts' ) ) {
        return;
    }
    
    $cookie_duration = 30 * DAY_IN_SECONDS;
    
    // Save filter preferences if they exist in the request
    $filters = [ 'assignee_filter', 'activity_filter', 'client_filter', 'project_filter' ];
    
    foreach ( $filters as $filter ) {
        if ( isset( $_GET[ $filter ] ) ) {
            $value = sanitize_text_field( $_GET[ $filter ] );
            setcookie( 'ptt_kanban_' . $filter, $value, time() + $cookie_duration, COOKIEPATH, COOKIE_DOMAIN );
        }
    }
}
add_action( 'admin_init', 'ptt_save_kanban_filter_preferences' );

/**
 * Get saved filter preference or default
 */
function ptt_get_kanban_filter( $filter_name, $default = '' ) {
    // Check GET parameter first
    if ( isset( $_GET[ $filter_name ] ) ) {
        return sanitize_text_field( $_GET[ $filter_name ] );
    }
    
    // Check cookie
    $cookie_name = 'ptt_kanban_' . $filter_name;
    if ( isset( $_COOKIE[ $cookie_name ] ) ) {
        return sanitize_text_field( $_COOKIE[ $cookie_name ] );
    }
    
    return $default;
}

/**
 * Render the Kanban Board page
 */
function ptt_kanban_page_html() {
    // Get filter values
    $assignee_filter = ptt_get_kanban_filter( 'assignee_filter', '0' );
    $activity_filter = ptt_get_kanban_filter( 'activity_filter', '30' );
    $client_filter = ptt_get_kanban_filter( 'client_filter', '0' );
    $project_filter = ptt_get_kanban_filter( 'project_filter', '0' );
    
    ?>
    <div class="wrap ptt-kanban-wrap">
        <h1><?php _e( 'Kanban Board', 'ptt' ); ?></h1>
        
        <!-- Filters Section -->
        <div class="ptt-kanban-filters">
            <form method="get" action="" id="ptt-kanban-filters-form">
                <input type="hidden" name="post_type" value="project_task">
                <input type="hidden" name="page" value="ptt-kanban">
                
                <div class="filter-row">
                    <!-- Assignee Filter -->
                    <div class="filter-item">
                        <label for="assignee_filter"><?php _e( 'Assignee:', 'ptt' ); ?></label>
                        <?php
                        wp_dropdown_users( [
                            'name' => 'assignee_filter',
                            'id' => 'assignee_filter',
                            'capability' => 'publish_posts',
                            'show_option_all' => __( 'All Assignees', 'ptt' ),
                            'selected' => intval( $assignee_filter ),
                        ] );
                        ?>
                    </div>
                    
                    <!-- Activity Period Filter -->
                    <div class="filter-item">
                        <label for="activity_filter"><?php _e( 'Activity Period:', 'ptt' ); ?></label>
                        <select name="activity_filter" id="activity_filter">
                            <option value="7" <?php selected( $activity_filter, '7' ); ?>><?php _e( 'Previous 7 days', 'ptt' ); ?></option>
                            <option value="14" <?php selected( $activity_filter, '14' ); ?>><?php _e( 'Previous 14 days', 'ptt' ); ?></option>
                            <option value="21" <?php selected( $activity_filter, '21' ); ?>><?php _e( 'Previous 21 days', 'ptt' ); ?></option>
                            <option value="30" <?php selected( $activity_filter, '30' ); ?>><?php _e( 'Previous 30 days', 'ptt' ); ?></option>
                            <option value="56" <?php selected( $activity_filter, '56' ); ?>><?php _e( 'Previous 8 weeks', 'ptt' ); ?></option>
                        </select>
                    </div>
                    
                    <!-- Client Filter -->
                    <div class="filter-item">
                        <label for="client_filter"><?php _e( 'Client:', 'ptt' ); ?></label>
                        <?php
                        wp_dropdown_categories( [
                            'taxonomy' => 'client',
                            'name' => 'client_filter',
                            'id' => 'client_filter',
                            'show_option_all' => __( 'All Clients', 'ptt' ),
                            'hide_empty' => false,
                            'selected' => intval( $client_filter ),
                            'hierarchical' => true,
                        ] );
                        ?>
                    </div>
                    
                    <!-- Project Filter -->
                    <div class="filter-item">
                        <label for="project_filter"><?php _e( 'Project:', 'ptt' ); ?></label>
                        <?php
                        wp_dropdown_categories( [
                            'taxonomy' => 'project',
                            'name' => 'project_filter',
                            'id' => 'project_filter',
                            'show_option_all' => __( 'All Projects', 'ptt' ),
                            'hide_empty' => false,
                            'selected' => intval( $project_filter ),
                            'hierarchical' => true,
                        ] );
                        ?>
                    </div>
                    
                    <!-- Filter Buttons -->
                    <div class="filter-item filter-buttons">
                        <button type="submit" class="button button-primary"><?php _e( 'Apply Filters', 'ptt' ); ?></button>
                        <a href="<?php echo admin_url( 'edit.php?post_type=project_task&page=ptt-kanban' ); ?>" 
                           class="button"><?php _e( 'Reset Filters', 'ptt' ); ?></a>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Kanban Board -->
        <div class="ptt-kanban-board" id="ptt-kanban-board">
            <?php ptt_render_kanban_board( $assignee_filter, $activity_filter, $client_filter, $project_filter ); ?>
        </div>
        
        <!-- Loading Overlay -->
        <div class="ptt-kanban-loading" id="ptt-kanban-loading" style="display: none;">
            <div class="ptt-ajax-spinner"></div>
            <span><?php _e( 'Updating board...', 'ptt' ); ?></span>
        </div>
    </div>
    <?php
}

/**
 * Render the Kanban board columns and tasks
 */
function ptt_render_kanban_board( $assignee_filter = 0, $activity_filter = 30, $client_filter = 0, $project_filter = 0 ) {
    // Get all task status terms
    $statuses = get_terms( [
        'taxonomy' => 'task_status',
        'hide_empty' => false,
        'orderby' => 'term_order',
        'order' => 'ASC',
    ] );
    
    if ( is_wp_error( $statuses ) || empty( $statuses ) ) {
        echo '<p>' . __( 'No task statuses found. Please create task statuses first.', 'ptt' ) . '</p>';
        return;
    }
    
    // Build base query args
    $base_args = [
        'post_type' => 'project_task',
        'posts_per_page' => -1,
        'post_status' => 'publish',
    ];
    
    // Apply assignee filter
    if ( $assignee_filter ) {
        $base_args['meta_query'][] = [
            'key' => 'ptt_assignee',
            'value' => $assignee_filter,
            'compare' => '=',
            'type' => 'NUMERIC',
        ];
    }
    
    // Apply activity period filter
    if ( $activity_filter ) {
        $date_query = [];
        $days_ago = intval( $activity_filter );
        
        // Include tasks created within the period
        $date_query[] = [
            'after' => $days_ago . ' days ago',
            'inclusive' => true,
        ];
        
        $base_args['date_query'] = [
            'relation' => 'OR',
            $date_query,
        ];
        
        // We'll also check for sessions within the period in the loop
    }
    
    // Apply taxonomy filters
    $tax_query = [];
    
    if ( $client_filter ) {
        $tax_query[] = [
            'taxonomy' => 'client',
            'field' => 'term_id',
            'terms' => $client_filter,
        ];
    }
    
    if ( $project_filter ) {
        $tax_query[] = [
            'taxonomy' => 'project',
            'field' => 'term_id',
            'terms' => $project_filter,
        ];
    }
    
    if ( ! empty( $tax_query ) ) {
        $tax_query['relation'] = 'AND';
        $base_args['tax_query'] = $tax_query;
    }
    
    // Render columns
    echo '<div class="ptt-kanban-columns">';
    
    foreach ( $statuses as $status ) {
        // Query tasks for this status
        $args = $base_args;
        $args['tax_query'][] = [
            'taxonomy' => 'task_status',
            'field' => 'term_id',
            'terms' => $status->term_id,
        ];
        
        $query = new WP_Query( $args );
        $tasks = [];
        
        // Filter tasks based on activity period (including session dates)
        if ( $query->have_posts() ) {
            $cutoff_date = $activity_filter ? date( 'Y-m-d H:i:s', strtotime( '-' . $activity_filter . ' days' ) ) : '';
            
            while ( $query->have_posts() ) {
                $query->the_post();
                $post_id = get_the_ID();
                $include_task = true;
                
                // Check if task has activity within the period
                if ( $activity_filter ) {
                    $include_task = false;
                    
                    // Check creation date
                    if ( get_the_date( 'Y-m-d H:i:s' ) >= $cutoff_date ) {
                        $include_task = true;
                    }
                    
                    // Check modification date
                    if ( ! $include_task && get_the_modified_date( 'Y-m-d H:i:s' ) >= $cutoff_date ) {
                        $include_task = true;
                    }
                    
                    // Check session dates
                    if ( ! $include_task ) {
                        $sessions = get_field( 'sessions', $post_id );
                        if ( ! empty( $sessions ) && is_array( $sessions ) ) {
                            foreach ( $sessions as $session ) {
                                if ( ! empty( $session['session_start_time'] ) && $session['session_start_time'] >= $cutoff_date ) {
                                    $include_task = true;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                if ( $include_task ) {
                    $tasks[] = ptt_get_task_card_data( $post_id );
                }
            }
            wp_reset_postdata();
        }
        
        // Render column
        ?>
        <div class="ptt-kanban-column" data-status-id="<?php echo esc_attr( $status->term_id ); ?>">
            <div class="ptt-kanban-column-header">
                <h3><?php echo esc_html( $status->name ); ?></h3>
                <span class="task-count"><?php echo count( $tasks ); ?></span>
            </div>
            <div class="ptt-kanban-column-tasks" data-status="<?php echo esc_attr( $status->term_id ); ?>">
                <?php
                if ( ! empty( $tasks ) ) {
                    foreach ( $tasks as $task ) {
                        ptt_render_task_card( $task );
                    }
                } else {
                    echo '<div class="ptt-kanban-empty-state">' . __( 'No tasks', 'ptt' ) . '</div>';
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    echo '</div>'; // .ptt-kanban-columns
}

/**
 * Get task card data
 */
function ptt_get_task_card_data( $post_id ) {
    // Get assignee
    $assignee_id = (int) get_post_meta( $post_id, 'ptt_assignee', true );
    $assignee_name = $assignee_id ? get_the_author_meta( 'display_name', $assignee_id ) : __( 'Unassigned', 'ptt' );
    
    // Get time logged
    $duration = get_field( 'calculated_duration', $post_id );
    $duration = $duration ? floatval( $duration ) : 0;
    
    // Get budget
    $task_budget = get_field( 'task_max_budget', $post_id );
    $project_terms = get_the_terms( $post_id, 'project' );
    $project_budget = 0;
    if ( ! is_wp_error( $project_terms ) && $project_terms ) {
        $project_budget = get_field( 'project_max_budget', 'project_' . $project_terms[0]->term_id );
    }
    $effective_budget = $task_budget ? $task_budget : $project_budget;
    $is_over_budget = ( $effective_budget > 0 && $duration > $effective_budget );
    
    // Get last activity
    $last_activity = ptt_get_last_activity_date( $post_id );
    
    // Check for active timer
    $has_active_timer = ptt_task_has_active_timer( $post_id );
    
    // Get client and project names
    $client_terms = get_the_terms( $post_id, 'client' );
    $client_name = ! is_wp_error( $client_terms ) && $client_terms ? $client_terms[0]->name : '';
    $project_name = ! is_wp_error( $project_terms ) && $project_terms ? $project_terms[0]->name : '';
    
    return [
        'id' => $post_id,
        'title' => get_the_title( $post_id ),
        'assignee' => $assignee_name,
        'duration' => $duration,
        'budget' => $effective_budget,
        'is_over_budget' => $is_over_budget,
        'last_activity' => $last_activity,
        'has_active_timer' => $has_active_timer,
        'client' => $client_name,
        'project' => $project_name,
        'edit_link' => get_edit_post_link( $post_id ),
    ];
}

/**
 * Get last activity date for a task
 */
function ptt_get_last_activity_date( $post_id ) {
    $dates = [];
    
    // Add creation date
    $dates[] = get_the_date( 'U', $post_id );
    
    // Add modification date
    $dates[] = get_the_modified_date( 'U', $post_id );
    
    // Check sessions for latest activity
    $sessions = get_field( 'sessions', $post_id );
    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
        foreach ( $sessions as $session ) {
            if ( ! empty( $session['session_start_time'] ) ) {
                $dates[] = strtotime( $session['session_start_time'] );
            }
            if ( ! empty( $session['session_stop_time'] ) ) {
                $dates[] = strtotime( $session['session_stop_time'] );
            }
        }
    }
    
    // Get the most recent date
    $last_activity = max( $dates );
    
    // Format relative time
    $current_time = current_time( 'timestamp' );
    $time_diff = $current_time - $last_activity;
    
    if ( $time_diff < DAY_IN_SECONDS ) {
        return __( 'Today', 'ptt' );
    } elseif ( $time_diff < 2 * DAY_IN_SECONDS ) {
        return __( 'Yesterday', 'ptt' );
    } elseif ( $time_diff < WEEK_IN_SECONDS ) {
        return sprintf( __( '%d days ago', 'ptt' ), round( $time_diff / DAY_IN_SECONDS ) );
    } else {
        return date_i18n( get_option( 'date_format' ), $last_activity );
    }
}

/**
 * Check if task has an active timer
 */
function ptt_task_has_active_timer( $post_id ) {
    // Check main timer
    $start_time = get_field( 'start_time', $post_id );
    $stop_time = get_field( 'stop_time', $post_id );
    
    if ( $start_time && ! $stop_time ) {
        return true;
    }
    
    // Check session timers
    $sessions = get_field( 'sessions', $post_id );
    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
        foreach ( $sessions as $session ) {
            if ( ! empty( $session['session_start_time'] ) && empty( $session['session_stop_time'] ) ) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Render a task card
 */
function ptt_render_task_card( $task ) {
    $card_classes = [ 'ptt-kanban-task' ];
    if ( $task['is_over_budget'] ) {
        $card_classes[] = 'over-budget';
    }
    if ( $task['has_active_timer'] ) {
        $card_classes[] = 'timer-active';
    }
    ?>
    <div class="<?php echo esc_attr( implode( ' ', $card_classes ) ); ?>" 
         data-task-id="<?php echo esc_attr( $task['id'] ); ?>">
        
        <div class="task-header">
            <h4 class="task-title">
                <a href="<?php echo esc_url( $task['edit_link'] ); ?>" target="_blank">
                    <?php echo esc_html( $task['title'] ); ?>
                </a>
            </h4>
            <?php if ( $task['has_active_timer'] ) : ?>
                <span class="timer-indicator" title="<?php esc_attr_e( 'Timer is running', 'ptt' ); ?>">⏱️</span>
            <?php endif; ?>
        </div>
        
        <div class="task-meta">
            <div class="task-assignee">
                <span class="dashicons dashicons-admin-users"></span>
                <?php echo esc_html( $task['assignee'] ); ?>
            </div>
            
            <div class="task-time">
                <span class="dashicons dashicons-clock"></span>
                <span class="<?php echo $task['is_over_budget'] ? 'over-budget-text' : ''; ?>">
                    <?php echo number_format( $task['duration'], 2 ); ?>h
                    <?php if ( $task['budget'] > 0 ) : ?>
                        / <?php echo number_format( $task['budget'], 2 ); ?>h
                    <?php endif; ?>
                </span>
            </div>
        </div>
        
        <div class="task-details">
            <?php if ( $task['client'] || $task['project'] ) : ?>
                <div class="task-taxonomy">
                    <?php if ( $task['client'] ) : ?>
                        <span class="task-client" title="<?php echo esc_attr( $task['client'] ); ?>">
                            <?php echo esc_html( ptt_truncate_text( $task['client'], 20 ) ); ?>
                        </span>
                    <?php endif; ?>
                    <?php if ( $task['project'] ) : ?>
                        <span class="task-project" title="<?php echo esc_attr( $task['project'] ); ?>">
                            <?php echo esc_html( ptt_truncate_text( $task['project'], 20 ) ); ?>
                        </span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="task-activity">
                <span class="dashicons dashicons-calendar-alt"></span>
                <?php echo esc_html( $task['last_activity'] ); ?>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Truncate text helper
 */
function ptt_truncate_text( $text, $length = 30 ) {
    if ( strlen( $text ) > $length ) {
        return substr( $text, 0, $length - 3 ) . '...';
    }
    return $text;
}

/**
 * AJAX handler for updating task status via drag and drop
 */
function ptt_kanban_update_status_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ptt' ) ] );
    }
    
    $task_id = isset( $_POST['task_id'] ) ? intval( $_POST['task_id'] ) : 0;
    $status_id = isset( $_POST['status_id'] ) ? intval( $_POST['status_id'] ) : 0;
    
    if ( ! $task_id || ! $status_id ) {
        wp_send_json_error( [ 'message' => __( 'Invalid data provided.', 'ptt' ) ] );
    }
    
    // Verify the task exists
    if ( 'project_task' !== get_post_type( $task_id ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid task.', 'ptt' ) ] );
    }
    
    // Verify the status exists
    if ( ! term_exists( $status_id, 'task_status' ) ) {
        wp_send_json_error( [ 'message' => __( 'Invalid status.', 'ptt' ) ] );
    }
    
    // Update the task status
    $result = wp_set_object_terms( $task_id, $status_id, 'task_status', false );
    
    if ( is_wp_error( $result ) ) {
        wp_send_json_error( [ 'message' => __( 'Failed to update task status.', 'ptt' ) ] );
    }
    
    // Get updated task data
    $task_data = ptt_get_task_card_data( $task_id );
    
    wp_send_json_success( [
        'message' => __( 'Task status updated successfully.', 'ptt' ),
        'task_data' => $task_data,
    ] );
}
add_action( 'wp_ajax_ptt_kanban_update_status', 'ptt_kanban_update_status_callback' );

/**
 * AJAX handler for refreshing the Kanban board
 */
function ptt_kanban_refresh_board_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    
    if ( ! current_user_can( 'edit_posts' ) ) {
        wp_send_json_error( [ 'message' => __( 'Permission denied.', 'ptt' ) ] );
    }
    
    // Get filter values from AJAX request
    $assignee_filter = isset( $_POST['assignee_filter'] ) ? intval( $_POST['assignee_filter'] ) : 0;
    $activity_filter = isset( $_POST['activity_filter'] ) ? intval( $_POST['activity_filter'] ) : 30;
    $client_filter = isset( $_POST['client_filter'] ) ? intval( $_POST['client_filter'] ) : 0;
    $project_filter = isset( $_POST['project_filter'] ) ? intval( $_POST['project_filter'] ) : 0;
    
    // Capture the board HTML
    ob_start();
    ptt_render_kanban_board( $assignee_filter, $activity_filter, $client_filter, $project_filter );
    $board_html = ob_get_clean();
    
    wp_send_json_success( [
        'html' => $board_html,
    ] );
}
add_action( 'wp_ajax_ptt_kanban_refresh_board', 'ptt_kanban_refresh_board_callback' );