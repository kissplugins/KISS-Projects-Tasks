// ... (previous code remains unchanged up to the self-test module)

    /**
     * ---------------------------------------------------------------
     * TODAY PAGE
     * ---------------------------------------------------------------
     */
    if ($('#ptt-today-page-container').length) {
        const $userSelect = $('#ptt-today-user-select');
        const $taskSelect = $('#ptt-today-task-select');
        const $projectDisplay = $('#ptt-today-project-display');
        const $sessionTitle = $('#ptt-today-session-title');
        const $startStopBtn = $('#ptt-today-start-stop-btn');
        const $timerDisplay = $('.ptt-today-timer-display');
        const $dateSelect = $('#ptt-today-date-select');
        const $entriesList = $('#ptt-today-entries-list');
        const $totalDisplay = $('#ptt-today-total strong');
        
        let activeTimerInterval = null;

        // Fetch tasks when user filter changes
        $userSelect.on('change', function() {
            loadTasksForUser();
            loadDailyEntries();
        });

        // Fetch project when task changes
        $taskSelect.on('change', function(){
            const taskId = $(this).val();
            $projectDisplay.val(''); // Clear previous
            if (!taskId) return;

            $.post(ptt_ajax_object.ajax_url, {
                action: 'ptt_get_project_for_task',
                nonce: ptt_ajax_object.nonce,
                task_id: taskId
            }).done(function(response){
                if (response.success) {
                    $projectDisplay.val(response.data.project_name);
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
                    $userSelect.prop('disabled', false);
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

                if (!taskId || !title.trim()) {
                    alert('Please enter a session title and select a task.');
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
                        $userSelect.prop('disabled', true);
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

        function loadTasksForUser() {
            const userId = $userSelect.val();
            $taskSelect.prop('disabled', true).html('<option value="">Loading...</option>');
            $projectDisplay.val(''); // Clear project on user change

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
            });
        }

        function loadDailyEntries() {
            const selectedDate = $dateSelect.val();
            const selectedUser = $userSelect.val();
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
    }


    /**
     * ---------------------------------------------------------------
     * SELF-TEST MODULE
     * ---------------------------------------------------------------
     */
// ... (rest of the file is unchanged)