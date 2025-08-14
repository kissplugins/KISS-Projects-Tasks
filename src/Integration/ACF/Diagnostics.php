<?php
namespace KISS\PTT\Integration\ACF;

/**
 * Non-invasive ACF schema diagnostics. Emits admin_notices if core groups/fields are missing
 * or have mismatched types/names. Does not modify DB – informational only.
 */
class Diagnostics {
    public static function register() {
        add_action('admin_notices', [__CLASS__, 'adminNotice']);
    }

    /**
     * Collect ACF schema issues for display in notices or admin pages.
     * @return array<string> human-readable issue strings
     */
    public static function collectIssues(): array {
        if ( ! function_exists('acf_get_field_groups') ) return [ 'ACF Pro not active: acf_get_field_groups() missing.' ];

        $issues = [];
        $groups = acf_get_field_groups();
        $groupKeys = array_map(function($g){ return $g['key'] ?? ''; }, $groups);

        // Required groups
        foreach (['group_ptt_task_fields','group_ptt_project_fields'] as $g) {
            if (!in_array($g, $groupKeys, true)) {
                $issues[] = "ACF field group missing: {$g}";
            }
        }

        // Validate fields (keys, names, types)
        if ( function_exists('acf_get_field') ) {
            $expectedParents = [
                'field_ptt_task_max_budget'      => ['name'=>'task_max_budget',      'type'=>'number'],
                'field_ptt_task_deadline'        => ['name'=>'task_deadline',        'type'=>'date_time_picker', 'display_format'=>'Y-m-d H:i:s', 'return_format'=>'Y-m-d H:i:s'],
                'field_ptt_start_time'           => ['name'=>'start_time',           'type'=>'date_time_picker', 'display_format'=>'Y-m-d H:i:s', 'return_format'=>'Y-m-d H:i:s'],
                'field_ptt_stop_time'            => ['name'=>'stop_time',            'type'=>'date_time_picker', 'display_format'=>'Y-m-d H:i:s', 'return_format'=>'Y-m-d H:i:s'],
                'field_ptt_calculated_duration'  => ['name'=>'calculated_duration',  'type'=>'text'],
                'field_ptt_manual_override'      => ['name'=>'manual_override',      'type'=>'true_false'],
                'field_ptt_manual_duration'      => ['name'=>'manual_duration',      'type'=>'number'],
                'field_ptt_sessions'             => ['name'=>'sessions',             'type'=>'repeater'],
            ];

            foreach ($expectedParents as $key => $expect) {
                $f = acf_get_field($key);
                if (!$f) { $issues[] = "ACF field missing: {$key}"; continue; }
                if (($f['name'] ?? null) !== $expect['name']) {
                    $issues[] = sprintf('ACF field name mismatch for %s: expected %s got %s', $key, $expect['name'], $f['name'] ?? '');
                }
                if (($f['type'] ?? null) !== $expect['type']) {
                    $issues[] = sprintf('ACF field type mismatch for %s: expected %s got %s', $key, $expect['type'], $f['type'] ?? '');
                }
                if ($expect['type'] === 'date_time_picker') {
                    $expDisp = $expect['display_format'] ?? null;
                    $expRet  = $expect['return_format'] ?? null;
                    if ($expDisp && (($f['display_format'] ?? null) !== $expDisp)) {
                        $issues[] = sprintf('ACF %s display_format mismatch: expected %s got %s', $key, $expDisp, $f['display_format'] ?? '');
                    }
                    if ($expRet && (($f['return_format'] ?? null) !== $expRet)) {
                        $issues[] = sprintf('ACF %s return_format mismatch: expected %s got %s', $key, $expRet, $f['return_format'] ?? '');
                    }
                }
            }

            // Sessions repeater sub-fields
            $sessions = acf_get_field('field_ptt_sessions');
            if ($sessions) {
                if (($sessions['type'] ?? null) !== 'repeater') {
                    $issues[] = sprintf('ACF field type mismatch for field_ptt_sessions: expected repeater got %s', $sessions['type'] ?? '');
                }
                $subExpected = [
                    'field_ptt_session_title'              => ['name'=>'session_title',             'type'=>'text'],
                    'field_ptt_session_notes'              => ['name'=>'session_notes',             'type'=>'textarea'],
                    'field_ptt_session_start_time'         => ['name'=>'session_start_time',        'type'=>'date_time_picker', 'display_format'=>'Y-m-d H:i:s', 'return_format'=>'Y-m-d H:i:s'],
                    'field_ptt_session_stop_time'          => ['name'=>'session_stop_time',         'type'=>'date_time_picker', 'display_format'=>'Y-m-d H:i:s', 'return_format'=>'Y-m-d H:i:s'],
                    'field_ptt_session_manual_override'     => ['name'=>'session_manual_override',   'type'=>'true_false'],
                    'field_ptt_session_manual_duration'     => ['name'=>'session_manual_duration',   'type'=>'number'],
                    'field_ptt_session_calculated_duration' => ['name'=>'session_calculated_duration','type'=>'text'],
                    'field_ptt_session_timer_controls'      => ['name'=>'session_timer_controls',    'type'=>'message'],
                ];
                $subByKey = [];
                foreach (($sessions['sub_fields'] ?? []) as $sf) { $subByKey[$sf['key']] = $sf; }
                foreach ($subExpected as $k => $expect) {
                    $sf = $subByKey[$k] ?? null;
                    if (!$sf) { $issues[] = "Sessions sub-field missing: {$k}"; continue; }
                    if (($sf['name'] ?? null) !== $expect['name']) {
                        $issues[] = sprintf('Sessions sub-field name mismatch for %s: expected %s got %s', $k, $expect['name'], $sf['name'] ?? '');
                    }
                    if (($sf['type'] ?? null) !== $expect['type']) {
                        $issues[] = sprintf('Sessions sub-field type mismatch for %s: expected %s got %s', $k, $expect['type'], $sf['type'] ?? '');
                    }
                    if ($expect['type'] === 'date_time_picker') {
                        $expDisp = $expect['display_format'] ?? null;
                        $expRet  = $expect['return_format'] ?? null;
                        if ($expDisp && (($sf['display_format'] ?? null) !== $expDisp)) {
                            $issues[] = sprintf('ACF %s display_format mismatch: expected %s got %s', $k, $expDisp, $sf['display_format'] ?? '');
                        }
                        if ($expRet && (($sf['return_format'] ?? null) !== $expRet)) {
                            $issues[] = sprintf('ACF %s return_format mismatch: expected %s got %s', $k, $expRet, $sf['return_format'] ?? '');
                        }
                    }
                }
            }
        }

        return $issues;
    }

    public static function adminNotice() {
        if ( ! current_user_can('manage_options') ) return;
        $issues = self::collectIssues();
        if (empty($issues)) return;
        echo '<div class="notice notice-warning"><p><strong>Project & Task Time Tracker:</strong> ACF schema warnings:</p><ul style="margin-left:1em;">';
        foreach ($issues as $msg) { echo '<li>' . esc_html($msg) . '</li>'; }
        echo '</ul></div>';
    }
}
