<?php
namespace KISS\PTT\Admin;

class SelfTestController {
    public static function register() {
        add_action('admin_menu', [__CLASS__, 'menu'], 60);
        add_action('admin_init', [__CLASS__, 'handleFlagSave']);

        add_action('wp_ajax_ptt_run_self_tests', [__CLASS__, 'ajaxRunSelfTests']);
        add_action('wp_ajax_ptt_sync_authors_assignee', [__CLASS__, 'ajaxSyncAuthors']);
    }

    public static function menu() {
        add_submenu_page(
            'edit.php?post_type=project_task',
            'Tracker Settings',
            'Settings',
            'manage_options',
            'ptt-self-test',
            [__CLASS__, 'renderSelfTestPage']
        );
        add_submenu_page(
            'edit.php?post_type=project_task',
            'Plugin Changelog',
            'Changelog – v' . PTT_VERSION,
            'manage_options',
            'ptt-changelog',
            [__CLASS__, 'renderChangelogPage']
        );
    }

    public static function handleFlagSave(){
        if (!isset($_POST['ptt_flags_nonce'])) return;
        if (!wp_verify_nonce($_POST['ptt_flags_nonce'], 'ptt_save_flags')) return;
        if (!current_user_can('manage_options')) return;
        Settings::saveFlagsFromRequest($_POST);
        add_action('admin_notices', function(){ echo '<div class="notice notice-success"><p>FSM flags saved.</p></div>'; });
    }

    // ------------------- Renderers -------------------
    public static function renderChangelogPage() {
        $file_path = PTT_PLUGIN_DIR . 'changelog.md';
        echo '<div class="wrap">';
        echo '<h1>Plugin Changelog</h1>';
        if ( function_exists( 'kiss_mdv_render_file' ) ) {
            $html = \kiss_mdv_render_file( $file_path );
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

    public static function renderSelfTestPage() {
        echo '<div class="wrap">';
        echo '<h1>Plugin Settings &amp; Self Test</h1>';
        echo '<p>This module verifies core plugin functionality. It creates test data and immediately deletes it.</p>';

        // Compact ACF Schema summary widget
        if ( class_exists('KISS\\PTT\\Integration\\ACF\\Diagnostics') ) {
            $issues = \KISS\PTT\Integration\ACF\Diagnostics::collectIssues();
            $hasIssues = !empty($issues);
            // Legacy ACF Schema card hidden now that it is part of main tests
            echo '<div class="card ptt-legacy-acf-schema-card" style="display:none;max-width:800px;">';
            echo '<h2>ACF Schema Status</h2>';
            if (!$hasIssues) {
                echo '<p><span class="dashicons dashicons-yes" style="color:green;"></span> No issues detected.</p>';
            } else {
                echo '<p><span class="dashicons dashicons-warning" style="color:#d35400;"></span> ' . count($issues) . ' warning(s) detected. ';

            echo '<hr />';
            echo '<h2>Feature Flags</h2>';
            $flags = Settings::getFlags();
            echo '<form method="post">';
            wp_nonce_field('ptt_save_flags','ptt_flags_nonce');
            echo '<label><input type="checkbox" name="ptt_fsm_enabled" value="1" '.checked($flags['enabled'],true,false).'> Enable FSM (global)</label><br />';
            echo '<label><input type="checkbox" name="ptt_fsm_today_enabled" value="1" '.checked($flags['today'],true,false).'> Today page</label><br />';
            echo '<label><input type="checkbox" name="ptt_fsm_editor_enabled" value="1" '.checked($flags['editor'],true,false).'> CPT Editor</label><br />';
            echo '<p><button class="button button-primary">Save Flags</button></p>';
            echo '</form>';

                echo '<a href="' . esc_url( admin_url('edit.php?post_type=project_task&page=ptt-acf-schema') ) . '">View details</a>.</p>';
            }
            echo '</div>';
        }

        echo '<button id="ptt-run-self-tests" class="button button-primary">Re‑Run Tests</button>';
        echo '<p id="ptt-last-test-time">';
        $last_run = get_option( 'ptt_tests_last_run' );
        if ( $last_run ) {
            echo 'Tests last ran at ' . esc_html( date_i18n( get_option( 'time_format' ), $last_run ) );
        } else {
            echo 'Tests last ran at --:--:--';
        }
        echo '</p>';
        echo '<div id="ptt-test-results-container" style="margin-top:20px;"><div class="ptt-ajax-spinner" style="display:none;"></div></div>';
        echo '<hr />';
        echo '<button id="ptt-sync-authors" class="button">Synchronize Authors &rarr; Assignee</button>';
        echo '<p id="ptt-sync-authors-result"></p>';
        echo '</div>';
    }

    // ------------------- AJAX -------------------
    public static function ajaxRunSelfTests() {
        check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }
        $results = \KISS\PTT\Diagnostics\SelfTests::run();
        $timestamp = current_time( 'timestamp' );
        update_option( 'ptt_tests_last_run', $timestamp );
        wp_send_json_success( [ 'results' => $results, 'time' => date_i18n( get_option( 'time_format' ), $timestamp ) ] );
    }

    public static function ajaxSyncAuthors() {
        check_ajax_referer( 'ptt_ajax_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => 'Permission denied.' ] );
        }
        $posts = get_posts( [ 'post_type' => 'project_task', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids' ] );
        $count = 0;
        foreach ( $posts as $post_id ) {
            $author_id = (int) get_post_field( 'post_author', $post_id );
            update_post_meta( $post_id, 'ptt_assignee', $author_id );
            $count++;
        }
        wp_send_json_success( [ 'count' => $count ] );
    }

    // ------------------- Helpers -------------------
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
                foreach ( $field_groups as $group ) { if ( $group['key'] === $group_key ) { $group_exists = true; break; } }
                $results[] = [ 'name' => "ACF Group: {$group_key}", 'status' => $group_exists ? 'Pass' : 'Fail', 'message' => $group_exists ? "ACF field group {$group_key} exists." : "CRITICAL: ACF field group {$group_key} is missing!" ];
            }
        } else {
            $results[] = [ 'name' => 'ACF Plugin', 'status' => 'Fail', 'message' => 'CRITICAL: ACF Pro is not active or acf_get_field_groups() function is missing!' ];
        }
        // Task fields
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
                    $exists = ( $field && $field['name'] === $field_name );
                    $results[] = [ 'name' => "Task Field: {$field_name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Task field {$field_name} ({$field_key}) exists." : "CRITICAL: Task field {$field_name} ({$field_key}) is missing!" ];
                }
            }
        }
        // Sessions sub-fields
        if ( function_exists( 'acf_get_field' ) ) {
            $sessions_field = acf_get_field( 'field_ptt_sessions' );
            if ( $sessions_field && isset( $sessions_field['sub_fields'] ) ) {
                $required_session_fields = [
                    'field_ptt_session_title','field_ptt_session_notes','field_ptt_session_start_time','field_ptt_session_stop_time','field_ptt_session_manual_override','field_ptt_session_manual_duration','field_ptt_session_calculated_duration','field_ptt_session_timer_controls',
                ];
                $session_field_keys = array_column( $sessions_field['sub_fields'], 'key' );
                foreach ( $required_session_fields as $field_key ) {
                    $exists = in_array( $field_key, $session_field_keys, true );
                    $name = str_replace(['field_ptt_','_'], ['', ' '], $field_key);
                    $results[] = [ 'name' => "Session Field: {$name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Session field {$name} ({$field_key}) exists." : "CRITICAL: Session field {$name} ({$field_key}) is missing!" ];
                }
            } else {
                $results[] = [ 'name' => 'Sessions Repeater Structure', 'status' => 'Fail', 'message' => 'CRITICAL: Sessions repeater field structure is missing or malformed!' ];
            }
        }
        // Project fields
        if ( function_exists( 'acf_get_field_group' ) ) {
            $project_group = acf_get_field_group( 'group_ptt_project_fields' );
            if ( $project_group ) {
                foreach ( [ 'field_ptt_project_max_budget' => 'project_max_budget', 'field_ptt_project_deadline' => 'project_deadline' ] as $field_key => $field_name ) {
                    $field = acf_get_field( $field_key );
                    $exists = ( $field && $field['name'] === $field_name );
                    $results[] = [ 'name' => "Project Field: {$field_name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Project field {$field_name} ({$field_key}) exists." : "CRITICAL: Project field {$field_name} ({$field_key}) is missing!" ];
                }
            }
        }
        // Core Functions exist
        foreach ( [ 'ptt_get_tasks_for_user','ptt_calculate_and_save_duration','ptt_get_total_sessions_duration','ptt_calculate_session_duration','ptt_get_active_session_index_for_user' ] as $fn ) {
            $exists = function_exists( $fn );
            $results[] = [ 'name' => "Function: {$fn}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Core function {$fn}() exists." : "CRITICAL: Core function {$fn}() is missing!" ];
        }
        // Core tables
        global $wpdb;
        foreach ( [ $wpdb->posts => 'posts', $wpdb->postmeta => 'postmeta', $wpdb->terms => 'terms', $wpdb->term_taxonomy => 'term_taxonomy', $wpdb->term_relationships => 'term_relationships' ] as $table => $name ) {
            $exists = ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table );
            $results[] = [ 'name' => "Database Table: {$name}", 'status' => $exists ? 'Pass' : 'Fail', 'message' => $exists ? "Database table {$name} exists." : "CRITICAL: Database table {$name} is missing!" ];
        }
        // Sample Data validation
        $sample_tasks = get_posts( [ 'post_type' => 'project_task', 'numberposts' => 1, 'post_status' => 'any' ] );
        if ( ! empty( $sample_tasks ) ) {
            $sample_task = $sample_tasks[0];
            $calculated_duration = get_field( 'calculated_duration', $sample_task->ID );
            $sessions = get_field( 'sessions', $sample_task->ID );
            $results[] = [ 'name' => 'Sample Data: ACF Field Retrieval', 'status' => ( $calculated_duration !== false || $sessions !== false ) ? 'Pass' : 'Fail', 'message' => ( $calculated_duration !== false || $sessions !== false ) ? 'ACF fields can be retrieved from existing tasks.' : 'WARNING: Cannot retrieve ACF fields from existing tasks.' ];
            $projects = get_the_terms( $sample_task->ID, 'project' );
            $clients  = get_the_terms( $sample_task->ID, 'client' );
            $results[] = [ 'name' => 'Sample Data: Taxonomy Relationships', 'status' => ( ! is_wp_error( $projects ) && ! is_wp_error( $clients ) ) ? 'Pass' : 'Fail', 'message' => ( ! is_wp_error( $projects ) && ! is_wp_error( $clients ) ) ? 'Taxonomy relationships are functioning properly.' : 'WARNING: Issues detected with taxonomy relationships.' ];
        } else {
            $results[] = [ 'name' => 'Sample Data Validation', 'status' => 'Skip', 'message' => 'No existing tasks found - sample data validation skipped.' ];
        }
        return $results;
    }
}

