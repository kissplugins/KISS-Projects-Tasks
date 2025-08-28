(function(root){
  /**
   * TaskFSM - Manages timer and session lifecycle
   * States: IDLE, CREATING, EDITING, VALIDATING, SAVING, AUTO_SAVING, ERROR
   * Handles both timer and session logic with built-in auto-save before timer start
   */
  function TaskFSM(effects, opts){
    this.effects = effects || {};
    this.state = 'IDLE';
    this.ctx = {
      sessionIndex: null,
      postId: null,
      isDirty: false,
      validationErrors: [],
      pendingChanges: {}
    };
    this.debug = (opts && opts.debug) || false;
    this.timerState = 'IDLE';
    this.timerCtx = { taskId:null, postId:null, sessionIndex:null, startUtc:null };
    this.saveTimeout = null; // Track save operation timeouts
  }

  TaskFSM.prototype.log = function(){ 
    if(this.debug && root.console){ 
      console.log.apply(console, ['[PTT TaskFSM]'].concat([].slice.call(arguments))); 
    } 
  };

  TaskFSM.prototype.transition = function(event, payload){
    const ts = this.timerState;
    if(ts==='IDLE' && event==='START_TIMER') return this._startTimer(payload);
    if(ts==='RUNNING' && event==='STOP_TIMER') return this._stopTimer(payload);
    if(event==='TIMER_ERROR'){ this.timerState='ERROR'; this.log('TIMER_ERROR', payload); return; }
    const s = this.state; 
    const e = event;
    
    // State transition table
    if(s==='IDLE' && e==='CREATE_SESSION') return this._createSession(payload);
    if(s==='IDLE' && e==='EDIT_SESSION') return this._editSession(payload);
    if(s==='IDLE' && e==='DELETE_SESSION') return this._deleteSession(payload);
    if(s==='IDLE' && e==='DUPLICATE_SESSION') return this._duplicateSession(payload);
    if(s==='IDLE' && e==='REORDER_SESSION') return this._reorderSession(payload);
    if(s==='IDLE' && e==='BULK_OPERATION') return this._bulkOperation(payload);
    if(s==='CREATING' && e==='SESSION_CREATED') return this._afterCreate(payload);
    if(s==='CREATING' && e==='CREATE_FAILED') return this._createFailed(payload);
    if(s==='DUPLICATING' && e==='SESSION_DUPLICATED') return this._sessionDuplicated(payload);
    if(s==='DUPLICATING' && e==='DUPLICATE_FAILED') return this._duplicateFailed(payload);
    if(s==='REORDERING' && e==='SESSION_REORDERED') return this._sessionReordered(payload);
    if(s==='REORDERING' && e==='REORDER_FAILED') return this._reorderFailed(payload);
    if(s==='BULK_PROCESSING' && e==='BULK_COMPLETED') return this._bulkCompleted(payload);
    if(s==='BULK_PROCESSING' && e==='BULK_FAILED') return this._bulkFailed(payload);
    if(s==='EDITING' && e==='FIELD_CHANGED') return this._fieldChanged(payload);
    if(s==='EDITING' && e==='VALIDATE_SESSION') return this._validateSession(payload);
    if(s==='EDITING' && e==='SAVE_SESSION') return this._saveSession(payload);
    if(s==='EDITING' && e==='AUTO_SAVE_FOR_TIMER') return this._autoSaveForTimer(payload);
    if(s==='EDITING' && e==='AUTO_IDLE') return this._autoIdle(payload);
    if(s==='AUTO_SAVING' && e==='AUTO_SAVE_COMPLETE') return this._autoSaveComplete(payload);
    if(s==='AUTO_SAVING' && e==='AUTO_SAVE_FAILED') return this._autoSaveFailed(payload);
    if(s==='DELETING' && e==='SESSION_DELETED') return this._sessionDeleted(payload);
    if(s==='DELETING' && e==='DELETE_FAILED') return this._deleteFailed(payload);
    if(s==='VALIDATING' && e==='VALIDATION_PASSED') return this._validationPassed(payload);
    if(s==='VALIDATING' && e==='VALIDATION_FAILED') return this._validationFailed(payload);
    if(s==='SAVING' && e==='SESSION_SAVED') return this._sessionSaved(payload);
    if(s==='SAVING' && e==='SAVE_FAILED') return this._saveFailed(payload);
    if(s==='ERROR' && e==='RETRY') return this._retry(payload);
    if(s==='ERROR' && e==='RESET') return this._reset();
    if(e==='SESSION_ERROR') {
      this.state='ERROR';
      var errorMsg = payload && payload.message ? payload.message : (typeof payload === 'string' ? payload : 'Unknown error');
      this.ctx.validationErrors = [errorMsg];
      this.log('ERROR', errorMsg);
      this.effects.showError && this.effects.showError(errorMsg);
      return;
    }
    
    this.log('Ignored', e, 'in', s);
  };

  TaskFSM.prototype._createSession = function(payload){
    this.state = 'CREATING';
    this.ctx.postId = payload.postId;
    var self = this;
    
    // Check if timer is running - prevent creating new session if timer active
    if(this.timerState === 'RUNNING'){
      this.state = 'ERROR';
      this.ctx.validationErrors = ['Cannot create new session while timer is running'];
      this.effects.showError && this.effects.showError('Stop the current timer before creating a new session');
      this.log('CREATE_FAILED', 'Timer running');
      return;
    }

    return this.effects.createSession(payload).then(function(res){
      self.ctx.sessionIndex = res.sessionIndex;
      self.state = 'IDLE';
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('SESSION_CREATED', self.ctx);
    }).catch(function(err){
      self.state = 'ERROR';
      self.ctx.validationErrors = [err.message || err];
      self.effects.showError && self.effects.showError(err);
      self.log('CREATE_FAILED', err);
    });
  };

  TaskFSM.prototype._editSession = function(payload){
    this.state = 'EDITING';
    this.ctx.sessionIndex = payload.sessionIndex;
    this.ctx.postId = payload.postId;
    this.ctx.isDirty = false;
    this.ctx.pendingChanges = {};
    this.ctx.validationErrors = [];

    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('EDITING_SESSION', this.ctx);
  };

  TaskFSM.prototype._deleteSession = function(payload){
    this.state = 'DELETING';
    this.ctx.sessionIndex = payload.sessionIndex;
    this.ctx.postId = payload.postId;
    var self = this;

    // Check if trying to delete active timer session
    if(this.timerState === 'RUNNING' &&
       this.timerCtx.sessionIndex === payload.sessionIndex){
      this.state = 'ERROR';
      this.ctx.validationErrors = ['Cannot delete session with active timer'];
      this.effects.showError && this.effects.showError('Stop the timer before deleting this session');
      this.log('DELETE_FAILED', 'Active timer session');
      return;
    }

    return this.effects.deleteSession(payload).then(function(res){
      self.state = 'IDLE';
      self.ctx = {
        sessionIndex: null,
        postId: null,
        isDirty: false,
        validationErrors: [],
        pendingChanges: {}
      };
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('SESSION_DELETED', res);
    }).catch(function(err){
      self.state = 'ERROR';
      self.ctx.validationErrors = [err.message || err];
      self.effects.showError && self.effects.showError(err);
      self.log('DELETE_FAILED', err);
    });
  };

  TaskFSM.prototype._sessionDeleted = function(payload){
    this.state = 'IDLE';
    this.ctx = {
      sessionIndex: null,
      postId: null,
      isDirty: false,
      validationErrors: [],
      pendingChanges: {}
    };
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('SESSION_DELETED', payload);
  };

  TaskFSM.prototype._deleteFailed = function(payload){
    this.state = 'ERROR';
    this.ctx.validationErrors = [payload.message || 'Delete failed'];
    this.effects.showError && this.effects.showError(payload.message || 'Failed to delete session');
    this.log('DELETE_FAILED', payload);
  };

  TaskFSM.prototype._duplicateSession = function(payload){
    this.state = 'DUPLICATING';
    this.ctx.sessionIndex = payload.sourceIndex;
    this.ctx.postId = payload.postId;
    var self = this;

    // Check if timer is running - prevent duplicating while timer active
    if(this.timerState === 'RUNNING'){
      this.state = 'ERROR';
      this.ctx.validationErrors = ['Cannot duplicate session while timer is running'];
      this.effects.showError && this.effects.showError('Stop the current timer before duplicating sessions');
      this.log('DUPLICATE_FAILED', 'Timer running');
      return;
    }

    return this.effects.duplicateSession(payload).then(function(res){
      self.state = 'IDLE';
      self.ctx = {
        sessionIndex: null,
        postId: null,
        isDirty: false,
        validationErrors: [],
        pendingChanges: {}
      };
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('SESSION_DUPLICATED', res);
    }).catch(function(err){
      self.state = 'ERROR';
      self.ctx.validationErrors = [err.message || err];
      self.effects.showError && self.effects.showError(err);
      self.log('DUPLICATE_FAILED', err);
    });
  };

  TaskFSM.prototype._sessionDuplicated = function(payload){
    this.state = 'IDLE';
    this.ctx = {
      sessionIndex: null,
      postId: null,
      isDirty: false,
      validationErrors: [],
      pendingChanges: {}
    };
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('SESSION_DUPLICATED', payload);
  };

  TaskFSM.prototype._duplicateFailed = function(payload){
    this.state = 'ERROR';
    this.ctx.validationErrors = [payload.message || 'Duplication failed'];
    this.effects.showError && this.effects.showError(payload.message || 'Failed to duplicate session');
    this.log('DUPLICATE_FAILED', payload);
  };

  TaskFSM.prototype._reorderSession = function(payload){
    this.state = 'REORDERING';
    this.ctx.sessionIndex = payload.fromIndex;
    this.ctx.postId = payload.postId;
    var self = this;

    // Check if timer is running - prevent reordering while timer active
    if(this.timerState === 'RUNNING'){
      this.state = 'ERROR';
      this.ctx.validationErrors = ['Cannot reorder sessions while timer is running'];
      this.effects.showError && this.effects.showError('Stop the current timer before reordering sessions');
      this.log('REORDER_FAILED', 'Timer running');
      return;
    }

    return this.effects.reorderSession(payload).then(function(res){
      self.state = 'IDLE';
      self.ctx = {
        sessionIndex: null,
        postId: null,
        isDirty: false,
        validationErrors: [],
        pendingChanges: {}
      };
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('SESSION_REORDERED', res);
    }).catch(function(err){
      self.state = 'ERROR';
      self.ctx.validationErrors = [err.message || err];
      self.effects.showError && self.effects.showError(err);
      self.log('REORDER_FAILED', err);
    });
  };

  TaskFSM.prototype._sessionReordered = function(payload){
    this.state = 'IDLE';
    this.ctx = {
      sessionIndex: null,
      postId: null,
      isDirty: false,
      validationErrors: [],
      pendingChanges: {}
    };
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('SESSION_REORDERED', payload);
  };

  TaskFSM.prototype._reorderFailed = function(payload){
    this.state = 'ERROR';
    this.ctx.validationErrors = [payload.message || 'Reorder failed'];
    this.effects.showError && this.effects.showError(payload.message || 'Failed to reorder session');
    this.log('REORDER_FAILED', payload);
  };

  TaskFSM.prototype._bulkOperation = function(payload){
    this.state = 'BULK_PROCESSING';
    this.ctx.bulkOperation = payload.operation;
    this.ctx.selectedIndices = payload.selectedIndices;
    this.ctx.postId = payload.postId;
    var self = this;

    // Check if timer is running - prevent bulk operations while timer active
    if(this.timerState === 'RUNNING'){
      this.state = 'ERROR';
      this.ctx.validationErrors = ['Cannot perform bulk operations while timer is running'];
      this.effects.showError && this.effects.showError('Stop the current timer before performing bulk operations');
      this.log('BULK_FAILED', 'Timer running');
      return;
    }

    // Validate selection
    if(!payload.selectedIndices || payload.selectedIndices.length === 0){
      this.state = 'ERROR';
      this.ctx.validationErrors = ['No sessions selected for bulk operation'];
      this.effects.showError && this.effects.showError('Please select at least one session');
      this.log('BULK_FAILED', 'No selection');
      return;
    }

    return this.effects.performBulkOperation(payload).then(function(res){
      self.state = 'IDLE';
      self.ctx = {
        sessionIndex: null,
        postId: null,
        isDirty: false,
        validationErrors: [],
        pendingChanges: {}
      };
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('BULK_COMPLETED', res);
    }).catch(function(err){
      self.state = 'ERROR';
      self.ctx.validationErrors = [err.message || err];
      self.effects.showError && self.effects.showError(err);
      self.log('BULK_FAILED', err);
    });
  };

  TaskFSM.prototype._bulkCompleted = function(payload){
    this.state = 'IDLE';
    this.ctx = {
      sessionIndex: null,
      postId: null,
      isDirty: false,
      validationErrors: [],
      pendingChanges: {}
    };
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('BULK_COMPLETED', payload);
  };

  TaskFSM.prototype._bulkFailed = function(payload){
    this.state = 'ERROR';
    this.ctx.validationErrors = [payload.message || 'Bulk operation failed'];
    this.effects.showError && this.effects.showError(payload.message || 'Failed to perform bulk operation');
    this.log('BULK_FAILED', payload);
  };

  TaskFSM.prototype._fieldChanged = function(payload){
    if(this.state !== 'EDITING') return;

    this.ctx.isDirty = true;
    this.ctx.pendingChanges[payload.field] = payload.value;

    // Real-time validation for certain fields
    if(payload.field === 'session_title' && !payload.value.trim()){
      this.ctx.validationErrors = ['Session title is required'];
    } else if(payload.field === 'session_manual_duration' && payload.value < 0){
      this.ctx.validationErrors = ['Duration cannot be negative'];
    } else {
      this.ctx.validationErrors = [];
    }

    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('FIELD_CHANGED', payload.field, payload.value);

    // Auto-transition back to IDLE if no meaningful changes
    this._checkAutoIdle();
  };

  TaskFSM.prototype._validateSession = function(payload){
    this.state = 'VALIDATING';
    var self = this;
    
    return this.effects.validateSession(this.ctx).then(function(result){
      if(result.valid){
        self.transition('VALIDATION_PASSED', result);
      } else {
        self.transition('VALIDATION_FAILED', result);
      }
    }).catch(function(err){
      self.transition('VALIDATION_FAILED', { errors: [err.message || err] });
    });
  };

  TaskFSM.prototype._validationPassed = function(payload){
    this.state = 'EDITING';
    this.ctx.validationErrors = [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('VALIDATION_PASSED');
  };

  TaskFSM.prototype._validationFailed = function(payload){
    this.state = 'EDITING';
    this.ctx.validationErrors = payload.errors || [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.effects.showError && this.effects.showError('Validation failed: ' + this.ctx.validationErrors.join(', '));
    this.log('VALIDATION_FAILED', this.ctx.validationErrors);
  };

  // Auto-save for timer start - FSM-centric approach
  TaskFSM.prototype._autoSaveForTimer = function(payload){
    this.state = 'AUTO_SAVING';
    this.ctx.autoSaveReason = 'timer_start';
    this.ctx.timerPayload = payload; // Store timer payload for after save
    var self = this;

    // Clear any existing timeout
    if(this.saveTimeout){
      clearTimeout(this.saveTimeout);
    }

    // Set a timeout for auto-save
    this.saveTimeout = setTimeout(function(){
      if(self.state === 'AUTO_SAVING'){
        self.state = 'EDITING';
        self.ctx.validationErrors = ['Auto-save timed out. Please try again.'];
        self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
        self.effects.showError && self.effects.showError('Auto-save timed out');
        self.log('AUTO_SAVE_TIMEOUT');

        // Notify timer that auto-save failed
        self.timerState = 'IDLE';
        self.effects.updateTimerUI && self.effects.updateTimerUI(self.timerState, self.timerCtx);
      }
    }, 10000); // 10 second timeout for auto-save

    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('AUTO_SAVE_STARTING', 'for timer start');

    return this.effects.autoSavePost(this.ctx).then(function(result){
      self.transition('AUTO_SAVE_COMPLETE', result);
    }).catch(function(err){
      self.transition('AUTO_SAVE_FAILED', err);
    });
  };

  TaskFSM.prototype._autoSaveComplete = function(payload){
    // Clear timeout on successful auto-save
    if(this.saveTimeout){
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }

    this.state = 'IDLE';
    this.ctx.isDirty = false;
    this.ctx.pendingChanges = {};
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('AUTO_SAVE_COMPLETE', payload);

    // Now proceed with timer start
    if(this.ctx.timerPayload){
      var timerPayload = this.ctx.timerPayload;
      this.ctx.timerPayload = null;
      this.transition('START_TIMER', timerPayload);
    }
  };

  TaskFSM.prototype._autoSaveFailed = function(payload){
    // Clear timeout on auto-save failure
    if(this.saveTimeout){
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }

    this.state = 'EDITING';
    this.ctx.validationErrors = [payload.message || payload];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.effects.showError && this.effects.showError('Auto-save failed: ' + (payload.message || payload));
    this.log('AUTO_SAVE_FAILED', payload);

    // Notify timer that auto-save failed
    this.timerState = 'IDLE';
    this.effects.updateTimerUI && this.effects.updateTimerUI(this.timerState, this.timerCtx);
  };

  TaskFSM.prototype._saveSession = function(payload){
    // Pre-save validation to prevent getting stuck
    var validationError = this.getValidationError('save sessions');
    if(validationError){
      this.state = 'EDITING'; // Stay in editing state
      this.ctx.validationErrors = [validationError];
      this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
      this.effects.showError && this.effects.showError(validationError);
      this.log('SAVE_BLOCKED', validationError);
      return Promise.reject(new Error(validationError));
    }

    this.state = 'SAVING';
    var self = this;

    // Clear any existing timeout
    if(this.saveTimeout){
      clearTimeout(this.saveTimeout);
    }

    // Set a timeout to prevent indefinite "Updating..." state
    this.saveTimeout = setTimeout(function(){
      if(self.state === 'SAVING'){
        self.state = 'EDITING';
        self.ctx.validationErrors = ['Save operation timed out. Please try again.'];
        self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
        self.effects.showError && self.effects.showError('Save operation timed out');
        self.log('SAVE_TIMEOUT');
      }
    }, 30000); // 30 second timeout

    return this.effects.saveSession(this.ctx).then(function(result){
      // Clear timeout on successful save
      if(self.saveTimeout){
        clearTimeout(self.saveTimeout);
        self.saveTimeout = null;
      }
      self.ctx.isDirty = false;
      self.ctx.pendingChanges = {};
      self.state = 'IDLE';
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('SESSION_SAVED', result);
    }).catch(function(err){
      // Clear timeout on save failure
      if(self.saveTimeout){
        clearTimeout(self.saveTimeout);
        self.saveTimeout = null;
      }

      // Always return to EDITING state on save failure, not ERROR
      self.state = 'EDITING';
      self.ctx.validationErrors = [err.message || err];
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.effects.showError && self.effects.showError(err);
      self.log('SAVE_FAILED', err);

      // Ensure UI is properly updated to remove "Updating..." state
      setTimeout(function(){
        self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      }, 100);
    });
  };

  TaskFSM.prototype._retry = function(payload){
    this.state = 'IDLE';
    this.ctx.validationErrors = [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('RETRY');
  };

  TaskFSM.prototype._reset = function(){
    // Clear any pending timeouts
    if(this.saveTimeout){
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }
    if(this._autoIdleTimeout){
      clearTimeout(this._autoIdleTimeout);
      this._autoIdleTimeout = null;
    }

    this.state = 'IDLE';
    this.ctx = {
      sessionIndex: null,
      postId: null,
      isDirty: false,
      validationErrors: [],
      pendingChanges: {}
    };
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('RESET');
  };

  // Emergency recovery method for stuck states
  TaskFSM.prototype.forceReset = function(){
    // Clear any pending timeouts
    if(this.saveTimeout){
      clearTimeout(this.saveTimeout);
      this.saveTimeout = null;
    }
    if(this._autoIdleTimeout){
      clearTimeout(this._autoIdleTimeout);
      this._autoIdleTimeout = null;
    }

    this.log('FORCE_RESET', 'Emergency recovery from stuck state:', this.state);
    this.transition('RESET');

    // Force UI update for all sessions
    var self = this;
    setTimeout(function(){
      // Remove any stuck "Updating..." buttons
      jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-button[disabled]').prop('disabled', false).text('Update');

      // Update all session UIs
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
    }, 100);

    return 'SessionFSM has been reset. You can now try your operation again.';
  };

  // Auto-transition to IDLE when no meaningful changes exist
  TaskFSM.prototype._checkAutoIdle = function(){
    var self = this;

    // Debounce the check to avoid rapid state changes
    if(this._autoIdleTimeout){
      clearTimeout(this._autoIdleTimeout);
    }

    this._autoIdleTimeout = setTimeout(function(){
      // Only auto-transition if we're still in EDITING state and have no meaningful changes
      if(self.state === 'EDITING' && !self._hasMeaningfulChanges()){
        self.transition('AUTO_IDLE');
      }
    }, 1000); // 1 second debounce
  };

  TaskFSM.prototype._autoIdle = function(payload){
    this.state = 'IDLE';
    this.ctx.isDirty = false;
    this.ctx.pendingChanges = {};
    this.ctx.sessionIndex = null;
    this.ctx.validationErrors = [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('AUTO_IDLE', 'Automatically returned to IDLE - no meaningful changes');
  };

  // Check if there are meaningful changes that warrant staying in EDITING state
  TaskFSM.prototype._hasMeaningfulChanges = function(){
    // If explicitly marked as dirty, we have changes
    if(this.ctx.isDirty && Object.keys(this.ctx.pendingChanges).length > 0){
      // Check if any pending changes are non-empty/meaningful
      for(var field in this.ctx.pendingChanges){
        var value = this.ctx.pendingChanges[field];
        if(value !== null && value !== undefined && value !== ''){
          return true;
        }
      }
    }
    return false;
  };

  // Helper methods
  TaskFSM.prototype.canCreateSession = function(){
    return this.state === 'IDLE' && this.timerState !== 'RUNNING';
  };

  // Enhanced validation with descriptive error messages
  TaskFSM.prototype.getValidationError = function(action){
    // Check if user is assignee of this task
    var currentUserId = this._getCurrentUserId();
    var taskAssigneeId = this._getTaskAssigneeId();

    if(currentUserId && taskAssigneeId && currentUserId !== taskAssigneeId){
      return 'You are not the assignee of this task, so you cannot ' + action + '.';
    }

    // Check timer state with specific messages
    if(this.timerState === 'RUNNING'){
      switch(action){
        case 'create sessions':
          return 'You already have a timer running. Stop the current timer before creating new sessions.';
        case 'delete sessions':
          return 'You cannot delete sessions while a timer is running. Stop the timer first.';
        case 'duplicate sessions':
          return 'You cannot duplicate sessions while a timer is running. Stop the timer first.';
        case 'reorder sessions':
          return 'You cannot reorder sessions while a timer is running. Stop the timer first.';
        case 'perform bulk operations':
          return 'You cannot perform bulk operations while a timer is running. Stop the timer first.';
        default:
          return 'You cannot ' + action + ' while a timer is running. Stop the timer first.';
      }
    }

    // Check FSM state
    if(this.state !== 'IDLE'){
      return 'Session system is busy (' + this.state + '). Please wait and try again.';
    }

    return null; // No error
  };

  // Helper to get current user ID from page context
  TaskFSM.prototype._getCurrentUserId = function(){
    // Try to get from global WordPress admin context
    if(window.userSettings && window.userSettings.uid){
      return parseInt(window.userSettings.uid, 10);
    }
    // Try to get from PTT global if available
    if(window.ptt_ajax_object && window.ptt_ajax_object.current_user_id){
      return parseInt(window.ptt_ajax_object.current_user_id, 10);
    }
    return null;
  };

  // Helper to get task assignee ID from page context
  TaskFSM.prototype._getTaskAssigneeId = function(){
    // Try to get from ACF field if available
    var $assigneeField = jQuery('[data-key="field_ptt_assignee"] select, [data-key="field_ptt_assignee"] input[type="hidden"]');
    if($assigneeField.length){
      var assigneeId = $assigneeField.val();
      return assigneeId ? parseInt(assigneeId, 10) : null;
    }

    // Try to get from meta box if available
    var $metaAssignee = jQuery('#ptt_assignee, input[name="ptt_assignee"]');
    if($metaAssignee.length){
      var assigneeId = $metaAssignee.val();
      return assigneeId ? parseInt(assigneeId, 10) : null;
    }

    return null;
  };

  TaskFSM.prototype.canEditSession = function(){
    return this.state === 'IDLE';
  };

  TaskFSM.prototype.canSaveSession = function(){
    return this.state === 'EDITING' && this.ctx.isDirty && this.ctx.validationErrors.length === 0;
  };

  TaskFSM.prototype.hasUnsavedChanges = function(){
    // Check if SessionFSM has unsaved changes
    if(this.ctx.isDirty) return true;

    // Check if WordPress indicates the post is dirty (more reliable than manual checks)
    if(typeof wp !== 'undefined' && wp.autosave && wp.autosave.server && wp.autosave.server.postChanged){
      return wp.autosave.server.postChanged();
    }

    // For new posts, only intercept if we have actual SessionFSM changes
    // Let WordPress handle normal publish flow for new posts with just title/content
    return false;
  };

  TaskFSM.prototype.canDeleteSession = function(sessionIndex){
    // Can't delete if FSM is busy
    if(this.state !== 'IDLE') return false;

    // Can't delete if timer is running on this session
    if(this.timerState === 'RUNNING' &&
       this.timerCtx.sessionIndex === sessionIndex){
      return false;
    }

    return true;
  };

  TaskFSM.prototype.canDuplicateSession = function(){
    // Can't duplicate if FSM is busy
    if(this.state !== 'IDLE') return false;

    // Can't duplicate if timer is running (to avoid confusion)
    if(this.timerState === 'RUNNING'){
      return false;
    }

    return true;
  };

  TaskFSM.prototype.canReorderSessions = function(){
    // Can't reorder if FSM is busy
    if(this.state !== 'IDLE') return false;

    // Can't reorder if timer is running (to avoid confusion and index mismatches)
    if(this.timerState === 'RUNNING'){
      return false;
    }

    return true;
  };

  TaskFSM.prototype.canPerformBulkOperations = function(){
    // Can't perform bulk operations if FSM is busy
    if(this.state !== 'IDLE') return false;

    // Can't perform bulk operations if timer is running (to avoid conflicts)
    if(this.timerState === 'RUNNING'){
      return false;
    }

    return true;
  };

  TaskFSM.prototype._startTimer = function(payload){
    this.timerState='STARTING';
    this.effects.updateTimerUI && this.effects.updateTimerUI(this.timerState, this.timerCtx);
    var self=this;
    if(this.hasUnsavedChanges && this.hasUnsavedChanges()){
      this.log('AUTO_SAVE_REQUIRED', 'Auto-saving before timer start');
      this.ctx.timerPayload = payload;
      this.transition('AUTO_SAVE_FOR_TIMER', payload);
      return;
    }
    return this.effects.startTimer(payload.taskId, payload.title).then(function(res){
      self.timerCtx = { taskId: payload.taskId, postId: res.postId, sessionIndex: res.sessionIndex, startUtc: res.startUtc };
      self.timerState='RUNNING';
      self.effects.updateTimerUI && self.effects.updateTimerUI(self.timerState, self.timerCtx);
      self.log('TIMER_STARTED', self.timerCtx);
    }).catch(function(err){
      self.timerState='IDLE';
      self.effects.updateTimerUI && self.effects.updateTimerUI(self.timerState, self.timerCtx);
      self.effects.showError && self.effects.showError(err);
      self.log('START_FAILED', err);
    });
  };

  TaskFSM.prototype._stopTimer = function(){
    this.timerState='STOPPING';
    var self=this;
    return this.effects.stopTimer(this.timerCtx.postId).then(function(){
      self.timerState='IDLE';
      self.effects.updateTimerUI && self.effects.updateTimerUI(self.timerState, self.timerCtx);
      self.log('TIMER_STOPPED');
      self.timerCtx={ taskId:null, postId:null, sessionIndex:null, startUtc:null };
    }).catch(function(err){
      self.timerState='RUNNING';
      self.effects.showError && self.effects.showError(err);
      self.log('STOP_FAILED', err);
    });
  };

  TaskFSM.prototype.rehydrate = function(){
    var self=this;
    return (this.effects.rehydrate? this.effects.rehydrate(): Promise.resolve({running:false}))
      .then(function(r){
        if(r.running){
          self.timerState='RUNNING';
          self.timerCtx={ taskId:r.taskId||null, postId:r.postId, sessionIndex:r.sessionIndex, startUtc:r.startUtc };
        } else {
          self.timerState='IDLE';
          self.timerCtx={ taskId:null, postId:null, sessionIndex:null, startUtc:null };
        }
        if(self.effects.updateTimerUI){
          self.effects.updateTimerUI(self.timerState, self.timerCtx);
        }
        self.log('REHYDRATED', self.timerState, self.timerCtx);
      }).catch(function(err){
        self.timerState='IDLE';
        self.timerCtx={ taskId:null, postId:null, sessionIndex:null, startUtc:null };
        if(self.effects.updateTimerUI){ self.effects.updateTimerUI(self.timerState, self.timerCtx); }
        self.log('REHYDRATION_FAILED', err);
      });
  };

  root.PTT = root.PTT || {};
  root.PTT.TaskFSM = TaskFSM;
})(window);
