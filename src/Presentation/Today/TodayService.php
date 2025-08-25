<?php
namespace KISS\PTT\Presentation\Today;

use KISS\PTT\Plugin;

/**
 * Minimal Today service to centralize data-building helpers under PSR-4.
 * This is a non-breaking wrapper over existing static helpers.
 */
class TodayService
{
    /** Calculate total duration from Today entries. */
    public static function calculateTotalDuration(array $entries): array
    {
        $total = 0;
        foreach ($entries as $e) {
            if (isset($e['duration_seconds'])) { $total += (int)$e['duration_seconds']; }
        }
        return [ 'seconds' => $total, 'formatted' => gmdate('H:i:s', $total) ];
    }

    /**
     * Orchestrate building entries for a post on a target local date.
     * Mirrors legacy process_task_for_date logic.
     */
    public static function buildEntriesForTaskOnDate(int $postId, string $targetDate): array
    {
        $entries = [];

        // Task metadata
        $taskTitle   = get_the_title($postId);
        $project     = get_the_terms($postId, 'project');
        $projectId   = (!is_wp_error($project) && $project) ? $project[0]->term_id : 0;
        $projectName = (!is_wp_error($project) && $project) ? $project[0]->name     : '–';
        $client      = get_the_terms($postId, 'client');
        $clientId    = (!is_wp_error($client) && $client) ? $client[0]->term_id : 0;
        $clientName  = (!is_wp_error($client) && $client) ? $client[0]->name     : '';
        $editLink    = get_edit_post_link($postId);

        // 1. Task created on date?
        $postDate = get_the_date('Y-m-d', $postId);
        $taskCreatedOnDate = ($postDate === $targetDate);

        // 2. Parent-level time tracking
        $parentStartTime = function_exists('get_field') ? get_field('start_time', $postId) : null;
        $parentMatchesDate = false; $startTs = 0; $stopTs = 0; $durationSeconds = 0; $isRunning = false;
        if ($parentStartTime) {
            $startTs = strtotime($parentStartTime);
            $parentMatchesDate = ($startTs && wp_date('Y-m-d', $startTs) === $targetDate);
        }

        // 3. Session entries for date
        $sessionEntries = EntryBuilder::buildSessionEntriesForDate($postId, $targetDate, [
            'task_title'   => $taskTitle,
            'project_name' => $projectName,
            'client_name'  => $clientName,
            'project_id'   => $projectId,
            'client_id'    => $clientId,
            'edit_link'    => $editLink,
        ]);
        $hasSessionForDate = !empty($sessionEntries);

        // Task-level entry when applicable and no session entries
        if (($taskCreatedOnDate || $parentMatchesDate) && !$hasSessionForDate) {
            $entryType = [];
            if ($taskCreatedOnDate) { $entryType[] = 'created'; }
            if ($parentMatchesDate) { $entryType[] = 'parent_time'; }

            if ($parentMatchesDate) {
                $parentStopTime   = function_exists('get_field') ? get_field('stop_time', $postId) : null;
                $manualOverride   = function_exists('get_field') ? get_field('manual_override', $postId) : false;
                if ($manualOverride) {
                    $manualDuration   = function_exists('get_field') ? get_field('manual_duration', $postId) : 0;
                    $durationSeconds  = $manualDuration ? (int) round((float)$manualDuration * 3600) : 0;
                    $stopTs           = $startTs; // same ts for display
                } elseif ($parentStopTime) {
                    $stopTs = strtotime($parentStopTime);
                    if ($startTs && $stopTs) { $durationSeconds = $stopTs - $startTs; }
                } else {
                    $durationSeconds = time() - $startTs;
                    $isRunning = true;
                }
            } else {
                // Created on date, no time tracking
                $startTs = strtotime($postDate . ' 00:00:00');
            }

            $entries[] = EntryBuilder::buildTaskLevelEntry([
                'post_id'          => $postId,
                'task_title'       => $taskTitle,
                'project_name'     => $projectName,
                'client_name'      => $clientName,
                'project_id'       => $projectId,
                'client_id'        => $clientId,
                'entry_type'       => $entryType,
                'start_ts'         => $startTs,
                'stop_ts'          => $stopTs,
                'duration_seconds' => $durationSeconds,
                'is_running'       => $isRunning,
                'is_manual'        => $parentMatchesDate && (function_exists('get_field') ? (bool) get_field('manual_override', $postId) : false),
                'edit_link'        => $editLink,
            ]);
        }

        // Append session entries
        $entries = array_merge($entries, $sessionEntries);
        return $entries;
    }
}

