<?php
namespace KISS\PTT\Diagnostics\SelfTests;

/**
 * End-to-End Workflow Test
 * 
 * Simulates real-world user behavior: Create client → project → task → log multiple sessions
 * This test validates the complete user journey rather than isolated components.
 */
class EndToEndWorkflowTest
{
    /**
     * Run the complete end-to-end workflow test
     * 
     * @return array Test results
     */
    public static function runCompleteWorkflow(): array
    {
        $results = [];
        $cleanup_data = [];
        
        try {
            // STEP 1: Create Client
            $client_result = self::createTestClient();
            $results[] = $client_result;
            if ($client_result['status'] !== 'PASS') {
                return array_merge($results, [['test' => 'E2E Workflow', 'status' => 'FAIL', 'message' => 'Failed at client creation step']]);
            }
            $cleanup_data['client_id'] = $client_result['data']['client_id'];
            
            // STEP 2: Create Project under Client
            $project_result = self::createTestProject($cleanup_data['client_id']);
            $results[] = $project_result;
            if ($project_result['status'] !== 'PASS') {
                self::cleanup($cleanup_data);
                return array_merge($results, [['test' => 'E2E Workflow', 'status' => 'FAIL', 'message' => 'Failed at project creation step']]);
            }
            $cleanup_data['project_id'] = $project_result['data']['project_id'];
            
            // STEP 3: Create Task assigned to Client & Project
            $task_result = self::createTestTask($cleanup_data['client_id'], $cleanup_data['project_id']);
            $results[] = $task_result;
            if ($task_result['status'] !== 'PASS') {
                self::cleanup($cleanup_data);
                return array_merge($results, [['test' => 'E2E Workflow', 'status' => 'FAIL', 'message' => 'Failed at task creation step']]);
            }
            $cleanup_data['task_id'] = $task_result['data']['task_id'];
            
            // STEP 4: Log Multiple Sessions (Timer + Manual)
            $sessions_result = self::logMultipleSessions($cleanup_data['task_id']);
            $results[] = $sessions_result;
            if ($sessions_result['status'] !== 'PASS') {
                self::cleanup($cleanup_data);
                return array_merge($results, [['test' => 'E2E Workflow', 'status' => 'FAIL', 'message' => 'Failed at session logging step']]);
            }
            
            // STEP 5: Verify Data Integrity
            $integrity_result = self::verifyDataIntegrity($cleanup_data);
            $results[] = $integrity_result;
            
            // STEP 6: Test FSM Integration (if enabled)
            $fsm_result = self::testFSMIntegration($cleanup_data['task_id']);
            $results[] = $fsm_result;
            
            // STEP 7: Cleanup
            $cleanup_result = self::cleanup($cleanup_data);
            $results[] = $cleanup_result;
            
            // FINAL: Overall workflow assessment
            $failed_steps = array_filter($results, function($r) { return $r['status'] === 'FAIL'; });
            $overall_status = empty($failed_steps) ? 'PASS' : 'FAIL';
            $overall_message = empty($failed_steps) 
                ? 'Complete end-to-end workflow executed successfully - real-world user journey validated'
                : 'Workflow failed at ' . count($failed_steps) . ' step(s) - see individual test results';
                
            $results[] = [
                'test' => 'E2E: Complete User Workflow',
                'status' => $overall_status,
                'message' => $overall_message
            ];
            
        } catch (\Exception $e) {
            self::cleanup($cleanup_data ?? []);
            $results[] = [
                'test' => 'E2E: Complete User Workflow', 
                'status' => 'FAIL', 
                'message' => 'Exception during workflow: ' . $e->getMessage()
            ];
        }
        
        return $results;
    }
    
    /**
     * Step 1: Create a test client
     */
    private static function createTestClient(): array
    {
        $client_name = 'E2E Test Client ' . wp_rand(1000, 9999);
        
        $client_term = wp_insert_term($client_name, 'client');
        
        if (is_wp_error($client_term)) {
            return [
                'test' => 'E2E Step 1: Create Client',
                'status' => 'FAIL',
                'message' => 'Failed to create client: ' . $client_term->get_error_message()
            ];
        }
        
        return [
            'test' => 'E2E Step 1: Create Client',
            'status' => 'PASS',
            'message' => "Created client: {$client_name}",
            'data' => ['client_id' => $client_term['term_id'], 'client_name' => $client_name]
        ];
    }
    
    /**
     * Step 2: Create a test project under the client
     */
    private static function createTestProject(int $client_id): array
    {
        $project_name = 'E2E Test Project ' . wp_rand(1000, 9999);
        
        $project_term = wp_insert_term($project_name, 'project');
        
        if (is_wp_error($project_term)) {
            return [
                'test' => 'E2E Step 2: Create Project',
                'status' => 'FAIL',
                'message' => 'Failed to create project: ' . $project_term->get_error_message()
            ];
        }
        
        return [
            'test' => 'E2E Step 2: Create Project',
            'status' => 'PASS',
            'message' => "Created project: {$project_name}",
            'data' => ['project_id' => $project_term['term_id'], 'project_name' => $project_name]
        ];
    }
    
    /**
     * Step 3: Create a task assigned to client & project
     */
    private static function createTestTask(int $client_id, int $project_id): array
    {
        $task_title = 'E2E Test Task ' . wp_rand(1000, 9999);
        $user_id = get_current_user_id();
        
        $task_id = wp_insert_post([
            'post_title' => $task_title,
            'post_type' => 'project_task',
            'post_status' => 'publish',
            'post_author' => $user_id
        ]);
        
        if (is_wp_error($task_id) || !$task_id) {
            return [
                'test' => 'E2E Step 3: Create Task',
                'status' => 'FAIL',
                'message' => 'Failed to create task'
            ];
        }
        
        // Assign to client and project
        wp_set_object_terms($task_id, [$client_id], 'client');
        wp_set_object_terms($task_id, [$project_id], 'project');
        
        // Set assignee
        update_post_meta($task_id, 'ptt_assignee', $user_id);
        
        // Verify assignments
        $client_terms = get_the_terms($task_id, 'client');
        $project_terms = get_the_terms($task_id, 'project');
        $assignee = get_post_meta($task_id, 'ptt_assignee', true);
        
        $client_assigned = !is_wp_error($client_terms) && !empty($client_terms) && $client_terms[0]->term_id == $client_id;
        $project_assigned = !is_wp_error($project_terms) && !empty($project_terms) && $project_terms[0]->term_id == $project_id;
        $assignee_set = $assignee == $user_id;
        
        if (!$client_assigned || !$project_assigned || !$assignee_set) {
            return [
                'test' => 'E2E Step 3: Create Task',
                'status' => 'FAIL',
                'message' => 'Task created but assignments failed'
            ];
        }
        
        return [
            'test' => 'E2E Step 3: Create Task',
            'status' => 'PASS',
            'message' => "Created task: {$task_title} with proper client/project/assignee",
            'data' => ['task_id' => $task_id, 'task_title' => $task_title]
        ];
    }
    
    /**
     * Step 4: Log multiple sessions (timer + manual)
     */
    private static function logMultipleSessions(int $task_id): array
    {
        if (!function_exists('update_field')) {
            return [
                'test' => 'E2E Step 4: Log Sessions',
                'status' => 'SKIP',
                'message' => 'ACF not available for session logging'
            ];
        }
        
        // Session 1: Timer-based session (start/stop times)
        $session1 = [
            'session_title' => 'E2E Timer Session',
            'session_notes' => 'Automated test session via timer',
            'session_start_time' => '2025-08-28 09:00:00',
            'session_stop_time' => '2025-08-28 10:30:00',
            'session_manual_override' => false,
            'session_manual_duration' => ''
        ];
        
        // Session 2: Manual session (manual duration)
        $session2 = [
            'session_title' => 'E2E Manual Session',
            'session_notes' => 'Automated test session via manual entry',
            'session_start_time' => '',
            'session_stop_time' => '',
            'session_manual_override' => true,
            'session_manual_duration' => '2.5'
        ];
        
        // Session 3: Mixed session (start time + manual duration)
        $session3 = [
            'session_title' => 'E2E Mixed Session',
            'session_notes' => 'Automated test session with mixed data',
            'session_start_time' => '2025-08-28 14:00:00',
            'session_stop_time' => '',
            'session_manual_override' => true,
            'session_manual_duration' => '1.25'
        ];
        
        $sessions = [$session1, $session2, $session3];
        update_field('sessions', $sessions, $task_id);
        
        // Verify sessions were saved
        $saved_sessions = get_field('sessions', $task_id);
        
        if (!$saved_sessions || count($saved_sessions) !== 3) {
            return [
                'test' => 'E2E Step 4: Log Sessions',
                'status' => 'FAIL',
                'message' => 'Failed to save all 3 test sessions'
            ];
        }
        
        // Calculate total duration
        if (function_exists('ptt_calculate_and_save_duration')) {
            $total_duration = ptt_calculate_and_save_duration($task_id);
        }
        
        return [
            'test' => 'E2E Step 4: Log Sessions',
            'status' => 'PASS',
            'message' => "Logged 3 sessions (timer, manual, mixed) - Total: " . ($total_duration ?? 'N/A') . " hours"
        ];
    }

    /**
     * Step 5: Verify data integrity across the workflow
     */
    private static function verifyDataIntegrity(array $data): array
    {
        $issues = [];

        // Verify client still exists
        $client = get_term($data['client_id'], 'client');
        if (is_wp_error($client) || !$client) {
            $issues[] = 'Client term missing or corrupted';
        }

        // Verify project still exists
        $project = get_term($data['project_id'], 'project');
        if (is_wp_error($project) || !$project) {
            $issues[] = 'Project term missing or corrupted';
        }

        // Verify task still exists with proper relationships
        $task = get_post($data['task_id']);
        if (!$task || $task->post_type !== 'project_task') {
            $issues[] = 'Task post missing or wrong type';
        } else {
            // Check client assignment
            $task_clients = get_the_terms($data['task_id'], 'client');
            if (is_wp_error($task_clients) || empty($task_clients) || $task_clients[0]->term_id != $data['client_id']) {
                $issues[] = 'Task-client relationship broken';
            }

            // Check project assignment
            $task_projects = get_the_terms($data['task_id'], 'project');
            if (is_wp_error($task_projects) || empty($task_projects) || $task_projects[0]->term_id != $data['project_id']) {
                $issues[] = 'Task-project relationship broken';
            }

            // Check assignee
            $assignee = get_post_meta($data['task_id'], 'ptt_assignee', true);
            if (!$assignee || $assignee != get_current_user_id()) {
                $issues[] = 'Task assignee missing or incorrect';
            }

            // Check sessions
            if (function_exists('get_field')) {
                $sessions = get_field('sessions', $data['task_id']);
                if (!$sessions || count($sessions) !== 3) {
                    $issues[] = 'Sessions missing or incorrect count';
                } else {
                    // Verify session data integrity
                    foreach ($sessions as $i => $session) {
                        if (empty($session['session_title'])) {
                            $issues[] = "Session {$i} missing title";
                        }
                    }
                }
            }
        }

        $status = empty($issues) ? 'PASS' : 'FAIL';
        $message = empty($issues)
            ? 'All data relationships and integrity checks passed'
            : 'Data integrity issues: ' . implode(', ', $issues);

        return [
            'test' => 'E2E Step 5: Data Integrity',
            'status' => $status,
            'message' => $message
        ];
    }

    /**
     * Step 6: Test FSM integration (if enabled)
     */
    private static function testFSMIntegration(int $task_id): array
    {
        // Check if FSM is enabled
        if (!class_exists('\KISS\PTT\Admin\Settings')) {
            return [
                'test' => 'E2E Step 6: FSM Integration',
                'status' => 'SKIP',
                'message' => 'FSM settings not available'
            ];
        }

        $flags = \KISS\PTT\Admin\Settings::getFlags();
        if (!$flags['enabled'] || !$flags['editor']) {
            return [
                'test' => 'E2E Step 6: FSM Integration',
                'status' => 'SKIP',
                'message' => 'EditorFSM not enabled'
            ];
        }

        // Test session AJAX endpoints that FSM uses
        $ajax_tests = [];

        // Test start session endpoint
        $_POST = [
            'action' => 'ptt_start_session_timer',
            'nonce' => wp_create_nonce('ptt_ajax_nonce'),
            'post_id' => $task_id,
            'row_index' => 0
        ];

        ob_start();
        try {
            if (function_exists('ptt_start_session_timer_callback')) {
                ptt_start_session_timer_callback();
                $output = ob_get_clean();
                $ajax_tests[] = 'Start session endpoint accessible';
            } else {
                ob_get_clean();
                $ajax_tests[] = 'Start session endpoint missing';
            }
        } catch (\Exception $e) {
            ob_get_clean();
            $ajax_tests[] = 'Start session endpoint error: ' . $e->getMessage();
        }

        // Clean up $_POST
        $_POST = [];

        $status = (count($ajax_tests) > 0 && strpos($ajax_tests[0], 'accessible') !== false) ? 'PASS' : 'FAIL';

        return [
            'test' => 'E2E Step 6: FSM Integration',
            'status' => $status,
            'message' => 'FSM endpoints: ' . implode(', ', $ajax_tests)
        ];
    }

    /**
     * Step 7: Cleanup test data
     */
    private static function cleanup(array $data): array
    {
        $cleaned = [];

        // Delete task
        if (!empty($data['task_id'])) {
            $deleted = wp_delete_post($data['task_id'], true);
            $cleaned[] = $deleted ? 'Task deleted' : 'Task deletion failed';
        }

        // Delete project term
        if (!empty($data['project_id'])) {
            $deleted = wp_delete_term($data['project_id'], 'project');
            $cleaned[] = (!is_wp_error($deleted) && $deleted) ? 'Project deleted' : 'Project deletion failed';
        }

        // Delete client term
        if (!empty($data['client_id'])) {
            $deleted = wp_delete_term($data['client_id'], 'client');
            $cleaned[] = (!is_wp_error($deleted) && $deleted) ? 'Client deleted' : 'Client deletion failed';
        }

        return [
            'test' => 'E2E Step 7: Cleanup',
            'status' => 'PASS',
            'message' => 'Cleanup: ' . implode(', ', $cleaned)
        ];
    }
}
