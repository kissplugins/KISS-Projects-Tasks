<?php
namespace KISS\PTT\Diagnostics;

class SelfTests {
    /**
     * Run the complete self-test suite and return structured results.
     * Note: Does not echo/exit; the caller is responsible for output/JSON.
     * @return array<int, array{name:string,status:string,message:string}>
     */
    public static function run(): array {
        $results = [];
        $currentTest = 'Initialization';

        // Ensure compatibility layers are loaded for PSR-4 migrated classes
        self::ensureCompatibilityLayersLoaded();

        try {
            // TEST 0 – ACF Schema Status (rolled into main group)
            $currentTest = 'ACF Schema Status';
            if ( class_exists('KISS\\PTT\\Integration\\ACF\\Diagnostics') ) {
                $issues = \KISS\PTT\Integration\ACF\Diagnostics::collectIssues();
                $ok = empty($issues);
                $results[] = [
                    'name'    => 'ACF Schema Status',
                    'status'  => $ok ? 'Pass' : 'Fail',
                    'message' => $ok ? 'All required ACF groups/fields look good.' : ( 'Warnings: ' . implode('; ', $issues) ),
                ];
            }
        } catch (\Throwable $e) {
            $results[] = [
                'name' => $currentTest,
                'status' => 'Error',
                'message' => 'FATAL ERROR: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')'
            ];
        }

        try {
            // TEST 0.5 – Cleanup orphaned test posts from previous runs
            $currentTest = 'Cleanup Orphaned Test Posts';

            // Define all test post patterns to clean up
            $test_patterns = [
                'CALC TEST POST',
                'SELF TEST POST',
                'TODAY DATE INCLUSION TEST',
                'STATUS TEST',
                'Session Move Source',
                'Session Move Target',
                'SELF TEST - AUTO TIMESTAMP',
                'Test Task A1',
                'Test Task B1',
                'Test Task A2/B2',
                'Convert Legacy Task Timer to New Sessions',
                'Today' // Exact match for single word "Today"
            ];

            $cleanup_count = 0;
            $cleanup_details = [];

            foreach ($test_patterns as $pattern) {
                // Search for posts containing this pattern
                $test_posts = get_posts([
                    'post_type' => 'project_task',
                    'post_status' => 'any',
                    'numberposts' => -1,
                    's' => $pattern
                ]);

                foreach ($test_posts as $post) {
                    // Check if this is actually a test post (avoid false positives)
                    $is_test_post = false;

                    if ($pattern === 'Today') {
                        // For "Today", only match exact title to avoid false positives
                        $is_test_post = ($post->post_title === 'Today');
                    } else {
                        // For other patterns, check if title contains the pattern
                        $is_test_post = (strpos($post->post_title, $pattern) !== false);
                    }

                    if ($is_test_post) {
                        wp_delete_post($post->ID, true);
                        $cleanup_count++;
                        $cleanup_details[] = $post->post_title;
                    }
                }
            }

            $message = $cleanup_count > 0
                ? "Cleaned up {$cleanup_count} orphaned test posts: " . implode(', ', array_slice($cleanup_details, 0, 5)) . ($cleanup_count > 5 ? '...' : '')
                : 'No orphaned test posts found.';

            $results[] = [
                'name' => 'Cleanup Orphaned Test Posts',
                'status' => 'Pass',
                'message' => $message
            ];
        } catch (\Throwable $e) {
            $results[] = [
                'name' => $currentTest,
                'status' => 'Error',
                'message' => 'FATAL ERROR: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')'
            ];
        }

        try {
            // TEST 1 – Task Post Save & Assignee Update
            $currentTest = 'Task Post Save & Assignee Update';
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
                    'message' => ( $saved_assignee === $admin_id ) ? 'Successfully created post and updated its assignee meta field.' : 'Created post but failed to update or verify assignee meta field.',
                ];
                wp_delete_post( $test_post_id, true );
            } else {
                $results[] = [ 'name' => 'Task Post Save & Assignee Update', 'status' => 'Fail', 'message' => 'Failed to create test post.' ];
            }
        } catch (\Throwable $e) {
            $results[] = [
                'name' => $currentTest,
                'status' => 'Error',
                'message' => 'FATAL ERROR: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')'
            ];
        }

        try {
            // TEST 2 – Create Client & Project taxonomies
            $currentTest = 'Create Client & Project';
            $client_term  = wp_insert_term( 'SELF TEST CLIENT',  'client'  );
            $project_term = wp_insert_term( 'SELF TEST PROJECT', 'project' );
            if ( ! is_wp_error( $client_term ) && ! is_wp_error( $project_term ) ) {
                $results[] = [ 'name' => 'Create Client & Project', 'status' => 'Pass', 'message' => 'Successfully created test taxonomies.' ];
                wp_delete_term( $client_term['term_id'],  'client' );
                wp_delete_term( $project_term['term_id'], 'project' );
            } else {
                $results[] = [ 'name' => 'Create Client & Project', 'status' => 'Fail', 'message' => 'Failed to create test taxonomies.' ];
            }
        } catch (\Throwable $e) {
            $results[] = [
                'name' => $currentTest,
                'status' => 'Error',
                'message' => 'FATAL ERROR: ' . $e->getMessage() . ' (File: ' . $e->getFile() . ', Line: ' . $e->getLine() . ')'
            ];
        }

        // TEST 3 – Calculate Total Time (session-based approach)
        $calc_post = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'CALC TEST POST', 'post_status' => 'publish' ] );
        if ( $calc_post && ! is_wp_error( $calc_post ) ) {
            try {
                if ( function_exists('update_field') ) {
                    // Test 1: 1h 30m session (10:00 to 11:30)
                    $session_1h30m = [
                        'session_title' => 'Test Session 1h30m',
                        'session_start_time' => '2025-07-19 10:00:00',
                        'session_stop_time' => '2025-07-19 11:30:00',
                        'session_manual_override' => false,
                        'session_manual_duration' => '',
                    ];
                    update_field( 'sessions', [ $session_1h30m ], $calc_post );
                    $duration = \ptt_calculate_and_save_duration( $calc_post );
                    $results[] = [ 'name' => 'Calculate Total Time (1h 30m)', 'status' => ( $duration === '1.50' ) ? 'Pass' : 'Fail', 'message' => ( $duration === '1.50' ) ? 'Correctly calculated 1.50 hours from session.' : "Calculation incorrect. Expected 1.50, got {$duration}." ];

                    // Test 2: 1 minute session (12:00 to 12:01) - should round to 0.02 hours
                    $session_1min = [
                        'session_title' => 'Test Session 1min',
                        'session_start_time' => '2025-07-19 12:00:00',
                        'session_stop_time' => '2025-07-19 12:01:00',
                        'session_manual_override' => false,
                        'session_manual_duration' => '',
                    ];
                    update_field( 'sessions', [ $session_1h30m, $session_1min ], $calc_post );
                    $total_duration = \ptt_calculate_and_save_duration( $calc_post );
                    // Total should be 1.50 + 0.02 = 1.52 (1h30m + 1min rounded)
                    $expected_total = 1.52; // 1.50 + 0.02
                    $results[] = [ 'name' => 'Calculate Total Time (Rounding)', 'status' => ( abs((float)$total_duration - $expected_total) < 0.01 ) ? 'Pass' : 'Fail', 'message' => ( abs((float)$total_duration - $expected_total) < 0.01 ) ? 'Correctly calculated and rounded session durations.' : "Expected ~{$expected_total} hours, got {$total_duration}." ];
                } else {
                    $results[] = [ 'name' => 'Calculate Total Time', 'status' => 'Skip', 'message' => 'ACF functions are not available; skipping duration calculation test.' ];
                }
            } finally {
                // Always cleanup, even if test fails
                wp_delete_post( $calc_post, true );
            }
        } else {
            $results[] = [ 'name' => 'Calculate Total Time', 'status' => 'Fail', 'message' => 'Could not create post for calculation test.' ];
        }

        // TEST 4 – Status Update Logic
        $status_term = wp_insert_term( 'SELF TEST STATUS ' . wp_rand(), 'task_status' );
        $status_post = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'STATUS TEST', 'post_status' => 'publish' ] );
        if ( $status_post && ! is_wp_error( $status_post ) && $status_term && ! is_wp_error( $status_term ) ) {
            wp_set_object_terms( $status_post, $status_term['term_id'], 'task_status', false );
            $assigned = has_term( $status_term['term_id'], 'task_status', $status_post );
            $results[] = [ 'name' => 'Status Update Logic', 'status' => $assigned ? 'Pass' : 'Fail', 'message' => $assigned ? 'Core status assignment successful.' : 'wp_set_object_terms failed to assign status.' ];
        } else {
            $results[] = [ 'name' => 'Status Update Logic', 'status' => 'Fail', 'message' => 'Could not create test post or term for status update test.' ];
        }
        wp_delete_post( $status_post, true );
        if ( $status_term && ! is_wp_error( $status_term ) ) { wp_delete_term( $status_term['term_id'], 'task_status' ); }

        // TEST 8 – User Query for Assignees
        $assignee_users = get_users( [ 'capability' => 'publish_posts', 'fields' => 'ID' ] );
        $results[] = [ 'name' => 'User Query for Assignees', 'status' => ( ! empty( $assignee_users ) ) ? 'Pass' : 'Fail', 'message' => ( ! empty( $assignee_users ) ) ? 'Found ' . count( $assignee_users ) . ' potential assignees.' : 'No users with “publish_posts” capability found; Assignee dropdown may be empty.' ];

        // TEST 9 – Taxonomy Registration & Visibility
        $taxonomies_to_check = [ 'client', 'project', 'task_status' ];
        $errors = [];
        foreach ( $taxonomies_to_check as $tax_slug ) {
            $tax_obj = get_taxonomy( $tax_slug );
            if ( ! $tax_obj ) { $errors[] = "Taxonomy '{$tax_slug}' is not registered."; continue; }
            if ( empty( $tax_obj->show_ui ) ) { $errors[] = "Taxonomy '{$tax_slug}' has show_ui disabled."; }
            if ( empty( $tax_obj->show_in_menu ) ) { $errors[] = "Taxonomy '{$tax_slug}' has show_in_menu disabled."; }
            if ( ! in_array( 'project_task', (array) $tax_obj->object_type, true ) ) { $errors[] = "Taxonomy '{$tax_slug}' is not associated with the 'project_task' post type."; }
        }
        $results[] = [ 'name' => 'Taxonomy Registration & Visibility', 'status' => empty( $errors ) ? 'Pass' : 'Fail', 'message' => empty( $errors ) ? 'All taxonomies are correctly registered and configured for menu visibility.' : implode( ' ', $errors ) ];

        // TEST 10 – Today Page User Data Isolation
        $user_a_id = wp_insert_user( [ 'user_login' => 'test_user_a', 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );
        $user_b_id = wp_insert_user( [ 'user_login' => 'test_user_b', 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );
        if ( is_wp_error( $user_a_id ) || is_wp_error( $user_b_id ) ) {
            $results[] = [ 'name' => 'User Data Isolation', 'status' => 'Fail', 'message' => 'Could not create test users.' ];
        } else {
            $task1 = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'Test Task A1', 'post_author' => $user_a_id, 'post_status' => 'publish' ]);
            update_post_meta($task1, 'ptt_assignee', $user_a_id);
            $task2 = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'Test Task B1', 'post_author' => $user_b_id, 'post_status' => 'publish' ]);
            update_post_meta($task2, 'ptt_assignee', $user_b_id);
            $task3 = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'Test Task A2/B2', 'post_author' => $user_a_id, 'post_status' => 'publish' ]);
            update_post_meta($task3, 'ptt_assignee', $user_b_id);
            $tasks_for_a = \ptt_get_tasks_for_user( $user_a_id );
            $tasks_for_b = \ptt_get_tasks_for_user( $user_b_id );
            $pass_a = count( $tasks_for_a ) === 1 && in_array( $task1, $tasks_for_a );
            $pass_b = count( $tasks_for_b ) === 2 && in_array( $task2, $tasks_for_b ) && in_array( $task3, $tasks_for_b );
            if ($pass_a && $pass_b) {
                $results[] = [ 'name' => 'User Data Isolation', 'status' => 'Pass', 'message' => 'ptt_get_tasks_for_user() correctly isolated tasks for assignees.' ];
            } else {
                $fail_message = 'ptt_get_tasks_for_user() failed. ';
                if (!$pass_a) $fail_message .= 'User A expected 1 task, got ' . count($tasks_for_a) . '. ';
                if (!$pass_b) $fail_message .= 'User B expected 2 tasks, got ' . count($tasks_for_b) . '. ';
                $results[] = [ 'name' => 'User Data Isolation', 'status' => 'Fail', 'message' => trim($fail_message) ];
            }
            wp_delete_post( $task1, true ); wp_delete_post( $task2, true ); wp_delete_post( $task3, true );
            require_once( ABSPATH . 'wp-admin/includes/user.php');
            wp_delete_user( $user_a_id ); wp_delete_user( $user_b_id );
        }

        // TEST 11 – Move Session Between Tasks
        $source_task = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Session Move Source', 'post_status' => 'publish' ] );
        $target_task = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Session Move Target', 'post_status' => 'publish' ] );
        if ( $source_task && ! is_wp_error( $source_task ) && $target_task && ! is_wp_error( $target_task ) ) {
            if ( function_exists('add_row') && function_exists('get_field') ) {
                $session_data = [ 'session_title' => 'Move Test', 'session_notes' => '', 'session_start_time' => '', 'session_stop_time' => '', 'session_manual_override' => 1, 'session_manual_duration' => 1.5, 'session_calculated_duration' => '1.50' ];
                $row = add_row( 'sessions', $session_data, $source_task );
                \ptt_calculate_and_save_duration( $source_task );
                $move_result = \ptt_move_session_to_task( $source_task, $row - 1, $target_task );
                $source_sessions = get_field( 'sessions', $source_task );
                $target_sessions = get_field( 'sessions', $target_task );
                // Calculate totals using session-only approach (calculated_duration field removed)
                $source_total = \ptt_calculate_and_save_duration( $source_task );
                $target_total = \ptt_calculate_and_save_duration( $target_task );
                $pass = ( $move_result !== false && empty( $source_sessions ) && is_array( $target_sessions ) && count( $target_sessions ) === 1 && $source_total === '0.00' && $target_total === '1.50' );
                $results[] = [ 'name' => 'Move Session Between Tasks', 'status' => $pass ? 'Pass' : 'Fail', 'message' => $pass ? 'Session reassigned successfully.' : 'Failed to reassign session correctly.' ];
            } else {
                $results[] = [ 'name' => 'Move Session Between Tasks', 'status' => 'Skip', 'message' => 'ACF functions are not available; skipping session move test.' ];
            }
            wp_delete_post( $source_task, true ); wp_delete_post( $target_task, true );
        } else {
            $results[] = [ 'name' => 'Move Session Between Tasks', 'status' => 'Fail', 'message' => 'Could not create test tasks for session move.' ];
        }

        // TEST 12 – Manual Session Not Auto-Timestamped
        $timestamp_post = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'SELF TEST - MANUAL NO AUTOSTAMP', 'post_status' => 'publish' ] );
        if ( $timestamp_post && ! is_wp_error( $timestamp_post ) ) {
            if ( function_exists('update_field') && function_exists('get_field') ) {
                $session_row = [ 'session_title' => 'Manual session without timestamps', 'session_start_time' => '', 'session_stop_time' => '', 'session_manual_override' => 1, 'session_manual_duration' => 0.5 ];
                update_field( 'sessions', [ $session_row ], $timestamp_post );

                // Trigger any duration calc without assigning timestamps
                if ( function_exists( 'ptt_calculate_and_save_duration' ) ) { \ptt_calculate_and_save_duration( $timestamp_post ); }

                $saved = get_field( 'sessions', $timestamp_post );
                $pass = false; $fail = 'An unknown error occurred during verification.'; $debug = '';
                if ( empty( $saved ) || ! is_array( $saved ) ) { $fail = 'Failed at step 1: The session data was not saved or was empty after retrieval.'; }
                else {
                    $first = $saved[0];
                    if ( !empty( $first['session_start_time'] ) || !empty( $first['session_stop_time'] ) ) {
                        $fail = 'Manual session without timestamps was altered (timestamps were added unexpectedly).';
                    } else {
                        $pass = true;
                    }
                }
                $results[] = [ 'name' => 'Manual Session Not Auto-Timestamped', 'status' => $pass ? 'Pass' : 'Fail', 'message' => $pass ? 'Manual session without timestamps remains unchanged; no auto-timestamp applied.' : $fail . $debug ];
            } else {
                $results[] = [ 'name' => 'Manual Session Not Auto-Timestamped', 'status' => 'Skip', 'message' => 'ACF functions are not available; skipping test.' ];
            }
            wp_delete_post( $timestamp_post, true );
        } else {
            $results[] = [ 'name' => 'Manual Session Not Auto-Timestamped', 'status' => 'Fail', 'message' => 'Could not create the test post required for the test.' ];
        }

        // TEST 8 – Data Structure Integrity (full sweep)
        $results = array_merge($results, self::dataStructureIntegrityLegacy());

        // TEST – Reports Assets Enqueued on Reports Page
        ob_start();
        do_action( 'admin_enqueue_scripts', 'project_task_page_ptt-reports' );
        ob_end_clean();
        $enqueued = wp_script_is( 'ptt-scripts', 'enqueued' );
        $results[] = [
            'name'    => 'Reports UI: Core assets enqueued',
            'status'  => $enqueued ? 'Pass' : 'Fail',
            'message' => $enqueued ? 'scripts.js/styles.css are enqueued on Reports screen.' : 'scripts.js not enqueued on Reports screen; week buttons may not work.'
        ];

        // TEST – Last Week button handler present in scripts
        $scripts_path = PTT_PLUGIN_DIR . 'scripts.js';
        $content = is_readable( $scripts_path ) ? file_get_contents( $scripts_path ) : '';
        $has_selector = strpos( $content, "#set-last-week" ) !== false;
        $has_delegate = ( strpos( $content, "$(document).on('click', '#set-last-week'" ) !== false ) || ( strpos( $content, '$(document).on("click", "#set-last-week"' ) !== false );
        $handler_ok = $has_selector && $has_delegate;
        $results[] = [
            'name'    => 'Reports UI: Last Week button handler present',
            'status'  => $handler_ok ? 'Pass' : 'Fail',
            'message' => $handler_ok ? 'Delegated click handler found in scripts.js.' : 'Could not find delegated click handler for #set-last-week in scripts.js.'
        ];

        // TEST – Last Week range calculation (server mirror of JS)
        $now_ts = current_time( 'timestamp' );
        $day = (int) date( 'w', $now_ts ); // 0 = Sunday
        $sunday_this_week = strtotime( '-' . $day . ' days', $now_ts );
        $last_sunday_ts   = strtotime( '-7 days', $sunday_this_week );
        $last_saturday_ts = strtotime( '-1 day', $sunday_this_week );
        $start_str = date( 'Y-m-d', $last_sunday_ts );
        $end_str   = date( 'Y-m-d', $last_saturday_ts );
        $span_days = (int) round( ( $last_saturday_ts - $last_sunday_ts ) / DAY_IN_SECONDS );
        $range_ok  = ( $span_days === 6 ) && ( $last_sunday_ts < $last_saturday_ts );
        $results[] = [
            'name'    => 'Reports: Last Week range computation (server mirror)',
            'status'  => $range_ok ? 'Pass' : 'Fail',
            'message' => $range_ok ? "Computed range {$start_str} to {$end_str} (7 days inclusive)." : "Unexpected range {$start_str} to {$end_str} (span_days={$span_days})."
        ];

        // TEST – Today session appears on local date boundary
        $tz = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone(get_option('timezone_string') ?: 'UTC');
        $local_now = new \DateTime('now', $tz);
        $local_today_str = $local_now->format('Y-m-d');
        $utc_now = clone $local_now; $utc_now->setTimezone(new \DateTimeZone('UTC'));
        $utc_str = $utc_now->format('Y-m-d H:i:s');

        $task_id = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'TODAY DATE INCLUSION TEST', 'post_status' => 'publish' ]);
        if ( $task_id && ! is_wp_error($task_id) ) {
            if ( function_exists('add_row') ) {
                add_row('sessions', [ 'session_title' => 'Test', 'session_start_time' => $utc_str, 'session_stop_time' => $utc_str ], $task_id);
            }
            if ( function_exists('ptt_get_tasks_for_user') ) {
                $user_id = get_current_user_id();
                update_post_meta( $task_id, 'ptt_assignee', $user_id );
                $candidate_statuses = [ 'In Progress', 'Not Started', 'Completed', 'Blocked' ];
                foreach ( $candidate_statuses as $name ) {
                    $term = get_term_by( 'name', $name, 'task_status' );
                    if ( $term && ! is_wp_error( $term ) ) { wp_set_object_terms( $task_id, [ $term->term_id ], 'task_status', false ); break; }
                }
                // Use PSR-4 class directly, with fallback to procedural class
                if (class_exists('\KISS\PTT\Presentation\Today\DataProvider')) {
                    $entries = \KISS\PTT\Presentation\Today\DataProvider::getDailyEntries( $user_id, $local_today_str, [] );
                } elseif (class_exists('PTT_Today_Data_Provider')) {
                    $entries = \PTT_Today_Data_Provider::get_daily_entries( $user_id, $local_today_str, [] );
                } else {
                    $entries = [];
                }
                $found = false;
                foreach ( $entries as $e ) { if ( $e['post_id'] === $task_id ) { $found = true; break; } }
                $results[] = [
                    'name'    => 'Today: Session included on local date',
                    'status'  => $found ? 'Pass' : 'Fail',
                    'message' => $found ? 'Session with UTC timestamp visible on the same local day.' : 'Session not visible on Today for local date.',
                ];
            } else {
                $results[] = [ 'name' => 'Today: Session included on local date', 'status' => 'Fail', 'message' => 'Provider not available in this environment.' ];
            }
            wp_delete_post( $task_id, true );
        } else {
            $results[] = [ 'name' => 'Today: Session included on local date', 'status' => 'Fail', 'message' => 'Could not create test task.' ];
        }

        return $results;
    }

    private static function dataStructureIntegrity(): array {
        $results = [];
        // Post Type
        $post_types = get_post_types( [], 'objects' );
        $project_task_exists = isset( $post_types['project_task'] );
        $results[] = [ 'name' => 'Post Type: project_task', 'status' => $project_task_exists ? 'Pass' : 'Fail', 'message' => $project_task_exists ? 'project_task post type is properly registered.' : 'CRITICAL: project_task post type is missing!' ];
        // Taxonomies
        $taxonomies = get_taxonomies( [], 'objects' );
        foreach ( ['client','project','task_status'] as $taxonomy ) {
            $exists = isset( $taxonomies[$taxonomy] );
            $results[] = [ 'name' => "Taxonomy: {$taxonomy}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "$taxonomy taxonomy is properly registered." : "CRITICAL: {$taxonomy} taxonomy is missing!" ];
        }
        // ACF Groups
        if ( function_exists( 'acf_get_field_groups' ) ) {
            $field_groups = acf_get_field_groups();
            foreach ( ['group_ptt_task_fields','group_ptt_project_fields'] as $group_key ) {
                $group_exists = false;
                foreach ( $field_groups as $group ) { if ( ($group['key'] ?? '') === $group_key ) { $group_exists = true; break; } }
                $results[] = [ 'name' => "ACF Group: {$group_key}", 'status' => $group_exists ? 'Pass' : 'Fail', 'message' => $group_exists ? "ACF field group {$group_key} exists." : "CRITICAL: ACF field group {$group_key} is missing!" ];
            }
        } else {
            $results[] = [ 'name' => 'ACF Plugin', 'status' => 'Fail', 'message' => 'CRITICAL: ACF Pro is not active or acf_get_field_groups() function is missing!' ];
        }
        return $results;
    }

    // Full legacy-detailed integrity suite (ported from self-test.php -> ptt_test_data_structure_integrity)
    private static function dataStructureIntegrityLegacy(): array {
        $results = [];
        // Post Type Registration
        $post_types = get_post_types( [], 'objects' );
        $project_task_exists = isset( $post_types['project_task'] );
        $results[] = [ 'name' => 'Post Type: project_task', 'status' => $project_task_exists ? 'Pass' : 'Fail', 'message' => $project_task_exists ? 'project_task post type is properly registered.' : 'CRITICAL: project_task post type is missing!' ];

        // Taxonomy Registration
        $taxonomies = get_taxonomies( [], 'objects' );
        foreach ( ['client','project','task_status'] as $taxonomy ) {
            $exists = isset( $taxonomies[$taxonomy] );
            $results[] = [ 'name' => "Taxonomy: {$taxonomy}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "$taxonomy taxonomy is properly registered." : "CRITICAL: {$taxonomy} taxonomy is missing!" ];
        }

        // ACF Field Groups
        if ( function_exists( 'acf_get_field_groups' ) ) {
            $field_groups = acf_get_field_groups();
            foreach ( ['group_ptt_task_fields','group_ptt_project_fields'] as $group_key ) {
                $group_exists = false;
                foreach ( $field_groups as $group ) { if ( ($group['key'] ?? '') === $group_key ) { $group_exists = true; break; } }
                $results[] = [ 'name' => "ACF Group: {$group_key}", 'status' => $group_exists ? 'Pass' : 'Fail', 'message' => $group_exists ? "ACF field group {$group_key} exists." : "CRITICAL: ACF field group {$group_key} is missing!" ];
            }
        } else {
            $results[] = [ 'name' => 'ACF Plugin', 'status' => 'Fail', 'message' => 'CRITICAL: ACF Pro is not active or acf_get_field_groups() function is missing!' ];
        }

        // Core Task Fields (parent-level timer fields removed - session-only approach)
        if ( function_exists( 'acf_get_field' ) ) {
            $required_fields = [
                'field_ptt_task_max_budget' => 'task_max_budget',
                'field_ptt_task_deadline' => 'task_deadline',
                'field_ptt_total_duration_display' => 'total_duration_display',
                'field_ptt_sessions' => 'sessions',
            ];
            foreach ( $required_fields as $field_key => $field_name ) {
                $field = acf_get_field( $field_key );
                $exists = ( $field && ($field['name'] ?? null) === $field_name );
                $results[] = [ 'name' => "Task Field: {$field_name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Task field {$field_name} ({$field_key}) exists." : "CRITICAL: Task field {$field_name} ({$field_key}) is missing!" ];
            }
        }

        // Sessions repeater sub-fields
        if ( function_exists( 'acf_get_field' ) ) {
            $sessions_field = acf_get_field( 'field_ptt_sessions' );
            if ( $sessions_field && isset( $sessions_field['sub_fields'] ) ) {
                $required_session_fields = [
                    'field_ptt_session_title' => 'session_title',
                    'field_ptt_session_notes' => 'session_notes',
                    'field_ptt_session_start_time' => 'session_start_time',
                    'field_ptt_session_stop_time' => 'session_stop_time',
                    'field_ptt_session_manual_override' => 'session_manual_override',
                    'field_ptt_session_manual_duration' => 'session_manual_duration',
                    'field_ptt_session_calculated_duration' => 'session_calculated_duration',
                    'field_ptt_session_timer_controls' => 'session_timer_controls',
                ];
                $session_field_keys = array_column( $sessions_field['sub_fields'], 'key' );
                foreach ( $required_session_fields as $field_key => $field_name ) {
                    $exists = in_array( $field_key, $session_field_keys, true );
                    $results[] = [ 'name' => "Session Field: {$field_name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Session field {$field_name} ({$field_key}) exists." : "CRITICAL: Session field {$field_name} ({$field_key}) is missing!" ];
                }
            } else {
                $results[] = [ 'name' => 'Sessions Repeater Structure', 'status' => 'Fail', 'message' => 'CRITICAL: Sessions repeater field structure is missing or malformed!' ];
            }
        }

        // Project Fields
        if ( function_exists( 'acf_get_field' ) ) {
            $required_project_fields = [
                'field_ptt_project_max_budget' => 'project_max_budget',
                'field_ptt_project_deadline' => 'project_deadline',
            ];
            foreach ( $required_project_fields as $field_key => $field_name ) {
                $field = acf_get_field( $field_key );
                $exists = ( $field && ($field['name'] ?? null) === $field_name );
                $results[] = [ 'name' => "Project Field: {$field_name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Project field {$field_name} ({$field_key}) exists." : "CRITICAL: Project field {$field_name} ({$field_key}) is missing!" ];
            }
        }

        // Core functions exist (PSR-4 + procedural wrappers)
        foreach ( [ 'ptt_get_tasks_for_user','ptt_calculate_and_save_duration','ptt_get_total_sessions_duration','ptt_calculate_session_duration','ptt_get_active_session_index_for_user' ] as $fn ) {
            $exists = function_exists( $fn );
            $results[] = [ 'name' => "Function: {$fn}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Core function {$fn}() exists." : "CRITICAL: Core function {$fn}() is missing!" ];
        }

        // PSR-4 TimeFunctions class exists
        $time_functions_class_exists = class_exists( '\KISS\PTT\Time\TimeFunctions' );
        $results[] = [ 'name' => 'PSR-4: TimeFunctions Class', 'status' => $time_functions_class_exists ? 'Pass' : 'Fail', 'message' => $time_functions_class_exists ? 'PSR-4 TimeFunctions class exists.' : 'CRITICAL: PSR-4 TimeFunctions class is missing!' ];

        // PSR-4 TimeFunctions methods exist
        if ( $time_functions_class_exists ) {
            $required_methods = [ 'calculateAndSaveDuration', 'getActiveSessionIndex', 'calculateSessionDuration', 'ensureManualSessionTimestamps', 'getTotalSessionsDuration' ];
            foreach ( $required_methods as $method ) {
                $method_exists = method_exists( '\KISS\PTT\Time\TimeFunctions', $method );
                $results[] = [ 'name' => "PSR-4 Method: TimeFunctions::{$method}", 'status' => $method_exists ? 'Pass' : 'Fail', 'message' => $method_exists ? "PSR-4 method {$method}() exists." : "CRITICAL: PSR-4 method {$method}() is missing!" ];
            }
        }

        // PSR-4 Helpers class exists
        $helpers_class_exists = class_exists( '\KISS\PTT\Utilities\Helpers' );
        $results[] = [ 'name' => 'PSR-4: Helpers Class', 'status' => $helpers_class_exists ? 'Pass' : 'Fail', 'message' => $helpers_class_exists ? 'PSR-4 Helpers class exists.' : 'CRITICAL: PSR-4 Helpers class is missing!' ];

        // PSR-4 Helpers methods exist
        if ( $helpers_class_exists ) {
            $required_helper_methods = [ 'getTasksForUser', 'getActiveSessionIndexForUser' ];
            foreach ( $required_helper_methods as $method ) {
                $method_exists = method_exists( '\KISS\PTT\Utilities\Helpers', $method );
                $results[] = [ 'name' => "PSR-4 Method: Helpers::{$method}", 'status' => $method_exists ? 'Pass' : 'Fail', 'message' => $method_exists ? "PSR-4 method {$method}() exists." : "CRITICAL: PSR-4 method {$method}() is missing!" ];
            }
        }

        // PSR-4 TodayHelpers classes exist
        $today_entry_renderer_exists = class_exists( '\KISS\PTT\Presentation\Today\EntryRenderer' );
        $today_data_provider_exists = class_exists( '\KISS\PTT\Presentation\Today\DataProvider' );
        $today_page_manager_exists = class_exists( '\KISS\PTT\Presentation\Today\PageManager' );

        $results[] = [ 'name' => 'PSR-4: Today EntryRenderer Class', 'status' => $today_entry_renderer_exists ? 'Pass' : 'Fail', 'message' => $today_entry_renderer_exists ? 'PSR-4 Today EntryRenderer class exists.' : 'CRITICAL: PSR-4 Today EntryRenderer class is missing!' ];
        $results[] = [ 'name' => 'PSR-4: Today DataProvider Class', 'status' => $today_data_provider_exists ? 'Pass' : 'Fail', 'message' => $today_data_provider_exists ? 'PSR-4 Today DataProvider class exists.' : 'CRITICAL: PSR-4 Today DataProvider class is missing!' ];
        $results[] = [ 'name' => 'PSR-4: Today PageManager Class', 'status' => $today_page_manager_exists ? 'Pass' : 'Fail', 'message' => $today_page_manager_exists ? 'PSR-4 Today PageManager class exists.' : 'CRITICAL: PSR-4 Today PageManager class is missing!' ];

        // PSR-4 TodayController class exists
        $today_controller_exists = class_exists( '\KISS\PTT\Presentation\Today\TodayController' );
        $results[] = [ 'name' => 'PSR-4: Today Controller Class', 'status' => $today_controller_exists ? 'Pass' : 'Fail', 'message' => $today_controller_exists ? 'PSR-4 Today Controller class exists.' : 'CRITICAL: PSR-4 Today Controller class is missing!' ];

        // PSR-4 TodayController methods exist
        if ( $today_controller_exists ) {
            $required_controller_methods = [ 'register', 'addTodayPage', 'renderTodayPageHtml', 'getDailyEntriesCallback', 'startNewSessionCallback' ];
            foreach ( $required_controller_methods as $method ) {
                $method_exists = method_exists( '\KISS\PTT\Presentation\Today\TodayController', $method );
                $results[] = [ 'name' => "PSR-4 Method: TodayController::{$method}", 'status' => $method_exists ? 'Pass' : 'Fail', 'message' => $method_exists ? "PSR-4 method {$method}() exists." : "CRITICAL: PSR-4 method {$method}() is missing!" ];
            }
        }

        // Database tables (core)
        global $wpdb;
        $required_tables = [ $wpdb->posts => 'posts', $wpdb->postmeta => 'postmeta', $wpdb->terms => 'terms', $wpdb->term_taxonomy => 'term_taxonomy', $wpdb->term_relationships => 'term_relationships' ];
        foreach ( $required_tables as $table => $name ) {
            $table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table );
            $results[] = [ 'name' => "Database Table: {$name}", 'status' => $table_exists ? 'Pass' : 'Fail', 'message' => $table_exists ? "Database table {$name} exists." : "CRITICAL: Database table {$name} is missing!" ];
        }

        // Sample data validation (session-only approach)
        $sample_tasks = get_posts( [ 'post_type' => 'project_task', 'numberposts' => 1, 'post_status' => 'any' ] );
        if ( ! empty( $sample_tasks ) ) {
            $sample_task = $sample_tasks[0];
            // Test session field retrieval (calculated_duration field removed in v2.2.14)
            $sessions = function_exists('get_field') ? get_field( 'sessions', $sample_task->ID ) : false;
            $results[] = [ 'name' => 'Sample Data: ACF Field Retrieval', 'status' => ( $sessions !== false ) ? 'Pass' : 'Fail', 'message' => ( $sessions !== false ) ? 'ACF session fields can be retrieved from existing tasks.' : 'WARNING: Cannot retrieve ACF session fields from existing tasks.' ];
            $projects = get_the_terms( $sample_task->ID, 'project' );
            $clients = get_the_terms( $sample_task->ID, 'client' );
            $results[] = [ 'name' => 'Sample Data: Taxonomy Relationships', 'status' => ( ! is_wp_error( $projects ) && ! is_wp_error( $clients ) ) ? 'Pass' : 'Fail', 'message' => ( ! is_wp_error( $projects ) && ! is_wp_error( $clients ) ) ? 'Taxonomy relationships are functioning properly.' : 'WARNING: Issues detected with taxonomy relationships.' ];
        } else {
            $results[] = [ 'name' => 'Sample Data Validation', 'status' => 'Skip', 'message' => 'No existing tasks found - sample data validation skipped.' ];
        }

        // Final cleanup: Remove any remaining test posts that may have been missed
        $test_patterns = [
            'CALC TEST POST',
            'SELF TEST POST',
            'TODAY DATE INCLUSION TEST',
            'STATUS TEST',
            'Session Move Source',
            'Session Move Target',
            'SELF TEST - AUTO TIMESTAMP',
            'Test Task A1',
            'Test Task B1',
            'Test Task A2/B2',
            'Convert Legacy Task Timer to New Sessions',
            'Today'
        ];

        $final_cleanup_count = 0;
        foreach ($test_patterns as $pattern) {
            $test_posts = get_posts([
                'post_type' => 'project_task',
                'post_status' => 'any',
                'numberposts' => -1,
                's' => $pattern
            ]);

            foreach ($test_posts as $post) {
                $is_test_post = false;

                if ($pattern === 'Today') {
                    $is_test_post = ($post->post_title === 'Today');
                } else {
                    $is_test_post = (strpos($post->post_title, $pattern) !== false);
                }

                if ($is_test_post) {
                    wp_delete_post($post->ID, true);
                    $final_cleanup_count++;
                }
            }
        }

        // Final verification: Check for any remaining test posts using broader search
        $remaining_count = 0;
        foreach ($test_patterns as $pattern) {
            $remaining_posts = get_posts([
                'post_type' => 'project_task',
                'post_status' => 'any',
                'numberposts' => -1,
                's' => $pattern
            ]);

            foreach ($remaining_posts as $post) {
                if ($pattern === 'Today') {
                    if ($post->post_title === 'Today') {
                        $remaining_count++;
                    }
                } else {
                    if (strpos($post->post_title, $pattern) !== false) {
                        $remaining_count++;
                    }
                }
            }
        }

        $cleanup_message = $final_cleanup_count > 0
            ? "Final cleanup removed {$final_cleanup_count} additional test posts. "
            : '';

        $verification_message = $remaining_count === 0
            ? $cleanup_message . 'All test posts properly cleaned up.'
            : $cleanup_message . "WARNING: {$remaining_count} test posts still remain - cleanup may have failed.";

        $results[] = [
            'name' => 'Test Post Cleanup Verification',
            'status' => $remaining_count === 0 ? 'Pass' : 'Fail',
            'message' => $verification_message
        ];

        return $results;
    }

    /**
     * Ensure PSR-4 compatibility layers are loaded
     *
     * @return void
     */
    private static function ensureCompatibilityLayersLoaded(): void
    {
        // PSR-4 Today classes (EntryRenderer, DataProvider, PageManager) are autoloaded by Composer
        // No explicit includes are required here.

        // Load Today helpers compatibility if not already loaded
        if (!class_exists('PTT_Today_Data_Provider') && defined('PTT_PLUGIN_DIR')) {
            $compatFile = PTT_PLUGIN_DIR . 'src/Presentation/Today/today-helpers-compat.php';
            if (file_exists($compatFile)) {
                require_once $compatFile;
            }
        }

        // Load Today controller compatibility if not already loaded
        if (!function_exists('ptt_render_today_page_html') && defined('PTT_PLUGIN_DIR')) {
            $compatFile = PTT_PLUGIN_DIR . 'src/Presentation/Today/today-compat.php';
            if (file_exists($compatFile)) {
                require_once $compatFile;
            }
        }
    }

}
