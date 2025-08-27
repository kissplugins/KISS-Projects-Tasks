(function(root){
  /**
   * SessionEffects - Handles session operations for CPT Task Editor
   * Manages ACF session rows, validation, and UI updates
   */
  function SessionEffects(opts){ 
    this.opts = opts || {}; 
  }

  // Helper to get session rows
  SessionEffects.prototype.getSessionRows = function(){
    return jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
  };

  // Helper to get specific session row
  SessionEffects.prototype.getSessionRow = function(index){
    var $rows = this.getSessionRows();
    return index >= 0 && index < $rows.length ? $rows.eq(index) : null;
  };

  // Helper to get session data from row
  SessionEffects.prototype.getSessionData = function(index){
    var $row = this.getSessionRow(index);
    if(!$row) return null;

    return {
      title: $row.find('[data-key="field_ptt_session_title"] input').val() || '',
      notes: $row.find('[data-key="field_ptt_session_notes"] textarea').val() || '',
      startTime: $row.find('[data-key="field_ptt_session_start_time"] input').val() || '',
      stopTime: $row.find('[data-key="field_ptt_session_stop_time"] input').val() || '',
      manualOverride: $row.find('[data-key="field_ptt_session_manual_override"] input').prop('checked') || false,
      manualDuration: parseFloat($row.find('[data-key="field_ptt_session_manual_duration"] input').val() || '0'),
      calculatedDuration: parseFloat($row.find('[data-key="field_ptt_session_calculated_duration"] input').val() || '0')
    };
  };

  // Create new session row
  SessionEffects.prototype.createSession = function(payload){
    var self = this;
    return new Promise(function(resolve, reject){
      try {
        // Validate we can create a session
        var $rows = self.getSessionRows();
        var hasRunningTimer = false;
        
        $rows.each(function(){
          var $row = jQuery(this);
          var start = $row.find('[data-key="field_ptt_session_start_time"] input').val();
          var stop = $row.find('[data-key="field_ptt_session_stop_time"] input').val();
          if(start && !stop){
            hasRunningTimer = true;
            return false;
          }
        });

        if(hasRunningTimer){
          reject(new Error('Cannot create session while timer is running'));
          return;
        }

        // Trigger ACF add row
        var $addButton = jQuery('.acf-field[data-key="field_ptt_sessions"] [data-event="add-row"], .acf-field[data-key="field_ptt_sessions"] [data-name="add-row"]');
        if($addButton.length){
          $addButton.trigger('click');
          
          // Wait for ACF to create the row, then resolve with new index
          setTimeout(function(){
            var $newRows = self.getSessionRows();
            var newIndex = $newRows.length - 1;
            
            // Auto-populate title if provided
            if(payload.title){
              var $newRow = $newRows.eq(newIndex);
              $newRow.find('[data-key="field_ptt_session_title"] input').val(payload.title).trigger('change');
            }
            
            resolve({ sessionIndex: newIndex });
          }, 100);
        } else {
          reject(new Error('Could not find add session button'));
        }
      } catch(err) {
        reject(err);
      }
    });
  };

  // Validate session data
  SessionEffects.prototype.validateSession = function(ctx){
    var self = this;
    return new Promise(function(resolve){
      var errors = [];
      var sessionData = self.getSessionData(ctx.sessionIndex);
      
      if(!sessionData){
        errors.push('Session not found');
        resolve({ valid: false, errors: errors });
        return;
      }

      // Required field validation
      if(!sessionData.title.trim()){
        errors.push('Session title is required');
      }

      // Time validation
      if(sessionData.startTime && sessionData.stopTime){
        var start = new Date(sessionData.startTime);
        var stop = new Date(sessionData.stopTime);
        if(stop <= start){
          errors.push('Stop time must be after start time');
        }
      }

      // Duration validation
      if(sessionData.manualOverride && sessionData.manualDuration < 0){
        errors.push('Manual duration cannot be negative');
      }

      // Check for conflicts with other sessions
      var $rows = self.getSessionRows();
      $rows.each(function(i){
        if(i === ctx.sessionIndex) return; // Skip self
        
        var otherData = self.getSessionData(i);
        if(!otherData || !otherData.startTime || !sessionData.startTime) return;
        
        var thisStart = new Date(sessionData.startTime);
        var thisStop = sessionData.stopTime ? new Date(sessionData.stopTime) : new Date();
        var otherStart = new Date(otherData.startTime);
        var otherStop = otherData.stopTime ? new Date(otherData.stopTime) : new Date();
        
        // Check for time overlap
        if(thisStart < otherStop && thisStop > otherStart){
          errors.push('Session times overlap with another session');
        }
      });

      resolve({ valid: errors.length === 0, errors: errors });
    });
  };

  // Save session (trigger WordPress save)
  SessionEffects.prototype.saveSession = function(ctx){
    return new Promise(function(resolve, reject){
      try {
        // Trigger WordPress post save
        var $saveButton = jQuery('#publish');
        if($saveButton.length && $saveButton.is(':enabled')){
          // Set flag to track save completion
          sessionStorage.setItem('ptt_session_save_pending', '1');
          $saveButton.trigger('click');
          resolve({ saved: true });
        } else {
          reject(new Error('Save button not available'));
        }
      } catch(err) {
        reject(err);
      }
    });
  };

  // Delete session row
  SessionEffects.prototype.deleteSession = function(payload){
    var self = this;
    return new Promise(function(resolve, reject){
      try {
        var sessionIndex = payload.sessionIndex;
        var $row = self.getSessionRow(sessionIndex);

        if(!$row || !$row.length){
          reject(new Error('Session row not found'));
          return;
        }

        // Check if session has active timer
        var sessionData = self.getSessionData(sessionIndex);
        if(sessionData && sessionData.startTime && !sessionData.stopTime){
          reject(new Error('Cannot delete session with active timer'));
          return;
        }

        // Find and trigger ACF delete button for this row
        var $deleteButton = $row.find('[data-event="remove-row"], .acf-icon.-minus');
        if($deleteButton.length){
          // Confirm deletion
          if(confirm('Are you sure you want to delete this session? This action cannot be undone.')){
            $deleteButton.trigger('click');

            // Wait for ACF to remove the row, then resolve
            setTimeout(function(){
              // Trigger save to persist the deletion
              var $saveButton = jQuery('#publish');
              if($saveButton.length && $saveButton.is(':enabled')){
                sessionStorage.setItem('ptt_session_delete_pending', '1');
                $saveButton.trigger('click');
              }
              resolve({ deleted: true, sessionIndex: sessionIndex });
            }, 100);
          } else {
            reject(new Error('Deletion cancelled by user'));
          }
        } else {
          reject(new Error('Could not find delete button for session'));
        }
      } catch(err) {
        reject(err);
      }
    });
  };

  // Duplicate session row
  SessionEffects.prototype.duplicateSession = function(payload){
    var self = this;
    return new Promise(function(resolve, reject){
      try {
        var sourceIndex = payload.sourceIndex;
        var $sourceRow = self.getSessionRow(sourceIndex);

        if(!$sourceRow || !$sourceRow.length){
          reject(new Error('Source session row not found'));
          return;
        }

        // Get source session data
        var sourceData = self.getSessionData(sourceIndex);
        if(!sourceData){
          reject(new Error('Could not read source session data'));
          return;
        }

        // Create new session first
        var $addButton = jQuery('.acf-field[data-key="field_ptt_sessions"] [data-event="add-row"], .acf-field[data-key="field_ptt_sessions"] [data-name="add-row"]');
        if(!$addButton.length){
          reject(new Error('Could not find add session button'));
          return;
        }

        $addButton.trigger('click');

        // Wait for ACF to create the row, then populate with source data
        setTimeout(function(){
          var $newRows = self.getSessionRows();
          var newIndex = $newRows.length - 1;
          var $newRow = $newRows.eq(newIndex);

          if(!$newRow.length){
            reject(new Error('Failed to create new session row'));
            return;
          }

          // Generate new title with "Copy of" prefix and timestamp
          var now = new Date();
          var mm = ('0'+(now.getMonth()+1)).slice(-2);
          var dd = ('0'+now.getDate()).slice(-2);
          var yy = String(now.getFullYear()).slice(-2);
          var HH = ('0'+now.getHours()).slice(-2);
          var MM = ('0'+now.getMinutes()).slice(-2);
          var newTitle = 'Copy of ' + (sourceData.title || 'Session') + ' (' + mm + '-' + dd + '-' + yy + ' ' + HH + ':' + MM + ')';

          // Populate new row with source data (excluding timer-specific fields)
          $newRow.find('[data-key="field_ptt_session_title"] input').val(newTitle).trigger('change');
          $newRow.find('[data-key="field_ptt_session_notes"] textarea').val(sourceData.notes).trigger('change');

          // Copy manual override and duration if set
          if(sourceData.manualOverride){
            $newRow.find('[data-key="field_ptt_session_manual_override"] input').prop('checked', true).trigger('change');
            $newRow.find('[data-key="field_ptt_session_manual_duration"] input').val(sourceData.manualDuration).trigger('change');
          }

          // Do NOT copy start/stop times or calculated duration - these should be fresh for new session

          // Trigger save to persist the duplication
          var $saveButton = jQuery('#publish');
          if($saveButton.length && $saveButton.is(':enabled')){
            sessionStorage.setItem('ptt_session_duplicate_pending', '1');
            $saveButton.trigger('click');
          }

          resolve({
            duplicated: true,
            sourceIndex: sourceIndex,
            newIndex: newIndex,
            newTitle: newTitle
          });
        }, 100);

      } catch(err) {
        reject(err);
      }
    });
  };

  // Reorder session row
  SessionEffects.prototype.reorderSession = function(payload){
    var self = this;
    return new Promise(function(resolve, reject){
      try {
        var fromIndex = payload.fromIndex;
        var toIndex = payload.toIndex;
        var direction = payload.direction; // 'up' or 'down'

        var $rows = self.getSessionRows();
        var $fromRow = $rows.eq(fromIndex);

        if(!$fromRow || !$fromRow.length){
          reject(new Error('Source session row not found'));
          return;
        }

        // Validate target position
        if(toIndex < 0 || toIndex >= $rows.length){
          reject(new Error('Invalid target position'));
          return;
        }

        if(fromIndex === toIndex){
          reject(new Error('Source and target positions are the same'));
          return;
        }

        // Check for time conflicts after reordering
        var conflicts = self.detectReorderConflicts(fromIndex, toIndex);
        if(conflicts.length > 0){
          var confirmMsg = 'Reordering may create time conflicts:\n' + conflicts.join('\n') + '\n\nContinue anyway?';
          if(!confirm(confirmMsg)){
            reject(new Error('Reorder cancelled due to time conflicts'));
            return;
          }
        }

        // Perform the reorder using DOM manipulation
        var $targetRow = $rows.eq(toIndex);

        if(direction === 'up' || fromIndex > toIndex){
          // Moving up: insert before target
          $fromRow.insertBefore($targetRow);
        } else {
          // Moving down: insert after target
          $fromRow.insertAfter($targetRow);
        }

        // Update ACF row indices (ACF handles this automatically when DOM changes)
        // Trigger save to persist the reordering
        var $saveButton = jQuery('#publish');
        if($saveButton.length && $saveButton.is(':enabled')){
          sessionStorage.setItem('ptt_session_reorder_pending', '1');
          $saveButton.trigger('click');
        }

        resolve({
          reordered: true,
          fromIndex: fromIndex,
          toIndex: toIndex,
          direction: direction,
          conflicts: conflicts
        });

      } catch(err) {
        reject(err);
      }
    });
  };

  // Detect potential time conflicts when reordering sessions
  SessionEffects.prototype.detectReorderConflicts = function(fromIndex, toIndex){
    var conflicts = [];
    var $rows = this.getSessionRows();
    var movingSession = this.getSessionData(fromIndex);

    if(!movingSession || !movingSession.startTime) return conflicts;

    var movingStart = new Date(movingSession.startTime);
    var movingEnd = movingSession.stopTime ? new Date(movingSession.stopTime) : new Date();

    // Check sessions around the target position for time overlaps
    var checkIndices = [];
    var start = Math.max(0, Math.min(fromIndex, toIndex) - 1);
    var end = Math.min($rows.length - 1, Math.max(fromIndex, toIndex) + 1);

    for(var i = start; i <= end; i++){
      if(i !== fromIndex) checkIndices.push(i);
    }

    for(var j = 0; j < checkIndices.length; j++){
      var checkIndex = checkIndices[j];
      var checkSession = this.getSessionData(checkIndex);

      if(!checkSession || !checkSession.startTime) continue;

      var checkStart = new Date(checkSession.startTime);
      var checkEnd = checkSession.stopTime ? new Date(checkSession.stopTime) : new Date();

      // Check for time overlap
      if(movingStart < checkEnd && movingEnd > checkStart){
        conflicts.push('Session "' + (movingSession.title || 'Untitled') + '" overlaps with "' + (checkSession.title || 'Untitled') + '"');
      }
    }

    return conflicts;
  };

  // Update session UI based on FSM state
  SessionEffects.prototype.updateSessionUI = function(state, ctx){
    var $rows = this.getSessionRows();
    
    // Update specific session row if we have an index
    if(ctx.sessionIndex !== null && ctx.sessionIndex >= 0){
      var $row = $rows.eq(ctx.sessionIndex);
      if($row.length){
        this.updateSessionRowUI($row, state, ctx);
      }
    }
    
    // Update global session controls
    this.updateGlobalSessionUI(state, ctx);
  };

  // Update individual session row UI
  SessionEffects.prototype.updateSessionRowUI = function($row, state, ctx){
    // Add state classes for styling
    $row.removeClass('ptt-session-idle ptt-session-editing ptt-session-validating ptt-session-saving ptt-session-error');
    $row.addClass('ptt-session-' + state.toLowerCase());

    // Show/hide validation errors
    var $errorContainer = $row.find('.ptt-session-errors');
    if(!$errorContainer.length){
      $errorContainer = jQuery('<div class="ptt-session-errors" style="color: #d63638; font-size: 12px; margin-top: 5px;"></div>');
      $row.find('.acf-input').first().append($errorContainer);
    }

    if(ctx.validationErrors && ctx.validationErrors.length > 0){
      $errorContainer.html(ctx.validationErrors.join('<br>')).show();
    } else {
      $errorContainer.hide();
    }

    // Add duplicate and reorder buttons if not already present
    this.ensureDuplicateButton($row);
    this.ensureReorderButtons($row);

    // Update field states based on FSM state
    var $inputs = $row.find('input, textarea, select');
    if(state === 'SAVING' || state === 'DELETING' || state === 'DUPLICATING' || state === 'REORDERING'){
      $inputs.prop('disabled', true);
    } else {
      $inputs.prop('disabled', false);
    }
  };

  // Ensure duplicate button exists in session row
  SessionEffects.prototype.ensureDuplicateButton = function($row){
    if($row.find('.ptt-session-duplicate').length) return; // Already exists

    // Find the row controls area (usually near the delete button)
    var $controls = $row.find('.acf-row-handle');
    if(!$controls.length) return;

    // Create duplicate button
    var $duplicateBtn = jQuery('<a href="#" class="ptt-session-duplicate acf-icon -duplicate" title="Duplicate Session" style="margin-left: 5px; color: #0073aa; text-decoration: none;">⧉</a>');

    // Insert after the delete button or at the end of controls
    var $deleteBtn = $controls.find('.acf-icon.-minus');
    if($deleteBtn.length){
      $deleteBtn.after($duplicateBtn);
    } else {
      $controls.append($duplicateBtn);
    }
  };

  // Ensure reorder buttons exist in session row
  SessionEffects.prototype.ensureReorderButtons = function($row){
    if($row.find('.ptt-session-reorder').length) return; // Already exists

    // Find the row controls area
    var $controls = $row.find('.acf-row-handle');
    if(!$controls.length) return;

    var rowIndex = $row.index();
    var totalRows = this.getSessionRows().length;

    // Create reorder buttons container
    var $reorderContainer = jQuery('<span class="ptt-session-reorder" style="margin-left: 5px;"></span>');

    // Create up button (only if not first row)
    if(rowIndex > 0){
      var $upBtn = jQuery('<a href="#" class="ptt-session-reorder-up acf-icon" title="Move Up" style="color: #666; text-decoration: none; font-size: 12px;">↑</a>');
      $reorderContainer.append($upBtn);
    }

    // Create down button (only if not last row)
    if(rowIndex < totalRows - 1){
      var $downBtn = jQuery('<a href="#" class="ptt-session-reorder-down acf-icon" title="Move Down" style="color: #666; text-decoration: none; font-size: 12px;">↓</a>');
      $reorderContainer.append($downBtn);
    }

    // Insert after duplicate button or delete button
    var $duplicateBtn = $controls.find('.ptt-session-duplicate');
    var $deleteBtn = $controls.find('.acf-icon.-minus');

    if($duplicateBtn.length){
      $duplicateBtn.after($reorderContainer);
    } else if($deleteBtn.length){
      $deleteBtn.after($reorderContainer);
    } else {
      $controls.append($reorderContainer);
    }
  };

  // Update global session UI
  SessionEffects.prototype.updateGlobalSessionUI = function(state, ctx){
    var $addButton = jQuery('.acf-field[data-key="field_ptt_sessions"] [data-event="add-row"], .acf-field[data-key="field_ptt_sessions"] [data-name="add-row"]');

    // Disable add button if we can't create sessions
    if(state === 'CREATING' || state === 'SAVING' || state === 'DUPLICATING' || state === 'REORDERING'){
      $addButton.prop('disabled', true);
    } else {
      $addButton.prop('disabled', false);
    }

    // Ensure all rows have duplicate and reorder buttons
    this.initializeAllRowButtons();
  };

  // Initialize duplicate and reorder buttons for all existing rows
  SessionEffects.prototype.initializeAllRowButtons = function(){
    var self = this;
    this.getSessionRows().each(function(){
      var $row = jQuery(this);
      self.ensureDuplicateButton($row);
      self.ensureReorderButtons($row);
    });
  };

  // Show error message
  SessionEffects.prototype.showError = function(msg){ 
    if(root.console) console.warn('[PTT SessionEffects]', msg);
    
    // Could enhance this to show user-friendly notifications
    if(typeof msg === 'string' && msg.length > 0){
      // For now, just use browser alert - could be enhanced with better UI
      setTimeout(function(){ alert('Session Error: ' + msg); }, 100);
    }
  };

  root.PTT = root.PTT || {}; 
  root.PTT.SessionEffects = SessionEffects;
})(window);
