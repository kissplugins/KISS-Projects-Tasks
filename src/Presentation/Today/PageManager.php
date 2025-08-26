<?php
namespace KISS\PTT\Presentation\Today;

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

