<?php
namespace KISS\PTT\Time;

/**
 * PSR-4 replacement for time-functions.php
 * 
 * Provides a clean interface for timer-related operations while maintaining
 * backward compatibility with existing procedural function calls.
 * 
 * This class serves as a facade over the Calculator class and provides
 * additional utility functions for timer operations.
 */
class TimeFunctions
{
    /**
     * Calculate and save total duration for a task from all sessions
     * 
     * @param int $postId The task post ID
     * @return string Formatted duration (e.g., "1.50")
     */
    public static function calculateAndSaveDuration(int $postId): string
    {
        return Calculator::calculate_and_save_duration($postId);
    }

    /**
     * Get the index of the currently active session for a task
     * 
     * @param int $postId The task post ID
     * @return int|false Session index (0-based) or false if no active session
     */
    public static function getActiveSessionIndex(int $postId)
    {
        return Calculator::get_active_session_index($postId);
    }

    /**
     * Calculate duration for a specific session
     * 
     * @param int $postId The task post ID
     * @param int $index The session index (0-based)
     * @return string Formatted duration (e.g., "1.50")
     */
    public static function calculateSessionDuration(int $postId, int $index): string
    {
        return Calculator::calculate_session_duration($postId, $index);
    }

    /**
     * Ensure manual sessions have proper timestamps
     * 
     * @param int $postId The task post ID
     * @return void
     */
    public static function ensureManualSessionTimestamps(int $postId): void
    {
        Calculator::ensure_manual_session_timestamps($postId);
    }

    /**
     * Get total duration from all sessions for a task
     * 
     * This method provides backward compatibility for self-tests and other
     * legacy code that expects the ptt_get_total_sessions_duration function.
     * 
     * @param int $postId The task post ID
     * @return float Total duration in hours
     */
    public static function getTotalSessionsDuration(int $postId): float
    {
        if (!function_exists('get_field')) {
            return 0.0;
        }

        $sessions = get_field('sessions', $postId);
        if (empty($sessions)) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($sessions as $idx => $session) {
            $duration = Calculator::calculate_session_duration($postId, $idx);
            $total += (float) $duration;
        }

        return (float) number_format(ceil($total * 100) / 100, 2, '.', '');
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
