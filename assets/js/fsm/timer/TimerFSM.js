(function(root){
  function TimerFSM(effects, opts){
    this.effects = effects || {};
    this.state = 'IDLE';
    this.ctx = { taskId: null, postId: null, sessionIndex: null, startUtc: null };
    this.debug = (opts && opts.debug) || false;
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
    var self=this;
    return this.effects.startTimer(payload.taskId, payload.title).then(function(res){
      self.ctx = { taskId: payload.taskId, postId: res.postId, sessionIndex: res.sessionIndex, startUtc: res.startUtc };
      self.state='RUNNING'; self.effects.updateTimerUI && self.effects.updateTimerUI(self.state, self.ctx); self.log('TIMER_STARTED', self.ctx);
    }).catch(function(err){ self.state='IDLE'; self.effects.showError && self.effects.showError(err); self.log('START_FAILED', err); });
  };
  TimerFSM.prototype._stop = function(){
    this.state='STOPPING'; var self=this;
    return this.effects.stopTimer(this.ctx.postId).then(function(res){ self.state='IDLE'; self.effects.updateTimerUI && self.effects.updateTimerUI(self.state, self.ctx); self.log('TIMER_STOPPED'); self.ctx={ taskId:null, postId:null, sessionIndex:null, startUtc:null }; })
      .catch(function(err){ self.state='RUNNING'; self.effects.showError && self.effects.showError(err); self.log('STOP_FAILED', err); });
  };
  TimerFSM.prototype.rehydrate = function(){ var self=this; return (this.effects.rehydrate? this.effects.rehydrate(): Promise.resolve({running:false}))
    .then(function(r){ if(r.running){ self.state='RUNNING'; self.ctx={ taskId:r.taskId||null, postId:r.postId, sessionIndex:r.sessionIndex, startUtc:r.startUtc }; } else { self.state='IDLE'; self.ctx={ taskId:null, postId:null, sessionIndex:null, startUtc:null }; } self.effects.updateTimerUI && self.effects.updateTimerUI(self.state, self.ctx); self.log('REHYDRATED', self.state, self.ctx); }); };
  root.PTT = root.PTT || {}; root.PTT.TimerFSM = TimerFSM;
})(window);

