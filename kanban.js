/**
 * Kanban Board JavaScript
 * 
 * @package PTT
 * @since 1.8.0
 */

(function($) {
    'use strict';

    // Kanban Board Module
    const PTTKanban = {
        // Configuration
        config: {
            draggedTask: null,
            originalColumn: null,
            isProcessing: false,
            autoSaveTimeout: null,
            filterTimeout: null,
        },

        // Initialize the Kanban board
        init: function() {
            if (!$('#ptt-kanban-board').length) {
                return;
            }

            this.bindEvents();
            this.initDragAndDrop();
            this.loadSavedPreferences();
            this.initKeyboardAccessibility();
        },

        // Bind event handlers
        bindEvents: function() {
            const self = this;

            // Filter form submission
            $('#ptt-kanban-filters-form').on('submit', function(e) {
                e.preventDefault();
                self.applyFilters();
            });

            // Auto-apply filters on change
            $('#assignee_filter, #activity_filter, #client_filter, #project_filter').on('change', function() {
                self.debouncedApplyFilters();
            });

            // Task card click handler (for mobile)
            $(document).on('click', '.ptt-kanban-task', function(e) {
                if ($(e.target).is('a')) {
                    return; // Allow link clicks
                }
                
                if ($(window).width() <= 768) {
                    self.showTaskOptions($(this));
                }
            });

            // Window resize handler for responsive adjustments
            let resizeTimeout;
            $(window).on('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(function() {
                    self.adjustBoardLayout();
                }, 250);
            });
        },

        // Initialize drag and drop functionality
        initDragAndDrop: function() {
            const self = this;

            // Make task cards draggable
            $('.ptt-kanban-task').draggable({
                revert: 'invalid',
                helper: 'clone',
                cursor: 'move',
                opacity: 0.7,
                zIndex: 1000,
                containment: '.ptt-kanban-board',
                start: function(event, ui) {
                    self.handleDragStart($(this), ui);
                },
                stop: function(event, ui) {
                    self.handleDragStop($(this), ui);
                }
            });

            // Make columns droppable
            $('.ptt-kanban-column-tasks').droppable({
                accept: '.ptt-kanban-task',
                hoverClass: 'drop-hover',
                tolerance: 'pointer',
                drop: function(event, ui) {
                    self.handleDrop($(this), ui);
                }
            });

            // Also support native HTML5 drag and drop for better mobile support
            this.initHTML5DragDrop();
        },

        // Initialize HTML5 drag and drop as fallback
        initHTML5DragDrop: function() {
            const self = this;

            // Make tasks draggable
            document.querySelectorAll('.ptt-kanban-task').forEach(task => {
                task.draggable = true;
                
                task.addEventListener('dragstart', function(e) {
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/html', this.innerHTML);
                    self.config.draggedTask = this;
                    self.config.originalColumn = this.closest('.ptt-kanban-column-tasks');
                    this.classList.add('dragging');
                });

                task.addEventListener('dragend', function(e) {
                    this.classList.remove('dragging');
                    document.querySelectorAll('.drop-hover').forEach(col => {
                        col.classList.remove('drop-hover');
                    });
                });
            });

            // Make columns droppable
            document.querySelectorAll('.ptt-kanban-column-tasks').forEach(column => {
                column.addEventListener('dragover', function(e) {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    this.classList.add('drop-hover');
                });

                column.addEventListener('dragleave', function(e) {
                    this.classList.remove('drop-hover');
                });

                column.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (self.config.draggedTask && self.config.draggedTask !== this) {
                        const newStatusId = this.dataset.status;
                        const taskId = self.config.draggedTask.dataset.taskId;
                        
                        // Move the task visually
                        this.appendChild(self.config.draggedTask);
                        
                        // Update on server
                        self.updateTaskStatus(taskId, newStatusId);
                    }
                    
                    this.classList.remove('drop-hover');
                });
            });
        },

        // Handle drag start
        handleDragStart: function($task, ui) {
            this.config.draggedTask = $task;
            this.config.originalColumn = $task.closest('.ptt-kanban-column-tasks');
            $task.addClass('dragging');
            
            // Add visual feedback
            $('.ptt-kanban-column-tasks').addClass('drag-active');
        },

        // Handle drag stop
        handleDragStop: function($task, ui) {
            $task.removeClass('dragging');
            $('.ptt-kanban-column-tasks').removeClass('drag-active');
        },

        // Handle drop
        handleDrop: function($column, ui) {
            const self = this;
            const $task = ui.draggable;
            const taskId = $task.data('task-id');
            const newStatusId = $column.data('status');
            const originalStatusId = this.config.originalColumn.data('status');

            // Check if status actually changed
            if (newStatusId === originalStatusId) {
                return;
            }

            // Prevent multiple simultaneous updates
            if (this.config.isProcessing) {
                return false;
            }

            this.config.isProcessing = true;

            // Show loading state
            $task.addClass('updating');
            this.showNotification('Updating task status...', 'info');

            // Optimistically move the task
            $task.detach().appendTo($column);
            
            // Update empty states
            this.updateEmptyStates();
            
            // Update task counts
            this.updateTaskCounts();

            // Send AJAX request to update status
            $.ajax({
                url: ptt_kanban.ajax_url,
                type: 'POST',
                data: {
                    action: 'ptt_kanban_update_status',
                    nonce: ptt_kanban.nonce,
                    task_id: taskId,
                    status_id: newStatusId
                },
                success: function(response) {
                    if (response.success) {
                        $task.removeClass('updating');
                        self.showNotification('Task status updated!', 'success');
                        
                        // Update task card with new data if needed
                        if (response.data.task_data) {
                            self.updateTaskCard($task, response.data.task_data);
                        }
                    } else {
                        // Revert on failure
                        $task.detach().appendTo(self.config.originalColumn);
                        self.updateEmptyStates();
                        self.updateTaskCounts();
                        self.showNotification(response.data.message || ptt_kanban.messages.drag_error, 'error');
                    }
                },
                error: function() {
                    // Revert on error
                    $task.detach().appendTo(self.config.originalColumn);
                    $task.removeClass('updating');
                    self.updateEmptyStates();
                    self.updateTaskCounts();
                    self.showNotification(ptt_kanban.messages.drag_error, 'error');
                },
                complete: function() {
                    self.config.isProcessing = false;
                }
            });
        },

        // Update task status via AJAX
        updateTaskStatus: function(taskId, statusId) {
            const self = this;
            
            if (this.config.isProcessing) {
                return;
            }

            this.config.isProcessing = true;

            $.ajax({
                url: ptt_kanban.ajax_url,
                type: 'POST',
                data: {
                    action: 'ptt_kanban_update_status',
                    nonce: ptt_kanban.nonce,
                    task_id: taskId,
                    status_id: statusId
                },
                success: function(response) {
                    if (response.success) {
                        self.updateEmptyStates();
                        self.updateTaskCounts();
                        self.showNotification('Task status updated!', 'success');
                    } else {
                        // Revert the task to original column
                        if (self.config.draggedTask && self.config.originalColumn) {
                            self.config.originalColumn.appendChild(self.config.draggedTask);
                        }
                        self.showNotification(response.data.message || ptt_kanban.messages.drag_error, 'error');
                    }
                },
                error: function() {
                    // Revert on error
                    if (self.config.draggedTask && self.config.originalColumn) {
                        self.config.originalColumn.appendChild(self.config.draggedTask);
                    }
                    self.showNotification(ptt_kanban.messages.drag_error, 'error');
                },
                complete: function() {
                    self.config.isProcessing = false;
                    self.config.draggedTask = null;
                    self.config.originalColumn = null;
                }
            });
        },

        // Update task card display
        updateTaskCard: function($task, taskData) {
            // Update over-budget status
            if (taskData.is_over_budget) {
                $task.addClass('over-budget');
            } else {
                $task.removeClass('over-budget');
            }

            // Update timer indicator
            if (taskData.has_active_timer) {
                $task.addClass('timer-active');
            } else {
                $task.removeClass('timer-active');
            }

            // Could update other fields here if needed
        },

        // Apply filters with debouncing
        debouncedApplyFilters: function() {
            const self = this;
            
            clearTimeout(this.config.filterTimeout);
            this.config.filterTimeout = setTimeout(function() {
                self.applyFilters();
            }, 500);
        },

        // Apply filters
        applyFilters: function() {
            const self = this;
            
            // Show loading overlay
            $('#ptt-kanban-loading').fadeIn();

            // Gather filter values
            const filters = {
                assignee_filter: $('#assignee_filter').val(),
                activity_filter: $('#activity_filter').val(),
                client_filter: $('#client_filter').val(),
                project_filter: $('#project_filter').val()
            };

            // Save preferences in cookies
            this.saveFilterPreferences(filters);

// Refresh board via AJAX
            $.ajax({
                url: ptt_kanban.ajax_url,
                type: 'POST',
                data: {
                    action: 'ptt_kanban_refresh_board',
                    nonce: ptt_kanban.nonce,
                    assignee_filter: filters.assignee_filter,
                    activity_filter: filters.activity_filter,
                    client_filter: filters.client_filter,
                    project_filter: filters.project_filter
                },
                success: function(response) {
                    if (response.success) {
                        $('#ptt-kanban-board').html(response.data.html);
                        self.initDragAndDrop();
                        self.adjustBoardLayout();
                    } else {
                        self.showNotification(ptt_kanban.messages.filter_error, 'error');
                    }
                },
                error: function() {
                    self.showNotification(ptt_kanban.messages.filter_error, 'error');
                },
                complete: function() {
                    $('#ptt-kanban-loading').fadeOut();
                }
            });
        },

        // Save filter preferences
        saveFilterPreferences: function(filters) {
            // Preferences are saved server-side via cookies
            // This is just for any client-side storage if needed
            if (typeof(Storage) !== "undefined") {
                localStorage.setItem('ptt_kanban_filters', JSON.stringify(filters));
            }
        },

        // Load saved preferences
        loadSavedPreferences: function() {
            // Preferences are loaded server-side from cookies
            // This is for any additional client-side preferences
            if (typeof(Storage) !== "undefined") {
                const saved = localStorage.getItem('ptt_kanban_view_mode');
                if (saved) {
                    // Apply any saved view preferences
                }
            }
        },

        // Update empty states
        updateEmptyStates: function() {
            $('.ptt-kanban-column-tasks').each(function() {
                const $column = $(this);
                const taskCount = $column.find('.ptt-kanban-task').length;
                
                if (taskCount === 0) {
                    if (!$column.find('.ptt-kanban-empty-state').length) {
                        $column.append('<div class="ptt-kanban-empty-state">' + ptt_kanban.messages.no_tasks + '</div>');
                    }
                } else {
                    $column.find('.ptt-kanban-empty-state').remove();
                }
            });
        },

        // Update task counts in column headers
        updateTaskCounts: function() {
            $('.ptt-kanban-column').each(function() {
                const $column = $(this);
                const taskCount = $column.find('.ptt-kanban-task').length;
                $column.find('.task-count').text(taskCount);
            });
        },

        // Show notification
        showNotification: function(message, type) {
            const $notification = $('<div class="ptt-kanban-notification ' + type + '">' + message + '</div>');
            
            $('body').append($notification);
            
            $notification.fadeIn(300);
            
            setTimeout(function() {
                $notification.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        },

        // Show task options (for mobile)
        showTaskOptions: function($task) {
            const taskId = $task.data('task-id');
            const $options = $('<div class="ptt-kanban-task-options"></div>');
            
            // Add status change options
            $('.ptt-kanban-column').each(function() {
                const statusId = $(this).data('status-id');
                const statusName = $(this).find('h3').text();
                const $button = $('<button>' + statusName + '</button>');
                
                $button.on('click', function() {
                    const $targetColumn = $('.ptt-kanban-column-tasks[data-status="' + statusId + '"]');
                    $task.detach().appendTo($targetColumn);
                    PTTKanban.updateTaskStatus(taskId, statusId);
                    $options.remove();
                });
                
                $options.append($button);
            });
            
            // Position and show options
            $options.css({
                top: $task.offset().top + $task.height(),
                left: $task.offset().left
            });
            
            $('body').append($options);
            
            // Close on outside click
            $(document).one('click', function(e) {
                if (!$(e.target).closest('.ptt-kanban-task-options').length) {
                    $options.remove();
                }
            });
        },

        // Adjust board layout for responsive design
        adjustBoardLayout: function() {
            const windowWidth = $(window).width();
            const $board = $('.ptt-kanban-board');
            const $columns = $('.ptt-kanban-columns');
            
            if (windowWidth <= 768) {
                // Mobile: Stack columns vertically
                $columns.addClass('mobile-view');
            } else if (windowWidth <= 1024) {
                // Tablet: 2 columns per row
                $columns.removeClass('mobile-view').addClass('tablet-view');
            } else {
                // Desktop: Horizontal scroll
                $columns.removeClass('mobile-view tablet-view');
            }
            
            // Adjust column heights for desktop
            if (windowWidth > 768) {
                this.equalizeColumnHeights();
            }
        },

        // Equalize column heights
        equalizeColumnHeights: function() {
            const $columns = $('.ptt-kanban-column-tasks');
            let maxHeight = 0;
            
            // Reset heights
            $columns.css('min-height', '');
            
            // Find max height
            $columns.each(function() {
                const height = $(this).height();
                if (height > maxHeight) {
                    maxHeight = height;
                }
            });
            
            // Apply min-height
            if (maxHeight > 300) {
                $columns.css('min-height', maxHeight + 'px');
            }
        },

        // Initialize keyboard accessibility
        initKeyboardAccessibility: function() {
            const self = this;
            
            // Make task cards focusable
            $('.ptt-kanban-task').attr('tabindex', '0');
            
            // Keyboard navigation
            $(document).on('keydown', '.ptt-kanban-task', function(e) {
                const $task = $(this);
                const key = e.which;
                
                // Space or Enter to open task
                if (key === 32 || key === 13) {
                    e.preventDefault();
                    const editLink = $task.find('.task-title a').attr('href');
                    if (editLink) {
                        window.open(editLink, '_blank');
                    }
                }
                
                // Arrow keys to move between columns
                if (key >= 37 && key <= 40) {
                    e.preventDefault();
                    self.navigateWithKeyboard($task, key);
                }
            });
        },

        // Navigate tasks with keyboard
        navigateWithKeyboard: function($task, keyCode) {
            const $currentColumn = $task.closest('.ptt-kanban-column');
            let $targetColumn;
            
            switch(keyCode) {
                case 37: // Left arrow
                    $targetColumn = $currentColumn.prev('.ptt-kanban-column');
                    break;
                case 39: // Right arrow
                    $targetColumn = $currentColumn.next('.ptt-kanban-column');
                    break;
                case 38: // Up arrow
                    const $prevTask = $task.prev('.ptt-kanban-task');
                    if ($prevTask.length) {
                        $prevTask.focus();
                    }
                    return;
                case 40: // Down arrow
                    const $nextTask = $task.next('.ptt-kanban-task');
                    if ($nextTask.length) {
                        $nextTask.focus();
                    }
                    return;
            }
            
            // Move task to new column if arrow left/right was pressed
            if ($targetColumn && $targetColumn.length) {
                const taskId = $task.data('task-id');
                const newStatusId = $targetColumn.data('status-id');
                const $targetContainer = $targetColumn.find('.ptt-kanban-column-tasks');
                
                $task.detach().appendTo($targetContainer);
                this.updateTaskStatus(taskId, newStatusId);
                $task.focus();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PTTKanban.init();
    });

    // Re-initialize after AJAX updates
    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.url && settings.url.includes('ptt_kanban')) {
            setTimeout(function() {
                PTTKanban.initDragAndDrop();
            }, 100);
        }
    });

})(jQuery);