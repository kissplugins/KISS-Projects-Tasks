<?php
namespace KISS\PTT\Presentation\Today;

/**
 * PSR-4 port of parts of PTT_Today_Data_Provider entry building.
 * This class exposes narrow helpers we can call from legacy code.
 */
class EntryBuilder
{
    /**
     * Build a task-level entry array (created/parent_time) with provided context.
     * Mirrors the legacy format used by Today page.
     */
    public static function buildTaskLevelEntry(array $ctx): array
    {
        // Required keys in $ctx: post_id, task_title, project_name, client_name, project_id, client_id,
        // entry_type (array), start_ts, stop_ts, duration_seconds, is_running, edit_link
        return [
            'entry_id'         => $ctx['post_id'] . '_task',
            'post_id'          => $ctx['post_id'],
            'session_index'    => -1,
            'session_title'    => implode(', ', (array)($ctx['entry_type'] ?? [])) . ': ' . ($ctx['task_title'] ?? ''),
            'session_notes'    => in_array('created', (array)($ctx['entry_type'] ?? []), true) ? 'Task created on this date' : 'Parent-level time tracking',
            'task_title'       => $ctx['task_title'] ?? '',
            'project_name'     => $ctx['project_name'] ?? '',
            'client_name'      => $ctx['client_name'] ?? '',
            'project_id'       => (int)($ctx['project_id'] ?? 0),
            'client_id'        => (int)($ctx['client_id'] ?? 0),
            'is_quick_start'   => (($ctx['project_name'] ?? '') === 'Quick Start'),
            'start_time'       => (int)($ctx['start_ts'] ?? 0),
            'stop_time'        => (int)($ctx['stop_ts'] ?? 0),
            'duration_seconds' => (int)($ctx['duration_seconds'] ?? 0),
            'is_manual'        => (bool)($ctx['is_manual'] ?? false),
            'duration'         => !empty($ctx['is_running']) ? 'Running' : gmdate('H:i:s', (int)($ctx['duration_seconds'] ?? 0)),
            'is_running'       => (bool)($ctx['is_running'] ?? false),
            'edit_link'        => $ctx['edit_link'] ?? '',
            'entry_type'       => (array)($ctx['entry_type'] ?? []),
        ];
    }

    /**
     * Build session-level entries for a given task and local date.
     * Mirrors legacy structure from today-helpers.php.
     */
    public static function buildSessionEntriesForDate(int $postId, string $targetDate, array $context): array
    {
        $entries = [];
        $sessions = function_exists('get_field') ? get_field('sessions', $postId) : [];
        if (empty($sessions) || !is_array($sessions)) { return $entries; }

        foreach ($sessions as $index => $session) {
            $startStr = $session['session_start_time'] ?? '';
            if (!$startStr) { continue; }
            $startTs = \KISS\PTT\Plugin::$acf->toUtcTimestamp($startStr);
            if (!$startTs) { continue; }
            if (!DateHelper::isUtcOnLocalDate($startStr, $targetDate)) { continue; }

            $stopStr = $session['session_stop_time'] ?? '';
            $stopTs = $stopStr ? \KISS\PTT\Plugin::$acf->toUtcTimestamp($stopStr) : null;
            $durationSeconds = 0;
            if ($startTs && $stopTs) { $durationSeconds = $stopTs - $startTs; }
            elseif ($startTs && !$stopStr) { $durationSeconds = time() - $startTs; }

            if (!empty($session['session_manual_override'])) {
                $manual = isset($session['session_manual_duration']) ? (float)$session['session_manual_duration'] : 0.0;
                if ($manual > 0) { $durationSeconds = (int) round($manual * 3600); }
            }

            $entries[] = [
                'entry_id'         => $postId . '_' . $index,
                'post_id'          => $postId,
                'session_index'    => $index,
                'session_title'    => $session['session_title'] ?? '',
                'session_notes'    => $session['session_notes'] ?? '',
                'task_title'       => $context['task_title'] ?? '',
                'project_name'     => $context['project_name'] ?? '',
                'client_name'      => $context['client_name'] ?? '',
                'project_id'       => (int)($context['project_id'] ?? 0),
                'client_id'        => (int)($context['client_id'] ?? 0),
                'is_quick_start'   => (($context['project_name'] ?? '') === 'Quick Start'),
                'start_time'       => $startTs,
                'stop_time'        => $stopTs,
                'duration_seconds' => $durationSeconds,
                'is_manual'        => !empty($session['session_manual_override']),
                'duration'         => $durationSeconds > 0 ? gmdate('H:i:s', $durationSeconds) : 'Running',
                'is_running'       => empty($stopStr),
                'edit_link'        => $context['edit_link'] ?? '',
                'entry_type'       => ['session'],
            ];
        }
        return $entries;
    }
}

