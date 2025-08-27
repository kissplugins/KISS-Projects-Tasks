<?php
namespace KISS\PTT\Presentation\Today;

/**
 * PSR-4 replacement for today.php
 * 
 * Handles the Today page registration, rendering, and AJAX functionality.
 * This controller manages the daily time-tracking dashboard view.
 */
class TodayController
{
    /**
     * Register all Today page hooks and handlers
     * 
     * @return void
     */
    public static function register(): void
    {
        // Menu registration
        add_action('admin_menu', [self::class, 'addTodayPage'], 5);
        add_action('admin_enqueue_scripts', [self::class, 'enqueueFont'], 20);

        // AJAX handlers
        add_action('wp_ajax_ptt_get_tasks_for_today_page', [self::class, 'getTasksForTodayPageCallback']);
        add_action('wp_ajax_ptt_today_start_new_session', [self::class, 'startNewSessionCallback']);
        add_action('wp_ajax_ptt_get_daily_entries', [self::class, 'getDailyEntriesCallback']);
        add_action('wp_ajax_ptt_update_session_duration', [self::class, 'updateSessionDurationCallback']);
        add_action('wp_ajax_ptt_update_session_field', [self::class, 'updateSessionFieldCallback']);
        add_action('wp_ajax_ptt_delete_session', [self::class, 'deleteSessionCallback']);
        add_action('wp_ajax_ptt_move_session', [self::class, 'moveSessionCallback']);
        add_action('wp_ajax_ptt_today_start_timer', [self::class, 'startTimerCallback']);
        add_action('wp_ajax_ptt_today_quick_start', [self::class, 'quickStartCallback']);
        add_action('wp_ajax_ptt_rehydrate_timer', [self::class, 'rehydrateTimerCallback']);
    }

    /**
     * Adds the "Today" link under the Tasks CPT menu
     * 
     * @return void
     */
    public static function addTodayPage(): void
    {
        add_submenu_page(
            'edit.php?post_type=project_task', // Parent slug
            'Today View',                      // Page title
            'Today',                           // Menu title
            'edit_posts',                      // Capability
            'ptt-today',                       // Menu slug
            [self::class, 'renderTodayPageHtml'] // Callback
        );
    }

    /**
     * Ensures time display styles are applied on the Today page
     * 
     * @param string $hook Current admin page hook
     * @return void
     */
    public static function enqueueFont(string $hook): void
    {
        if ('project_task_page_ptt-today' !== $hook) {
            return;
        }
        // Using local @font-face via styles.css; no external font enqueue needed.
    }

    /**
     * Renders the Today page HTML
     * 
     * @return void
     */
    public static function renderTodayPageHtml(): void
    {
        ?>
        <div class="wrap" id="ptt-today-page-container">
            <h1>Today</h1>

            <!-- Filter Controls -->
            <div class="ptt-today-filters">
                <label for="ptt-today-date-picker">Date:</label>
                <input type="date" id="ptt-today-date-picker" value="<?php echo esc_attr(date('Y-m-d')); ?>">

                <label for="ptt-today-client-filter">Client:</label>
                <select id="ptt-today-client-filter">
                    <option value="">All Clients</option>
                    <?php
                    $clients = get_terms(['taxonomy' => 'client', 'hide_empty' => false]);
                    if (!is_wp_error($clients)) {
                        foreach ($clients as $client) {
                            echo '<option value="' . esc_attr($client->term_id) . '">' . esc_html($client->name) . '</option>';
                        }
                    }
                    ?>
                </select>

                <label for="ptt-today-project-filter">Project:</label>
                <select id="ptt-today-project-filter">
                    <option value="">All Projects</option>
                    <?php
                    $projects = get_terms(['taxonomy' => 'project', 'hide_empty' => false]);
                    if (!is_wp_error($projects)) {
                        foreach ($projects as $project) {
                            echo '<option value="' . esc_attr($project->term_id) . '">' . esc_html($project->name) . '</option>';
                        }
                    }
                    ?>
                </select>

                <button type="button" id="ptt-today-refresh-btn" class="button">Refresh</button>
            </div>

            <!-- Quick Start Section -->
            <div class="ptt-today-quick-start">
                <h3>Quick Start</h3>
                <div class="ptt-quick-start-controls">
                    <label for="ptt-quick-start-client">Client:</label>
                    <select id="ptt-quick-start-client">
                        <option value="">Select Client</option>
                        <?php
                        if (!is_wp_error($clients)) {
                            foreach ($clients as $client) {
                                echo '<option value="' . esc_attr($client->term_id) . '">' . esc_html($client->name) . '</option>';
                            }
                        }
                        ?>
                    </select>
                    <button type="button" id="ptt-quick-start-btn" class="button button-primary">Quick Start</button>
                </div>
            </div>

            <!-- Add Session Section -->
            <div class="ptt-today-add-session">
                <h3>Add Session</h3>
                <div class="ptt-add-session-controls">
                    <label for="ptt-session-task-selector">Task:</label>
                    <select id="ptt-session-task-selector">
                        <option value="">Select Task</option>
                    </select>

                    <label for="ptt-session-title">Session Title:</label>
                    <input type="text" id="ptt-session-title" placeholder="Session title">

                    <label for="ptt-session-notes">Notes:</label>
                    <textarea id="ptt-session-notes" placeholder="Session notes"></textarea>

                    <button type="button" id="ptt-add-session-btn" class="button button-primary">Add Session</button>
                </div>
            </div>

            <!-- Entries Display -->
            <div class="ptt-today-entries">
                <div class="ptt-today-entries-header">
                    <h3>Time Entries</h3>
                    <div class="ptt-today-total">
                        Total: <span id="ptt-today-total-time">00:00:00</span>
                    </div>
                </div>
                <div id="ptt-today-entries-list">
                    <!-- Entries will be loaded here via AJAX -->
                </div>
            </div>

            <!-- Debug Section -->
            <div id="ptt-today-debug-section"></div>

            <!-- Entry Template (Hidden, for JS cloning) -->
            <template id="ptt-today-entry-template">
                <div class="ptt-today-entry" data-entry-id="" data-post-id="" data-session-index="">
                    <div class="entry-details">
                        <span class="entry-session-title" data-field="session_title"></span>
                        <span class="entry-meta">
                            <select class="ptt-entry-task-selector" data-original-task=""></select>
                            <button type="button" class="button button-small ptt-move-session-btn" style="display:none;">Move</button>
                            <button type="button" class="button button-small ptt-cancel-move-btn" style="display:none;">Cancel</button>
                            &bull;
                            <span class="entry-project-name" data-field="project_name"></span>
                            <span class="entry-client-wrapper" data-client-wrapper style="display:none;">
                                &bull;
                                <span class="entry-client-name" data-field="client_name"></span>
                            </span>
                        </span>
                    </div>
                    <div class="entry-duration">
                        <span class="ptt-session-elapsed-time">00:00:00</span>
                    </div>
                    <div class="entry-actions">
                        <!-- Actions will be populated by JavaScript -->
                    </div>
                </div>
            </template>

            <!-- Hidden Data Storage for JS -->
            <div id="ptt-today-data-storage" style="display: none;">
                <input type="hidden" id="ptt-current-user-id" value="<?php echo get_current_user_id(); ?>">
                <input type="hidden" id="ptt-ajax-nonce" value="<?php echo wp_create_nonce('ptt_ajax_nonce'); ?>">
                <input type="hidden" id="ptt-ajax-url" value="<?php echo admin_url('admin-ajax.php'); ?>">
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler to get tasks for the Today page dropdown
     * 
     * @return void
     */
    public static function getTasksForTodayPageCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error();
        }

        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;
        $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $userId = get_current_user_id();

        // Get all tasks assigned to the current user
        $userTaskIds = function_exists('ptt_get_tasks_for_user') ? ptt_get_tasks_for_user($userId) : [];
        if (empty($userTaskIds)) {
            wp_send_json_success([]); // Send empty array if user has no tasks
        }

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

        // Filter by task status (Not Started or In Progress)
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key' => 'task_status',
                'value' => ['not-started', 'in-progress'],
                'compare' => 'IN',
            ],
            [
                'key' => 'task_status',
                'compare' => 'NOT EXISTS',
            ],
        ];

        $query = new \WP_Query($args);
        $tasks = [];
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $postId = get_the_ID();

                // Get additional metadata for richer dropdown display
                $projectTerms = get_the_terms($postId, 'project');
                $projectName = !is_wp_error($projectTerms) && $projectTerms ? $projectTerms[0]->name : '';

                $tasks[] = [
                    'id' => $postId,
                    'title' => get_the_title(),
                    'project_name' => $projectName,
                    'edit_link' => get_edit_post_link($postId),
                ];
            }
            wp_reset_postdata();
        }

        wp_send_json_success($tasks);
    }

    /**
     * AJAX handler to start a new session from the Today page
     * 
     * @return void
     */
    public static function startNewSessionCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $sessionTitle = isset($_POST['session_title']) ? sanitize_text_field($_POST['session_title']) : 'New Session';
        $sessionNotes = isset($_POST['session_notes']) ? sanitize_textarea_field($_POST['session_notes']) : '';

        if (!$postId) {
            wp_send_json_error(['message' => 'Invalid Task ID.']);
        }

        // Stop any other running session for the current user first (global invariant)
        $activeSession = function_exists('ptt_get_active_session_index_for_user') ? ptt_get_active_session_index_for_user(get_current_user_id()) : false;
        if ($activeSession) {
            \KISS\PTT\Plugin::$timer->stopActive($activeSession['post_id']);
        }

        $newSession = [
            'session_title' => $sessionTitle,
            'session_notes' => $sessionNotes,
            'session_start_time' => current_time('mysql', 1), // UTC
        ];

        $newRowIndex = add_row('sessions', $newSession, $postId);

        if (!$newRowIndex) {
            wp_send_json_error(['message' => 'Failed to create new session.']);
        }

        // Recalculate duration
        if (function_exists('ptt_calculate_and_save_duration')) {
            ptt_calculate_and_save_duration($postId);
        }

        // Get task info for response
        $taskTitle = get_the_title($postId);
        $projectTerms = get_the_terms($postId, 'project');
        $projectName = !is_wp_error($projectTerms) && $projectTerms ? $projectTerms[0]->name : '';

        wp_send_json_success([
            'message' => 'Session started successfully!',
            'post_id' => $postId,
            'session_index' => $newRowIndex - 1, // Convert to 0-based index
            'session_title' => $sessionTitle,
            'task_title' => $taskTitle,
            'project_name' => $projectName,
            'start_time' => $newSession['session_start_time'],
        ]);
    }

    /**
     * AJAX handler to get time entries for a specific day for the current user
     *
     * @return void
     */
    public static function getDailyEntriesCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error();
        }

        $userId = get_current_user_id();
        $targetDate = isset($_POST['date']) ? sanitize_text_field($_POST['date']) : date('Y-m-d');
        $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        $projectId = isset($_POST['project_id']) ? intval($_POST['project_id']) : 0;

        // Build filters array
        $filters = [];
        if ($clientId > 0) {
            $filters['client_id'] = $clientId;
        }
        if ($projectId > 0) {
            $filters['project_id'] = $projectId;
        }

        // Fetch entries and build custom HTML with start/end times
        $entries = class_exists('PTT_Today_Data_Provider') ? \PTT_Today_Data_Provider::get_daily_entries($userId, $targetDate, $filters) : [];
        $total = class_exists('PTT_Today_Data_Provider') ? \PTT_Today_Data_Provider::calculate_total_duration($entries) : ['seconds' => 0];

        ob_start();
        if (empty($entries)) {
            echo '<div class="ptt-today-no-entries">No tasks or time entries found for this day.</div>';
        } else {
            echo '<div class="ptt-today-entries-wrapper" data-date="' . esc_attr($targetDate) . '">';
            foreach ($entries as $entry) {
                $entryHtml = class_exists('PTT_Today_Entry_Renderer') ? \PTT_Today_Entry_Renderer::render_entry($entry) : '';

                $durationClass = !empty($entry['is_running']) ? 'entry-duration-running' : '';
                $editableAttr = !empty($entry['is_running']) ? '' : 'data-editable="true"';

                $startNum = $entry['start_time'] ? wp_date('h:i:s', $entry['start_time']) : '--:--:--';
                $startAmpm = $entry['start_time'] ? wp_date('A', $entry['start_time']) : '';
                $endNum = $entry['end_time'] ? wp_date('h:i:s', $entry['end_time']) : '--:--:--';
                $endAmpm = $entry['end_time'] ? wp_date('A', $entry['end_time']) : '';

                ob_start();
                ?>
                <div class="entry-duration <?php echo esc_attr($durationClass); ?>" <?php echo $editableAttr; ?>>
                    <div class="entry-time-range">
                        <span class="entry-start-time">
                            <span class="time-num"><?php echo esc_html($startNum); ?></span>
                            <span class="time-ampm"><?php echo esc_html($startAmpm); ?></span>
                        </span>
                        <span class="time-separator">—</span>
                        <span class="entry-end-time">
                            <span class="time-num"><?php echo esc_html($endNum); ?></span>
                            <span class="time-ampm"><?php echo esc_html($endAmpm); ?></span>
                        </span>
                    </div>
                    <span class="ptt-session-elapsed-time"><?php echo esc_html($entry['duration'] ?? '00:00:00'); ?></span>
                </div>
                <?php
                $durationDiv = ob_get_clean();
                $entryHtml = preg_replace('#<div class="entry-duration[^>]*>.*?</div>#s', $durationDiv, $entryHtml);
                echo $entryHtml;
            }
            echo '</div>';
        }
        $html = ob_get_clean();

        // Get debug info
        $entriesCount = count($entries);
        $tasksCount = count(array_unique(array_column($entries, 'post_id')));
        $debugHtml = class_exists('PTT_Today_Page_Manager') ? \PTT_Today_Page_Manager::get_debug_info($userId, $targetDate, $tasksCount, $entriesCount, $entries) : '';

        wp_send_json_success([
            'html' => $html,
            'total' => gmdate('H:i:s', $total['seconds']),
            'debug' => $debugHtml,
            'entries' => $entries, // Include raw data for JS manipulation
        ]);
    }

    /**
     * AJAX handler to update session duration
     *
     * @return void
     */
    public static function updateSessionDurationCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $sessionIndex = isset($_POST['session_index']) ? intval($_POST['session_index']) : -1;
        $newDuration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : '';

        if (!$postId || $sessionIndex < 0) {
            wp_send_json_error(['message' => 'Invalid parameters.']);
        }

        // Convert duration to decimal hours
        $durationParts = explode(':', $newDuration);
        if (count($durationParts) !== 3) {
            wp_send_json_error(['message' => 'Invalid duration format.']);
        }

        $hours = intval($durationParts[0]);
        $minutes = intval($durationParts[1]);
        $seconds = intval($durationParts[2]);
        $decimalHours = $hours + ($minutes / 60) + ($seconds / 3600);

        // Update the session
        $sessions = function_exists('get_field') ? get_field('sessions', $postId) : [];
        if (!is_array($sessions) || !isset($sessions[$sessionIndex])) {
            wp_send_json_error(['message' => 'Session not found.']);
        }

        $sessions[$sessionIndex]['session_manual_override'] = true;
        $sessions[$sessionIndex]['session_manual_duration'] = number_format($decimalHours, 2);

        if (function_exists('update_field')) {
            update_field('sessions', $sessions, $postId);
        }

        // Recalculate total duration
        if (function_exists('ptt_calculate_and_save_duration')) {
            ptt_calculate_and_save_duration($postId);
        }

        wp_send_json_success(['message' => 'Duration updated successfully.']);
    }

    /**
     * AJAX handler to update session field
     *
     * @return void
     */
    public static function updateSessionFieldCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $sessionIndex = isset($_POST['session_index']) ? intval($_POST['session_index']) : -1;
        $field = isset($_POST['field']) ? sanitize_text_field($_POST['field']) : '';
        $value = isset($_POST['value']) ? sanitize_text_field($_POST['value']) : '';

        if (!$postId || $sessionIndex < 0 || !$field) {
            wp_send_json_error(['message' => 'Invalid parameters.']);
        }

        // Get sessions
        $sessions = function_exists('get_field') ? get_field('sessions', $postId) : [];
        if (!is_array($sessions) || !isset($sessions[$sessionIndex])) {
            wp_send_json_error(['message' => 'Session not found.']);
        }

        // Update the field
        $sessions[$sessionIndex][$field] = $value;

        if (function_exists('update_field')) {
            update_field('sessions', $sessions, $postId);
        }

        wp_send_json_success(['message' => 'Field updated successfully.']);
    }

    /**
     * AJAX handler to delete session
     *
     * @return void
     */
    public static function deleteSessionCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $sessionIndex = isset($_POST['session_index']) ? intval($_POST['session_index']) : -1;

        if (!$postId || $sessionIndex < 0) {
            wp_send_json_error(['message' => 'Invalid parameters.']);
        }

        // Delete the session row
        if (function_exists('delete_row')) {
            $result = delete_row('sessions', $sessionIndex + 1, $postId); // ACF uses 1-based indexing
            if ($result) {
                // Recalculate total duration
                if (function_exists('ptt_calculate_and_save_duration')) {
                    ptt_calculate_and_save_duration($postId);
                }
                wp_send_json_success(['message' => 'Session deleted successfully.']);
            } else {
                wp_send_json_error(['message' => 'Failed to delete session.']);
            }
        } else {
            wp_send_json_error(['message' => 'ACF functions not available.']);
        }
    }

    /**
     * AJAX handler to move session to another task
     *
     * @return void
     */
    public static function moveSessionCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $sourcePostId = isset($_POST['source_post_id']) ? intval($_POST['source_post_id']) : 0;
        $sessionIndex = isset($_POST['session_index']) ? intval($_POST['session_index']) : -1;
        $targetPostId = isset($_POST['target_post_id']) ? intval($_POST['target_post_id']) : 0;

        if (!$sourcePostId || !$targetPostId || $sessionIndex < 0) {
            wp_send_json_error(['message' => 'Invalid parameters.']);
        }

        $result = self::moveSessionToTask($sourcePostId, $sessionIndex, $targetPostId);
        if ($result) {
            wp_send_json_success(['message' => 'Session moved successfully.']);
        } else {
            wp_send_json_error(['message' => 'Failed to move session.']);
        }
    }

    /**
     * Move a session from one task to another
     *
     * @param int $sourcePostId Source task ID
     * @param int $sessionIndex Session index
     * @param int $targetPostId Target task ID
     * @return bool Success status
     */
    public static function moveSessionToTask(int $sourcePostId, int $sessionIndex, int $targetPostId): bool
    {
        if (!$sourcePostId || !$targetPostId || $sessionIndex < 0) {
            return false;
        }

        // Get source sessions
        $sourceSessions = function_exists('get_field') ? get_field('sessions', $sourcePostId) : [];
        if (!is_array($sourceSessions) || !isset($sourceSessions[$sessionIndex])) {
            return false;
        }

        // Get the session to move
        $sessionToMove = $sourceSessions[$sessionIndex];

        // Add to target task
        if (function_exists('add_row')) {
            $result = add_row('sessions', $sessionToMove, $targetPostId);
            if (!$result) {
                return false;
            }
        } else {
            return false;
        }

        // Remove from source task
        if (function_exists('delete_row')) {
            delete_row('sessions', $sessionIndex + 1, $sourcePostId); // ACF uses 1-based indexing
        }

        // Recalculate durations for both tasks
        if (function_exists('ptt_calculate_and_save_duration')) {
            ptt_calculate_and_save_duration($sourcePostId);
            ptt_calculate_and_save_duration($targetPostId);
        }

        return true;
    }

    /**
     * AJAX handler to start a timer from the Today page
     *
     * @return void
     */
    public static function startTimerCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        if (!$postId) {
            wp_send_json_error(['message' => 'Invalid task ID.']);
        }

        // Check if user has any active sessions
        $activeSession = function_exists('ptt_get_active_session_index_for_user') ? ptt_get_active_session_index_for_user(get_current_user_id()) : false;
        if ($activeSession) {
            wp_send_json_error([
                'message' => 'You have an active timer running. Please stop it before starting a new one.',
                'active_task_id' => $activeSession['post_id']
            ]);
        }

        // Get task title for auto-generated session title
        $taskTitle = get_the_title($postId);
        $sessionTitle = 'Timer: ' . $taskTitle;

        // Create new session
        $newSession = [
            'session_title' => $sessionTitle,
            'session_notes' => '',
            'session_start_time' => current_time('mysql', 1), // UTC
        ];

        $newRowIndex = add_row('sessions', $newSession, $postId);
        if (!$newRowIndex) {
            wp_send_json_error(['message' => 'Failed to create session.']);
        }

        wp_send_json_success([
            'message' => 'Timer started successfully!',
            'post_id' => $postId,
            'session_index' => $newRowIndex - 1,
            'session_title' => $sessionTitle,
        ]);
    }

    /**
     * AJAX handler for Quick Start functionality
     *
     * @return void
     */
    public static function quickStartCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $clientId = isset($_POST['client_id']) ? intval($_POST['client_id']) : 0;
        if (!$clientId) {
            wp_send_json_error(['message' => 'Client is required.']);
        }

        $userId = get_current_user_id();

        // Stop any other running session for the current user first (global invariant)
        $activeSession = function_exists('ptt_get_active_session_index_for_user') ? ptt_get_active_session_index_for_user($userId) : false;
        if ($activeSession) {
            \KISS\PTT\Plugin::$timer->stopActive($activeSession['post_id']);
        }

        // Get or create Quick Start project
        $projectTermId = self::getOrCreateQuickStartProject();
        if (!$projectTermId) {
            wp_send_json_error(['message' => 'Failed to create Quick Start project.']);
        }

        // Get or create daily Quick Start task
        $taskId = self::getOrCreateDailyQuickStartTask($userId, $projectTermId, $clientId);
        if (!$taskId) {
            wp_send_json_error(['message' => 'Failed to create Quick Start task.']);
        }

        // Start a new session
        $sessionTitle = 'Quick Start Session';
        $newSession = [
            'session_title' => $sessionTitle,
            'session_notes' => '',
            'session_start_time' => current_time('mysql', 1), // UTC
        ];

        $newRowIndex = add_row('sessions', $newSession, $taskId);
        if (!$newRowIndex) {
            wp_send_json_error(['message' => 'Failed to start session.']);
        }

        wp_send_json_success([
            'message' => 'Quick Start session created successfully!',
            'task_id' => $taskId,
            'session_index' => $newRowIndex - 1,
        ]);
    }

    /**
     * Get or create the global placeholder Project
     *
     * @return int Project term ID
     */
    public static function getOrCreateQuickStartProject(): int
    {
        $term = get_term_by('slug', 'quick-start', 'project');
        if ($term && !is_wp_error($term)) {
            return $term->term_id;
        }
        $created = wp_insert_term('Quick Start', 'project', ['slug' => 'quick-start']);
        if (is_wp_error($created)) {
            return 0;
        }
        return (int) ($created['term_id'] ?? 0);
    }

    /**
     * Get or create the per-user placeholder Task under the placeholder project
     *
     * @param int $userId User ID
     * @param int $projectTermId Project term ID
     * @param int $clientTermId Client term ID
     * @return int Task post ID
     */
    public static function getOrCreateDailyQuickStartTask(int $userId, int $projectTermId, int $clientTermId): int
    {
        $dateLabel = wp_date('M. j, Y');
        $clientObj = $clientTermId ? get_term($clientTermId, 'client') : null;
        $clientLabel = ($clientObj && !is_wp_error($clientObj)) ? (' — ' . $clientObj->name) : '';
        $taskTitle = sprintf('Quick Start — %s — %s%s', $dateLabel, wp_get_current_user()->display_name, $clientLabel);

        // Check if task already exists for today
        $existingTasks = get_posts([
            'post_type' => 'project_task',
            'post_status' => 'publish',
            'meta_query' => [
                [
                    'key' => 'ptt_assignee',
                    'value' => $userId,
                    'compare' => '='
                ]
            ],
            'tax_query' => [
                [
                    'taxonomy' => 'project',
                    'field' => 'term_id',
                    'terms' => $projectTermId,
                ]
            ],
            'title' => $taskTitle,
            'numberposts' => 1,
        ]);

        if (!empty($existingTasks)) {
            return $existingTasks[0]->ID;
        }

        // Create new task
        $taskId = wp_insert_post([
            'post_type' => 'project_task',
            'post_title' => $taskTitle,
            'post_status' => 'publish',
            'post_author' => $userId,
        ]);

        if (!$taskId || is_wp_error($taskId)) {
            return 0;
        }

        // Set assignee
        update_post_meta($taskId, 'ptt_assignee', $userId);

        // Set taxonomies
        wp_set_object_terms($taskId, [$projectTermId], 'project');
        if ($clientTermId) {
            wp_set_object_terms($taskId, [$clientTermId], 'client');
        }

        return $taskId;
    }

    /**
     * AJAX handler to rehydrate timer state for Today page FSM
     *
     * @return void
     */
    public static function rehydrateTimerCallback(): void
    {
        check_ajax_referer('ptt_ajax_nonce', 'nonce');
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permission denied.']);
        }

        $userId = get_current_user_id();
        if (!$userId || !function_exists('ptt_get_active_session_index_for_user')) {
            wp_send_json_success(['running' => false]);
        }

        $active = ptt_get_active_session_index_for_user($userId);
        if (!$active || empty($active['post_id'])) {
            wp_send_json_success(['running' => false]);
        }

        $postId = (int) $active['post_id'];
        $index0 = (int) $active['index'];
        $sessions = function_exists('get_field') ? get_field('sessions', $postId) : [];
        $start = '';
        $title = '';
        if (is_array($sessions) && isset($sessions[$index0])) {
            $start = $sessions[$index0]['session_start_time'] ?? '';
            $title = $sessions[$index0]['session_title'] ?? '';
        }

        wp_send_json_success([
            'running' => true,
            'taskId' => $postId,
            'postId' => $postId,
            'sessionIndex' => $index0,
            'startUtc' => $start,
            'sessionTitle' => $title,
        ]);
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
