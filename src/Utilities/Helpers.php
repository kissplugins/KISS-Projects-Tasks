<?php
namespace KISS\PTT\Utilities;

use KISS\PTT\Helpers\TaskHelper;

/**
 * PSR-4 replacement for helpers.php
 * 
 * Provides utility functions for common operations across the plugin.
 * This class serves as a facade for various helper operations while maintaining
 * backward compatibility with existing procedural function calls.
 */
class Helpers
{
    /**
     * Gets all task post IDs assigned to a specific user
     * 
     * A task belongs to a user if they are the ptt_assignee meta value.
     * This ensures users only see tasks that are actually assigned to them,
     * not tasks they created for others.
     * 
     * @param int $userId The ID of the user
     * @return array An array of task post IDs. Returns an empty array if no tasks are found.
     */
    public static function getTasksForUser(int $userId): array
    {
        return TaskHelper::get_tasks_for_user($userId);
    }

    /**
     * Find the currently running session for a user, if any
     * 
     * @param int $userId The user ID to check
     * @return array|false An array with post_id and index of the active session, or false if none
     */
    public static function getActiveSessionIndexForUser(int $userId)
    {
        if (!$userId) {
            return false;
        }

        if (!function_exists('get_field')) {
            return false;
        }

        // Get tasks assigned to user
        $taskIds = self::getTasksForUser($userId);
        if (empty($taskIds)) {
            return false;
        }

        foreach ($taskIds as $taskId) {
            $sessions = get_field('sessions', $taskId);
            if (empty($sessions) || !is_array($sessions)) {
                continue;
            }

            foreach ($sessions as $idx => $session) {
                $hasStart = !empty($session['session_start_time']);
                $hasStop = !empty($session['session_stop_time']);
                $isRunning = $hasStart && !$hasStop;

                if ($isRunning) {
                    return [
                        'post_id' => (int) $taskId,
                        'index' => (int) $idx
                    ];
                }
            }
        }

        return false;
    }

    /**
     * Register procedural function wrappers for backward compatibility
     * 
     * Note: This method doesn't actually register functions since PHP doesn't
     * allow function declarations inside class methods. Instead, the functions
     * are defined in a separate compatibility file.
     * 
     * @return void
     */
    public static function registerProceduralWrappers(): void
    {
        // Functions are defined in the compatibility file loaded by Plugin class
        // This method exists for API consistency but doesn't need to do anything
    }
}
