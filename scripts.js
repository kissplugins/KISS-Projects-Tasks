jQuery(document).ready(function ($) {
    'use strict';

    // Session recovery - store active task in localStorage
    const PTT_STORAGE_KEY = 'ptt_active_task';
    
    function saveActiveTaskToStorage(postId, taskName, startTime) {
        if (postId) {
            localStorage.setItem(PTT_STORAGE_KEY, JSON.stringify({
                postId: postId,
                taskName: taskName,
                startTime: startTime,
                timestamp: new Date().getTime()
            }));
        }
    }
    
    function clearActiveTaskFromStorage() {
        localStorage.removeItem(PTT_STORAGE_KEY);
    }
    
    function getActiveTaskFromStorage() {
        const stored = localStorage.getItem(PTT_STORAGE_KEY);
        if (stored) {
            try {
                const data = JSON.parse(stored);
                // Check if data is less than 24 hours old
                if (data.timestamp && (new Date().getTime() - data.timestamp) < 86400000) {
                    return data;
                }
            } catch (e) {
                console.error('Error parsing stored task data:', e);
            }
        }
        return null;
    }

    /**
     * Helper to show/hide spinner and messages
     */
    function showSpinner($container) {
        $container.find('.ptt-ajax-spinner').css('display', 'inline-block');
    }

    function hideSpinner($container) {
        $container.find('.ptt-ajax-spinner').hide();
    }

    function showMessage($container, text, isError) {
        $container.find('.ptt-ajax-message')
            .text(text)
            .removeClass('success error')
            .addClass(isError ? 'error' : 'success')
            .show()
            .delay(5000)
            .fadeOut('slow');
    }

    function showMessageWithHTML($container, html, isError) {
        $container.find('.ptt-ajax-message')
            .html(html)
            .removeClass('success error')
            .addClass(isError ? 'error' : 'success')
            .show();
    }


    /**
     * ---------------------------------------------------------------
     * ADMIN UI (CPT EDITOR)
     * ---------------------------------------------------------------
     */
    if ($('#ptt-timer-controls').length) {
        const $timerControls = $('#ptt-timer-controls');
        const postId = $timerControls.data('postid');

        // Start Timer
        $timerControls.on('click', '#ptt-start-timer', function (e) {
            e.preventDefault();
            const $button = $(this);
            $button.prop('disabled', true);
            showSpinner($timerControls);

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_start_timer',
                nonce: ptt_ajax_object.nonce,
                post_id: postId,
            }).done(function (response) {
                if (response.success) {
                    showMessage($timerControls, response.data.message, false);
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    showMessage($timerControls, response.data.message, true);
                    $button.prop('disabled', false);
                }
            }).fail(function () {
                showMessage($timerControls, 'An unexpected error occurred.', true);
                $button.prop('disabled', false);
            }).always(function () {
                hideSpinner($timerControls);
            });
        });

        // Stop Timer
        $timerControls.on('click', '#ptt-stop-timer', function (e) {
            e.preventDefault();
            const $button = $(this);
            $button.prop('disabled', true);
            showSpinner($timerControls);

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_stop_timer',
                nonce: ptt_ajax_object.nonce,
                post_id: postId,
            }).done(function (response) {
                if (response.success) {
                    showMessage($timerControls, response.data.message, false);
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    showMessage($timerControls, response.data.message, true);
                    $button.prop('disabled', false);
                }
            }).fail(function () {
                showMessage($timerControls, 'An unexpected error occurred.', true);
                $button.prop('disabled', false);
            }).always(function () {
                hideSpinner($timerControls);
            });
        });
    }


    /**
     * ---------------------------------------------------------------
     * FRONT-END SHORTCODE [task-enter]
     * ---------------------------------------------------------------
     */
    const $frontendTracker = $('#ptt-frontend-tracker');
    if ($frontendTracker.length) {
        const $newTaskForm = $('#ptt-new-task-form');
        const $activeTaskDisplay = $('#ptt-active-task-display');
        const $projectSelect = $('#ptt_project');
        const $taskSelect = $('#ptt_task');
        const $createNewFields = $('#ptt-create-new-fields');
        const $projectBudgetDisplay = $('#ptt-project-budget-display');
        const $taskBudgetDisplay = $('#ptt-task-budget-display');
        const $messageContainer = $('#ptt-frontend-message').parent();
        let suggestedTimeInterval = null;
        let activeTimerInterval = null;


        /**
         * Fetches active task info and updates the UI.
         */
        function checkActiveTask() {
            // First, check localStorage for recovery
            const storedTask = getActiveTaskFromStorage();
            
            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_active_task_info',
                nonce: ptt_ajax_object.nonce
            }).done(function(response) {
                if (response.success) {
                    $('#ptt-active-task-name').text(response.data.task_name);
                    $('#ptt-frontend-stop-btn').data('postid', response.data.post_id);
                    $('#ptt-frontend-force-stop-btn').data('postid', response.data.post_id);
                    $newTaskForm.hide();
                    $activeTaskDisplay.show();
                    startActiveTimer(response.data.start_time);
                    // Update localStorage with server data
                    saveActiveTaskToStorage(response.data.post_id, response.data.task_name, response.data.start_time);
                } else if (storedTask && storedTask.postId) {
                    // No active task from server, but we have one in localStorage
                    // Try to verify if it's still valid
                    showMessage($messageContainer, 'Recovering previous session...', false);
                    $('#ptt-active-task-name').text(storedTask.taskName + ' (Recovering...)');
                    $('#ptt-frontend-stop-btn').data('postid', storedTask.postId);
                    $('#ptt-frontend-force-stop-btn').data('postid', storedTask.postId);
                    $newTaskForm.hide();
                    $activeTaskDisplay.show();
                    $('.ptt-error-recovery').show();
                    startActiveTimer(storedTask.startTime);
                } else {
                    // No active task at all
                    clearActiveTaskFromStorage();
                }
            }).fail(function() {
                // If server check fails but we have localStorage data, show it
                if (storedTask && storedTask.postId) {
                    showMessage($messageContainer, 'Connection error. Showing cached task data.', true);
                    $('#ptt-active-task-name').text(storedTask.taskName + ' (Offline)');
                    $('#ptt-frontend-stop-btn').data('postid', storedTask.postId);
                    $('#ptt-frontend-force-stop-btn').data('postid', storedTask.postId);
                    $newTaskForm.hide();
                    $activeTaskDisplay.show();
                    $('.ptt-error-recovery').show();
                    startActiveTimer(storedTask.startTime);
                }
            });
        }
        
        // Check for active task on page load
        checkActiveTask();

        /**
         * Starts the on-screen timer.
         */
        function startActiveTimer(startTimeStr) {
            if (activeTimerInterval) clearInterval(activeTimerInterval);

            const startTime = new Date(startTimeStr.replace(/-/g, '/')); // Fix for cross-browser parsing

            const updateTimer = () => {
                const now = new Date();
                const diff = now - startTime;
                
                const hours = Math.floor(diff / 3600000);
                const minutes = Math.floor((diff % 3600000) / 60000);

                const formattedHours = ('0' + hours).slice(-2);
                const formattedMinutes = ('0' + minutes).slice(-2);

                $('#ptt-active-task-timer .hours').text(formattedHours);
                $('#ptt-active-task-timer .minutes').text(formattedMinutes);
            };

            updateTimer(); // Initial call
            activeTimerInterval = setInterval(updateTimer, 60000); // Update every minute
        }


        /**
         * Calculates and updates the suggested end time text.
         */
        function updateSuggestedTime() {
            if (!$taskBudgetDisplay.is(':visible') || !$taskBudgetDisplay.data('budget-hours')) {
                if (suggestedTimeInterval) clearInterval(suggestedTimeInterval);
                return;
            }
            const taskBudgetHours = parseFloat($taskBudgetDisplay.data('budget-hours'));
            let budgetString = `This Task's Budget: ${taskBudgetHours.toFixed(2)} hour(s)`;

            if (taskBudgetHours > 0) {
                const now = new Date();
                const suggestedEndTime = new Date(now.getTime() + taskBudgetHours * 60 * 60 * 1000);
                
                let hours = suggestedEndTime.getHours();
                const minutes = suggestedEndTime.getMinutes();
                const ampm = hours >= 12 ? 'PM' : 'AM';
                
                hours = hours % 12;
                hours = hours ? hours : 12; // The hour '0' should be '12'
                
                let formattedTime = String(hours);
                
                if (minutes > 0) {
                    const formattedMinutes = minutes < 10 ? '0' + minutes : minutes;
                    formattedTime += `:${formattedMinutes}`;
                }
                
                formattedTime += ` ${ampm}`;
                budgetString += ` (Suggested end time at approx. ${formattedTime})`;
            }
            $taskBudgetDisplay.text(budgetString);
        }

        // When project changes, fetch its tasks
        $projectSelect.on('change', function() {
            if (suggestedTimeInterval) clearInterval(suggestedTimeInterval);
            const projectId = $(this).val();

            $taskSelect.html('<option value="">Loading tasks...</option>').prop('disabled', true);
            $createNewFields.hide();
            $taskBudgetDisplay.hide().data('budget-hours', '');
            $projectBudgetDisplay.hide();

            if (!projectId) {
                $taskSelect.html('<option value="">-- Select Project First --</option>').prop('disabled', true);
                return;
            }

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_tasks_for_project',
                nonce: ptt_ajax_object.nonce,
                project_id: projectId
            }).done(function(response) {
                if (response.success && response.data.budget && parseFloat(response.data.budget) > 0) {
                    let budget = parseFloat(response.data.budget);
                    let unit = (budget === 1) ? 'hr' : 'hrs';
                    $projectBudgetDisplay.text(`- Initial Budget: ${budget} ${unit}`).show();
                }

                let options;
                if (response.success && response.data.tasks.length > 0) {
                    options = '<option value="">-- Select a Task --</option>';
                    response.data.tasks.forEach(function(task) {
                        options += `<option value="${task.id}">${task.title}</option>`;
                    });
                } else {
                    options = '<option value="">No un-started tasks found</option>';
                }
                
                options += '<option value="new">-- Create New Task --</option>';
                $taskSelect.html(options).prop('disabled', false);

            }).fail(function() {
                $taskSelect.html('<option value="new">Error loading tasks. Create new?</option>').prop('disabled', false);
            });
        });

        // When task changes, show/hide relevant fields
        $taskSelect.on('change', function() {
            if (suggestedTimeInterval) clearInterval(suggestedTimeInterval);
            const taskId = $(this).val();

            $taskBudgetDisplay.hide().data('budget-hours', '');

            if (taskId === 'new') {
                $createNewFields.show();
            } else {
                $createNewFields.hide();
                if (taskId) {
                    // Fetch budget details for the selected task
                    $.post(ptt_ajax_object.ajax_url, {
                        action: 'ptt_get_task_details',
                        nonce: ptt_ajax_object.nonce,
                        task_id: taskId
                    }).done(function(response) {
                        if (response.success && response.data.task_budget && parseFloat(response.data.task_budget) > 0) {
                            $taskBudgetDisplay.data('budget-hours', response.data.task_budget).show();
                            updateSuggestedTime(); // Initial call
                            suggestedTimeInterval = setInterval(updateSuggestedTime, 300000); // 5 minutes
                        }
                    });
                }
            }
        });

        // Handle the main form submission (Start Timer)
        $newTaskForm.on('submit', function (e) {
            e.preventDefault();

            const client = $('#ptt_client').val();
            const project = $projectSelect.val();
            const task = $taskSelect.val();
            const taskName = $('#ptt_task_name').val();

            if (!client || !project || !task) {
                showMessage($messageContainer, 'Please select a Client, Project, and Task.', true);
                return;
            }
    
            if (task === 'new' && !taskName.trim()) {
                showMessage($messageContainer, 'Please enter a name for the new task.', true);
                return;
            }

            const $button = $('#ptt-frontend-start-btn');
            const selectedTaskId = $taskSelect.val();
            
            showSpinner($newTaskForm);
            $button.prop('disabled', true);

            let ajaxAction;
            let formData;

            if (selectedTaskId === 'new') {
                ajaxAction = 'ptt_frontend_start_task';
                formData = {
                    action: ajaxAction,
                    nonce: ptt_ajax_object.nonce,
                    client: $('#ptt_client').val(),
                    project: $projectSelect.val(),
                    task_name: $('#ptt_task_name').val(),
                    notes: $('#ptt_notes').val(),
                };
            } else {
                ajaxAction = 'ptt_start_timer';
                formData = {
                    action: ajaxAction,
                    nonce: ptt_ajax_object.nonce,
                    post_id: selectedTaskId
                };
            }

            $.post(ptt_ajax_object.ajax_url, formData)
                .done(function (response) {
                    if (response.success) {
                        const currentTaskName = (selectedTaskId === 'new') ? formData.task_name : $taskSelect.find('option:selected').text();
                        const taskPostId = response.data.post_id || selectedTaskId;
                        $('#ptt-active-task-name').text(currentTaskName);
                        $('#ptt-frontend-stop-btn').data('postid', taskPostId);
                        $('#ptt-frontend-force-stop-btn').data('postid', taskPostId);
                        $newTaskForm.hide();
                        $activeTaskDisplay.show();
                        $('#ptt-frontend-message').hide();
                        startActiveTimer(response.data.start_time);
                        // Save to localStorage for recovery
                        saveActiveTaskToStorage(taskPostId, currentTaskName, response.data.start_time);
                    } else {
                         if (response.data.active_task_id) {
                            const stopLink = `<a href="#" class="ptt-stop-and-start-new" data-postid="${response.data.active_task_id}">stop</a>`;
                            const viewLink = `<a href="${ptt_ajax_object.edit_post_link}${response.data.active_task_id}" target="_blank">view the task</a>`;
                            const message = `You have another task running. Would you like to ${stopLink} the task and create a new one? Or would you like to ${viewLink} and current time for it?`;
                            showMessageWithHTML($messageContainer, message, true);
                        } else {
                            showMessage($messageContainer, response.data.message, true);
                        }
                    }
                })
                .fail(function () {
                    showMessage($messageContainer, 'An unexpected error occurred.', true);
                })
                .always(function () {
                    if (!$activeTaskDisplay.is(':visible')) {
                        $button.prop('disabled', false);
                    }
                    hideSpinner($newTaskForm);
                });
        });

        // Click handler for the "stop and start new" link
        $messageContainer.on('click', '.ptt-stop-and-start-new', function(e) {
            e.preventDefault();
            const $link = $(this);
            const postId = $link.data('postid');

            $link.text('Stopping...');
            
            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_stop_timer',
                nonce: ptt_ajax_object.nonce,
                post_id: postId,
            }).done(function(response) {
                if (response.success) {
                    $('#ptt-frontend-message').fadeOut('slow', function() {
                        $(this).html('').show();
                    });
                     // Resubmit the form
                    $newTaskForm.trigger('submit');
                } else {
                     showMessage($messageContainer, 'Could not stop the active task. Please try again.', true);
                }
            }).fail(function() {
                showMessage($messageContainer, 'An unexpected error occurred while stopping the task.', true);
            });
        });


        // Stop the active task from the frontend
        $('#ptt-frontend-stop-btn').on('click', function () {
            if (suggestedTimeInterval) clearInterval(suggestedTimeInterval);
            if (activeTimerInterval) clearInterval(activeTimerInterval);
            const $button = $(this);
            const postId = $button.data('postid');
            
            if (!postId) {
                showMessage($messageContainer, 'Error: No task ID found. Please refresh the page.', true);
                return;
            }
            
            showSpinner($activeTaskDisplay);
            $button.prop('disabled', true);
            
            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_stop_timer',
                nonce: ptt_ajax_object.nonce,
                post_id: postId,
            }).done(function(response) {
                if (response.success) {
                    $activeTaskDisplay.hide();
                    $newTaskForm.trigger('reset');
                    // Manually reset dropdowns and displays
                    $projectSelect.val('');
                    $taskSelect.html('<option value="">-- Select Project First --</option>').prop('disabled', true);
                    $createNewFields.hide();
                    $taskBudgetDisplay.hide().data('budget-hours', '');
                    $projectBudgetDisplay.hide();
                    $newTaskForm.show();
                    $('#ptt-frontend-start-btn').prop('disabled', false);
                    $('.ptt-error-recovery').hide();
                    $('#ptt-frontend-force-stop-btn').hide();
                    showMessage($messageContainer, response.data.message, false);
                    // Clear localStorage on successful stop
                    clearActiveTaskFromStorage();
                } else {
                    showMessage($messageContainer, response.data.message, true);
                    $button.prop('disabled', false);
                    // Show recovery options after error
                    $('.ptt-error-recovery').show();
                }
            }).fail(function(jqXHR, textStatus, errorThrown) {
                 showMessage($messageContainer, 'Connection error: ' + textStatus + '. Please check your internet connection.', true);
                 $button.prop('disabled', false);
                 $('.ptt-error-recovery').show();
            }).always(function() {
                hideSpinner($activeTaskDisplay);
            });
        });

        // Show recovery options
        $('#ptt-show-recovery-options').on('click', function(e) {
            e.preventDefault();
            $('#ptt-frontend-force-stop-btn').show();
            $(this).parent().html('Use Force Stop if the regular Stop button is not working. This will end the timer immediately.');
        });

        // Force stop handler
        $('#ptt-frontend-force-stop-btn').on('click', function() {
            if (!confirm('Force stop will immediately end this timer. Are you sure?')) {
                return;
            }
            
            const $button = $(this);
            const postId = $button.data('postid') || $('#ptt-frontend-stop-btn').data('postid');
            
            if (!postId) {
                showMessage($messageContainer, 'Error: No task ID found. Please refresh the page.', true);
                return;
            }
            
            showSpinner($activeTaskDisplay);
            $button.prop('disabled', true);
            $('#ptt-frontend-stop-btn').prop('disabled', true);
            
            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_force_stop_timer',
                nonce: ptt_ajax_object.nonce,
                post_id: postId,
            }).done(function(response) {
                if (response.success) {
                    $activeTaskDisplay.hide();
                    $newTaskForm.trigger('reset');
                    $projectSelect.val('');
                    $taskSelect.html('<option value="">-- Select Project First --</option>').prop('disabled', true);
                    $createNewFields.hide();
                    $taskBudgetDisplay.hide().data('budget-hours', '');
                    $projectBudgetDisplay.hide();
                    $newTaskForm.show();
                    $('#ptt-frontend-start-btn').prop('disabled', false);
                    $('.ptt-error-recovery').hide();
                    $('#ptt-frontend-force-stop-btn').hide();
                    showMessage($messageContainer, 'Timer force-stopped successfully!', false);
                    // Clear localStorage on successful force stop
                    clearActiveTaskFromStorage();
                } else {
                    showMessage($messageContainer, 'Force stop failed: ' + response.data.message, true);
                    $button.prop('disabled', false);
                    $('#ptt-frontend-stop-btn').prop('disabled', false);
                }
            }).fail(function() {
                showMessage($messageContainer, 'Critical error. Please contact support.', true);
                $button.prop('disabled', false);
                $('#ptt-frontend-stop-btn').prop('disabled', false);
            }).always(function() {
                hideSpinner($activeTaskDisplay);
            });
        });
    }

    /**
     * ---------------------------------------------------------------
     * ADMIN REPORTS PAGE
     * ---------------------------------------------------------------
     */
    const formatDate = (date) => {
        const y = date.getFullYear();
        const m = ('0' + (date.getMonth() + 1)).slice(-2);
        const d = ('0' + date.getDate()).slice(-2);
        return `${y}-${m}-${d}`;
    };

    $('#set-this-week').on('click', function(e) {
        e.preventDefault();
        const today = new Date();
        const day = today.getDay(); // Sunday - 0, Monday - 1, ..., Saturday - 6
        
        const sunday = new Date(today);
        sunday.setDate(today.getDate() - day);
        
        const saturday = new Date(today);
        saturday.setDate(today.getDate() - day + 6);

        $('#start_date').val(formatDate(sunday));
        $('#end_date').val(formatDate(saturday));
    });

    $('#set-last-week').on('click', function(e) {
        e.preventDefault();
        const today = new Date();
        const day = today.getDay();
        
        const lastSunday = new Date(today);
        lastSunday.setDate(today.getDate() - day - 7);
        
        const lastSaturday = new Date(today);
        lastSaturday.setDate(today.getDate() - day - 1);

        $('#start_date').val(formatDate(lastSunday));
        $('#end_date').val(formatDate(lastSaturday));
    });


    /**
     * ---------------------------------------------------------------
     * SELF-TEST MODULE
     * ---------------------------------------------------------------
     */
    $('#ptt-run-self-tests').on('click', function () {
        const $button = $(this);
        const $resultsContainer = $('#ptt-test-results-container');
        const $spinner = $resultsContainer.find('.ptt-ajax-spinner');

        $button.prop('disabled', true);
        $spinner.show();
        $resultsContainer.find('table').remove();

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_run_self_tests',
            nonce: ptt_ajax_object.nonce
        }).done(function (response) {
            if (response.success) {
                const results = response.data.results || response.data;
                let tableHtml = '<table class="wp-list-table widefat striped"><thead><tr><th>Test Name</th><th>Status</th><th>Message</th></tr></thead><tbody>';
                results.forEach(function (result) {
                    tableHtml += `<tr>
                        <td>${result.name}</td>
                        <td class="status-${result.status.toLowerCase()}">${result.status}</td>
                        <td>${result.message}</td>
                    </tr>`;
                });
                tableHtml += '</tbody></table>';
                $resultsContainer.append(tableHtml);
                if (response.data.time) {
                    $('#ptt-last-test-time').text('Tests Last Ran at ' + response.data.time);
                }
            } else {
                $resultsContainer.append('<p class="error">An error occurred while running tests.</p>');
            }
        }).fail(function () {
            $resultsContainer.append('<p class="error">A server error occurred.</p>');
        }).always(function () {
            $button.prop('disabled', false);
            $spinner.hide();
        });
    });

    if ($('#ptt-run-self-tests').length) {
        $('#ptt-run-self-tests').trigger('click');
    }
});