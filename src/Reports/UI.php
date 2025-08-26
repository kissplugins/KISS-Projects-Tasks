<?php
namespace KISS\PTT\Reports;

class UI
{
    /**
     * Returns the static informational block explaining the date filter behavior.
     * Pure markup string used in the Reports page header.
     */
    public static function dateFilterHelpHtml(): string
    {
        ob_start();
        ?>
        <div class="notice notice-info inline">
            <p><strong>How the Date Filter Works:</strong></p>
            <ul style="list-style: disc; padding-left: 20px;">
                <li><strong>Classic &amp; Task Focused Views:</strong> The date range shows tasks that were either created or had work sessions logged within that period.</li>
                <li><strong>Single Day View:</strong> The single date picker shows all tasks that were created or had work sessions logged on that specific day.</li>
            </ul>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * Returns an inline debug notice wrapper with a heading and content block.
     */
    public static function debugNotice(string $titleHtml, string $contentHtml): string
    {
        return '<div class="notice notice-info" style="padding: 15px; margin: 20px 0; border-left-color: #0073aa;">'
            . $titleHtml
            . $contentHtml
            . '</div>';
    }
}

