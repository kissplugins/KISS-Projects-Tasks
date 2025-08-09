jQuery(document).ready(function($) {
    const container = $('#ptt-today-page-container');
    if (!container.length) {
        return; // Exit if we are not on the Today page
    }

    // --- Cache selectors ---
    const clientFilter = $('#ptt-today-client-filter');
    const projectFilter = $('#ptt-today-project-filter');
    const taskSelect = $('#ptt-today-task-select');
    const dateSelect = $('#ptt-today-date-select');
    const startStopBtn = $('#ptt-today-start-stop-btn');
    const sessionTitleInput = $('#ptt-today-session-title');
    const entriesList = $('#ptt-today-entries-list');
    const entriesSpinner = entriesList.find('.ptt-ajax-spinner');

    // --- Debug selectors ---
    const debugDate = $('#debug-date');
    const debugClient = $('#debug-client');
    const debugProject = $('#debug-project');
    const debugSessionCount = $('#debug-session-count');

    /**
     * Shows a spinner inside a dropdown select element.
     * @param {jQuery} $select - The jQuery object for the select element.
     * @param {string} text - The text to display with the spinner.
     */
    function showSpinnerInSelect($select, text = 'Loading...') {
        $select.prop('disabled', true).html(`<option value="">${text}</option>`);
    }

    /**
     * Populates a select dropdown with options.
     * @param {jQuery} $select - The jQuery object for the select element.
     * @param {Array} items - Array of objects with 'id' and 'name' properties.
     * @param {string} placeholder - The placeholder text for the first option.
     */
    function populateSelect($select, items, placeholder) {
        $select.prop('disabled', false).empty().append($('<option>', { value: '0', text: placeholder }));
        $.each(items, function(i, item) {
            $select.append($('<option>', { value: item.id, text: item.name }));
        });
    }

    /**
     * Fetches and populates the projects dropdown based on the selected client.
     */
    function updateProjects() {
        const clientId = clientFilter.val();
        showSpinnerInSelect(projectFilter, 'Loading Projects...');
        showSpinnerInSelect(taskSelect, '-- Select Project First --');
        updateDebugInfo();

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_get_projects_for_client',
            nonce: ptt_ajax_object.nonce,
            client_id: clientId
        }).done(function(response) {
            if (response.success) {
                populateSelect(projectFilter, response.data, '-- Select a Project --');
                updateTasks(); // Load tasks for the new project list
            } else {
                projectFilter.prop('disabled', false).html('<option value="0">Could not load projects</option>');
            }
        }).fail(function() {
            projectFilter.prop('disabled', false).html('<option value="0">Error loading projects</option>');
        });
    }

    /**
     * Fetches and populates the tasks dropdown based on selected client/project.
     */
    function updateTasks() {
        const clientId = clientFilter.val();
        const projectId = projectFilter.val();
        showSpinnerInSelect(taskSelect, 'Loading Tasks...');
        updateDebugInfo();

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_get_tasks_for_today_page',
            nonce: ptt_ajax_object.nonce,
            client_id: clientId,
            project_id: projectId
        }).done(function(response) {
            if (response.success) {
                populateSelect(taskSelect, response.data, '-- Select a Task --');
            } else {
                taskSelect.prop('disabled', false).html('<option value="">Could not load tasks</option>');
            }
        }).fail(function() {
            taskSelect.prop('disabled', false).html('<option value="">Error loading tasks</option>');
        });
    }

    /**
     * Fetches and displays time entries for the selected date.
     */
    function getDailyEntries() {
        entriesSpinner.show();
        entriesList.css('opacity', '0.5');
        updateDebugInfo();

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_get_daily_entries',
            nonce: ptt_ajax_object.nonce,
            date: dateSelect.val()
        }).done(function(response) {
            if (response.success) {
                $('#ptt-today-total strong').text(response.data.total);
                debugSessionCount.text(response.data.session_count || 0);
                entriesList.html(response.data.html);
            } else {
                entriesList.html('<div class="ptt-today-no-entries">Error loading entries.</div>');
            }
        }).always(function() {
            entriesSpinner.hide();
            entriesList.css('opacity', '1');
        });
    }

    /**
     * Updates the debug information display at the bottom of the page.
     */
    function updateDebugInfo() {
        debugDate.text(dateSelect.find('option:selected').text() + ` (${dateSelect.val()})`);
        debugClient.text(clientFilter.find('option:selected').text());
        debugProject.text(projectFilter.find('option:selected').text());
    }

    /**
     * Starts a new session timer.
     */
    function startNewSession() {
        const taskId = taskSelect.val();
        const sessionTitle = sessionTitleInput.val();

        if (!taskId || taskId === '0') {
            alert('Please select a task.');
            return;
        }
        if (!sessionTitle) {
            alert('Please enter a title for this session.');
            return;
        }

        startStopBtn.prop('disabled', true).text('Starting...');

        $.post(ptt_ajax_object.ajax_url, {
            action: 'ptt_today_start_new_session',
            nonce: ptt_ajax_object.nonce,
            post_id: taskId,
            session_title: sessionTitle
        }).done(function(response) {
            if (response.success) {
                // On success, reset the date to today and reload entries
                dateSelect.val(dateSelect.find('option:first').val()).trigger('change');
                sessionTitleInput.val(''); // Clear input
            } else {
                alert(response.data.message || 'An error occurred.');
            }
        }).always(function() {
            startStopBtn.prop('disabled', false).text('Start');
        });
    }


    // --- Event Handlers ---
    clientFilter.on('change', updateProjects);
    projectFilter.on('change', updateTasks);
    dateSelect.on('change', getDailyEntries);
    startStopBtn.on('click', startNewSession);


    // --- Initial Load ---
    function initializePage() {
        updateProjects(); // This will also trigger updateTasks()
        getDailyEntries();
    }

    initializePage();
});