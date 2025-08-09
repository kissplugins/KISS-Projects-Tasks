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
    try {
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
    } catch (Throwable $t) {
        $results[] = [ 'name' => 'Task Post Save & Assignee Update', 'status' => 'Fail', 'message' => 'Caught Exception: ' . $t->getMessage() ];
    }

    /* -------------------------------------------------------------
     * TEST 11 – Today Page Cascading Filters
     * -----------------------------------------------------------*/
    try {
        // Cleanup any leftover data from a failed previous run to make test resilient
        require_once(ABSPATH.'wp-admin/includes/user.php');
        if ( $existing_user = get_user_by( 'login', 'test_user_filter' ) ) {
            wp_delete_user( $existing_user->ID );
        }
        if ( $term = get_term_by('name', 'Filter Client X', 'client') ) { wp_delete_term( $term->term_id, 'client' ); }
        if ( $term = get_term_by('name', 'Filter Client Y', 'client') ) { wp_delete_term( $term->term_id, 'client' ); }
        if ( $term = get_term_by('name', 'Filter Project P', 'project') ) { wp_delete_term( $term->term_id, 'project' ); }
        if ( $term = get_term_by('name', 'Filter Project Q', 'project') ) { wp_delete_term( $term->term_id, 'project' ); }

        $test_user_id = wp_insert_user( [ 'user_login' => 'test_user_filter', 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );
        $client_x = wp_insert_term( 'Filter Client X', 'client' );
        $client_y = wp_insert_term( 'Filter Client Y', 'client' );
        $project_p = wp_insert_term( 'Filter Project P', 'project' );
        $project_q = wp_insert_term( 'Filter Project Q', 'project' );
        $post_ids_to_delete = [];

        if ( is_wp_error($test_user_id) || is_wp_error($client_x) || is_wp_error($client_y) || is_wp_error($project_p) || is_wp_error($project_q) ) {
            $results[] = [ 'name' => 'Cascading Filters', 'status' => 'Fail', 'message' => 'Could not create test data (users/terms).' ];
        } else {
            $task_p = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Task P', 'post_author' => $test_user_id, 'post_status' => 'publish' ] );
            $post_ids_to_delete[] = $task_p;
            wp_set_object_terms( $task_p, $client_x['term_id'], 'client' );
            wp_set_object_terms( $task_p, $project_p['term_id'], 'project' );
            update_post_meta($task_p, 'ptt_assignee', $test_user_id);

            $task_q = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Task Q', 'post_author' => $test_user_id, 'post_status' => 'publish' ] );
            $post_ids_to_delete[] = $task_q;
            wp_set_object_terms( $task_q, $client_y['term_id'], 'client' );
            wp_set_object_terms( $task_q, $project_q['term_id'], 'project' );
            update_post_meta($task_q, 'ptt_assignee', $test_user_id);

            // Temporarily switch to the test user to simulate their view
            $original_user_id = get_current_user_id();
            wp_set_current_user( $test_user_id );
            
            // Test 1: Get Projects for Client X
            $_POST['client_id'] = $client_x['term_id'];
            $response1 = ptt_get_projects_for_client_callback(true);
            $projects_for_client_x = ($response1['success']) ? wp_list_pluck( $response1['data'], 'id' ) : [];
            $pass1 = ( count($projects_for_client_x) === 1 && $projects_for_client_x[0] === $project_p['term_id'] );
            
            // Test 2: Get Tasks for Client X
            $_POST['project_id'] = 0; // No specific project
            $response2 = ptt_get_tasks_for_today_page_callback(true);
            $tasks_for_client_x = ($response2['success']) ? wp_list_pluck( $response2['data'], 'id' ) : [];
            $pass2 = ( count($tasks_for_client_x) === 1 && in_array($task_p, $tasks_for_client_x) );
            
            // Test 3: Get Tasks for Project Q
            $_POST['client_id'] = 0; // No specific client
            $_POST['project_id'] = $project_q['term_id'];
            $response3 = ptt_get_tasks_for_today_page_callback(true);
            $tasks_for_project_q = ($response3['success']) ? wp_list_pluck( $response3['data'], 'id' ) : [];
            $pass3 = ( count($tasks_for_project_q) === 1 && in_array($task_q, $tasks_for_project_q) );
            
            // Restore original user and clean up POST variables
            wp_set_current_user( $original_user_id );
            unset( $_POST['client_id'], $_POST['project_id'] );

            if ( $pass1 && $pass2 && $pass3 ) {
                $results[] = [ 'name' => 'Cascading Filters', 'status' => 'Pass', 'message' => 'Successfully filtered projects and tasks.' ];
            } else {
                $fail_msg = 'Filter logic failed: ';
                if (!$pass1) $fail_msg .= 'Projects not filtered by Client. ';
                if (!$pass2) $fail_msg .= 'Tasks not filtered by Client. ';
                if (!$pass3) $fail_msg .= 'Tasks not filtered by Project. ';
                $results[] = [ 'name' => 'Cascading Filters', 'status' => 'Fail', 'message' => trim($fail_msg) ];
            }
        }
    } catch (Throwable $t) {
        $results[] = [ 'name' => 'Cascading Filters', 'status' => 'Fail', 'message' => 'Caught Exception: ' . $t->getMessage() ];
    } finally {
        // Cleanup regardless of success or failure
        if (isset($original_user_id)) {
            wp_set_current_user( $original_user_id );
        }
        unset( $_POST['client_id'], $_POST['project_id'] );

        if (!empty($post_ids_to_delete)) {
            foreach($post_ids_to_delete as $pid) {
                wp_delete_post( $pid, true );
            }
        }
        if (isset($client_x) && !is_wp_error($client_x)) { wp_delete_term( $client_x['term_id'], 'client' ); }
        if (isset($client_y) && !is_wp_error($client_y)) { wp_delete_term( $client_y['term_id'], 'client' ); }
        if (isset($project_p) && !is_wp_error($project_p)) { wp_delete_term( $project_p['term_id'], 'project' ); }
        if (isset($project_q) && !is_wp_error($project_q)) { wp_delete_term( $project_q['term_id'], 'project' ); }
        if (isset($test_user_id) && !is_wp_error($test_user_id)) { wp_delete_user( $test_user_id ); }
    }


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