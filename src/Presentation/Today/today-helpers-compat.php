<?php
/**
 * Backward compatibility classes for today-helpers.php
 * 
 * This file provides procedural class wrappers around the PSR-4 TodayHelpers classes
 * to maintain backward compatibility with existing code.
 */

use KISS\PTT\Presentation\Today\EntryRenderer;
use KISS\PTT\Presentation\Today\DataProvider;
use KISS\PTT\Presentation\Today\PageManager;

// Block direct access
if (!defined('WPINC')) {
    die;
}

if (!class_exists('PTT_Today_Entry_Renderer')) {
    /**
     * Backward compatibility class for PTT_Today_Entry_Renderer
     * 
     * Handles the rendering of individual time entries for the Today page.
     * This class provides a flexible structure for future enhancements.
     */
    class PTT_Today_Entry_Renderer
    {
        /**
         * Renders a single time entry row
         * 
         * @param array $entry Entry data array
         * @return string HTML output
         */
        public static function render_entry($entry)
        {
            return EntryRenderer::renderEntry($entry);
        }

        /**
         * Renders the details section of an entry
         * 
         * @param array $entry Entry data
         * @return string HTML output
         */
        private static function render_entry_details($entry)
        {
            // This method is private in the original, so we'll delegate to the public method
            // The PSR-4 class handles this internally
            return '';
        }

        /**
         * Renders the duration section of an entry
         * 
         * @param array $entry Entry data
         * @return string HTML output
         */
        private static function render_entry_duration($entry)
        {
            // This method is private in the original, so we'll delegate to the public method
            // The PSR-4 class handles this internally
            return '';
        }

        /**
         * Renders the actions section of an entry
         * 
         * @param array $entry Entry data
         * @return string HTML output
         */
        private static function render_entry_actions($entry)
        {
            // This method is private in the original, so we'll delegate to the public method
            // The PSR-4 class handles this internally
            return '';
        }
    }
}

if (!class_exists('PTT_Today_Data_Provider')) {
    /**
     * Backward compatibility class for PTT_Today_Data_Provider
     * 
     * Handles data fetching and processing for the Today page.
     */
    class PTT_Today_Data_Provider
    {
        /**
         * Gets time entries for a specific user and date
         * 
         * @param int $user_id User ID
         * @param string $target_date Date in Y-m-d format
         * @param array $filters Optional filters array
         * @return array Processed entries array
         */
        public static function get_daily_entries($user_id, $target_date, $filters = [])
        {
            return DataProvider::getDailyEntries($user_id, $target_date, $filters);
        }

        /**
         * Processes a task for the target date and returns entries
         * 
         * @param int $post_id Task post ID
         * @param string $target_date Target date in Y-m-d format
         * @return array Array of entry data
         */
        private static function process_task_for_date($post_id, $target_date)
        {
            // This method is private in the original, handled internally by PSR-4 class
            return [];
        }

        /**
         * Processes sessions for a task and returns entries for the target date
         * 
         * @param int $post_id Task post ID
         * @param string $target_date Target date in Y-m-d format
         * @param string $task_title Task title
         * @param string $project_name Project name
         * @param string $client_name Client name
         * @param int $project_id Project ID
         * @param int $client_id Client ID
         * @param string $edit_link Edit link
         * @return array Array of session entry data
         */
        private static function process_task_sessions($post_id, $target_date, $task_title, $project_name, $client_name, $project_id, $client_id, $edit_link)
        {
            // This method is private in the original, handled internally by PSR-4 class
            return [];
        }

        /**
         * Calculates total duration from an array of entries
         * 
         * @param array $entries Array of entry data
         * @return array Total time in seconds and formatted string
         */
        public static function calculate_total_duration($entries)
        {
            return DataProvider::calculateTotalDuration($entries);
        }
    }
}

if (!class_exists('PTT_Today_Page_Manager')) {
    /**
     * Backward compatibility class for PTT_Today_Page_Manager
     * 
     * Main manager class for the Today page functionality.
     */
    class PTT_Today_Page_Manager
    {
        /**
         * Renders the complete entries list HTML
         * 
         * @param int $user_id User ID
         * @param string $target_date Target date
         * @param array $filters Optional filters
         * @return array HTML and total duration
         */
        public static function render_entries_list($user_id, $target_date, $filters = [])
        {
            return PageManager::renderEntriesList($user_id, $target_date, $filters);
        }

        /**
         * Get debug information for the Today page
         * 
         * @param int $user_id User ID
         * @param string $target_date Target date
         * @param int $tasks_count Tasks count
         * @param int $entries_count Entries count
         * @param array $entries Entries array
         * @return string Debug HTML
         */
        public static function get_debug_info($user_id, $target_date, $tasks_count, $entries_count, $entries)
        {
            return PageManager::getDebugInfo($user_id, $target_date, $tasks_count, $entries_count, $entries);
        }
    }
}
