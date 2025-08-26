<?php
namespace KISS\PTT\Presentation\Today;

/**
 * Data Provider
 *
 * Handles data fetching and processing for the Today page.
 */
class DataProvider
{
    /**
     * Gets time entries for a specific user and date
     *
     * @param int $userId User ID
     * @param string $targetDate Date in Y-m-d format
     * @param array $filters Optional filters array
     * @return array Processed entries array
     */
    public static function getDailyEntries(int $userId, string $targetDate, array $filters = []): array
    {
        $allEntries = [];

        // Get all tasks for the current user
        $userTaskIds = function_exists('ptt_get_tasks_for_user') ? ptt_get_tasks_for_user($userId) : [];

        if (empty($userTaskIds)) {
            return $allEntries;
        }

        // Apply filters
        $projectId = $filters['project_id'] ?? 0;
        $clientId = $filters['client_id'] ?? 0;

        $args = [
            'post_type' => 'project_task',
            'posts_per_page' => -1,
            'post__in' => $userTaskIds,
            'post_status' => 'publish',
            'orderby' => 'modified',
            'order' => 'DESC',
        ];

        // Apply taxonomy filters
        $taxQuery = [];
        if ($projectId) {
            $taxQuery[] = [
                'taxonomy' => 'project',
                'field' => 'term_id',
                'terms' => $projectId,
            ];
        }
        if ($clientId) {
            $taxQuery[] = [
                'taxonomy' => 'client',
                'field' => 'term_id',
                'terms' => $clientId,
            ];
        }
        if (!empty($taxQuery)) {
            $args['tax_query'] = $taxQuery;
        }

        $tasks = get_posts($args);

        foreach ($tasks as $task) {
            $taskEntries = self::processTaskForDate($task->ID, $targetDate);
            $allEntries = array_merge($allEntries, $taskEntries);
        }

        // Sort entries by start time (most recent first)
        usort($allEntries, function ($a, $b) {
            $aTime = $a['start_time'] ?? 0;
            $bTime = $b['start_time'] ?? 0;
            return $bTime <=> $aTime;
        });

        return $allEntries;
    }

    /**
     * Processes a task for the target date and returns entries
     *
     * @param int $postId Task post ID
     * @param string $targetDate Target date in Y-m-d format
     * @return array Array of entry data
     */
    private static function processTaskForDate(int $postId, string $targetDate): array
    {
        return TodayService::buildEntriesForTaskOnDate($postId, $targetDate);
    }

    /**
     * Calculates total duration from an array of entries
     *
     * @param array $entries Array of entry data
     * @return array Total time in seconds and formatted string
     */
    public static function calculateTotalDuration(array $entries): array
    {
        return TodayService::calculateTotalDuration($entries);
    }
}

