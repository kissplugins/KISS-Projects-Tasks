<?php
namespace KISS\PTT\Integration\ACF;

/**
 * Non-invasive ACF schema diagnostics. Emits admin_notices if core groups/fields are missing.
 */
class Diagnostics {
    public static function register() {
        add_action('admin_notices', [__CLASS__, 'adminNotice']);
    }

    public static function adminNotice() {
        if ( ! current_user_can('manage_options') ) return;
        if ( ! function_exists('acf_get_field_groups') ) return;

        $missing = [];
        $groups = acf_get_field_groups();
        $groupKeys = array_map(function($g){ return $g['key'] ?? ''; }, $groups);

        // Required groups
        foreach (['group_ptt_task_fields','group_ptt_project_fields'] as $g) {
            if (!in_array($g, $groupKeys, true)) {
                $missing[] = "ACF field group missing: {$g}";
            }
        }

        // Required fields (only check if groups exist)
        if ( function_exists('acf_get_field') ) {
            foreach ([
                'field_ptt_task_max_budget','field_ptt_task_deadline','field_ptt_start_time','field_ptt_stop_time','field_ptt_calculated_duration','field_ptt_manual_override','field_ptt_manual_duration','field_ptt_sessions'
            ] as $key) {
                $f = acf_get_field($key);
                if (!$f) { $missing[] = "ACF field missing: {$key}"; }
            }
            $sessions = acf_get_field('field_ptt_sessions');
            if ($sessions && isset($sessions['sub_fields'])) {
                $have = array_column($sessions['sub_fields'], 'key');
                foreach ([
                    'field_ptt_session_title','field_ptt_session_notes','field_ptt_session_start_time','field_ptt_session_stop_time','field_ptt_session_manual_override','field_ptt_session_manual_duration','field_ptt_session_calculated_duration','field_ptt_session_timer_controls'
                ] as $k) {
                    if (!in_array($k, $have, true)) { $missing[] = "Sessions sub-field missing: {$k}"; }
                }
            }
        }

        if (!empty($missing)) {
            echo '<div class="notice notice-warning"><p><strong>Project & Task Time Tracker:</strong> ACF schema warnings:<br>';
            echo esc_html(implode(' | ', $missing));
            echo '</p></div>';
        }
    }
}

