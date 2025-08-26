<?php
namespace KISS\PTT\Presentation\Today;

/**
 * PSR-4 replacement for today-helpers.php
 * 
 * Contains helper classes for the Today page functionality, providing a modular
 * approach for rendering and managing time entries.
 * 
 * This class serves as a namespace container for the Today page helper classes
 * while maintaining backward compatibility with existing procedural class calls.
 */
class TodayHelpers
{
    /**
     * Register procedural class wrappers for backward compatibility
     * 
     * Note: This method doesn't actually register classes since PHP doesn't
     * allow class declarations inside class methods. Instead, the classes
     * are defined in a separate compatibility file.
     * 
     * @return void
     */
    public static function registerProceduralWrappers(): void
    {
        // Classes are defined in the compatibility file loaded by Plugin class
        // This method exists for API consistency but doesn't need to do anything
    }
}

/**
 * Entry Renderer
 * 
 * Handles the rendering of individual time entries for the Today page.
 * This class provides a flexible structure for future enhancements.
 */
class EntryRenderer
{
    /**
     * Renders a single time entry row
     * 
     * @param array $entry Entry data array
     * @return string HTML output
     */
    public static function renderEntry(array $entry): string
    {
        $runningClass = !empty($entry['is_running']) ? 'running' : '';
        $entryId = $entry['entry_id'] ?? '';
        $postId = $entry['post_id'] ?? '';
        $sessionIndex = $entry['session_index'] ?? '';

        ob_start();
        ?>
        <div class="ptt-today-entry <?php echo esc_attr($runningClass); ?>"
             data-entry-id="<?php echo esc_attr($entryId); ?>"
             data-post-id="<?php echo esc_attr($postId); ?>"
             data-session-index="<?php echo esc_attr($sessionIndex); ?>">

            <?php echo self::renderEntryDetails($entry); ?>
            <?php echo self::renderEntryDuration($entry); ?>
            <?php echo self::renderEntryActions($entry); ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the details section of an entry
     * 
     * @param array $entry Entry data
     * @return string HTML output
     */
    private static function renderEntryDetails(array $entry): string
    {
        ob_start();
        ?>
        <div class="entry-details">
            <span class="entry-session-title" data-field="session_title">
                <?php echo esc_html($entry['session_title']); ?>
                <?php if (!empty($entry['is_quick_start'])) : ?>
                    <span class="ptt-badge-quick-start" title="Quick Start Task">Quick Start</span>
                <?php endif; ?>
            </span>
            <span class="entry-meta">
                <?php
                $sessionIndex = $entry['session_index'] ?? 0;
                $isTaskLevelEntry = ($sessionIndex === -1);
                ?>
                <select class="ptt-entry-task-selector" data-original-task="<?php echo esc_attr($entry['post_id'] ?? ''); ?>" style="display:none;">
                    <option value="">Select a task...</option>
                </select>
                <button type="button" class="button button-small ptt-move-session-btn" style="display:none;">Move</button>
                <button type="button" class="button button-small ptt-cancel-move-btn" style="display:none;">Cancel</button>
                &bull;
                <span class="entry-project-name" data-field="project_name"><?php echo esc_html($entry['project_name'] ?? ''); ?></span>
                <span class="entry-client-wrapper" data-client-wrapper style="<?php echo empty($entry['client_name']) ? 'display:none;' : ''; ?>">
                    &bull;
                    <span class="entry-client-name" data-field="client_name"><?php echo esc_html($entry['client_name'] ?? ''); ?></span>
                </span>
            </span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the duration section of an entry
     * 
     * @param array $entry Entry data
     * @return string HTML output
     */
    private static function renderEntryDuration(array $entry): string
    {
        $isRunning = !empty($entry['is_running']);
        $durationClass = $isRunning ? 'entry-duration-running' : '';
        $editableAttr = $isRunning ? '' : 'data-editable="true"';

        ob_start();
        ?>
        <div class="entry-duration <?php echo esc_attr($durationClass); ?>" <?php echo $editableAttr; ?>>
            <span class="ptt-session-elapsed-time"><?php echo esc_html($entry['duration'] ?? '00:00:00'); ?></span>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders the actions section of an entry
     * 
     * @param array $entry Entry data
     * @return string HTML output
     */
    private static function renderEntryActions(array $entry): string
    {
        $sessionIndex = $entry['session_index'] ?? 0;
        $isTaskLevelEntry = ($sessionIndex === -1);
        $isRunning = !empty($entry['is_running']);
        $postId = $entry['post_id'] ?? '';
        $editLink = $entry['edit_link'] ?? '';

        ob_start();
        ?>
        <div class="entry-actions">
            <?php if ($isTaskLevelEntry && !$isRunning) : ?>
                <!-- Start Timer button for task-level entries without active sessions -->
                <button type="button"
                        class="button button-small ptt-start-timer-btn"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-action="start-timer">
                    <span class="dashicons dashicons-controls-play"></span> Start Timer
                </button>
            <?php elseif (!$isTaskLevelEntry && !$isRunning) : ?>
                <!-- Add Another Session button for completed session entries -->
                <button type="button"
                        class="button button-small ptt-add-session-btn"
                        data-post-id="<?php echo esc_attr($postId); ?>"
                        data-task-title="<?php echo esc_attr($entry['task_title'] ?? ''); ?>"
                        data-project-name="<?php echo esc_attr($entry['project_name'] ?? ''); ?>"
                        data-project-id="<?php echo esc_attr($entry['project_id'] ?? ''); ?>"
                        data-action="add-session">
                    <span class="dashicons dashicons-plus-alt"></span> Add Another Session
                </button>
            <?php endif; ?>

            <!-- Edit Task button for all entries -->
            <?php if ($editLink) : ?>
                <a href="<?php echo esc_url($editLink); ?>"
                   class="button button-small ptt-edit-task-btn"
                   target="_blank"
                   title="Edit Task">
                    <span class="dashicons dashicons-edit"></span> Edit Task
                </a>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

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

/**
 * Page Manager
 * 
 * Main manager class for the Today page functionality.
 */
class PageManager
{
    /**
     * Renders the complete entries list HTML
     * 
     * @param int $userId User ID
     * @param string $targetDate Target date
     * @param array $filters Optional filters
     * @return array HTML and total duration
     */
    public static function renderEntriesList(int $userId, string $targetDate, array $filters = []): array
    {
        $entries = DataProvider::getDailyEntries($userId, $targetDate, $filters);
        $total = DataProvider::calculateTotalDuration($entries);

        ob_start();
        if (empty($entries)) {
            echo '<div class="ptt-today-no-entries">No tasks or time entries found for this day.</div>';
        } else {
            echo '<div class="ptt-today-entries-wrapper" data-date="' . esc_attr($targetDate) . '">';
            foreach ($entries as $entry) {
                echo EntryRenderer::renderEntry($entry);
            }
            echo '</div>';
        }
        $html = ob_get_clean();

        return [
            'html' => $html,
            'total' => $total['formatted'],
            'entries' => $entries,
        ];
    }

    /**
     * Get debug information for the Today page
     * 
     * @param int $userId User ID
     * @param string $targetDate Target date
     * @param int $tasksCount Tasks count
     * @param int $entriesCount Entries count
     * @param array $entries Entries array
     * @return string Debug HTML
     */
    public static function getDebugInfo(int $userId, string $targetDate, int $tasksCount, int $entriesCount, array $entries): string
    {
        if (!isset($_GET['ptt_debug']) || $_GET['ptt_debug'] !== '1') {
            return '';
        }

        ob_start();
        ?>
        <div class="ptt-debug-info" style="margin-top: 20px; padding: 10px; background: #f0f0f0; border: 1px solid #ccc;">
            <h4>Debug Information</h4>
            <p><strong>User ID:</strong> <?php echo esc_html($userId); ?></p>
            <p><strong>Target Date:</strong> <?php echo esc_html($targetDate); ?></p>
            <p><strong>Tasks Found:</strong> <?php echo esc_html($tasksCount); ?></p>
            <p><strong>Entries Found:</strong> <?php echo esc_html($entriesCount); ?></p>
            <details>
                <summary>Raw Entries Data</summary>
                <pre><?php echo esc_html(print_r($entries, true)); ?></pre>
            </details>
        </div>
        <?php
        return ob_get_clean();
    }
}
