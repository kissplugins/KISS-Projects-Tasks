<?php

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

// =================================================================
// 11.0 SELF-TEST MODULE
// =================================================================

/**
 * Adds the "Settings" link under the Tasks CPT menu.
 */
function ptt_add_settings_submenu_page() {
    add_submenu_page(
        'edit.php?post_type=project_task', // Parent slug
        'Tracker Settings',                // Page title
        'Settings - v' . PTT_VERSION,      // Menu title - UPDATED
        'manage_options',                  // Capability
        'ptt-self-test',                   // Menu slug
        'ptt_self_test_page_html'          // Function
    );
}
add_action('admin_menu', 'ptt_add_settings_submenu_page');

/**
 * Renders the Self Test page HTML.
 */
function ptt_self_test_page_html() {
    ?>
    <div class="wrap">
        <h1>Plugin Settings & Self Test</h1>
        <p>This module helps verify core plugin functionality. It creates and then immediately deletes test data.</p>
        <button id="ptt-run-self-tests" class="button button-primary">Re-Run Tests</button>
        <p id="ptt-last-test-time">
            <?php
            $last_run = get_option( 'ptt_tests_last_run' );
            if ( $last_run ) {
                echo 'Tests Last Ran at ' . esc_html( date_i18n( get_option( 'time_format' ), $last_run ) );
            } else {
                echo 'Tests Last Ran at --:--:--';
            }
            ?>
        </p>
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
    
    $timestamp = current_time( 'timestamp' );
    update_option( 'ptt_tests_last_run', $timestamp );

    wp_send_json_success([
        'results' => $results,
        'time'    => date_i18n( get_option( 'time_format' ), $timestamp ),
    ]);
}
add_action( 'wp_ajax_ptt_run_self_tests', 'ptt_run_self_tests_callback' );