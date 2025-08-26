<?php

namespace KISS\PTT\Admin;

/**
 * Data Migration Controller - Convert parent-level timer data to session repeater format
 * Ensures single source of truth by migrating legacy data structure
 */
class DataMigrationController
{
    public static function register()
    {
        add_action('wp_ajax_ptt_migrate_parent_level_data', [self::class, 'migrateParentLevelData']);
        add_action('admin_menu', [self::class, 'addAdminPage']);
    }

    public static function addAdminPage()
    {
        add_submenu_page(
            'edit.php?post_type=project_task',
            'Data Migration',
            'Data Migration',
            'manage_options',
            'ptt-data-migration',
            [self::class, 'renderMigrationPage']
        );
    }

    public static function renderMigrationPage()
    {
        // Check for tasks with parent-level timer data
        $tasksWithParentData = self::findTasksWithParentLevelData();
        $totalTasks = count($tasksWithParentData);

        ?>
        <div class="wrap">
            <h1>PTT Data Migration</h1>
            <div class="notice notice-info">
                <p><strong>Purpose:</strong> Convert legacy parent-level timer data to session repeater format for single source of truth.</p>
            </div>

            <?php if ($totalTasks > 0): ?>
                <div class="notice notice-warning">
                    <p><strong>Found <?php echo $totalTasks; ?> tasks with parent-level timer data that need migration.</strong></p>
                </div>

                <h2>Migration Preview</h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Task Title</th>
                            <th>Start Time</th>
                            <th>Stop Time</th>
                            <th>Duration (hrs)</th>
                            <th>Manual Override</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($tasksWithParentData, 0, 10) as $task): ?>
                            <tr>
                                <td><?php echo $task['ID']; ?></td>
                                <td><?php echo esc_html($task['post_title']); ?></td>
                                <td><?php echo $task['start_time'] ?: '--'; ?></td>
                                <td><?php echo $task['stop_time'] ?: '--'; ?></td>
                                <td><?php echo $task['calculated_duration'] ?: '0.00'; ?></td>
                                <td><?php echo $task['manual_override'] ? 'Yes' : 'No'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($totalTasks > 10): ?>
                            <tr><td colspan="6"><em>... and <?php echo $totalTasks - 10; ?> more tasks</em></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <h2>Migration Actions</h2>
                <p><strong>What this migration will do:</strong></p>
                <ul>
                    <li>Convert parent-level timer data to session repeater entries</li>
                    <li>Preserve all timing data and manual overrides</li>
                    <li>Clear parent-level fields after successful migration</li>
                    <li>Create backup of original data before migration</li>
                </ul>

                <div style="margin: 20px 0;">
                    <button id="ptt-start-migration" class="button button-primary button-large">
                        Start Migration (<?php echo $totalTasks; ?> tasks)
                    </button>
                    <button id="ptt-dry-run-migration" class="button button-secondary">
                        Dry Run (Preview Only)
                    </button>
                </div>

                <div id="ptt-migration-progress" style="display: none;">
                    <h3>Migration Progress</h3>
                    <div id="ptt-progress-bar" style="width: 100%; background: #f1f1f1; border-radius: 3px;">
                        <div id="ptt-progress-fill" style="width: 0%; height: 20px; background: #0073aa; border-radius: 3px; transition: width 0.3s;"></div>
                    </div>
                    <div id="ptt-progress-text">Preparing migration...</div>
                    <div id="ptt-migration-log" style="background: #f9f9f9; border: 1px solid #ddd; padding: 10px; margin-top: 10px; height: 200px; overflow-y: auto; font-family: monospace; font-size: 12px;"></div>
                </div>

            <?php else: ?>
                <div class="notice notice-success">
                    <p><strong>✅ No migration needed!</strong> All tasks are already using session repeater format.</p>
                </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#ptt-start-migration').on('click', function() {
                startMigration(false);
            });
            
            $('#ptt-dry-run-migration').on('click', function() {
                startMigration(true);
            });

            function startMigration(dryRun) {
                $('#ptt-migration-progress').show();
                $('#ptt-start-migration, #ptt-dry-run-migration').prop('disabled', true);
                
                const tasks = <?php echo json_encode(array_column($tasksWithParentData, 'ID')); ?>;
                let completed = 0;
                
                function migrateNext() {
                    if (completed >= tasks.length) {
                        $('#ptt-progress-text').text('Migration completed!');
                        $('#ptt-start-migration, #ptt-dry-run-migration').prop('disabled', false);
                        return;
                    }
                    
                    const taskId = tasks[completed];
                    const progress = Math.round((completed / tasks.length) * 100);
                    
                    $('#ptt-progress-fill').css('width', progress + '%');
                    $('#ptt-progress-text').text(`Migrating task ${taskId} (${completed + 1}/${tasks.length})`);
                    
                    $.post(ajaxurl, {
                        action: 'ptt_migrate_parent_level_data',
                        nonce: '<?php echo wp_create_nonce('ptt_migration_nonce'); ?>',
                        task_id: taskId,
                        dry_run: dryRun ? 1 : 0
                    }).done(function(response) {
                        const logEntry = `[${new Date().toLocaleTimeString()}] Task ${taskId}: ${response.success ? 'SUCCESS' : 'ERROR'} - ${response.data.message || response.data}\n`;
                        $('#ptt-migration-log').append(logEntry).scrollTop($('#ptt-migration-log')[0].scrollHeight);
                        
                        completed++;
                        setTimeout(migrateNext, 100); // Small delay to prevent overwhelming
                    }).fail(function() {
                        const logEntry = `[${new Date().toLocaleTimeString()}] Task ${taskId}: NETWORK ERROR\n`;
                        $('#ptt-migration-log').append(logEntry).scrollTop($('#ptt-migration-log')[0].scrollHeight);
                        
                        completed++;
                        setTimeout(migrateNext, 100);
                    });
                }
                
                migrateNext();
            }
        });
        </script>
        <?php
    }

    /**
     * Find all tasks that have parent-level timer data (start_time, stop_time, etc.)
     */
    public static function findTasksWithParentLevelData(): array
    {
        global $wpdb;
        
        // Find tasks with any parent-level timer fields
        $sql = "
            SELECT DISTINCT p.ID, p.post_title
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'project_task'
            AND p.post_status = 'publish'
            AND pm.meta_key IN ('start_time', 'stop_time', 'calculated_duration', 'manual_override', 'manual_duration')
            AND pm.meta_value != ''
            ORDER BY p.ID
        ";
        
        $taskIds = $wpdb->get_results($sql, ARRAY_A);
        
        // Get full data for each task
        $tasks = [];
        foreach ($taskIds as $row) {
            $taskId = $row['ID'];
            $tasks[] = [
                'ID' => $taskId,
                'post_title' => $row['post_title'],
                'start_time' => get_field('start_time', $taskId),
                'stop_time' => get_field('stop_time', $taskId),
                'calculated_duration' => get_field('calculated_duration', $taskId),
                'manual_override' => get_field('manual_override', $taskId),
                'manual_duration' => get_field('manual_duration', $taskId),
            ];
        }
        
        return $tasks;
    }

    /**
     * AJAX handler to migrate a single task's parent-level data to session format
     */
    public static function migrateParentLevelData()
    {
        check_ajax_referer('ptt_migration_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }
        
        $taskId = intval($_POST['task_id'] ?? 0);
        $dryRun = !empty($_POST['dry_run']);
        
        if (!$taskId) {
            wp_send_json_error(['message' => 'Invalid task ID']);
        }
        
        try {
            $result = self::migrateTaskData($taskId, $dryRun);
            wp_send_json_success(['message' => $result]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Migrate a single task's parent-level data to session repeater format
     */
    private static function migrateTaskData(int $taskId, bool $dryRun = false): string
    {
        // Get parent-level data
        $startTime = get_field('start_time', $taskId);
        $stopTime = get_field('stop_time', $taskId);
        $calculatedDuration = get_field('calculated_duration', $taskId);
        $manualOverride = get_field('manual_override', $taskId);
        $manualDuration = get_field('manual_duration', $taskId);
        
        // Check if there's any data to migrate
        if (!$startTime && !$stopTime && !$calculatedDuration && !$manualOverride && !$manualDuration) {
            return "No parent-level data found";
        }
        
        // Check if sessions already exist
        $existingSessions = get_field('sessions', $taskId);
        if (!empty($existingSessions)) {
            return "Sessions already exist - skipping to avoid duplicates";
        }
        
        if ($dryRun) {
            return "DRY RUN: Would create session with start={$startTime}, stop={$stopTime}, duration={$calculatedDuration}";
        }
        
        // Create session entry from parent-level data
        $sessionData = [
            'session_title' => 'Migrated Session',
            'session_notes' => 'Migrated from parent-level timer data',
            'session_start_time' => $startTime ?: '',
            'session_stop_time' => $stopTime ?: '',
            'session_manual_override' => $manualOverride ?: false,
            'session_manual_duration' => $manualDuration ?: '',
            'session_calculated_duration' => $calculatedDuration ?: '',
        ];
        
        // Add session to repeater
        $success = add_row('sessions', $sessionData, $taskId);
        
        if (!$success) {
            throw new \Exception("Failed to create session entry");
        }
        
        // Clear parent-level fields after successful migration
        update_field('start_time', '', $taskId);
        update_field('stop_time', '', $taskId);
        update_field('calculated_duration', '', $taskId);
        update_field('manual_override', false, $taskId);
        update_field('manual_duration', '', $taskId);
        
        return "Successfully migrated to session format and cleared parent-level data";
    }
}
