/**
 * PTT Plugin JavaScript
 * Handles all timer functionality, AJAX interactions, and UI updates
 */

jQuery(document).ready(function($) {
    'use strict';

    /**
     * ---------------------------------------------------------------
     * GLOBAL TIMER FUNCTIONALITY
     * ---------------------------------------------------------------
     */
    
    // Timer state management
    let timerInterval = null;
    let startTime = null;

    // Format seconds to HH:MM:SS
    function formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
    }

    // Update timer display
    function updateTimerDisplay() {
        if (!startTime) return;
        
        const now = new Date();
        const elapsed = Math.floor((now - startTime) / 1000);
        $('.ptt-timer-display').text(formatTime(elapsed));
    }

    // Start timer display
    function startTimerDisplay(startTimeStr) {
        startTime = new Date(startTimeStr.replace(' ', 'T') + 'Z');
        updateTimerDisplay();
        timerInterval = setInterval(updateTimerDisplay, 1000);
    }

    // Stop timer display
    function stopTimerDisplay() {
        if (timerInterval) {
            clearInterval(timerInterval);
            timerInterval = null;
        }
        startTime = null;
        $('.ptt-timer-display').text('00:00:00');
    }

    /**
     * ---------------------------------------------------------------
     * TASK EDITOR PAGE FUNCTIONALITY
     * ---------------------------------------------------------------
     */
    
    // Main timer controls on task edit page
    $('.ptt-timer-start').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const postId = $btn.data('post-id');
        
        $btn.prop('disabled', true);
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_start_timer',
            nonce: ptt_ajax_object.nonce,
            post_id: postId
        })
        .done(function(response) {
            if (response.success) {
                $btn.hide();
                $('.ptt-timer-stop').show();
                startTimerDisplay(response.data.start_time);
                location.reload(); // Reload to show updated fields
            } else {
                alert(response.data.message || 'Failed to start timer');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            $btn.prop('disabled', false);
        });
    });

    $('.ptt-timer-stop').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(ptt_ajax_object.confirm_stop)) {
            return;
        }
        
        const $btn = $(this);
        const postId = $btn.data('post-id');
        
        $btn.prop('disabled', true);
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_stop_timer',
            nonce: ptt_ajax_object.nonce,
            post_id: postId
        })
        .done(function(response) {
            if (response.success) {
                $btn.hide();
                $('.ptt-timer-start').show();
                stopTimerDisplay();
                location.reload(); // Reload to show updated duration
            } else {
                alert(response.data.message || 'Failed to stop timer');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            $btn.prop('disabled', false);
        });
    });

    /**
     * ---------------------------------------------------------------
     * SESSION TIMER FUNCTIONALITY
     * ---------------------------------------------------------------
     */
    
    // Session timer controls in repeater fields
    $(document).on('click', '.ptt-session-start', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const postId = $btn.data('post-id');
        const rowIndex = $btn.data('row-index');
        
        $btn.prop('disabled', true);
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_start_session_timer',
            nonce: ptt_ajax_object.nonce,
            post_id: postId,
            row_index: rowIndex
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Failed to start session timer');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            $btn.prop('disabled', false);
        });
    });

    $(document).on('click', '.ptt-session-stop', function(e) {
        e.preventDefault();
        
        if (!confirm(ptt_ajax_object.confirm_stop)) {
            return;
        }
        
        const $btn = $(this);
        const postId = $btn.data('post-id');
        const rowIndex = $btn.data('row-index');
        
        $btn.prop('disabled', true);
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_stop_session_timer',
            nonce: ptt_ajax_object.nonce,
            post_id: postId,
            row_index: rowIndex
        })
        .done(function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.data.message || 'Failed to stop session timer');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            $btn.prop('disabled', false);
        });
    });

    /**
     * ---------------------------------------------------------------
     * REPORTS PAGE FUNCTIONALITY
     * ---------------------------------------------------------------
     */
    
    // Task status update on reports page
    $('.ptt-status-select').on('change', function() {
        const $select = $(this);
        const postId = $select.data('post-id');
        const statusId = $select.val();
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_update_task_status',
            nonce: ptt_ajax_object.nonce,
            post_id: postId,
            status_id: statusId
        })
        .done(function(response) {
            if (response.success) {
                $select.css('background-color', '#d4edda');
                setTimeout(function() {
                    $select.css('background-color', '');
                }, 1000);
            } else {
                alert('Failed to update status');
                location.reload();
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
            location.reload();
        });
    });

    /**
     * ---------------------------------------------------------------
     * TODAY PAGE
     * ---------------------------------------------------------------
     */
    if ($('#ptt-today-page-container').length) {
        const $userFilter = $('#ptt-today-user-filter'); // Fixed: correct ID selector
        const $taskSelect = $('#ptt-today-task-select');
        const $projectDisplay = $('#ptt-today-project-display');
        const $sessionTitle = $('#ptt-today-session-title');
        const $startStopBtn = $('#ptt-today-start-stop-btn');
        const $timerDisplay = $('.ptt-today-timer-display');
        const $dateSelect = $('#ptt-today-date-select');
        const $entriesList = $('#ptt-today-entries-list');
        const $totalDisplay = $('#ptt-today-total strong');
        
        let activeTimerInterval = null;
        let currentUserId = $userFilter.val();

        // Check for active session on page load
        function checkActiveSession() {
            const userId = $userFilter.val();
            if (!userId || userId === '0') return;

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_check_active_session',
                nonce: ptt_ajax_object.nonce,
                user_id: userId
            }).done(function(response) {
                if (response.success && response.data.has_active) {
                    // Set up the UI for the active timer
                    $sessionTitle.val(response.data.session_title).prop('disabled', true);
                    $taskSelect.prop('disabled', true);
                    $userFilter.prop('disabled', true);
                    $startStopBtn.addClass('running').text('Stop');
                    $startStopBtn.data('postid', response.data.post_id);
                    $startStopBtn.data('rowindex', response.data.row_index);
                    
                    // Load the task into the dropdown
                    loadTasksForUser(function() {
                        $taskSelect.val(response.data.post_id);
                        // Trigger change to load project
                        $taskSelect.trigger('change');
                    });
                    
                    // Start the timer display
                    startTodayPageTimer(response.data.start_time);
                } else {
                    // No active timer, reset UI
                    stopTodayPageTimer();
                    $startStopBtn.removeClass('running').text('Start');
                    $startStopBtn.data('postid', '').data('rowindex', '');
                    $sessionTitle.val('').prop('disabled', false);
                    $taskSelect.prop('disabled', false);
                    $userFilter.prop('disabled', false);
                }
            });
        }

        // Fetch tasks when user filter changes
        $userFilter.on('change', function() {
            currentUserId = $(this).val();
            loadTasksForUser();
            loadDailyEntries();
            checkActiveSession(); // Check if this user has an active session
        });

        // Fetch project when task changes
        $taskSelect.on('change', function(){
            const taskId = $(this).val();
            $projectDisplay.text('-- Project will appear here --'); // Reset text
            if (!taskId) return;

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_project_for_task',
                nonce: ptt_ajax_object.nonce,
                task_id: taskId
            }).done(function(response){
                if (response.success) {
                    $projectDisplay.text(response.data.project_name); // Use .text() for div
                }
            });
        });

        // Main Start/Stop button logic
        $startStopBtn.on('click', function() {
            const $btn = $(this);
            const isRunning = $btn.hasClass('running');
            
            if (isRunning) {
                // --- STOP TIMER ---
                const postId = $btn.data('postid');
                const rowIndex = $btn.data('rowindex');
                
                if (!postId || rowIndex === undefined) {
                    alert('Timer data not found. Please refresh the page.');
                    return;
                }
                
                $btn.prop('disabled', true).text('Stopping...');

                $.post(ptt_ajax_object.ajax_url, {
                    action: 'ptt_stop_session_timer',
                    nonce: ptt_ajax_object.nonce,
                    post_id: postId,
                    row_index: rowIndex
                }).done(function(response){
                    stopTodayPageTimer();
                    $btn.removeClass('running').data('postid', '').data('rowindex', '');
                    $sessionTitle.val('').prop('disabled', false);
                    $taskSelect.prop('disabled', false);
                    $userFilter.prop('disabled', false);
                    loadDailyEntries(); // Refresh list
                }).fail(function(){
                    alert('Failed to stop timer.');
                }).always(function(){
                    $btn.prop('disabled', false).text('Start');
                });

            } else {
                // --- START TIMER ---
                const taskId = $taskSelect.val();
                const title = $sessionTitle.val();
                const userId = $userFilter.val();

                if (!userId || userId === '0') {
                    alert('Please select a user.');
                    return;
                }

                if (!taskId || !title.trim()) {
                    alert('Please enter a session title and select a task.');
                    return;
                }

                $btn.prop('disabled', true).text('Starting...');

                $.post(ptt_ajax_object.ajax_url, {
                    action: 'ptt_today_start_new_session',
                    nonce: ptt_ajax_object.nonce,
                    post_id: taskId,
                    session_title: title,
                    user_id: userId // Pass the selected user ID
                }).done(function(response){
                    if (response.success) {
                        $btn.addClass('running').text('Stop');
                        $btn.data('postid', response.data.post_id);
                        $btn.data('rowindex', response.data.row_index);
                        $sessionTitle.prop('disabled', true);
                        $taskSelect.prop('disabled', true);
                        $userFilter.prop('disabled', true);
                        startTodayPageTimer(response.data.start_time);
                        loadDailyEntries(); // Refresh list to show running timer
                    } else {
                        alert(response.data.message || 'Could not start timer.');
                        $btn.text('Start');
                    }
                }).fail(function(){
                    alert('An error occurred.');
                    $btn.text('Start');
                }).always(function(){
                    $btn.prop('disabled', false);
                });
            }
        });

        // Load entries when date changes
        $dateSelect.on('change', function(){
            loadDailyEntries();
        });

        function loadTasksForUser(callback) {
            const userId = $userFilter.val();
            
            if (!userId || userId === '0') {
                $taskSelect.prop('disabled', true).html('<option value="">-- Select a User First --</option>');
                $projectDisplay.text('-- Project will appear here --');
                return;
            }
            
            $taskSelect.prop('disabled', true).html('<option value="">Loading...</option>');
            $projectDisplay.text('-- Project will appear here --'); // Clear project on user change

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_tasks_for_today_page',
                nonce: ptt_ajax_object.nonce,
                user_id: userId
            }).done(function(response) {
                if (response.success && response.data.length) {
                    let options = '<option value="">-- Select a Task --</option>';
                    response.data.forEach(task => {
                        options += `<option value="${task.id}">${task.title}</option>`;
                    });
                    $taskSelect.html(options).prop('disabled', false);
                } else {
                    $taskSelect.html('<option value="">No available tasks for this user</option>');
                }
                
                // Execute callback if provided
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }

        function loadDailyEntries() {
            const selectedDate = $dateSelect.val();
            const selectedUser = $userFilter.val();
            
            if (!selectedUser || selectedUser === '0') {
                $entriesList.html('<div class="ptt-today-no-entries">Please select a user to view time entries.</div>');
                $totalDisplay.text('00:00');
                return;
            }
            
            $entriesList.html('<div class="ptt-ajax-spinner" style="display:block; margin: 40px auto;"></div>');

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_daily_entries',
                nonce: ptt_ajax_object.nonce,
                date: selectedDate,
                user_id: selectedUser
            }).done(function(response){
                if (response.success) {
                    $entriesList.html(response.data.html);
                    $totalDisplay.text(response.data.total);
                }
            });
        }
        
        function startTodayPageTimer(startTimeStr) {
            if (activeTimerInterval) clearInterval(activeTimerInterval);
            const startTime = new Date(startTimeStr.replace(' ', 'T') + 'Z');

            const updateTimer = () => {
                const now = new Date();
                const diff = now - startTime;

                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);
                const seconds = Math.floor((diff % 60000) / 1000);

                const timeString = `${('0'+hours).slice(-2)}:${('0'+minutes).slice(-2)}:${('0'+seconds).slice(-2)}`;
                $timerDisplay.text(timeString);
            };
            updateTimer();
            activeTimerInterval = setInterval(updateTimer, 1000);
        }

        function stopTodayPageTimer() {
            if (activeTimerInterval) clearInterval(activeTimerInterval);
            activeTimerInterval = null;
            $timerDisplay.text('00:00:00');
        }

        // Initial load
        loadTasksForUser();
        loadDailyEntries();
        checkActiveSession(); // Check for active session on load
    }

    /**
     * ---------------------------------------------------------------
     * SELF-TEST MODULE
     * ---------------------------------------------------------------
     */
    
    // Run self-test
    $('#ptt-run-self-test').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $results = $('#ptt-self-test-results');
        
        $btn.prop('disabled', true).text('Running tests...');
        $results.html('<div class="ptt-ajax-spinner"></div>');
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_run_self_test',
            nonce: ptt_ajax_object.nonce
        })
        .done(function(response) {
            if (response.success) {
                $results.html(response.data.html);
            } else {
                $results.html('<div class="notice notice-error"><p>Failed to run self-test.</p></div>');
            }
        })
        .fail(function() {
            $results.html('<div class="notice notice-error"><p>Network error during self-test.</p></div>');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Run Self-Test');
        });
    });

    // Sync authors to assignees
    $('#ptt-sync-authors').on('click', function(e) {
        e.preventDefault();
        
        if (!confirm(ptt_ajax_object.sync_authors_confirm)) {
            return;
        }
        
        const $btn = $(this);
        const $message = $('#ptt-sync-message');
        
        $btn.prop('disabled', true).text('Synchronizing...');
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_sync_authors_to_assignees',
            nonce: ptt_ajax_object.nonce
        })
        .done(function(response) {
            if (response.success) {
                $message.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
            } else {
                $message.html('<div class="notice notice-error"><p>Synchronization failed.</p></div>');
            }
        })
        .fail(function() {
            $message.html('<div class="notice notice-error"><p>Network error during synchronization.</p></div>');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Sync Authors to Assignees');
        });
    });

    /**
     * ---------------------------------------------------------------
     * KANBAN BOARD
     * ---------------------------------------------------------------
     */
    
    if ($('.ptt-kanban-board').length) {
        // Make tasks draggable
        $('.ptt-kanban-task').draggable({
            helper: 'clone',
            revert: 'invalid',
            cursor: 'move',
            opacity: 0.7,
            zIndex: 1000
        });
        
        // Make columns droppable
        $('.ptt-kanban-column').droppable({
            accept: '.ptt-kanban-task',
            hoverClass: 'ptt-kanban-hover',
            drop: function(event, ui) {
                const $task = ui.draggable;
                const $column = $(this);
                const taskId = $task.data('task-id');
                const newStatus = $column.data('status');
                
                // Move task to new column
                $task.detach().appendTo($column.find('.ptt-kanban-tasks'));
                
                // Update status via AJAX
                $.post(ptt_ajax_object.ajax_url, {
                    action: 'ptt_update_task_status',
                    nonce: ptt_ajax_object.nonce,
                    post_id: taskId,
                    status_id: newStatus
                })
                .done(function(response) {
                    if (!response.success) {
                        alert('Failed to update task status');
                        location.reload();
                    }
                })
                .fail(function() {
                    alert('Network error. Please refresh the page.');
                    location.reload();
                });
            }
        });
    }

    /**
     * ---------------------------------------------------------------
     * SHORTCODE FUNCTIONALITY
     * ---------------------------------------------------------------
     */
    
    // Quick task creation from shortcode
    $('#ptt-quick-task-form').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $submitBtn = $form.find('button[type="submit"]');
        const $message = $('#ptt-form-message');
        
        $submitBtn.prop('disabled', true).text('Creating...');
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_create_quick_task',
            nonce: ptt_ajax_object.nonce,
            title: $form.find('#task_title').val(),
            description: $form.find('#task_description').val(),
            client: $form.find('#task_client').val(),
            project: $form.find('#task_project').val()
        })
        .done(function(response) {
            if (response.success) {
                $message.html('<div class="ptt-success">Task created successfully!</div>');
                $form[0].reset();
                setTimeout(function() {
                    window.location.href = response.data.edit_link;
                }, 1000);
            } else {
                $message.html('<div class="ptt-error">' + (response.data.message || 'Failed to create task') + '</div>');
            }
        })
        .fail(function() {
            $message.html('<div class="ptt-error">Network error. Please try again.</div>');
        })
        .always(function() {
            $submitBtn.prop('disabled', false).text('Create Task');
        });
    });

    /**
     * ---------------------------------------------------------------
     * UTILITY FUNCTIONS
     * ---------------------------------------------------------------
     */
    
    // Auto-save draft
    let autoSaveTimer;
    $('.ptt-autosave').on('input change', function() {
        clearTimeout(autoSaveTimer);
        const $field = $(this);
        
        autoSaveTimer = setTimeout(function() {
            const postId = $field.data('post-id');
            const fieldName = $field.attr('name');
            const fieldValue = $field.val();
            
            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_autosave_field',
                nonce: ptt_ajax_object.nonce,
                post_id: postId,
                field_name: fieldName,
                field_value: fieldValue
            });
        }, 1000);
    });

    // Tooltip initialization
    if ($.fn.tooltip) {
        $('.ptt-tooltip').tooltip();
    }

    // Confirm delete actions
    $('.ptt-delete-action').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
        }
    });

    // Print functionality for reports
    $('#ptt-print-report').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // Export to CSV
    $('#ptt-export-csv').on('click', function(e) {
        e.preventDefault();
        const $btn = $(this);
        
        $btn.prop('disabled', true).text('Generating CSV...');
        
        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_export_csv',
            nonce: ptt_ajax_object.nonce,
            filters: {
                date_from: $('#filter_date_from').val(),
                date_to: $('#filter_date_to').val(),
                client: $('#filter_client').val(),
                project: $('#filter_project').val(),
                user: $('#filter_user').val()
            }
        })
        .done(function(response) {
            if (response.success && response.data.download_url) {
                window.location.href = response.data.download_url;
            } else {
                alert('Failed to generate CSV export');
            }
        })
        .fail(function() {
            alert('Network error. Please try again.');
        })
        .always(function() {
            $btn.prop('disabled', false).text('Export to CSV');
        });
    });

});