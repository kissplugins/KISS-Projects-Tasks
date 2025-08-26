<?php
namespace KISS\PTT\Diagnostics\SelfTests;

class ListTableSelfTests
{
    /**
     * Verify that Assignee column is sortable and filter exists on All Tasks list.
     */
    public static function run(): array
    {
        $results = [];

        // Check sortable columns registration
        $sortable = apply_filters('manage_edit-project_task_sortable_columns', []);
        $isSortable = isset($sortable['ptt_assignee']);
        $results[] = [
            'name' => 'Admin List: Assignee column sortable',
            'status' => $isSortable ? 'Pass' : 'Fail',
            'message' => $isSortable ? 'Assignee column is marked sortable.' : 'Assignee column is not marked sortable.'
        ];

        // Simulate filter presence by executing restrict_manage_posts and capturing output
        ob_start();
        do_action('restrict_manage_posts', 'project_task');
        $html = ob_get_clean();
        $hasDropdown = (strpos($html, 'ptt_assignee_filter') !== false);
        $results[] = [
            'name' => 'Admin List: Assignee filter dropdown',
            'status' => $hasDropdown ? 'Pass' : 'Fail',
            'message' => $hasDropdown ? 'Assignee dropdown rendered on list table.' : 'Assignee dropdown not found.'
        ];

        return $results;
    }
}

