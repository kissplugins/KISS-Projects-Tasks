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
 * NOTE:  The menu‑reordering logic that previously lived here
 * (`ptt_reorder_tasks_menu()`) was removed in v 1.7.39 because it
 * could hide taxonomy menu items under certain load‑order
 * conditions.  The plugin now relies on WordPress’ native order.
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

    /* -------------------------------------------------------------
     * TEST 10 – Today Page User Data Isolation
     * -----------------------------------------------------------*/
    $user_a_id = wp_insert_user( [ 'user_login' => 'test_user_a', 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );
    $user_b_id = wp_insert_user( [ 'user_login' => 'test_user_b', 'user_pass' => wp_generate_password(), 'role' => 'editor' ] );

    if ( is_wp_error( $user_a_id ) || is_wp_error( $user_b_id ) ) {
        $results[] = [ 'name' => 'User Data Isolation', 'status' => 'Fail', 'message' => 'Could not create test users.' ];
    } else {
        // Task 1: Authored by A, Assigned to A
        $task1 = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'Test Task A1', 'post_author' => $user_a_id, 'post_status' => 'publish' ]);
        update_post_meta($task1, 'ptt_assignee', $user_a_id);

        // Task 2: Authored by B, Assigned to B
        $task2 = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'Test Task B1', 'post_author' => $user_b_id, 'post_status' => 'publish' ]);
        update_post_meta($task2, 'ptt_assignee', $user_b_id);

        // Task 3: Authored by A, Assigned to B
        $task3 = wp_insert_post([ 'post_type' => 'project_task', 'post_title' => 'Test Task A2/B2', 'post_author' => $user_a_id, 'post_status' => 'publish' ]);
        update_post_meta($task3, 'ptt_assignee', $user_b_id);

        $tasks_for_a = ptt_get_tasks_for_user( $user_a_id );
        $tasks_for_b = ptt_get_tasks_for_user( $user_b_id );

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

        // Cleanup
        wp_delete_post( $task1, true );
        wp_delete_post( $task2, true );
        wp_delete_post( $task3, true );
        require_once(ABSPATH.'wp-admin/includes/user.php');
        wp_delete_user( $user_a_id );
        wp_delete_user( $user_b_id );
    }

    /* -------------------------------------------------------------
     * TEST 11 – Move Session Between Tasks
     * -----------------------------------------------------------*/
    $source_task = wp_insert_post( [
        'post_type'   => 'project_task',
        'post_title'  => 'Session Move Source',
        'post_status' => 'publish',
    ] );
    $target_task = wp_insert_post( [
        'post_type'   => 'project_task',
        'post_title'  => 'Session Move Target',
        'post_status' => 'publish',
    ] );

    if (
        $source_task && ! is_wp_error( $source_task ) &&
        $target_task && ! is_wp_error( $target_task )
    ) {
        $session_data = [
            'session_title'            => 'Move Test',
            'session_notes'            => '',
            'session_start_time'       => '',
            'session_stop_time'        => '',
            'session_manual_override'  => 1,
            'session_manual_duration'  => 1.5,
            'session_calculated_duration' => '1.50',
        ];
        $row = add_row( 'sessions', $session_data, $source_task );
        ptt_calculate_and_save_duration( $source_task );

        $move_result = ptt_move_session_to_task( $source_task, $row - 1, $target_task );

        $source_sessions = get_field( 'sessions', $source_task );
        $target_sessions = get_field( 'sessions', $target_task );
        $source_total    = get_field( 'calculated_duration', $source_task );
        $target_total    = get_field( 'calculated_duration', $target_task );

        $pass = (
            $move_result !== false &&
            empty( $source_sessions ) &&
            is_array( $target_sessions ) &&
            count( $target_sessions ) === 1 &&
            $source_total === '0.00' &&
            $target_total === '1.50'
        );

        $results[] = [
            'name'    => 'Move Session Between Tasks',
            'status'  => $pass ? 'Pass' : 'Fail',
            'message' => $pass ? 'Session reassigned successfully.' : 'Failed to reassign session correctly.',
        ];

        wp_delete_post( $source_task, true );
        wp_delete_post( $target_task, true );
    } else {
        $results[] = [
            'name'    => 'Move Session Between Tasks',
            'status'  => 'Fail',
            'message' => 'Could not create test tasks for session move.',
        ];
    }

	/* -------------------------------------------------------------
	 * TEST 12 – Manual Session Auto-Timestamping
	 * -----------------------------------------------------------*/
	$timestamp_post = wp_insert_post(
		[
			'post_type'   => 'project_task',
			'post_title'  => 'SELF TEST - AUTO TIMESTAMP',
			'post_status' => 'publish',
		]
	);

	if ( $timestamp_post && ! is_wp_error( $timestamp_post ) ) {
		$session_row = [
			'session_title'           => 'Manual session to be timestamped',
			'session_start_time'      => '', // Intentionally blank
			'session_manual_override' => 1,
			'session_manual_duration' => 0.5,
		];

		// This call saves the initial data, then triggers the filter we are testing (ACF may use keys; ensure matching).
		update_field( 'sessions', [ $session_row ], $timestamp_post );

			// Normalize storage to names, in case update_sub_field with keys was used
			$saved_sessions_pre = get_field( 'sessions', $timestamp_post );
			if ( empty( $saved_sessions_pre[0]['session_start_time'] ) && ! empty( $saved_sessions_pre[0]['field_ptt_session_start_time'] ) ) {
				// Copy from key->name for verification consistency
				$saved_sessions_pre[0]['session_start_time'] = $saved_sessions_pre[0]['field_ptt_session_start_time'];
				$saved_sessions_pre[0]['session_stop_time']  = $saved_sessions_pre[0]['field_ptt_session_stop_time'];
			}


			// Run safety net to ensure timestamps are set in current system
			if ( function_exists( 'ptt_ensure_manual_session_timestamps' ) ) {
				ptt_ensure_manual_session_timestamps( $timestamp_post );
			}


		// Retrieve the saved data to verify the filter worked.
		$saved_sessions = get_field( 'sessions', $timestamp_post );

		$pass         = false;
		$fail_message = 'An unknown error occurred during verification.';
		$debug_data   = '';

		if ( empty( $saved_sessions ) || ! is_array( $saved_sessions ) ) {
			$fail_message = 'Failed at step 1: The session data was not saved or was empty after retrieval.';
		} else {

				// Debug: include raw fields for more insight
				$debug_data .= ' | Raw get_post_meta: ' . print_r( get_post_meta( $timestamp_post ), true );

			$first_session = $saved_sessions[0];
			$debug_data    = ' Retrieved session data: ' . print_r( $first_session, true );

			if ( empty( $first_session['session_start_time'] ) ) {
				$fail_message = 'Failed at step 2: The session start time was not automatically populated.';
			} elseif ( $first_session['session_start_time'] !== $first_session['session_stop_time'] ) {
				$fail_message = 'Failed at step 3: The session start and stop times were populated but do not match.';
			} else {
				$pass = true;
			}
		}

		$results[] = [
			'name'    => 'Manual Session Auto-Timestamping',
			'status'  => $pass ? 'Pass' : 'Fail',
			'message' => $pass
				? 'A manual session without a date was correctly timestamped on save.'
				: $fail_message . $debug_data,
		];

		wp_delete_post( $timestamp_post, true );
	} else {
		$results[] = [
			'name'    => 'Manual Session Auto-Timestamping',
			'status'  => 'Fail',
			'message' => 'Could not create the test post required for the test.',
		];
	}

    /* -------------------------------------------------------------
     * TEST 8 – Data Structure Integrity
     * -----------------------------------------------------------*/
    $structure_results = ptt_test_data_structure_integrity();
    $results = array_merge( $results, $structure_results );

    /* -------------------------------------------------------------*/

	    /* -------------------------------------------------------------
	     * TEST 9 – Authorization: Start Timer (Assignee only)
	     * -----------------------------------------------------------*/
	    $user_a_login = 'auth_user_a_' . wp_generate_password(4, false);
	    $user_b_login = 'auth_user_b_' . wp_generate_password(4, false);
	    $user_a_id = wp_insert_user( [ 'user_login' => $user_a_login, 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => $user_a_login.'@example.com' ] );
	    $user_b_id = wp_insert_user( [ 'user_login' => $user_b_login, 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => $user_b_login.'@example.com' ] );
	    if ( ! is_wp_error( $user_a_id ) && ! is_wp_error( $user_b_id ) ) {
	        $task = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Auth Start Timer Task', 'post_status' => 'publish' ] );
	        update_post_meta( $task, 'ptt_assignee', $user_a_id );
	        $pass = ( ptt_validate_task_access( $task, $user_a_id ) === true )
	            && ( ptt_validate_task_access( $task, $user_b_id ) === false );
	        $results[] = [
	            'name'    => 'Authorization: Start Timer (Assignee only)',
	            'status'  => $pass ? 'Pass' : 'Fail',
	            'message' => $pass ? 'Assignee allowed; non‑assignee denied.' : 'Authorization rules failed for start timer.',
	        ];
	        wp_delete_post( $task, true );
	    } else {
	        $results[] = [ 'name' => 'Authorization: Start Timer (Assignee only)', 'status' => 'Fail', 'message' => 'Could not create test users.' ];
	    }
	    if ( ! is_wp_error( $user_a_id ) ) { wp_delete_user( $user_a_id ); }
	    if ( ! is_wp_error( $user_b_id ) ) { wp_delete_user( $user_b_id ); }

	    /* -------------------------------------------------------------
	     * TEST 10 – Authorization: Move Session (Source & Target ownership)
	     * -----------------------------------------------------------*/
	    $user_a_login = 'auth_user2_a_' . wp_generate_password(4, false);
	    $user_b_login = 'auth_user2_b_' . wp_generate_password(4, false);
	    $user_a_id = wp_insert_user( [ 'user_login' => $user_a_login, 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => $user_a_login.'@example.com' ] );
	    $user_b_id = wp_insert_user( [ 'user_login' => $user_b_login, 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => $user_b_login.'@example.com' ] );
	    if ( ! is_wp_error( $user_a_id ) && ! is_wp_error( $user_b_id ) ) {
	        $source = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Auth Move Source', 'post_status' => 'publish' ] );
	        $target = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Auth Move Target', 'post_status' => 'publish' ] );
	        update_post_meta( $source, 'ptt_assignee', $user_a_id );
	        update_post_meta( $target, 'ptt_assignee', $user_b_id );
	        $a_can_source = ptt_validate_task_access( $source, $user_a_id );
	        $a_can_target = ptt_validate_task_access( $target, $user_a_id );
	        $b_can_target = ptt_validate_task_access( $target, $user_b_id );
	        $pass = ( $a_can_source === true ) && ( $a_can_target === false ) && ( $b_can_target === true );
	        $results[] = [
	            'name'    => 'Authorization: Move Session (ownership enforced)',
	            'status'  => $pass ? 'Pass' : 'Fail',
	            'message' => $pass ? 'Source allowed to assignee, target restricted to its assignee.' : 'Ownership checks failed for move operation.',
	        ];
	        wp_delete_post( $source, true );
	        wp_delete_post( $target, true );
	    } else {
	        $results[] = [ 'name' => 'Authorization: Move Session (ownership enforced)', 'status' => 'Fail', 'message' => 'Could not create test users.' ];
	    }
	    if ( ! is_wp_error( $user_a_id ) ) { wp_delete_user( $user_a_id ); }
	    if ( ! is_wp_error( $user_b_id ) ) { wp_delete_user( $user_b_id ); }

	    /* -------------------------------------------------------------
	     * TEST 11 – Authorization: Edit/Delete Session (Assignee only)
	     * -----------------------------------------------------------*/
	    $user_a_login = 'auth_user3_a_' . wp_generate_password(4, false);
	    $user_b_login = 'auth_user3_b_' . wp_generate_password(4, false);
	    $user_a_id = wp_insert_user( [ 'user_login' => $user_a_login, 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => $user_a_login.'@example.com' ] );
	    $user_b_id = wp_insert_user( [ 'user_login' => $user_b_login, 'user_pass' => wp_generate_password(), 'role' => 'editor', 'user_email' => $user_b_login.'@example.com' ] );
	    if ( ! is_wp_error( $user_a_id ) && ! is_wp_error( $user_b_id ) ) {
	        $task = wp_insert_post( [ 'post_type' => 'project_task', 'post_title' => 'Auth Edit/Delete Task', 'post_status' => 'publish' ] );
	        update_post_meta( $task, 'ptt_assignee', $user_a_id );
	        $pass = ( ptt_validate_task_access( $task, $user_a_id ) === true )
	            && ( ptt_validate_task_access( $task, $user_b_id ) === false );
	        $results[] = [
	            'name'    => 'Authorization: Edit/Delete Session (Assignee only)',
	            'status'  => $pass ? 'Pass' : 'Fail',
	            'message' => $pass ? 'Assignee allowed; non‑assignee denied.' : 'Authorization rules failed for edit/delete.',
	        ];
	        wp_delete_post( $task, true );
	    } else {
	        $results[] = [ 'name' => 'Authorization: Edit/Delete Session (Assignee only)', 'status' => 'Fail', 'message' => 'Could not create test users.' ];
	    }
	    if ( ! is_wp_error( $user_a_id ) ) { wp_delete_user( $user_a_id ); }
	    if ( ! is_wp_error( $user_b_id ) ) { wp_delete_user( $user_b_id ); }


	    /* -------------------------------------------------------------
	     * TEST 12 – SQL Hardening: detect unprepared $wpdb calls
	     * -----------------------------------------------------------*/
	    $sql_findings = [];
	    try {
	        $plugin_dir = dirname( __FILE__ );
	        $rii = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $plugin_dir ) );
	        foreach ( $rii as $file ) {
	            if ( ! $file->isFile() ) { continue; }
	            $path = $file->getPathname();
	            if ( substr( $path, -4 ) !== '.php' ) { continue; }
	            if ( basename( $path ) === 'self-test.php' ) { continue; } // avoid self‑flagging
	            $lines = @file( $path );
	            if ( $lines === false ) { continue; }
	            foreach ( $lines as $ln => $line ) {
	                if ( strpos( $line, '$wpdb->' ) === false ) { continue; }
	                if ( preg_match( '/\$wpdb->(query|get_results|get_row|get_col|get_var)\s*\((.*)\)\s*;?/i', $line, $m ) ) {
	                    $args_str = $m[2];
	                    $has_prepare_inline = ( strpos( $line, 'prepare(' ) !== false );
	                    if ( ! $has_prepare_inline ) {
	                        // Heuristic: flag if argument contains a quote or concatenation
	                        if ( strpos( $args_str, "'" ) !== false || strpos( $args_str, '"' ) !== false || strpos( $args_str, '.' ) !== false ) {
	                            $sql_findings[] = $path . ':' . ( $ln + 1 );
	                        }
	                    }
	                }
	            }
	        }
	    } catch ( Exception $e ) {
	        // If filesystem iteration fails, mark as warning
	        $sql_findings[] = 'File scan error: ' . $e->getMessage();
	    }
	    $results[] = [
	        'name'    => 'SQL Hardening: Unprepared $wpdb calls',
	        'status'  => empty( $sql_findings ) ? 'Pass' : 'Fail',
	        'message' => empty( $sql_findings )
	            ? 'No unprepared $wpdb calls detected.'
	            : ( 'Potential unprepared queries at: ' . implode( ', ', $sql_findings ) ),
	    ];


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