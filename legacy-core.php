<?php
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

