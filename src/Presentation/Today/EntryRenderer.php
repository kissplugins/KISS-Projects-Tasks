<?php
namespace KISS\PTT\Presentation\Today;

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

