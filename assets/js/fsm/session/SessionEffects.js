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
    
    // Update field states based on FSM state
    var $inputs = $row.find('input, textarea, select');
    if(state === 'SAVING'){
      $inputs.prop('disabled', true);
    } else {
      $inputs.prop('disabled', false);
    }
  };

  // Update global session UI
  SessionEffects.prototype.updateGlobalSessionUI = function(state, ctx){
    var $addButton = jQuery('.acf-field[data-key="field_ptt_sessions"] [data-event="add-row"], .acf-field[data-key="field_ptt_sessions"] [data-name="add-row"]');
    
    // Disable add button if we can't create sessions
    if(state === 'CREATING' || state === 'SAVING'){
      $addButton.prop('disabled', true);
    } else {
      $addButton.prop('disabled', false);
    }
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
