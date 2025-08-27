<?php
namespace KISS\PTT\Admin;

/**
 * List Table Controller
 * 
 * Handles sorting and filtering functionality for the project_task post type
 * in the WordPress admin All Tasks listing page.
 */
class ListTable
{
    /**
     * Register hooks for list table functionality
     */
    public static function register(): void
    {
        // Make Assignee column sortable
        add_filter('manage_edit-project_task_sortable_columns', [self::class, 'makeAssigneeColumnSortable']);
        
        // Handle sorting by assignee
        add_action('pre_get_posts', [self::class, 'handleAssigneeSorting']);
        
        // Add assignee filter dropdown
        add_action('restrict_manage_posts', [self::class, 'addAssigneeFilterDropdown']);
        
        // Handle assignee filtering
        add_action('pre_get_posts', [self::class, 'filterTasksByAssignee']);
    }

    /**
     * Make the Assignee column sortable
     * 
     * @param array $columns Existing sortable columns
     * @return array Modified sortable columns
     */
    public static function makeAssigneeColumnSortable(array $columns): array
    {
        $columns['ptt_assignee'] = 'ptt_assignee';
        return $columns;
    }

    /**
     * Handle sorting by assignee
     * 
     * @param \WP_Query $query The WP_Query instance
     */
    public static function handleAssigneeSorting(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'project_task') {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'ptt_assignee') {
            $query->set('meta_key', 'ptt_assignee');
            $query->set('orderby', 'meta_value_num');
        }
    }

    /**
     * Add assignee filter dropdown to the All Tasks page
     * 
     * @param string $post_type Current post type
     */
    public static function addAssigneeFilterDropdown(string $post_type): void
    {
        if ($post_type !== 'project_task') {
            return;
        }

        // Get current filter value
        $selected_assignee = isset($_GET['assignee_filter']) ? intval($_GET['assignee_filter']) : 0;

        // Get all users who have been assigned to tasks
        global $wpdb;
        $assigned_user_ids = $wpdb->get_col(
            "SELECT DISTINCT meta_value 
             FROM {$wpdb->postmeta} pm
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
             WHERE pm.meta_key = 'ptt_assignee' 
             AND pm.meta_value != '' 
             AND pm.meta_value != '0'
             AND p.post_type = 'project_task'
             AND p.post_status = 'publish'"
        );

        if (empty($assigned_user_ids)) {
            return;
        }

        // Get user objects for assigned users
        $users = get_users([
            'include' => $assigned_user_ids,
            'capability' => 'publish_posts',
            'orderby' => 'display_name',
            'order' => 'ASC'
        ]);

        if (empty($users)) {
            return;
        }

        echo '<select name="assignee_filter" id="assignee_filter">';
        echo '<option value="">' . __('All Assignees', 'ptt') . '</option>';
        
        // Add "Unassigned" option
        echo '<option value="unassigned"' . selected($selected_assignee === 'unassigned', true, false) . '>' . __('Unassigned', 'ptt') . '</option>';
        
        // Add user options
        foreach ($users as $user) {
            echo '<option value="' . esc_attr($user->ID) . '"' . selected($selected_assignee, $user->ID, false) . '>';
            echo esc_html($user->display_name);
            echo '</option>';
        }
        
        echo '</select>';
    }

    /**
     * Filter tasks by assignee
     * 
     * @param \WP_Query $query The WP_Query instance
     */
    public static function filterTasksByAssignee(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->post_type !== 'project_task') {
            return;
        }

        $assignee_filter = isset($_GET['assignee_filter']) ? $_GET['assignee_filter'] : '';
        
        if (empty($assignee_filter)) {
            return;
        }

        $meta_query = $query->get('meta_query') ?: [];

        if ($assignee_filter === 'unassigned') {
            // Show tasks with no assignee or assignee = 0
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'ptt_assignee',
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key' => 'ptt_assignee',
                    'value' => '',
                    'compare' => '='
                ],
                [
                    'key' => 'ptt_assignee',
                    'value' => '0',
                    'compare' => '='
                ]
            ];
        } else {
            // Show tasks assigned to specific user
            $assignee_id = intval($assignee_filter);
            if ($assignee_id > 0) {
                $meta_query[] = [
                    'key' => 'ptt_assignee',
                    'value' => $assignee_id,
                    'compare' => '=',
                    'type' => 'NUMERIC'
                ];
            }
        }

        if (!empty($meta_query)) {
            $query->set('meta_query', $meta_query);
        }
    }
}
