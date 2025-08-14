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

    // --- START DEBUGGING INFO ---
    // This adds a debugging box to the top of the "Edit Task" page.
    // To disable this, you can delete this entire block of code.
    if ($('body').hasClass('post-type-project_task')) {
        const debugBox = $('<div id="ptt-timer-debug-log" style="background: #fff; border: 2px dashed red; padding: 10px; margin-bottom: 15px; font-family: monospace;"><h3>Timer Debugging Info</h3></div>');
        $('#poststuff').before(debugBox);
    }
    // --- END DEBUGGING INFO ---


	    // Auto-refresh editor after ACF saves so server-side timestamps are visible immediately
	    if ($('body').hasClass('post-type-project_task')) {
	        // Clear refresh guard on load
	        try {
	            if (sessionStorage.getItem('ptt_after_save_refresh') === '1') {
	                sessionStorage.removeItem('ptt_after_save_refresh');
	            }
	        } catch (e) {}

	        if (window.acf && typeof window.acf.addAction === 'function') {
	            window.acf.addAction('submit_success', function($form, result) {
	                try {
	                    sessionStorage.setItem('ptt_after_save_refresh', '1');
	                    sessionStorage.setItem('ptt_scroll_after_save', '1');
	                } catch (e) {}
	                window.location.reload();
	            });

	        // After reload from a save, if flagged, scroll to the bottom near the Sessions repeater
	        try {
	            if (sessionStorage.getItem('ptt_scroll_after_save') === '1') {
	                sessionStorage.removeItem('ptt_scroll_after_save');
	                setTimeout(function(){
	                    const $field = $('.acf-field[data-key="field_ptt_sessions"]');
	                    if ($field.length) {
	                        // Scroll so the actions (Add/Update) area is visible
	                        const $actions = $field.find('.acf-actions').last();
	                        const target = $actions.length ? $actions.offset().top : $field.offset().top + $field.outerHeight();
	                        $('html, body').animate({ scrollTop: target - 80 }, 400);
	                    } else {
	                        // Fallback: scroll to bottom
	                        window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' });
	                    }
	                }, 250);
	            }
	        } catch (e) {}

	        }

	        // Backup: set scroll flag when WP Publish/Update is clicked or form submits
	        if ($('body').hasClass('post-type-project_task')) {
	            $(document).on('click', '#publish, #save-post', function(){
	                try { sessionStorage.setItem('ptt_scroll_after_save', '1'); } catch (e) {}
	            });
	            $('#post').on('submit', function(){
	                try { sessionStorage.setItem('ptt_scroll_after_save', '1'); } catch (e) {}
	            });
	        }

	    }


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

        // FIX: Treat the incoming time string as UTC by appending 'Z'
        const startTime = new Date(startTimeStr.replace(' ', 'T') + 'Z');
        const $timerDisplay = $container.find('.ptt-session-elapsed-time');

        const updateTimer = () => {
            const now = new Date();
            const diff = now - startTime; // in milliseconds

            // --- START DEBUGGING INFO ---
            const debugLog = $('#ptt-timer-debug-log');
            if (debugLog.length) {
                debugLog.html(
                    '<h3>Timer Debugging Info</h3>' +
                    '<b>Start Time String (from server/UTC):</b> ' + startTimeStr + '<br>' +
                    '<b>Browser "Now" Time (local):</b> ' + now.toString() + '<br>' +
                    '<b>Parsed Start Time (local):</b> ' + startTime.toString() + '<br>' +
                    '<b>Difference (ms):</b> ' + diff
                );
            }
            // --- END DEBUGGING INFO ---

            // FIX: Correctly calculate and display negative time if it occurs
            const isNegative = diff < 0;
            const absDiff = Math.abs(diff);

            const hours = Math.floor(absDiff / 3600000);
            const minutes = Math.floor((absDiff % 3600000) / 60000);
            const seconds = Math.floor((absDiff % 60000) / 1000);

            const formattedHours = ('0' + hours).slice(-2);
            const formattedMinutes = ('0' + minutes).slice(-2);
            const formattedSeconds = ('0' + seconds).slice(-2);

            let timeString = `${formattedHours}<span class="colon">:</span>${formattedMinutes}<span class="colon">:</span>${formattedSeconds}`;
            if (isNegative) {
                timeString = '-' + timeString;
            }
            $timerDisplay.html(timeString);
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
            $container.find('.acf-input').html(controlsHtml);

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

	    // Add an "Update" button next to the Add Session button that triggers the WP Update/Save
	    function addUpdateButtonToSessionsRepeater($context) {
	        $context = $context || $(document);
	        const $field = $context.find('.acf-field[data-key="field_ptt_sessions"]').first();
	        if (!$field.length) return;

	        // Prefer to append into the ACF actions area
	        const $actions = $field.find('.acf-actions').first();
	        if ($actions.length) {
	            if ($actions.find('.ptt-session-update-btn').length === 0) {
	                const $btn = $('<button type="button" class="button button-secondary ptt-session-update-btn" style="margin-left:8px;">Update</button>');
	                $actions.append($btn);
	            }
	            return;
	        }

	        // Fallback: place after the Add Row button if actions container is not found
	        const $addBtn = $field.find('[data-event="add-row"], [data-name="add-row"]').last();
	        if ($addBtn.length && $addBtn.next('.ptt-session-update-btn').length === 0) {
	            const $btn = $('<button type="button" class="button button-secondary ptt-session-update-btn" style="margin-left:8px;">Update</button>');
	            $addBtn.after($btn);
	        }
	    }

	    // Initialize on load and when ACF appends new content
	    addUpdateButtonToSessionsRepeater();
	    if (window.acf) {
	        window.acf.addAction('append', function($el){ addUpdateButtonToSessionsRepeater($el); });
	        window.acf.addAction('ready', function($el){ addUpdateButtonToSessionsRepeater($el); });
	    }

	    // Click handler: replicate the Publish/Update button
	    $(document).on('click', '.ptt-session-update-btn', function(e){
	        e.preventDefault();
	        const $btn = $(this);
	        $btn.prop('disabled', true).text('Updating...');
	        $('#publish').trigger('click');
	    });

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

            // FIX: Treat the incoming time string as UTC
            const startTime = new Date(startTimeStr.replace(' ', 'T') + 'Z');

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
    function handleReportViewModeChange() {
        const viewMode = $('input[name="view_mode"]:checked').val();
        const $dateRow = $('.form-table tr:has(#start_date)');
        const $endDate = $('#end_date');
        const $separator = $dateRow.find('.date-range-separator');
        const $dateLabel = $dateRow.find('th label');
        const $weekButtons = $('#set-this-week, #set-last-week');

        if (viewMode === 'single_day') {
            $endDate.hide();
            $separator.hide();
            $weekButtons.hide();
            $dateLabel.text('Select Day');
        } else {
            $endDate.show();
            $separator.show();
            $weekButtons.show();
            $dateLabel.text('Date Range');
        }
    }

    // Handle view mode change
    $('input[name="view_mode"]').on('change', handleReportViewModeChange);

    // Run on page load to set initial state
    if ($('input[name="view_mode"]').length) {
        handleReportViewModeChange();
    }


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

    // Handle status change on reports page
    $(document).on('change', '.ptt-report-status-select', function() {
        const $select = $(this);
        const postId = $select.data('postid');
        const statusId = $select.val();
        const $container = $select.parent();

        showSpinner($container);
        $select.prop('disabled', true);

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_update_task_status',
            nonce: ptt_ajax_object.nonce,
            post_id: postId,
            status_id: statusId
        }).done(function(response) {
            // Optional: Show a success indicator
        }).fail(function() {
            alert('Failed to update status.');
            // Revert on failure if needed
            $select.val($select.find('option[selected]').val());
        }).always(function() {
            hideSpinner($container);
            $select.prop('disabled', false);
        });
    });

        /**
     * ---------------------------------------------------------------
     * TODAY PAGE
     * ---------------------------------------------------------------
     */
    if ($('#ptt-today-page-container').length) {
        const $projectFilter = $('#ptt-today-project-filter');
        const $clientFilter = $('#ptt-today-client-filter');
        const $taskSelect = $('#ptt-today-task-select');
        const $sessionTitle = $('#ptt-today-session-title');
        const $startStopBtn = $('#ptt-today-start-stop-btn');
        const $timerDisplay = $('.ptt-today-timer-display');
        const $dateSelect = $('#ptt-today-date-select');
        const $entriesList = $('#ptt-today-entries-list');
        const $totalDisplay = $('#ptt-today-total strong');

        let activeTimerInterval = null;

        // Fetch tasks based on filters
        function loadTasks() {
            const projectId = $projectFilter.val();
            const clientId = $clientFilter.val();
            $taskSelect.prop('disabled', true).html('<option value="">Loading...</option>');

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_tasks_for_today_page',
                nonce: ptt_ajax_object.nonce,
                project_id: projectId,
                client_id: clientId
            }).done(function(response) {
                if (response.success && response.data.length) {
                    let options = '<option value="">-- Select a Task --</option>';
                    response.data.forEach(task => {
                        options += `<option value="${task.id}">${task.title}</option>`;
                    });
                    $taskSelect.html(options).prop('disabled', false);
                } else {
                    $taskSelect.html('<option value="">No available tasks</option>');
                }
            }).fail(function() {
                $taskSelect.html('<option value="">-- Select a Task --</option>');
            });
        }

		// Provide task options to per-row selectors once to avoid N+1 queries
		function populatePerRowTaskSelectors() {
		    const optionsHtml = $taskSelect.html();
		    $('.ptt-entry-task-selector').each(function(){
		        const $sel = $(this);
		        if (!$sel.data('populated')) {
		            $sel.html(optionsHtml);
		            $sel.data('populated', true);
		        }
		    });
		}

		// After tasks load, also populate the per-row selectors
		const _origLoadTasks = loadTasks;
		loadTasks = function(){
		    return $.post(ptt_ajax_object.ajax_url, {
		        action: 'ptt_get_tasks_for_today_page',
		        nonce: ptt_ajax_object.nonce,
		        project_id: $projectFilter.val(),
		        client_id: $clientFilter.val()
		    }).done(function(response){
		        if (response.success && response.data.length) {
		            let options = '<option value="">-- Select a Task --</option>';
		            response.data.forEach(task => { options += `<option value="${task.id}">${task.title}</option>`; });
		            $taskSelect.html(options).prop('disabled', false);
		            populatePerRowTaskSelectors();
		        } else {
		            $taskSelect.html('<option value="">No available tasks</option>');
		            populatePerRowTaskSelectors();
		        }
		    }).fail(function(){
		        $taskSelect.html('<option value="">-- Select a Task --</option>');
		        populatePerRowTaskSelectors();
		    });
		};


        $projectFilter.on('change', loadTasks);
        $clientFilter.on('change', loadTasks);

        // Main Start/Stop button logic
        $startStopBtn.on('click', function() {
            const $btn = $(this);
            const isRunning = $btn.hasClass('running');

            if (isRunning) {
                // --- STOP TIMER ---
                const postId = $btn.data('postid');
                const rowIndex = $btn.data('rowindex');
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
                    $projectFilter.prop('disabled', false);
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
                const clientId = $clientFilter.val();

                // Quick Start path: Client selected, but missing task or title
                if (clientId && (!taskId || !title.trim())) {
                    $btn.prop('disabled', true).text('Starting...');
                    $.post(ptt_ajax_object.ajax_url, {
                        action: 'ptt_today_quick_start',
                        nonce: ptt_ajax_object.nonce,
                        client_id: clientId
                    }).done(function(response){
                        if (response.success) {
                            $btn.text('Stop').addClass('running');
                            $taskSelect.prop('disabled', true);
                            $projectFilter.prop('disabled', true);
                            $clientFilter.prop('disabled', true);
                            startTodayPageTimer(response.data.start_time);
                            // Fill session title with server provided title
                            if (response.data.session_data && response.data.session_data.title) {
                                $sessionTitle.val(response.data.session_data.title);
                            }
                            loadDailyEntries();
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
                    return;
                }

                if (!taskId || !title.trim()) {
                    alert('Please enter a session title and select a task (or choose a Client for Quick Start).');
                    return;
                }

                $btn.prop('disabled', true).text('Starting...');

                $.post(ptt_ajax_object.ajax_url, {
                    action: 'ptt_today_start_new_session',
                    nonce: ptt_ajax_object.nonce,
                    post_id: taskId,
                    session_title: title
                }).done(function(response){
                    if (response.success) {
                        $btn.addClass('running').text('Stop');
                        $btn.data('postid', response.data.post_id);
                        $btn.data('rowindex', response.data.row_index);
                        $sessionTitle.prop('disabled', true);
                        $taskSelect.prop('disabled', true);
                        $projectFilter.prop('disabled', true);
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

        function loadDailyEntries() {
            const selectedDate = $dateSelect.val();
            $entriesList.html('<div class="ptt-ajax-spinner" style="display:block; margin: 40px auto;"></div>');

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_daily_entries',
                nonce: ptt_ajax_object.nonce,
                date: selectedDate
            }).done(function(response){
                if (response.success) {
                    $entriesList.html(response.data.html);
                    $totalDisplay.text(response.data.total);
                    // Update debug info
                    if (response.data.debug) {
                        $('#ptt-debug-content').html(response.data.debug);
                    }
                    initializeEntryTaskSelectors();
                }
            });
        }

        function initializeEntryTaskSelectors() {
            $entriesList.find('.ptt-entry-task-selector').each(function() {
                const $select = $(this);
                const $entry = $select.closest('.ptt-today-entry');
                const $moveBtn = $select.siblings('.ptt-move-session-btn');
                const $cancelBtn = $select.siblings('.ptt-cancel-move-btn');
                const original = String($select.data('original-task'));

                $select.on('change', function() {
                    if ($select.val() && $select.val() !== original) {
                        $moveBtn.show();
                        $cancelBtn.show();
                    } else {
                        $moveBtn.hide();
                        $cancelBtn.hide();
                    }
                });

                $cancelBtn.on('click', function() {
                    $select.val(original);
                    $moveBtn.hide();
                    $cancelBtn.hide();
                });

                $moveBtn.on('click', function() {
                    const targetId = $select.val();
                    $moveBtn.prop('disabled', true).text('Moving...');
                    $.post(ptt_ajax_object.ajax_url, {
                        action: 'ptt_move_session',
                        nonce: ptt_ajax_object.nonce,
                        post_id: $entry.data('post-id'),
                        session_index: $entry.data('session-index'),
                        target_post_id: targetId
                    }).done(function(response){
                        if (response.success) {
                            loadDailyEntries();
                        } else {
                            alert(response.data.message || 'Move failed.');
                        }
                    }).fail(function(){
                        alert('Move failed.');
                    }).always(function(){
                        $moveBtn.prop('disabled', false).text('Move');
                    });
                });
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

        // Handle "Start Timer" button clicks from Today page entries
        $entriesList.on('click', '.ptt-start-timer-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const postId = $btn.data('post-id');

            if (!postId) {
                alert('Invalid task ID.');
                return;
            }

            // Check if there's already an active timer
            if ($startStopBtn.hasClass('running')) {
                alert('You have an active timer running. Please stop it before starting a new one.');
                return;
            }

            $btn.prop('disabled', true).text('Starting...');

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_today_start_timer',
                nonce: ptt_ajax_object.nonce,
                post_id: postId
            }).done(function(response) {
                if (response.success) {
                    // Update the main timer controls
                    $sessionTitle.val(response.data.session_title).prop('disabled', true);
                    $taskSelect.val(postId).prop('disabled', true);
                    $projectFilter.prop('disabled', true);
                    $startStopBtn.addClass('running').text('Stop').removeClass('button-primary').addClass('button-secondary');
                    $startStopBtn.data('postid', response.data.post_id);
                    $startStopBtn.data('rowindex', response.data.session_index);

                    // Start the timer display
                    startTodayPageTimer(response.data.start_time);

                    // Refresh the entries list
                    loadDailyEntries();

                    // Show success message
                    alert(response.data.message);
                } else {
                    alert(response.data.message || 'Could not start timer.');
                }
            }).fail(function() {
                alert('Network error. Please try again.');
            }).always(function() {
                $btn.prop('disabled', false).text('Start Timer');
            });
        });

        // Handle "Edit Task" button clicks (these are just links, but we can add analytics if needed)
        $entriesList.on('click', '.ptt-edit-task-btn', function(e) {
            // The link will open naturally, but we can track this action if needed
            console.log('Edit task clicked for post ID:', $(this).closest('.ptt-today-entry').data('post-id'));
        });

        // Handle "Add Another Session" button clicks
        $entriesList.on('click', '.ptt-add-session-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const postId = $btn.data('post-id');
            const taskTitle = $btn.data('task-title');
            const projectName = $btn.data('project-name');
            const projectId = $btn.data('project-id');

            if (!postId) {
                alert('Invalid task ID.');
                return;
            }

            // Check if there's already an active timer
            if ($startStopBtn.hasClass('running')) {
                alert('You have an active timer running. Please stop it before starting a new session.');
                return;
            }

            // Populate the session title
            const currentTime = new Date();
            const timeString = currentTime.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit', hour12: true});
            const sessionTitle = `Session ${timeString}`;
            $sessionTitle.val(sessionTitle);

            // Function to select the task once project is loaded
            const selectTask = () => {
                $taskSelect.val(postId);
                if ($taskSelect.val() === postId.toString()) {
                    // Success! Task is now selected
                    $sessionTitle.focus().select();
                } else {
                    // Task still not found, show helpful message
                    alert(`Task "${taskTitle}" prepared. You may need to select the correct project manually.`);
                    $sessionTitle.focus();
                }
            };

            // Check if correct project is already selected
            if ($projectFilter.val() === projectId.toString() && projectId) {
                // Project already selected, just select the task
                selectTask();
            } else if (projectId) {
                // Need to select the correct project first
                $projectFilter.val(projectId);

                // Trigger project change and wait for tasks to load
                $projectFilter.trigger('change');

                // Wait a moment for AJAX to complete, then select task
                setTimeout(selectTask, 500);
            } else {
                // No project ID available, just focus on session title
                $sessionTitle.focus().select();
                alert(`Session prepared for "${taskTitle}". Please select the correct project and task.`);
            }

            // Scroll to top so user can see the populated form
            $('html, body').animate({
                scrollTop: $('#ptt-today-page-container').offset().top
            }, 300);
        });

        // Initial load
        $projectFilter.trigger('change');
        loadDailyEntries();
    }

    /**
     * ---------------------------------------------------------------
     * DEBUG PANEL TOGGLE
     * ---------------------------------------------------------------
     */

    // Initialize debug panel state from localStorage
    function initDebugPanel() {
        const $toggle = $('.ptt-debug-toggle');
        const $content = $('.ptt-debug-panel-content');
        const savedState = localStorage.getItem('ptt_debug_panel_expanded');

        if (savedState === 'true') {
            $content.show();
            $toggle.attr('aria-expanded', 'true');
        }
    }

    // Handle debug panel toggle
    $('.ptt-debug-toggle').on('click', function(e) {
        e.preventDefault();
        const $toggle = $(this);
        const $content = $('.ptt-debug-panel-content');
        const isExpanded = $toggle.attr('aria-expanded') === 'true';

        if (isExpanded) {
            // Collapse
            $content.slideUp(200);
            $toggle.attr('aria-expanded', 'false');
            localStorage.setItem('ptt_debug_panel_expanded', 'false');
        } else {
            // Expand
            $content.slideDown(200);
            $toggle.attr('aria-expanded', 'true');
            localStorage.setItem('ptt_debug_panel_expanded', 'true');
        }
    });

    // Initialize debug panel on page load
    if ($('.ptt-debug-panel').length > 0) {
        initDebugPanel();
    }

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
            if (response.success && response.data && Array.isArray(response.data.results)) {
                const results = response.data.results;
                const total = results.length;
                const failed = results.filter(r => r.status && r.status.toLowerCase() === 'failed').length;
                const firstFailedIndex = results.findIndex(r => r.status && r.status.toLowerCase() === 'failed');
                const jumpLink = failed ? `<a href="#ptt-first-failed" style="margin-left:8px;">Jump to first failed</a>` : '';
                const passedNote = failed === 0 ? ` <span style="color:#2e7d32;font-weight:bold;">All tests have passed.</span>` : '';
                const summaryHtml = `<div class="notice ${failed ? 'notice-error' : 'notice-success'}"><strong>Number of Tests:</strong> ${failed} out of ${total} Failed.${passedNote} ${jumpLink}</div>`;
                let tableHtml = summaryHtml + '<table class="wp-list-table widefat striped"><thead><tr><th>Test Name</th><th>Status</th><th>Message</th></tr></thead><tbody>';
                results.forEach(function (result, idx) {
                    const isFailed = result.status && result.status.toLowerCase() === 'failed';
                    const anchor = (isFailed && idx === firstFailedIndex) ? ' id="ptt-first-failed"' : '';
                    tableHtml += `<tr${anchor}>
                        <td>${result.name}</td>
                        <td class="status-${result.status.toLowerCase()}">${result.status}</td>
                        <td>${result.message}</td>
                    </tr>`;
                });
                tableHtml += '</tbody></table>';
                $resultsContainer.html(tableHtml);
                if (response.data.time) {
                    $('#ptt-last-test-time').text('Tests Last Ran at ' + response.data.time);
                }
            } else {
                // Handle cases where the response is not what was expected
                let errorMessage = '<p class="status-fail">An error occurred while running tests. The server returned an unexpected response, which may indicate a fatal error or that a sub-process was interrupted.</p>';
                $resultsContainer.html(errorMessage);
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

    $('#ptt-sync-authors').on('click', function (e) {
        e.preventDefault();
        const $button = $(this);
        const $result = $('#ptt-sync-authors-result');

        $('<div>' + ptt_ajax_object.sync_authors_confirm + '</div>').dialog({
            modal: true,
            title: ptt_ajax_object.sync_authors_title,
            buttons: {
                'Yes': function () {
                    $(this).dialog('close');
                    $button.prop('disabled', true);
                    $result.text('Synchronizing...');
                    $.post(ptt_ajax_object.ajax_url, {
                        action: 'ptt_sync_authors_assignee',
                        nonce: ptt_ajax_object.nonce
                    }).done(function (response) {
                        if (response.success && typeof response.data.count !== 'undefined') {
                            $result.text('Synchronized ' + response.data.count + ' tasks.');
                        } else {
                            $result.text('Synchronization failed.');
                        }
                    }).fail(function () {
                        $result.text('Server error.');
                    }).always(function () {
                        $button.prop('disabled', false);
                    });
                },
                'No': function () {
                    $(this).dialog('close');
                }
            },
            open: function () {
                $(this).parent().find('.ui-dialog-buttonpane button:contains("No")').focus();
            },
            close: function () {
                $(this).remove();
            }
        });
    });
});