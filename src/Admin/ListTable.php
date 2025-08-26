<?php
namespace KISS\PTT\Admin;

/**
 * Admin List Table enhancements for project_task CPT.
 * - Adds sortable Assignee column
 * - Adds Assignee filter dropdown
 * - Applies sorting and filtering to main admin list query
 */
class ListTable
{
    public static function register(): void
    {
        add_filter('manage_edit-project_task_sortable_columns', [__CLASS__, 'sortableColumns']);
        add_action('restrict_manage_posts', [__CLASS__, 'assigneeDropdown']);
        add_action('pre_get_posts', [__CLASS__, 'handleSortAndFilter']);
    }

    /**
     * Mark Assignee column as sortable.
     */
    public static function sortableColumns(array $columns): array
    {
        $columns['ptt_assignee'] = 'ptt_assignee';
        return $columns;
    }

    /**
     * Render Assignee dropdown on the All Tasks screen.
     */
    public static function assigneeDropdown(string $post_type = ''): void
    {
        global $typenow;
        $current = $post_type ?: $typenow;
        if ($current !== 'project_task') { return; }

        $selected = isset($_GET['ptt_assignee_filter']) ? intval($_GET['ptt_assignee_filter']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        echo '<label class="screen-reader-text" for="ptt_assignee_filter">Assignee</label>';
        wp_dropdown_users([
            'name'            => 'ptt_assignee_filter',
            'id'              => 'ptt_assignee_filter',
            'capability'      => 'publish_posts',
            'show_option_all' => __('All Assignees', 'ptt'),
            'selected'        => $selected,
        ]);
    }

    /**
     * Apply sorting and filter to the main query.
     */
    public static function handleSortAndFilter($query): void
    {
        if (!is_admin() || !function_exists('get_current_screen')) { return; }
        // Only adjust the main list table query for project_task
        if (!$query->is_main_query()) { return; }
        $screen = get_current_screen();
        if (!$screen || $screen->id !== 'edit-project_task') { return; }

        // Sorting by Assignee
        $orderby = $query->get('orderby');
        if ($orderby === 'ptt_assignee') {
            $query->set('meta_key', 'ptt_assignee');
            $query->set('orderby', 'meta_value_num');
        }

        // Filtering by Assignee
        $assignee = isset($_GET['ptt_assignee_filter']) ? intval($_GET['ptt_assignee_filter']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        if ($assignee) {
            $meta_query   = (array) $query->get('meta_query');
            $meta_query[] = [
                'key'     => 'ptt_assignee',
                'value'   => $assignee,
                'compare' => '=',
                'type'    => 'NUMERIC',
            ];
            $query->set('meta_query', $meta_query);
        }
    }
}

