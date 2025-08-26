<?php
namespace KISS\PTT\Reports;

/**
 * Read-only helpers used by the Reports admin page.
 * PSR-4 safe conversion with no behavior changes.
 */
class Helpers
{
    /**
     * Format Task Notes: strip tags, trim, truncate, and linkify URLs.
     * Mirrors legacy ptt_format_task_notes()
     */
    public static function formatTaskNotes($content, int $maxLength = 200): string
    {
        $content = wp_strip_all_tags((string)$content);
        $content = trim($content);
        if ($content === '') { return ''; }

        $truncated = false;
        if (strlen($content) > $maxLength) {
            $content   = substr($content, 0, $maxLength - 3);
            $truncated = true;
        }

        $content = esc_html($content);

        $url_pattern = '/(https?:\/\/[^\s<>"{}|\\^`\[\]]+)/i';
        $content = preg_replace_callback($url_pattern, function ($m) {
            $url = $m[1];
            $display = strlen($url) > 50 ? substr($url, 0, 47) . '…' : $url;
            return '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . $display . '</a>';
        }, $content);

        if ($truncated) { $content .= '…'; }
        return $content;
    }

    /**
     * Get the Assignee display name for a task or fallback string.
     * Mirrors legacy ptt_get_assignee_name()
     */
    public static function getAssigneeName(int $postId): string
    {
        $assignee_id = (int) get_post_meta($postId, 'ptt_assignee', true);
        if ($assignee_id) {
            return (string) get_the_author_meta('display_name', $assignee_id);
        }
        return __('No Assignee', 'ptt');
    }
}

