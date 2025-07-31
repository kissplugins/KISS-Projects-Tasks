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

    // Test 4: Status Update Logic
    $status_term = wp_insert_term('SELF TEST STATUS ' . wp_rand(), 'task_status');
    $status_post = wp_insert_post([
        'post_type'   => 'project_task',
        'post_title'  => 'STATUS TEST',
        'post_status' => 'publish'
    ]);

    if ($status_post && !is_wp_error($status_post) && $status_term && !is_wp_error($status_term)) {
        wp_set_object_terms($status_post, $status_term['term_id'], 'task_status', false);
        $assigned = has_term($status_term['term_id'], 'task_status', $status_post);
        if ($assigned) {
            $results[] = ['name' => 'Status Update Logic', 'status' => 'Pass', 'message' => 'Core status assignment successful.'];
        } else {
            $results[] = ['name' => 'Status Update Logic', 'status' => 'Fail', 'message' => 'wp_set_object_terms failed to assign the status.'];
        }
    } else {
        $results[] = ['name' => 'Status Update Logic', 'status' => 'Fail', 'message' => 'Could not create test post or term for status update test.'];
    }
    
    // Cleanup for Test 4 - This is now unconditional to ensure deletion.
    wp_delete_post($status_post, true);
    if ($status_term && !is_wp_error($status_term)) {
        wp_delete_term($status_term['term_id'], 'task_status');
    }

    // Test 5: Reporting Logic
    $report_client  = wp_insert_term('REPORT CLIENT ' . wp_rand(), 'client');
    $report_project = wp_insert_term('REPORT PROJECT ' . wp_rand(), 'project');
    $report_status  = wp_insert_term('REPORT STATUS ' . wp_rand(), 'task_status');
    $admin_id = get_current_user_id();
    $report_post1 = wp_insert_post([
        'post_type'   => 'project_task',
        'post_title'  => 'REPORT POST 1',
        'post_status' => 'publish',
        'post_author' => $admin_id
    ]);
    wp_update_post([
        'ID'            => $report_post1,
        'post_date'     => '2025-07-20 08:00:00',
        'post_date_gmt' => get_gmt_from_date('2025-07-20 08:00:00')
    ]);
    wp_set_object_terms($report_post1, $report_client['term_id'], 'client');
    wp_set_object_terms($report_post1, $report_project['term_id'], 'project');
    wp_set_object_terms($report_post1, $report_status['term_id'], 'task_status');
    update_field('start_time', '2025-07-20 08:00:00', $report_post1);
    update_field('stop_time', '2025-07-20 09:00:00', $report_post1);
    ptt_calculate_and_save_duration($report_post1);
    $report_post2 = wp_insert_post([
        'post_type'   => 'project_task',
        'post_title'  => 'REPORT POST 2',
        'post_status' => 'publish',
        'post_author' => $admin_id
    ]);
    wp_update_post([
        'ID'            => $report_post2,
        'post_date'     => '2025-07-21 08:00:00',
        'post_date_gmt' => get_gmt_from_date('2025-07-21 08:00:00')
    ]);
    wp_set_object_terms($report_post2, $report_client['term_id'], 'client');
    wp_set_object_terms($report_post2, $report_project['term_id'], 'project');
    wp_set_object_terms($report_post2, $report_status['term_id'], 'task_status');
    update_field('start_time', '2025-07-21 08:00:00', $report_post2);
    update_field('stop_time', '2025-07-21 10:00:00', $report_post2);
    ptt_calculate_and_save_duration($report_post2);
    $other_user = wp_insert_user([
        'user_login' => 'ptt_other_' . wp_generate_password(4, false),
        'user_pass'  => wp_generate_password(),
        'role'       => 'subscriber'
    ]);
    $report_post3 = wp_insert_post([
        'post_type'   => 'project_task',
        'post_title'  => 'REPORT POST 3',
        'post_status' => 'publish',
        'post_author' => $other_user
    ]);
    wp_set_object_terms($report_post3, $report_client['term_id'], 'client');
    wp_set_object_terms($report_post3, $report_project['term_id'], 'project');
    wp_set_object_terms($report_post3, $report_status['term_id'], 'task_status');
    update_field('start_time', '2025-07-21 08:00:00', $report_post3);
    update_field('stop_time', '2025-07-21 09:00:00', $report_post3);
    ptt_calculate_and_save_duration($report_post3);

    $args = [
        'post_type'      => 'project_task',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'orderby'        => ['author' => 'ASC', 'date' => 'ASC'],
        'author'         => $admin_id,
        'tax_query'      => [
            [
                'taxonomy' => 'client',
                'field'    => 'term_id',
                'terms'    => $report_client['term_id'],
            ],
        ],
        'date_query'     => [
            [
                'after'     => '2025-07-19 00:00:00',
                'before'    => '2025-07-22 23:59:59',
                'inclusive' => true,
            ],
        ],
    ];
    $report_query = new WP_Query($args);
    $grand = 0.0;
    if ($report_query->have_posts()) {
        foreach ($report_query->posts as $rp) {
            $grand += (float) get_field('calculated_duration', $rp->ID);
        }
    }
    wp_reset_postdata();

    if ($report_query->post_count === 2 && number_format($grand, 2) === '3.00') {
        $results[] = ['name' => 'Reporting Logic', 'status' => 'Pass', 'message' => 'Report query returned expected posts and total.'];
    } else {
        $results[] = ['name' => 'Reporting Logic', 'status' => 'Fail', 'message' => 'Unexpected report results.'];
    }

    wp_delete_post($report_post1, true);
    wp_delete_post($report_post2, true);
    wp_delete_post($report_post3, true);
    wp_delete_user($other_user);
    wp_delete_term($report_client['term_id'], 'client');
    wp_delete_term($report_project['term_id'], 'project');
    wp_delete_term($report_status['term_id'], 'task_status');

    // Test 6: Multi-Session Duration Calculation
    $session_post_id = wp_insert_post(['post_type' => 'project_task', 'post_title' => 'SESSION TEST POST', 'post_status' => 'publish']);
    if ($session_post_id && !is_wp_error($session_post_id)) {
        $sessions_data = [
            [
                'session_start_time' => '2025-07-22 10:00:00', // 1.5 hours
                'session_stop_time'  => '2025-07-22 11:30:00',
            ],
            [
                'session_manual_override' => true,
                'session_manual_duration' => '0.5', // 0.5 hours
            ],
        ];
        update_field('sessions', $sessions_data, $session_post_id);
        
        // Recalculate all durations
        ptt_recalculate_on_save($session_post_id);

        $total_duration = get_field('calculated_duration', $session_post_id);
        
        if ($total_duration == '2.00') {
            $results[] = ['name' => 'Multi-Session Calculation', 'status' => 'Pass', 'message' => 'Correctly calculated total from mixed sessions (1.5 + 0.5 = 2.00).'];
        } else {
            $results[] = ['name' => 'Multi-Session Calculation', 'status' => 'Fail', 'message' => 'Calculation incorrect. Expected 2.00, got ' . $total_duration];
        }

        wp_delete_post($session_post_id, true);
    } else {
        $results[] = ['name' => 'Multi-Session Calculation', 'status' => 'Fail', 'message' => 'Could not create post for session test.'];
    }

    // Test 7: Report Date Range Filter
    $range_post1 = wp_insert_post(['post_type' => 'project_task', 'post_title' => 'RANGE POST 1', 'post_status' => 'publish']);
    $range_post2 = wp_insert_post(['post_type' => 'project_task', 'post_title' => 'RANGE POST 2', 'post_status' => 'publish']);
    $range_post3 = wp_insert_post(['post_type' => 'project_task', 'post_title' => 'RANGE POST 3', 'post_status' => 'publish']);
    if (
        $range_post1 && $range_post2 && $range_post3 &&
        ! is_wp_error($range_post1) && ! is_wp_error($range_post2) && ! is_wp_error($range_post3)
    ) {
        wp_update_post([
            'ID'            => $range_post1,
            'post_date'     => '2025-07-10 09:00:00',
            'post_date_gmt' => get_gmt_from_date('2025-07-10 09:00:00')
        ]);
        wp_update_post([
            'ID'            => $range_post2,
            'post_date'     => '2025-07-22 09:00:00',
            'post_date_gmt' => get_gmt_from_date('2025-07-22 09:00:00')
        ]);
        wp_update_post([
            'ID'            => $range_post3,
            'post_date'     => '2025-07-23 09:00:00',
            'post_date_gmt' => get_gmt_from_date('2025-07-23 09:00:00')
        ]);

        update_field('sessions', [
            [
                'session_start_time' => '2025-07-20 09:00:00',
                'session_stop_time'  => '2025-07-20 10:00:00',
            ],
        ], $range_post1);
        update_field('sessions', [
            [
                'session_start_time' => '2025-07-22 09:00:00',
                'session_stop_time'  => '2025-07-22 10:00:00',
            ],
        ], $range_post2);
        update_field('sessions', [
            [
                'session_start_time' => '2025-07-23 09:00:00',
                'session_stop_time'  => '2025-07-23 10:00:00',
            ],
        ], $range_post3);

        ptt_calculate_and_save_duration($range_post1);
        ptt_calculate_and_save_duration($range_post2);
        ptt_calculate_and_save_duration($range_post3);

        $args_range = [
            'post_type'      => 'project_task',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => [ 'author' => 'ASC', 'date' => 'ASC' ],
            // Limit the query strictly to the posts created for this test so pre-existing
            // tasks in the database do not affect the results.
            'post__in'       => [ $range_post1, $range_post2, $range_post3 ],
        ];
        $q_range       = new WP_Query( $args_range );
        $start_ts      = strtotime( '2025-07-20 00:00:00' );
        $end_ts        = strtotime( '2025-07-22 23:59:59' );
        $included_post = [];
        if ($q_range->have_posts()) {
            while ($q_range->have_posts()) {
                $q_range->the_post();
                $pid          = get_the_ID();
                $is_relevant  = false;
                $creation_ts  = get_the_date( 'U', $pid );
                if ( $creation_ts >= $start_ts && $creation_ts <= $end_ts ) {
                    $is_relevant = true;
                }
                if ( ! $is_relevant ) {
                    $sessions = get_field( 'sessions', $pid );
                    if ( ! empty( $sessions ) && is_array( $sessions ) ) {
                        foreach ( $sessions as $session ) {
                            if ( ! empty( $session['session_start_time'] ) ) {
                                $session_ts = strtotime( $session['session_start_time'] );
                                if ( $session_ts >= $start_ts && $session_ts <= $end_ts ) {
                                    $is_relevant = true;
                                    break;
                                }
                            }
                        }
                    }
                }
                if ( $is_relevant ) {
                    $included_post[] = $pid;
                }
            }
        }
        wp_reset_postdata();

        $expected = [ $range_post1, $range_post2 ];
        sort( $expected );
        sort( $included_post );
        if ( $included_post === $expected ) {
            $results[] = [
                'name'    => 'Report Date Range Filter',
                'status'  => 'Pass',
                'message' => 'Date range filtering returned expected tasks.',
            ];
        } else {
            $missing    = array_diff( $expected, $included_post );
            $unexpected = array_diff( $included_post, $expected );
            $parts      = [];
            if ( ! empty( $missing ) ) {
                $parts[] = 'Missing tasks: ' . implode( ',', $missing );
            }
            if ( ! empty( $unexpected ) ) {
                $parts[] = 'Unexpected tasks: ' . implode( ',', $unexpected );
            }
            $results[] = [
                'name'    => 'Report Date Range Filter',
                'status'  => 'Fail',
                'message' => implode( '; ', $parts ) . '.',
            ];
        }

        wp_delete_post($range_post1, true);
        wp_delete_post($range_post2, true);
        wp_delete_post($range_post3, true);
    } else {
        $results[] = ['name' => 'Report Date Range Filter', 'status' => 'Fail', 'message' => 'Could not create posts for date range test.'];
    }
    
    $timestamp = current_time( 'timestamp' );
    update_option( 'ptt_tests_last_run', $timestamp );

    wp_send_json_success([
        'results' => $results,
        'time'    => date_i18n( get_option( 'time_format' ), $timestamp ),
    ]);
}
add_action( 'wp_ajax_ptt_run_self_tests', 'ptt_run_self_tests_callback' );