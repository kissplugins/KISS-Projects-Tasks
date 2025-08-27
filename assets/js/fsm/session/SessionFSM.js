(function(root){
  /**
   * SessionFSM - Manages session lifecycle for CPT Task Editor
   * States: IDLE, CREATING, EDITING, VALIDATING, SAVING, ERROR
   * Coordinates with TimerFSM to prevent conflicts
   */
  function SessionFSM(effects, opts){
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
    this.timerFSM = opts.timerFSM || null; // Reference to coordinate with TimerFSM
  }

  SessionFSM.prototype.log = function(){ 
    if(this.debug && root.console){ 
      console.log.apply(console, ['[PTT SessionFSM]'].concat([].slice.call(arguments))); 
    } 
  };

  SessionFSM.prototype.transition = function(event, payload){
    const s = this.state; 
    const e = event;
    
    // State transition table
    if(s==='IDLE' && e==='CREATE_SESSION') return this._createSession(payload);
    if(s==='IDLE' && e==='EDIT_SESSION') return this._editSession(payload);
    if(s==='CREATING' && e==='SESSION_CREATED') return this._afterCreate(payload);
    if(s==='CREATING' && e==='CREATE_FAILED') return this._createFailed(payload);
    if(s==='EDITING' && e==='FIELD_CHANGED') return this._fieldChanged(payload);
    if(s==='EDITING' && e==='VALIDATE_SESSION') return this._validateSession(payload);
    if(s==='EDITING' && e==='SAVE_SESSION') return this._saveSession(payload);
    if(s==='VALIDATING' && e==='VALIDATION_PASSED') return this._validationPassed(payload);
    if(s==='VALIDATING' && e==='VALIDATION_FAILED') return this._validationFailed(payload);
    if(s==='SAVING' && e==='SESSION_SAVED') return this._sessionSaved(payload);
    if(s==='SAVING' && e==='SAVE_FAILED') return this._saveFailed(payload);
    if(s==='ERROR' && e==='RETRY') return this._retry(payload);
    if(s==='ERROR' && e==='RESET') return this._reset();
    if(e==='SESSION_ERROR') { this.state='ERROR'; this.log('ERROR', payload); return; }
    
    this.log('Ignored', e, 'in', s);
  };

  SessionFSM.prototype._createSession = function(payload){
    this.state = 'CREATING';
    this.ctx.postId = payload.postId;
    var self = this;
    
    // Check if timer is running - prevent creating new session if timer active
    if(this.timerFSM && this.timerFSM.state === 'RUNNING'){
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

  SessionFSM.prototype._editSession = function(payload){
    this.state = 'EDITING';
    this.ctx.sessionIndex = payload.sessionIndex;
    this.ctx.postId = payload.postId;
    this.ctx.isDirty = false;
    this.ctx.pendingChanges = {};
    this.ctx.validationErrors = [];
    
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('EDITING_SESSION', this.ctx);
  };

  SessionFSM.prototype._fieldChanged = function(payload){
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
  };

  SessionFSM.prototype._validateSession = function(payload){
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

  SessionFSM.prototype._validationPassed = function(payload){
    this.state = 'EDITING';
    this.ctx.validationErrors = [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('VALIDATION_PASSED');
  };

  SessionFSM.prototype._validationFailed = function(payload){
    this.state = 'EDITING';
    this.ctx.validationErrors = payload.errors || [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.effects.showError && this.effects.showError('Validation failed: ' + this.ctx.validationErrors.join(', '));
    this.log('VALIDATION_FAILED', this.ctx.validationErrors);
  };

  SessionFSM.prototype._saveSession = function(payload){
    this.state = 'SAVING';
    var self = this;
    
    return this.effects.saveSession(this.ctx).then(function(result){
      self.ctx.isDirty = false;
      self.ctx.pendingChanges = {};
      self.state = 'IDLE';
      self.effects.updateSessionUI && self.effects.updateSessionUI(self.state, self.ctx);
      self.log('SESSION_SAVED', result);
    }).catch(function(err){
      self.state = 'ERROR';
      self.ctx.validationErrors = [err.message || err];
      self.effects.showError && self.effects.showError(err);
      self.log('SAVE_FAILED', err);
    });
  };

  SessionFSM.prototype._retry = function(payload){
    this.state = 'IDLE';
    this.ctx.validationErrors = [];
    this.effects.updateSessionUI && this.effects.updateSessionUI(this.state, this.ctx);
    this.log('RETRY');
  };

  SessionFSM.prototype._reset = function(){
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

  // Helper methods
  SessionFSM.prototype.canCreateSession = function(){
    return this.state === 'IDLE' && (!this.timerFSM || this.timerFSM.state !== 'RUNNING');
  };

  SessionFSM.prototype.canEditSession = function(){
    return this.state === 'IDLE';
  };

  SessionFSM.prototype.canSaveSession = function(){
    return this.state === 'EDITING' && this.ctx.isDirty && this.ctx.validationErrors.length === 0;
  };

  SessionFSM.prototype.hasUnsavedChanges = function(){
    return this.ctx.isDirty;
  };

  root.PTT = root.PTT || {}; 
  root.PTT.SessionFSM = SessionFSM;
})(window);
