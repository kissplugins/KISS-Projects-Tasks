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

    function validateSessionRows() {
        let valid = true;
        $('.acf-field[data-key="field_ptt_sessions"] .acf-row').each(function(){
            const $row = $(this);
            if ($row.find('.acf-row-handle.order').text() === 'New') return; // Skip new unsaved rows

            const title = $row.find('[data-key="field_ptt_session_title"] input').val();
            const notes = $row.find('[data-key="field_ptt_session_notes"] textarea').val();
            const start = $row.find('[data-key="field_ptt_session_start_time"] input').val();
            const stop = $row.find('[data-key="field_ptt_session_stop_time"] input').val();
            const override = $row.find('[data-key="field_ptt_session_manual_override"] input').prop('checked');
            const manual = $row.find('[data-key="field_ptt_session_manual_duration"] input').val();

            if (!title) {
                valid = false; return false;
            }

            if (override) {
                if (manual === '' || manual === null) {
                    valid = false; return false;
                }
            } else {
                if (start && !stop) {
                    valid = false; return false;
                }
            }
        });
        return valid;
    }


    /**
     * ---------------------------------------------------------------
     * ADMIN UI (CPT EDITOR)
     * ---------------------------------------------------------------
     */

    // Add "Use Today's Date" button next to the title input
    if ($('body').hasClass('post-type-project_task') && ($('body').hasClass('post-new-php') || $('body').hasClass('post-php'))) {
        const $titlewrap = $('#titlewrap');
        if ($titlewrap.length) {
            const dateButton = $('<button type="button" id="ptt-use-todays-date" class="button" style="margin-bottom: 10px;">Use Today\'s Date</button>');
            $titlewrap.after(dateButton); // Place button after the title wrapper

            dateButton.on('click', function(e) {
                e.preventDefault();
                const today = ptt_ajax_object.todays_date_formatted;
                const $titleInput = $('#title');
                const currentTitle = $titleInput.val();
                let newTitle = today + ' - ' + currentTitle;
                
                if ( !currentTitle.trim() ) {
                    newTitle = today + ' - ';
                } else if (currentTitle.includes(today)) {
                    // Don't add if date is already there
                    return; 
                }
                
                $titleInput.val(newTitle);
                $('#title-prompt-text').addClass('screen-reader-text');
            });
        }
    }
    
    /**
     * Reusable live timer function for session rows.
     */
    function manageLiveTimer($container, startTimeStr) {
        // Clear any existing timer for this container
        if ($container.data('timerIntervalId')) {
            clearInterval($container.data('timerIntervalId'));
        }

        const startTime = new Date(startTimeStr.replace(/-/g, '/'));
        const $timerDisplay = $container.find('.ptt-session-elapsed-time');

        const updateTimer = () => {
            const now = new Date();
            const diff = now - startTime; // in milliseconds

            const hours = Math.floor(diff / 3600000);
            const minutes = Math.floor((diff % 3600000) / 60000);
            const seconds = Math.floor((diff % 60000) / 1000);

            const formattedHours = ('0' + hours).slice(-2);
            const formattedMinutes = ('0' + minutes).slice(-2);
            const formattedSeconds = ('0' + seconds).slice(-2);
            
            $timerDisplay.html(`${formattedHours}<span class="colon">:</span>${formattedMinutes}<span class="colon">:</span>${formattedSeconds}`);
        };

        updateTimer(); // Initial call
        const intervalId = setInterval(updateTimer, 1000); // Update every second
        $container.data('timerIntervalId', intervalId);
    }
    
    function stopLiveTimer($container) {
        if ($container.data('timerIntervalId')) {
            clearInterval($container.data('timerIntervalId'));
            $container.removeData('timerIntervalId');
        }
    }

    /**
     * Initializes the timer controls for each session row.
     */
    function initSessionRows($context) {
        $context = $context || $(document);

        $context.find('.ptt-session-timer').each(function() {
            const $container = $(this);
            if ($container.data('initialized')) return;
            $container.data('initialized', true);

            const $row = $container.closest('.acf-row');
            const $startInput = $row.find('[data-key="field_ptt_session_start_time"] input');
            const $stopInput = $row.find('[data-key="field_ptt_session_stop_time"] input');
            const $durationInput = $row.find('[data-key="field_ptt_session_calculated_duration"] input');

            const controlsHtml = `
                <div class="ptt-session-controls">
                    <button type="button" class="button ptt-session-start">Start Timer</button>
                    <div class="ptt-session-active-timer" style="display: none;">
                        <span class="duration-label">Duration: </span>
                        <span class="ptt-session-elapsed-time">00:00:00</span>
                        <button type="button" class="button ptt-session-stop ptt-stop-button">Stop Timer</button>
                    </div>
                    <div class="ptt-session-message" style="display: none;"></div>
                    <div class="ptt-ajax-spinner" style="display: none; margin-left: 8px;"></div>
                </div>`;
            $container.find('.acf-input > p').html(controlsHtml);
            
            const $controls = $container.find('.ptt-session-controls');
            const $startButton = $controls.find('.ptt-session-start');
            const $stopButton = $controls.find('.ptt-session-stop');
            const $activeDisplay = $controls.find('.ptt-session-active-timer');
            const $message = $controls.find('.ptt-session-message');

            function updateUIState() {
                const startVal = $startInput.val();
                const stopVal = $stopInput.val();

                stopLiveTimer($controls);

                if (startVal && !stopVal) { // Running
                    $startButton.hide();
                    $activeDisplay.css('display', 'inline-flex');
                    $message.hide();
                    manageLiveTimer($controls, startVal);
                } else if (startVal && stopVal) { // Stopped
                    $startButton.hide();
                    $activeDisplay.hide();
                    const duration = parseFloat($durationInput.val() || 0).toFixed(2);
                    $message.text(`Session completed. Duration: ${duration} hrs.`).show();
                } else { // Not started
                    $startButton.show();
                    $activeDisplay.hide();
                    $message.hide();
                }
            }
            
            updateUIState();
        });
    }
    
    // Initialise existing rows
    initSessionRows();

    // Handle ACF Repeater "Add Row" event
    if (window.acf) {
        window.acf.addAction('append', function($el) {
            initSessionRows($el);
        });
    }

    // New unified click handlers using event delegation
    $(document).on('click', '.ptt-session-start', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $controls = $btn.closest('.ptt-session-controls');
        const $row = $btn.closest('.acf-row');
        const index = $row.index();
        const postId = $('#post_ID').val();

        $btn.prop('disabled', true);
        showSpinner($controls);

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_start_session_timer',
            nonce: ptt_ajax_object.nonce,
            post_id: postId,
            row_index: index
        }).done(function(response){
            if (response.success) {
                $row.find('[data-key="field_ptt_session_start_time"] input').val(response.data.start_time).trigger('change');
                $btn.hide();
                $controls.find('.ptt-session-active-timer').css('display', 'inline-flex');
                manageLiveTimer($controls, response.data.start_time);
            } else {
                alert(response.data.message || 'An error occurred.');
            }
        }).fail(function(){
            alert('An unexpected server error occurred.');
        }).always(function(){
            $btn.prop('disabled', false);
            hideSpinner($controls);
        });
    });

    $(document).on('click', '.ptt-session-stop', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $controls = $btn.closest('.ptt-session-controls');
        const $row = $btn.closest('.acf-row');
        const index = $row.index();
        const postId = $('#post_ID').val();

        $btn.prop('disabled', true);
        showSpinner($controls);

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_stop_session_timer',
            nonce: ptt_ajax_object.nonce,
            post_id: postId,
            row_index: index
        }).done(function(response){
            if (response.success) {
                stopLiveTimer($controls);
                $row.find('[data-key="field_ptt_session_stop_time"] input').val(response.data.stop_time).trigger('change');
                $row.find('[data-key="field_ptt_session_calculated_duration"] input').val(response.data.duration).trigger('change');
                
                $controls.find('.ptt-session-active-timer').hide();
                const $message = $controls.find('.ptt-session-message');
                $message.text(`Session stopped. Duration: ${response.data.duration} hrs.`).show();
                
                // Trigger save post to update total duration
                $('#publish').trigger('click');
            } else {
                alert(response.data.message || 'An error occurred.');
            }
        }).fail(function(){
            alert('An unexpected server error occurred.');
        }).always(function(){
            $btn.prop('disabled', false);
            hideSpinner($controls);
        });
    });

    $(document).on('click', '.acf-field[data-key="field_ptt_sessions"] [data-event="add-row"], .acf-field[data-key="field_ptt_sessions"] [data-name="add-row"]', function(e){
        if (!validateSessionRows()) {
            e.preventDefault();
            alert('Please complete all fields for open sessions and stop any running timers before adding a new one.');
            return false;
        }
        const $saveButton = $('#publish');
        if ($saveButton.length && $saveButton.is(':enabled')) {
            setTimeout(function(){ $saveButton.trigger('click'); }, 100);
        }
    });


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
        const $taskStatusDisplay = $('#ptt-task-status-display');
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
                    $('#ptt-active-task-status').text(response.data.task_status || '');
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
                    $('#ptt-active-task-status').text('');
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
                    $('#ptt-active-task-status').text('');
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
            $taskStatusDisplay.hide();
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
                        if (response.success) {
                            if (response.data.task_budget && parseFloat(response.data.task_budget) > 0) {
                                $taskBudgetDisplay.data('budget-hours', response.data.task_budget).show();
                                updateSuggestedTime(); // Initial call
                                suggestedTimeInterval = setInterval(updateSuggestedTime, 300000); // 5 minutes
                            }
                            if (response.data.task_status) {
                                $taskStatusDisplay.text('Current Status: ' + response.data.task_status).show();
                            } else {
                                $taskStatusDisplay.hide();
                            }
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
                        $('#ptt-active-task-status').text(response.data.task_status || '');
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
                    $taskStatusDisplay.hide();
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
                    $taskStatusDisplay.hide();
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

        // Manual time entry functionality
        $('#ptt-frontend-manual-btn').on('click', function(e) {
            e.preventDefault();
            $('#ptt-manual-time-section').slideToggle();
        });

        $('#ptt-cancel-manual-entry').on('click', function(e) {
            e.preventDefault();
            $('#ptt-manual-time-section').slideUp();
            $('#ptt_manual_hours').val('');
        });

        $('#ptt-save-manual-entry').on('click', function(e) {
            e.preventDefault();
            
            const $button = $(this);
            const manualHours = parseFloat($('#ptt_manual_hours').val());
            const selectedTaskId = $taskSelect.val();
            const createNew = selectedTaskId === 'new';
            
            // Validation
            if (isNaN(manualHours) || manualHours <= 0) {
                showMessage($messageContainer, 'Please enter a valid time greater than 0.', true);
                return;
            }
            
            const client = $('#ptt_client').val();
            const project = $projectSelect.val();
            const task = $taskSelect.val();
            
            if (!client || !project || !task) {
                showMessage($messageContainer, 'Please select a Client, Project, and Task.', true);
                return;
            }
            
            if (createNew) {
                const taskName = $('#ptt_task_name').val();
                if (!taskName.trim()) {
                    showMessage($messageContainer, 'Please enter a name for the new task.', true);
                    return;
                }
            }
            
            // Prepare data
            let formData = {
                action: 'ptt_frontend_manual_time',
                nonce: ptt_ajax_object.nonce,
                manual_hours: manualHours,
                create_new: createNew
            };
            
            if (createNew) {
                formData.client = client;
                formData.project = project;
                formData.task_name = $('#ptt_task_name').val();
                formData.notes = $('#ptt_notes').val();
            } else {
                formData.task_id = selectedTaskId;
            }
            
            $button.prop('disabled', true);
            showSpinner($('#ptt-manual-time-section'));
            
            $.post(ptt_ajax_object.ajax_url, formData)
                .done(function(response) {
                    if (response.success) {
                        showMessage($messageContainer, response.data.message, false);
                        // Reset form
                        $newTaskForm.trigger('reset');
                        $projectSelect.val('');
                        $taskSelect.html('<option value="">-- Select Project First --</option>').prop('disabled', true);
                        $createNewFields.hide();
                        $taskBudgetDisplay.hide().data('budget-hours', '');
                        $projectBudgetDisplay.hide();
                        $('#ptt-manual-time-section').slideUp();
                        $('#ptt_manual_hours').val('');
                    } else {
                        showMessage($messageContainer, response.data.message, true);
                    }
                })
                .fail(function() {
                    showMessage($messageContainer, 'An unexpected error occurred.', true);
                })
                .always(function() {
                    $button.prop('disabled', false);
                    hideSpinner($('#ptt-manual-time-section'));
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