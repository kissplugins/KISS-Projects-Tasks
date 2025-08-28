(function(root){
  function TimerFSM(effects, opts){
    this.effects = effects || {};
    this.state = 'IDLE';
    this.ctx = { taskId: null, postId: null, sessionIndex: null, startUtc: null };
    this.debug = (opts && opts.debug) || false;
    this.sessionFSM = opts.sessionFSM || null; // Reference to coordinate with SessionFSM
  }
  TimerFSM.prototype.log = function(){ if(this.debug && root.console){ console.log.apply(console, ['[PTT TimerFSM]'].concat([].slice.call(arguments))); } };
  TimerFSM.prototype.transition = function(event, payload){
    const s = this.state; const e = event;
    if(s==='IDLE' && e==='START_TIMER') return this._start(payload);
    if(s==='STARTING' && (e==='TIMER_STARTED' || e==='START_FAILED')) return this._afterStart(payload);
    if(s==='RUNNING' && e==='STOP_TIMER') return this._stop(payload);
    if(s==='STOPPING' && (e==='TIMER_STOPPED' || e==='STOP_FAILED')) return this._afterStop(payload);
    if(e==='TIMER_ERROR') { this.state='ERROR'; this.log('ERROR', payload); return; }
    this.log('Ignored', e, 'in', s);
  };
  TimerFSM.prototype._start = function(payload){
    this.state='STARTING';
    this.effects.updateTimerUI && this.effects.updateTimerUI(this.state, this.ctx);
    var self=this;

    // FSM-centric approach: Check if SessionFSM needs to auto-save first
    if(this.sessionFSM && this.sessionFSM.hasUnsavedChanges()){
      this.log('AUTO_SAVE_REQUIRED', 'Delegating to SessionFSM for auto-save');
      // Delegate to SessionFSM for auto-save, which will call back to start timer
      this.sessionFSM.transition('AUTO_SAVE_FOR_TIMER', payload);
      return; // SessionFSM will handle the rest
    }

    // No auto-save needed, proceed directly with timer start
    return this.effects.startTimer(payload.taskId, payload.title).then(function(res){
      self.ctx = { taskId: payload.taskId, postId: res.postId, sessionIndex: res.sessionIndex, startUtc: res.startUtc };
      self.state='RUNNING'; self.effects.updateTimerUI && self.effects.updateTimerUI(self.state, self.ctx); self.log('TIMER_STARTED', self.ctx);
    }).catch(function(err){ self.state='IDLE'; self.effects.updateTimerUI && self.effects.updateTimerUI(self.state, self.ctx); self.effects.showError && self.effects.showError(err); self.log('START_FAILED', err); });
  };
  TimerFSM.prototype._stop = function(){
    this.state='STOPPING'; var self=this;
    return this.effects.stopTimer(this.ctx.postId).then(function(res){ self.state='IDLE'; self.effects.updateTimerUI && self.effects.updateTimerUI(self.state, self.ctx); self.log('TIMER_STOPPED'); self.ctx={ taskId:null, postId:null, sessionIndex:null, startUtc:null }; })
      .catch(function(err){ self.state='RUNNING'; self.effects.showError && self.effects.showError(err); self.log('STOP_FAILED', err); });
  };
  TimerFSM.prototype.rehydrate = function(){
    var self=this;
    console.log('PTT FSM: Starting rehydration...');
    return (this.effects.rehydrate? this.effects.rehydrate(): Promise.resolve({running:false}))
      .then(function(r){
        console.log('PTT FSM: Rehydration result:', r);
        if(r.running){
          self.state='RUNNING';
          self.ctx={ taskId:r.taskId||null, postId:r.postId, sessionIndex:r.sessionIndex, startUtc:r.startUtc };
          console.log('PTT FSM: Set to RUNNING state with ctx:', self.ctx);
        } else {
          self.state='IDLE';
          self.ctx={ taskId:null, postId:null, sessionIndex:null, startUtc:null };
          console.log('PTT FSM: Set to IDLE state');
        }
        if(self.effects.updateTimerUI) {
          console.log('PTT FSM: Updating timer UI with state:', self.state);
          self.effects.updateTimerUI(self.state, self.ctx);
        }
        self.log('REHYDRATED', self.state, self.ctx);
      }).catch(function(err){
        console.error('PTT FSM: Rehydration error:', err);
        self.state='IDLE';
        self.ctx={ taskId:null, postId:null, sessionIndex:null, startUtc:null };
        if(self.effects.updateTimerUI) {
          self.effects.updateTimerUI(self.state, self.ctx);
        }
        self.log('REHYDRATION_FAILED', err);
      });
  };
  root.PTT = root.PTT || {}; root.PTT.TimerFSM = TimerFSM;
})(window);

