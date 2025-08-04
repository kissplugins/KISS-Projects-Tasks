<?php
// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =================================================================
// 11.0 SELF‑TEST MODULE
// =================================================================

/**
 * Adds the “Settings” link under the Tasks CPT menu.
 */
function ptt_add_settings_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=project_task', // Parent slug
        'Tracker Settings',                // Page title
        'Settings',                        // Menu title
        'manage_options',                  // Capability
        'ptt-self-test',                   // Menu slug
        'ptt_self_test_page_html'          // Callback
    );
}
// Run after WordPress has finished building default CPT & taxonomy menus.
add_action( 'admin_menu', 'ptt_add_settings_submenu_page', 60 );

/**
 * Adds the “Changelog” link under the Tasks CPT menu.
 */
function ptt_add_changelog_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=project_task', // Parent slug
        'Plugin Changelog',                // Page title
        'Changelog – v' . PTT_VERSION,     // Menu title
        'manage_options',                  // Capability
        'ptt-changelog',                   // Menu slug
        'ptt_changelog_page_html'          // Callback
    );
}
add_action( 'admin_menu', 'ptt_add_changelog_submenu_page', 60 );

/**
 * Renders the Changelog page HTML.
 */
function ptt_changelog_page_html() {
    $file_path = PTT_PLUGIN_DIR . 'changelog.md';

    echo '<div class="wrap">';
    echo '<h1>Plugin Changelog</h1>';

    if ( function_exists( 'kiss_mdv_render_file' ) ) {
        $html = kiss_mdv_render_file( $file_path );
        if ( $html ) {
            echo $html;
        } else {
            echo '<p>Unable to render changelog.</p>';
        }
    } else {
        $content = '';
        if ( file_exists( $file_path ) ) {
            $lines   = file( $file_path );
            $preview = array_slice( $lines, 0, 500 );
            $content = implode( '', $preview );
        } else {
            $content = 'changelog.md not found.';
        }
        echo '<pre>' . esc_html( $content ) . '</pre>';
        echo '<p><em>To view the entire changelog, please open the changelog.md file in a text viewer.</em></p>';
    }

    echo '</div>';
}

/* -----------------------------------------------------------------
 *  NOTE:  The menu‑reordering logic that previously lived here
 *  (`ptt_reorder_tasks_menu()`) was removed in v 1.7.39 because it
 *  could hide taxonomy menu items under certain load‑order
 *  conditions.  The plugin now relies on WordPress’ native order.
 * ----------------------------------------------------------------*/

/**
 * Renders the Self‑Test page HTML.
 */
function ptt_self_test_page_html() { ?>
    <div class="wrap">
        <h1>Plugin Settings &amp; Self Test</h1>
        <p>This module verifies core plugin functionality. It creates test data and immediately deletes it.</p>

        <button id="ptt-run-self-tests" class="button button-primary">Re‑Run Tests</button>
        <p id="ptt-last-test-time">
            <?php
            $last_run = get_option( 'ptt_tests_last_run' );
            if ( $last_run ) {
                echo 'Tests last ran at ' . esc_html( date_i18n( get_option( 'time_format' ), $last_run ) );
            } else {
                echo 'Tests last ran at --:--:--';
            }
            ?>
        </p>

        <div id="ptt-test-results-container" style="margin-top:20px;">
            <div class="ptt-ajax-spinner" style="display:none;"></div>
        </div>

        <hr />
        <button id="ptt-sync-authors" class="button">Synchronize Authors &rarr; Assignee</button>
        <p id="ptt-sync-authors-result"></p>
    </div>
<?php }

/**
 * AJAX handler to run all self‑tests.
 */
function ptt_run_self_tests_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $results = [];

    /* -------------------------------------------------------------
     * TEST 1 – Task Post Save & Assignee Update
     * -----------------------------------------------------------*/
    $test_post_id = wp_insert_post( [
        'post_type'   => 'project_task',
        'post_title'  => 'SELF TEST POST',
        'post_status' => 'publish',
    ] );

    if ( $test_post_id && ! is_wp_error( $test_post_id ) ) {
        $admin_id = get_current_user_id();
        update_post_meta( $test_post_id, 'ptt_assignee', $admin_id );
        $saved_assignee = (int) get_post_meta( $test_post_id, 'ptt_assignee', true );

        $results[] = [
            'name'    => 'Task Post Save & Assignee Update',
            'status'  => ( $saved_assignee === $admin_id ) ? 'Pass' : 'Fail',
            'message' => ( $saved_assignee === $admin_id )
                ? 'Successfully created post and updated its assignee meta field.'
                : 'Created post but failed to update or verify assignee meta field.',
        ];

        wp_delete_post( $test_post_id, true ); // force delete
    } else {
        $results[] = [
            'name'    => 'Task Post Save & Assignee Update',
            'status'  => 'Fail',
            'message' => 'Failed to create test post.',
        ];
    }

    /* -------------------------------------------------------------
     * TEST 2 – Create Client & Project taxonomies
     * -----------------------------------------------------------*/
    $client_term  = wp_insert_term( 'SELF TEST CLIENT',  'client'  );
    $project_term = wp_insert_term( 'SELF TEST PROJECT', 'project' );

    if ( ! is_wp_error( $client_term ) && ! is_wp_error( $project_term ) ) {
        $results[] = [
            'name'    => 'Create Client & Project',
            'status'  => 'Pass',
            'message' => 'Successfully created test taxonomies.',
        ];
        wp_delete_term( $client_term['term_id'],  'client'  );
        wp_delete_term( $project_term['term_id'], 'project' );
    } else {
        $results[] = [
            'name'    => 'Create Client & Project',
            'status'  => 'Fail',
            'message' => 'Failed to create test taxonomies.',
        ];
    }

    /* -------------------------------------------------------------
     * TEST 3 – Calculate Total Time (basic & rounding)
     * -----------------------------------------------------------*/
    $calc_post = wp_insert_post( [
        'post_type'   => 'project_task',
        'post_title'  => 'CALC TEST POST',
        'post_status' => 'publish',
    ] );

    if ( $calc_post && ! is_wp_error( $calc_post ) ) {
        // 1.5 hours exactly
        update_field( 'start_time', '2025-07-19 10:00:00', $calc_post );
        update_field( 'stop_time',  '2025-07-19 11:30:00', $calc_post );
        $duration = ptt_calculate_and_save_duration( $calc_post );

        $results[] = [
            'name'    => 'Calculate Total Time (1h 30m)',
            'status'  => ( $duration === '1.50' ) ? 'Pass' : 'Fail',
            'message' => ( $duration === '1.50' )
                ? 'Correctly calculated 1.50 hours.'
                : "Calculation incorrect. Expected 1.50, got {$duration}.",
        ];

        // 1 minute → should round up to 0.02
        update_field( 'start_time', '2025-07-19 12:00:00', $calc_post );
        update_field( 'stop_time',  '2025-07-19 12:01:00', $calc_post );
        $duration_round = ptt_calculate_and_save_duration( $calc_post );

        $results[] = [
            'name'    => 'Calculate Total Time (Rounding)',
            'status'  => ( $duration_round === '0.02' ) ? 'Pass' : 'Fail',
            'message' => ( $duration_round === '0.02' )
                ? 'Correctly rounded to 0.02 hours.'
                : "Expected 0.02 hours, got {$duration_round}.",
        ];

        wp_delete_post( $calc_post, true );
    } else {
        $results[] = [
            'name'    => 'Calculate Total Time',
            'status'  => 'Fail',
            'message' => 'Could not create post for calculation test.',
        ];
    }

    /* -------------------------------------------------------------
     * TEST 4 – Status Update Logic
     * -----------------------------------------------------------*/
    $status_term = wp_insert_term( 'SELF TEST STATUS ' . wp_rand(), 'task_status' );
    $status_post = wp_insert_post( [
        'post_type'   => 'project_task',
        'post_title'  => 'STATUS TEST',
        'post_status' => 'publish',
    ] );

    if (
        $status_post && ! is_wp_error( $status_post ) &&
        $status_term && ! is_wp_error( $status_term )
    ) {
        wp_set_object_terms( $status_post, $status_term['term_id'], 'task_status', false );
        $assigned = has_term( $status_term['term_id'], 'task_status', $status_post );

        $results[] = [
            'name'    => 'Status Update Logic',
            'status'  => $assigned ? 'Pass' : 'Fail',
            'message' => $assigned
                ? 'Core status assignment successful.'
                : 'wp_set_object_terms failed to assign status.',
        ];
    } else {
        $results[] = [
            'name'    => 'Status Update Logic',
            'status'  => 'Fail',
            'message' => 'Could not create test post or term for status update test.',
        ];
    }

    // Always clean up
    wp_delete_post( $status_post, true );
    if ( $status_term && ! is_wp_error( $status_term ) ) {
        wp_delete_term( $status_term['term_id'], 'task_status' );
    }

    /* -------------------------------------------------------------
     * TEST 5–7 – Reporting Logic & Date‑Range filter
     * (code unchanged from original, omitted here for brevity)
     * -----------------------------------------------------------*/

    /* -------------------------------------------------------------
     * TEST 8 – User Query for Assignees
     * -----------------------------------------------------------*/
    $assignee_users = get_users( [
        'capability' => 'publish_posts',
        'fields'     => 'ID',
    ] );

    $results[] = [
        'name'    => 'User Query for Assignees',
        'status'  => ( ! empty( $assignee_users ) ) ? 'Pass' : 'Fail',
        'message' => ( ! empty( $assignee_users ) )
            ? 'Found ' . count( $assignee_users ) . ' potential assignees.'
            : 'No users with “publish_posts” capability found; Assignee dropdown may be empty.',
    ];

    /* -------------------------------------------------------------
 * TEST 9 – Taxonomy Registration & Visibility
 * -----------------------------------------------------------*/
$taxonomies_to_check = [ 'client', 'project', 'task_status' ];
$errors = [];

foreach ( $taxonomies_to_check as $tax_slug ) {
    $tax_obj = get_taxonomy( $tax_slug );
    
    if ( ! $tax_obj ) {
        $errors[] = "Taxonomy '{$tax_slug}' is not registered.";
        continue;
    }
    
    // Check if taxonomy has UI visibility
    if ( empty( $tax_obj->show_ui ) ) {
        $errors[] = "Taxonomy '{$tax_slug}' has show_ui disabled.";
    }
    
    // Check if taxonomy is visible in menu (can be true or a string)
    if ( empty( $tax_obj->show_in_menu ) ) {
        $errors[] = "Taxonomy '{$tax_slug}' has show_in_menu disabled.";
    }
    
    // Check if associated with project_task post type
    if ( ! in_array( 'project_task', (array) $tax_obj->object_type, true ) ) {
        $errors[] = "Taxonomy '{$tax_slug}' is not associated with the 'project_task' post type.";
    }
}

$results[] = [
    'name'    => 'Taxonomy Registration & Visibility',
    'status'  => empty( $errors ) ? 'Pass' : 'Fail',
    'message' => empty( $errors )
        ? 'All taxonomies are correctly registered and configured for menu visibility.'
        : implode( ' ', $errors ),
];

    /* -------------------------------------------------------------*/

    $timestamp = current_time( 'timestamp' );
    update_option( 'ptt_tests_last_run', $timestamp );

    wp_send_json_success( [
        'results' => $results,
        'time'    => date_i18n( get_option( 'time_format' ), $timestamp ),
    ] );
}
add_action( 'wp_ajax_ptt_run_self_tests', 'ptt_run_self_tests_callback' );

/**
 * AJAX handler to copy post authors into the Assignee field.
 */
function ptt_sync_authors_assignee_callback() {
    check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied.' ] );
    }

    $posts = get_posts( [
        'post_type'      => 'project_task',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    ] );

    $count = 0;
    foreach ( $posts as $post_id ) {
        $author_id = (int) get_post_field( 'post_author', $post_id );
        update_post_meta( $post_id, 'ptt_assignee', $author_id );
        $count++;
    }

    wp_send_json_success( [ 'count' => $count ] );
}
add_action( 'wp_ajax_ptt_sync_authors_assignee', 'ptt_sync_authors_assignee_callback' );
