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
            this.debug('Kanban initialized');
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

            // Make columns sortable for vertical reordering
            $('.ptt-kanban-column-tasks').sortable({
                connectWith: '.ptt-kanban-column-tasks',
                placeholder: 'ptt-kanban-task-placeholder',
                handle: '.ptt-kanban-task',
                cursor: 'move',
                opacity: 0.7,
                revert: 100,
                tolerance: 'pointer',
                forcePlaceholderSize: true,
                start: function(event, ui) {
                    self.handleSortStart(event, ui);
                },
                stop: function(event, ui) {
                    self.handleSortStop(event, ui);
                },
                update: function(event, ui) {
                    // Only process if this is the receiving column
                    if (this === ui.item.parent()[0]) {
                        self.handleSortUpdate(event, ui);
                    }
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
                    
                    // Get the task being dragged over
                    const afterElement = self.getDragAfterElement(this, e.clientY);
                    const draggable = document.querySelector('.dragging');
                    
                    if (afterElement == null) {
                        this.appendChild(draggable);
                    } else {
                        this.insertBefore(draggable, afterElement);
                    }
                });

                column.addEventListener('dragleave', function(e) {
                    if (e.target === this) {
                        this.classList.remove('drop-hover');
                    }
                });

                column.addEventListener('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (self.config.draggedTask && self.config.draggedTask !== this) {
                        const newStatusId = this.dataset.status;
                        const taskId = self.config.draggedTask.dataset.taskId;
                        
                        // Get all task IDs in new order
                        const tasksOrder = [...this.querySelectorAll('.ptt-kanban-task')].map(task => 
                            parseInt(task.dataset.taskId)
                        );
                        
                        // Update on server
                        self.updateTaskPositions(taskId, newStatusId, tasksOrder);
                    }
                    
                    this.classList.remove('drop-hover');
                });
            });
        },

        // Get the element after which the dragged element should be inserted
        getDragAfterElement: function(container, y) {
            const draggableElements = [...container.querySelectorAll('.ptt-kanban-task:not(.dragging)')];
            
            return draggableElements.reduce((closest, child) => {
                const box = child.getBoundingClientRect();
                const offset = y - box.top - box.height / 2;
                
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                } else {
                    return closest;
                }
            }, { offset: Number.NEGATIVE_INFINITY }).element;
        },

        // Handle sort start
        handleSortStart: function(event, ui) {
            this.config.originalColumn = ui.item.closest('.ptt-kanban-column-tasks');
            ui.item.addClass('dragging');
            
            // Add visual feedback
            $('.ptt-kanban-column-tasks').addClass('drag-active');
            
            // Set placeholder height to match the dragged item
            ui.placeholder.height(ui.item.height());
        },

        // Handle sort stop
        handleSortStop: function(event, ui) {
            ui.item.removeClass('dragging');
            $('.ptt-kanban-column-tasks').removeClass('drag-active');
        },

        // Handle sort update (when item is dropped)
        handleSortUpdate: function(event, ui) {
            const self = this;
            const $task = ui.item;
            const taskId = $task.data('task-id');
            const $newColumn = $task.closest('.ptt-kanban-column-tasks');
            const newStatusId = $newColumn.data('status');
            const originalStatusId = this.config.originalColumn.data('status');
            
            // Get all task IDs in the new order
            const tasksOrder = $newColumn.find('.ptt-kanban-task').map(function() {
                return $(this).data('task-id');
            }).get();

            // Prevent multiple simultaneous updates
            if (this.config.isProcessing) {
                return false;
            }

            this.config.isProcessing = true;

            // Show loading state
            $task.addClass('updating');
            
            // Update empty states
            this.updateEmptyStates();
            
            // Update task counts
            this.updateTaskCounts();

            // Determine which action to take
            let ajaxData;
            
            if (newStatusId === originalStatusId) {
                // Same column - just update position
                this.showNotification('Updating task position...', 'info');
                
                ajaxData = {
                    action: 'ptt_kanban_update_position',
                    nonce: ptt_kanban.nonce,
                    task_id: taskId,
                    status_id: newStatusId,
                    tasks_order: tasksOrder
                };
            } else {
                // Different column - update status and position
                this.showNotification('Moving task to new status...', 'info');
                
                ajaxData = {
                    action: 'ptt_kanban_update_status',
                    nonce: ptt_kanban.nonce,
                    task_id: taskId,
                    status_id: newStatusId,
                    position: tasksOrder.indexOf(taskId)
                };
            }

            // Send AJAX request
            $.ajax({
                url: ptt_kanban.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response.success) {
                        $task.removeClass('updating');
                        
                        if (newStatusId === originalStatusId) {
                            self.showNotification('Task position updated!', 'success');
                        } else {
                            self.showNotification('Task moved successfully!', 'success');
                            
                            // Update task card with new data if status changed
                            if (response.data.task_data) {
                                self.updateTaskCard($task, response.data.task_data);
                            }
                        }
                    } else {
                        // Revert on failure
                        self.revertTaskPosition($task);
                        self.showNotification(response.data.message || ptt_kanban.messages.drag_error, 'error');
                    }
                },
                error: function() {
                    // Revert on error
                    self.revertTaskPosition($task);
                    $task.removeClass('updating');
                    self.showNotification(ptt_kanban.messages.drag_error, 'error');
                },
                complete: function() {
                    self.config.isProcessing = false;
                }
            });
        },

        // Revert task to original position
        revertTaskPosition: function($task) {
            // This will be handled by jQuery UI sortable's revert option
            // But we also update our UI state
            this.updateEmptyStates();
            this.updateTaskCounts();
        },

        // Update task positions via AJAX
        updateTaskPositions: function(taskId, statusId, tasksOrder) {
            const self = this;
            
            if (this.config.isProcessing) {
                return;
            }

            this.config.isProcessing = true;

            $.ajax({
                url: ptt_kanban.ajax_url,
                type: 'POST',
                data: {
                    action: 'ptt_kanban_update_position',
                    nonce: ptt_kanban.nonce,
                    task_id: taskId,
                    status_id: statusId,
                    tasks_order: tasksOrder
                },
                success: function(response) {
                    if (response.success) {
                        self.updateEmptyStates();
                        self.updateTaskCounts();
                        self.showNotification('Task position updated!', 'success');
                    } else {
                        self.showNotification(response.data.message || ptt_kanban.messages.position_error, 'error');
                    }
                },
                error: function() {
                    self.showNotification(ptt_kanban.messages.position_error, 'error');
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

            // Debug: show selected filters
            self.debug('Applying filters: ' + JSON.stringify(filters));

            if (typeof ptt_kanban === 'undefined') {
                self.debug('ptt_kanban configuration missing.');
                $('#ptt-kanban-loading').fadeOut();
                return;
            }

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
                    try {
                        if (response.success) {
                            $('#ptt-kanban-board').html(response.data.html);
                            self.initDragAndDrop();
                            self.adjustBoardLayout();
                            self.debug('Board updated successfully.');
                        } else {
                            self.showNotification(ptt_kanban.messages.filter_error, 'error');
                            self.debug('Server returned error while applying filters.');
                        }
                    } catch (err) {
                        self.debug('Processing error: ' + err.message);
                    }
                },
                error: function(xhr, status, error) {
                    self.showNotification(ptt_kanban.messages.filter_error, 'error');
                    self.debug('AJAX error: ' + status + ' ' + error);
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

        // Debug helper
        debug: function(message) {
            const $debug = $('#ptt-kanban-debug');
            if ($debug.length) {
                const time = new Date().toLocaleTimeString();
                $debug.append('<div>[' + time + '] ' + message + '</div>');
            }
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
                    
                    // Get new order
                    const tasksOrder = $targetColumn.find('.ptt-kanban-task').map(function() {
                        return $(this).data('task-id');
                    }).get();
                    
                    PTTKanban.updateTaskPositions(taskId, statusId, tasksOrder);
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
                
                // Arrow keys to move between columns or tasks
                if (key >= 37 && key <= 40) {
                    e.preventDefault();
                    self.navigateWithKeyboard($task, key);
                }
            });
        },

        // Navigate tasks with keyboard
        navigateWithKeyboard: function($task, keyCode) {
            const $currentColumn = $task.closest('.ptt-kanban-column');
            const $currentColumnTasks = $task.closest('.ptt-kanban-column-tasks');
            let $targetColumn, $targetTask;
            
            switch(keyCode) {
                case 37: // Left arrow
                    $targetColumn = $currentColumn.prev('.ptt-kanban-column');
                    if ($targetColumn && $targetColumn.length) {
                        const taskId = $task.data('task-id');
                        const newStatusId = $targetColumn.data('status-id');
                        const $targetContainer = $targetColumn.find('.ptt-kanban-column-tasks');
                        
                        $task.detach().appendTo($targetContainer);
                        
                        const tasksOrder = $targetContainer.find('.ptt-kanban-task').map(function() {
                            return $(this).data('task-id');
                        }).get();
                        
                        this.updateTaskPositions(taskId, newStatusId, tasksOrder);
                        $task.focus();
                    }
                    break;
                    
                case 39: // Right arrow
                    $targetColumn = $currentColumn.next('.ptt-kanban-column');
                    if ($targetColumn && $targetColumn.length) {
                        const taskId = $task.data('task-id');
                        const newStatusId = $targetColumn.data('status-id');
                        const $targetContainer = $targetColumn.find('.ptt-kanban-column-tasks');
                        
                        $task.detach().appendTo($targetContainer);
                        
                        const tasksOrder = $targetContainer.find('.ptt-kanban-task').map(function() {
                            return $(this).data('task-id');
                        }).get();
                        
                        this.updateTaskPositions(taskId, newStatusId, tasksOrder);
                        $task.focus();
                    }
                    break;
                    
                case 38: // Up arrow
                    $targetTask = $task.prev('.ptt-kanban-task');
                    if ($targetTask.length) {
                        // Move task before the previous task
                        $task.insertBefore($targetTask);
                        
                        const tasksOrder = $currentColumnTasks.find('.ptt-kanban-task').map(function() {
                            return $(this).data('task-id');
                        }).get();
                        
                        this.updateTaskPositions($task.data('task-id'), $currentColumnTasks.data('status'), tasksOrder);
                        $task.focus();
                    }
                    break;
                    
                case 40: // Down arrow
                    $targetTask = $task.next('.ptt-kanban-task');
                    if ($targetTask.length) {
                        // Move task after the next task
                        $task.insertAfter($targetTask);
                        
                        const tasksOrder = $currentColumnTasks.find('.ptt-kanban-task').map(function() {
                            return $(this).data('task-id');
                        }).get();
                        
                        this.updateTaskPositions($task.data('task-id'), $currentColumnTasks.data('status'), tasksOrder);
                        $task.focus();
                    }
                    break;
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