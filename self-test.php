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
// Hook is now registered via KISS\PTT\Admin\SelfTestController::register().
// add_action( 'admin_menu', 'ptt_add_settings_submenu_page', 60 );

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
// Hook is now registered via KISS\PTT\Admin\SelfTestController::register().
// add_action( 'admin_menu', 'ptt_add_changelog_submenu_page', 60 );

/**
 * Ensure core admin assets (scripts.js, styles.css) are loaded and localized on Self‑Test/Changelog pages.
 */
function ptt_enqueue_selftest_assets( $hook ) {
    $targets = [ 'project_task_page_ptt-self-test', 'project_task_page_ptt-changelog' ];
    if ( ! in_array( $hook, $targets, true ) ) {
        return;
    }
    // Styles
    wp_enqueue_style( 'ptt-styles', PTT_PLUGIN_URL . 'styles.css', [], PTT_VERSION );
    // Main plugin JS (contains Self‑Test handlers)
    wp_enqueue_script( 'ptt-scripts', PTT_PLUGIN_URL . 'scripts.js', [ 'jquery' ], PTT_VERSION, true );
    // Localize for AJAX
    wp_localize_script( 'ptt-scripts', 'ptt_ajax_object', [
        'ajax_url' => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'ptt_ajax_nonce' ),
    ] );
}
// Enqueued centrally via KISS\PTT\Admin\Assets::enqueue_admin().
// add_action( 'admin_enqueue_scripts', 'ptt_enqueue_selftest_assets' );


/**
 * Renders the Changelog page HTML.
 */
function ptt_changelog_page_html() {
    // BC wrapper; now handled by KISS\\PTT\\Admin\\SelfTestController
    return \KISS\PTT\Admin\SelfTestController::renderChangelogPage();
}

/* -----------------------------------------------------------------
 * NOTE:  The menu‑reordering logic that previously lived here
 * (`ptt_reorder_tasks_menu()`) was removed in v 1.7.39 because it
 * could hide taxonomy menu items under certain load‑order
 * conditions.  The plugin now relies on WordPress’ native order.
 * ----------------------------------------------------------------*/

/**
 * Renders the Self‑Test page HTML.
 */
function ptt_self_test_page_html() {
    // BC wrapper; now handled by KISS\\PTT\\Admin\\SelfTestController
    return \KISS\PTT\Admin\SelfTestController::renderSelfTestPage();
}

/**
 * AJAX handler to run all self‑tests (delegates to controller).
 */
function ptt_run_self_tests_callback() {
    return \KISS\PTT\Admin\SelfTestController::ajaxRunSelfTests();
}
// Hook is now registered via KISS\PTT\Admin\SelfTestController::register().
// add_action( 'wp_ajax_ptt_run_self_tests', 'ptt_run_self_tests_callback' );

/**
 * AJAX handler to copy post authors into the Assignee field (delegates to controller).
 */
function ptt_sync_authors_assignee_callback() {
    return \KISS\PTT\Admin\SelfTestController::ajaxSyncAuthors();
}
// Hook is now registered via KISS\PTT\Admin\SelfTestController::register().
// add_action( 'wp_ajax_ptt_sync_authors_assignee', 'ptt_sync_authors_assignee_callback' );

/**
 * Data Structure Integrity Test
 *
 * Validates the complete data model hierarchy:
 * Clients -> Projects -> Tasks -> Sessions
 *
 * Checks for:
 * - Post types exist and are properly registered
 * - Taxonomies exist and are properly registered
 * - ACF field groups and fields exist
 * - Required meta fields are present
 * - Data relationships are intact
 */
function ptt_test_data_structure_integrity() {
    $results = [];

    // Test 1: Post Type Registration
    $post_types = get_post_types( [], 'objects' );
    $project_task_exists = isset( $post_types['project_task'] );

    $results[] = [
        'name'    => 'Post Type: project_task',
        'status'  => $project_task_exists ? 'Pass' : 'Fail',
        'message' => $project_task_exists
            ? 'project_task post type is properly registered.'
            : 'CRITICAL: project_task post type is missing!',
    ];

    // Test 2: Taxonomy Registration
    $taxonomies = get_taxonomies( [], 'objects' );
    $required_taxonomies = ['client', 'project', 'task_status'];

    foreach ( $required_taxonomies as $taxonomy ) {
        $exists = isset( $taxonomies[$taxonomy] );
        $results[] = [
            'name'    => "Taxonomy: {$taxonomy}",
            'status'  => $exists ? 'Pass' : 'Fail',
            'message' => $exists
                ? "{$taxonomy} taxonomy is properly registered."
                : "CRITICAL: {$taxonomy} taxonomy is missing!",
        ];
    }

    // Test 3: ACF Field Groups
    if ( function_exists( 'acf_get_field_groups' ) ) {
        $field_groups = acf_get_field_groups();
        $required_groups = ['group_ptt_task_fields', 'group_ptt_project_fields'];

        foreach ( $required_groups as $group_key ) {
            $group_exists = false;
            foreach ( $field_groups as $group ) {
                if ( $group['key'] === $group_key ) {
                    $group_exists = true;
                    break;
                }
            }

            $results[] = [
                'name'    => "ACF Group: {$group_key}",
                'status'  => $group_exists ? 'Pass' : 'Fail',
                'message' => $group_exists
                    ? "ACF field group {$group_key} exists."
                    : "CRITICAL: ACF field group {$group_key} is missing!",
            ];
        }
    } else {
        $results[] = [
            'name'    => 'ACF Plugin',
            'status'  => 'Fail',
            'message' => 'CRITICAL: ACF Pro is not active or acf_get_field_groups() function is missing!',
        ];
    }

    // Test 4: Core Task Fields
    if ( function_exists( 'acf_get_field_group' ) ) {
        $task_group = acf_get_field_group( 'group_ptt_task_fields' );
        if ( $task_group ) {
            $required_fields = [
                'field_ptt_task_max_budget' => 'task_max_budget',
                'field_ptt_task_deadline' => 'task_deadline',
                'field_ptt_start_time' => 'start_time',
                'field_ptt_stop_time' => 'stop_time',
                'field_ptt_calculated_duration' => 'calculated_duration',
                'field_ptt_manual_override' => 'manual_override',
                'field_ptt_manual_duration' => 'manual_duration',
                'field_ptt_sessions' => 'sessions',
            ];

            foreach ( $required_fields as $field_key => $field_name ) {
                $field = acf_get_field( $field_key );
                $field_exists = ( $field && $field['name'] === $field_name );

                $results[] = [
                    'name'    => "Task Field: {$field_name}",
                    'status'  => $field_exists ? 'Pass' : 'Fail',
                    'message' => $field_exists
                        ? "Task field {$field_name} ({$field_key}) exists."
                        : "CRITICAL: Task field {$field_name} ({$field_key}) is missing!",
                ];
            }
        }
    }

    // Test 5: Session Sub-Fields
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
                $field_exists = in_array( $field_key, $session_field_keys );

                $results[] = [
                    'name'    => "Session Field: {$field_name}",
                    'status'  => $field_exists ? 'Pass' : 'Fail',
                    'message' => $field_exists
                        ? "Session field {$field_name} ({$field_key}) exists."
                        : "CRITICAL: Session field {$field_name} ({$field_key}) is missing!",
                ];
            }
        } else {
            $results[] = [
                'name'    => 'Sessions Repeater Structure',
                'status'  => 'Fail',
                'message' => 'CRITICAL: Sessions repeater field structure is missing or malformed!',
            ];
        }
    }

    // Test 6: Project Fields
    if ( function_exists( 'acf_get_field_group' ) ) {
        $project_group = acf_get_field_group( 'group_ptt_project_fields' );
        if ( $project_group ) {
            $required_project_fields = [
                'field_ptt_project_max_budget' => 'project_max_budget',
                'field_ptt_project_deadline' => 'project_deadline',
            ];

            foreach ( $required_project_fields as $field_key => $field_name ) {
                $field = acf_get_field( $field_key );
                $field_exists = ( $field && $field['name'] === $field_name );

                $results[] = [
                    'name'    => "Project Field: {$field_name}",
                    'status'  => $field_exists ? 'Pass' : 'Fail',
                    'message' => $field_exists
                        ? "Project field {$field_name} ({$field_key}) exists."
                        : "CRITICAL: Project field {$field_name} ({$field_key}) is missing!",
                ];
            }
        }
    }

    // Test 7: Core Functions Exist
    $required_functions = [
        'ptt_get_tasks_for_user',
        'ptt_calculate_and_save_duration',
        'ptt_get_total_sessions_duration',
        'ptt_calculate_session_duration',
        'ptt_get_active_session_index_for_user',
    ];

    foreach ( $required_functions as $function_name ) {
        $function_exists = function_exists( $function_name );

        $results[] = [
            'name'    => "Function: {$function_name}",
            'status'  => $function_exists ? 'Pass' : 'Fail',
            'message' => $function_exists
                ? "Core function {$function_name}() exists."
                : "CRITICAL: Core function {$function_name}() is missing!",
        ];
    }

    // Test 8: Database Table Integrity (WordPress core tables)
    global $wpdb;

    $required_tables = [
        $wpdb->posts => 'posts',
        $wpdb->postmeta => 'postmeta',
        $wpdb->terms => 'terms',
        $wpdb->term_taxonomy => 'term_taxonomy',
        $wpdb->term_relationships => 'term_relationships',
    ];

    foreach ( $required_tables as $table => $name ) {
        $table_exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table );

        $results[] = [
            'name'    => "Database Table: {$name}",
            'status'  => $table_exists ? 'Pass' : 'Fail',
            'message' => $table_exists
                ? "Database table {$name} exists."
                : "CRITICAL: Database table {$name} is missing!",
        ];
    }

    // Test 9: Sample Data Validation (if any tasks exist)
    $sample_tasks = get_posts( [
        'post_type' => 'project_task',
        'numberposts' => 1,
        'post_status' => 'any',
    ] );

    if ( ! empty( $sample_tasks ) ) {
        $sample_task = $sample_tasks[0];

        // Check if ACF fields can be retrieved
        $calculated_duration = get_field( 'calculated_duration', $sample_task->ID );
        $sessions = get_field( 'sessions', $sample_task->ID );

        $results[] = [
            'name'    => 'Sample Data: ACF Field Retrieval',
            'status'  => ( $calculated_duration !== false || $sessions !== false ) ? 'Pass' : 'Fail',
            'message' => ( $calculated_duration !== false || $sessions !== false )
                ? 'ACF fields can be retrieved from existing tasks.'
                : 'WARNING: Cannot retrieve ACF fields from existing tasks.',
        ];

        // Check taxonomy assignments
        $projects = get_the_terms( $sample_task->ID, 'project' );
        $clients = get_the_terms( $sample_task->ID, 'client' );

        $results[] = [
            'name'    => 'Sample Data: Taxonomy Relationships',
            'status'  => ( ! is_wp_error( $projects ) && ! is_wp_error( $clients ) ) ? 'Pass' : 'Fail',
            'message' => ( ! is_wp_error( $projects ) && ! is_wp_error( $clients ) )
                ? 'Taxonomy relationships are functioning properly.'
                : 'WARNING: Issues detected with taxonomy relationships.',
        ];
    } else {
        $results[] = [
            'name'    => 'Sample Data Validation',
            'status'  => 'Skip',
            'message' => 'No existing tasks found - sample data validation skipped.',
        ];
    }

    return $results;
}