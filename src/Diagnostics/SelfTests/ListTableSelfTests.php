<?php
namespace KISS\PTT\Diagnostics\SelfTests;

/**
 * Self Tests for List Table functionality
 * 
 * Tests the All Tasks admin page sorting and filtering functionality
 */
class ListTableSelfTests
{
    /**
     * Run all list table self tests
     * 
     * @return array Test results
     */
    public static function runTests(): array
    {
        $results = [];
        
        $results[] = self::testAssigneeColumnSortable();
        $results[] = self::testAssigneeFilterDropdown();
        $results[] = self::testFilterQueryHandling();
        
        return $results;
    }

    /**
     * Test that the assignee column is registered as sortable
     * 
     * @return array Test result
     */
    private static function testAssigneeColumnSortable(): array
    {
        $test_name = 'Assignee Column Sortable Registration';
        
        try {
            // Get sortable columns for project_task post type
            $sortable_columns = apply_filters('manage_edit-project_task_sortable_columns', []);
            
            if (!isset($sortable_columns['ptt_assignee'])) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Assignee column is not registered as sortable'
                ];
            }
            
            if ($sortable_columns['ptt_assignee'] !== 'ptt_assignee') {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Assignee column sortable key is incorrect'
                ];
            }
            
            return [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Assignee column is properly registered as sortable'
            ];
            
        } catch (\Exception $e) {
            return [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test that the assignee filter dropdown renders properly
     * 
     * @return array Test result
     */
    private static function testAssigneeFilterDropdown(): array
    {
        $test_name = 'Assignee Filter Dropdown Rendering';
        
        try {
            // Create a test task with an assignee to ensure dropdown has content
            $user_id = get_current_user_id();
            if (!$user_id) {
                return [
                    'test' => $test_name,
                    'status' => 'SKIP',
                    'message' => 'No current user available for test'
                ];
            }
            
            $test_task_id = wp_insert_post([
                'post_title' => 'Test Task for Assignee Filter',
                'post_type' => 'project_task',
                'post_status' => 'publish',
                'post_author' => $user_id
            ]);
            
            if (is_wp_error($test_task_id)) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Failed to create test task'
                ];
            }
            
            // Set assignee meta
            update_post_meta($test_task_id, 'ptt_assignee', $user_id);
            
            // Capture the dropdown output
            ob_start();
            \KISS\PTT\Admin\ListTable::addAssigneeFilterDropdown('project_task');
            $dropdown_output = ob_get_clean();
            
            // Clean up test task
            wp_delete_post($test_task_id, true);
            
            if (empty($dropdown_output)) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Assignee filter dropdown did not render any output'
                ];
            }
            
            if (strpos($dropdown_output, 'assignee_filter') === false) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Dropdown does not contain expected assignee_filter name attribute'
                ];
            }
            
            if (strpos($dropdown_output, 'All Assignees') === false) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Dropdown does not contain "All Assignees" option'
                ];
            }
            
            if (strpos($dropdown_output, 'Unassigned') === false) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Dropdown does not contain "Unassigned" option'
                ];
            }
            
            return [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Assignee filter dropdown renders correctly with expected options'
            ];
            
        } catch (\Exception $e) {
            return [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Test that filter query handling works correctly
     * 
     * @return array Test result
     */
    private static function testFilterQueryHandling(): array
    {
        $test_name = 'Filter Query Handling';
        
        try {
            // Test with mock query
            $query = new \WP_Query();
            $query->query_vars['post_type'] = 'project_task';
            
            // Simulate admin context
            if (!is_admin()) {
                set_current_screen('edit-project_task');
            }
            
            // Test unassigned filter
            $_GET['assignee_filter'] = 'unassigned';
            \KISS\PTT\Admin\ListTable::filterTasksByAssignee($query);
            
            $meta_query = $query->get('meta_query');
            if (empty($meta_query)) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Meta query not set for unassigned filter'
                ];
            }
            
            // Test specific user filter
            $_GET['assignee_filter'] = '1';
            $query = new \WP_Query();
            $query->query_vars['post_type'] = 'project_task';
            \KISS\PTT\Admin\ListTable::filterTasksByAssignee($query);
            
            $meta_query = $query->get('meta_query');
            if (empty($meta_query)) {
                return [
                    'test' => $test_name,
                    'status' => 'FAIL',
                    'message' => 'Meta query not set for specific user filter'
                ];
            }
            
            // Clean up
            unset($_GET['assignee_filter']);
            
            return [
                'test' => $test_name,
                'status' => 'PASS',
                'message' => 'Filter query handling works correctly for both unassigned and specific user filters'
            ];
            
        } catch (\Exception $e) {
            // Clean up
            unset($_GET['assignee_filter']);
            
            return [
                'test' => $test_name,
                'status' => 'FAIL',
                'message' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
}
