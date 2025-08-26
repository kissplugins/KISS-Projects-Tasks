<?php
namespace KISS\PTT\Presentation\Editor;

/**
 * Compatibility wrappers and AJAX endpoints for the Post Editor page.
 * Provides FSM-friendly endpoints to rehydrate an active session for the current user.
 */
class EditorCompat
{
    public static function register(): void
    {
        add_action('wp_ajax_ptt_get_active_session_for_user', [__CLASS__, 'getActiveSessionForUser']);
    }

    /**
     * Returns the user's active session across tasks. If found, returns:
     * { running: true, post_id, session_index, start_time }
     */
    public static function getActiveSessionForUser(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $userId = get_current_user_id();
        if (!$userId || !function_exists('ptt_get_active_session_index_for_user')) {
            wp_send_json_success(['running' => false]);
        }

        $active = ptt_get_active_session_index_for_user($userId);
        if (!$active || empty($active['post_id'])) {
            wp_send_json_success(['running' => false]);
        }

        $postId = (int) $active['post_id'];
        $index0 = (int) $active['index'];
        $sessions = function_exists('get_field') ? get_field('sessions', $postId) : [];
        $start = '';
        if (is_array($sessions) && isset($sessions[$index0])) {
            $start = $sessions[$index0]['session_start_time'] ?? '';
        }

        wp_send_json_success([
            'running' => true,
            'post_id' => $postId,
            'session_index' => $index0,
            'start_time' => $start,
        ]);
    }
}

