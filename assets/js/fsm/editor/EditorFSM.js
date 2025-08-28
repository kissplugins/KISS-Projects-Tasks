(function(root){
  function debounce(fn, wait){ var t=null; return function(){ var a=arguments; clearTimeout(t); t=setTimeout(function(){ fn.apply(null,a); }, wait||200); }; }

  // Phase 1: Minimal unified EditorFSM (timer + basic editing/save skeleton)
  function EditorFSM(effects, opts){
    this.effects = effects || {};
    this.debug = (opts && opts.debug) || false;
    this.state = 'IDLE';
    this.ctx = {
      postId: null,
      sessionIndex: null,
      running: false,
      runningSessionIndex: null,
      runningStartUtc: null,
      isDirty: false,
      pendingChanges: {},
      validationErrors: [],
      op: null
    };
    this._queued = null; // drop-or-latest queue size = 1
    this._watchdog = null;
    this._info = debounce(this._info.bind(this), 2000);
  }

  EditorFSM.prototype.log = function(){ if(this.debug && root.console){ console.log.apply(console, ['[PTT EditorFSM]'].concat([].slice.call(arguments))); } };
  EditorFSM.prototype._info = function(msg){ if(this.effects && this.effects.showInfo){ this.effects.showInfo(msg); } };
  EditorFSM.prototype._error = function(msg){ if(this.effects && this.effects.showError){ this.effects.showError(msg); } };
  EditorFSM.prototype._updateUI = function(){ if(this.effects && this.effects.updateUI){ this.effects.updateUI(this.state, this.ctx); } if(this.effects && this.effects.updateTimerUI){ // keep timer UI in sync for Phase 1
      var s = (this.state.indexOf('TIMER.RUNNING')===0 || this.state==='TIMER.RUNNING') ? 'RUNNING' : 'IDLE';
      this.effects.updateTimerUI(s, { sessionIndex:this.ctx.runningSessionIndex, postId:this.ctx.postId, startUtc:this.ctx.runningStartUtc });
    }
  };

  EditorFSM.prototype._startWatchdog = function(kind, ms){ var self=this; this._clearWatchdog(); this.ctx.op = { name: kind, startedAt: Date.now() }; this._watchdog = setTimeout(function(){ self.log('Watchdog timeout for', kind); self._error(kind+' operation timed out. Please try again.'); // recover
      if(self.ctx.isDirty || self.ctx.sessionIndex!=null){ self.state='EDITING'; } else { self.state='IDLE'; }
      self.ctx.op = null; self._updateUI(); }, ms||20000); };
  EditorFSM.prototype._clearWatchdog = function(){ if(this._watchdog){ clearTimeout(this._watchdog); this._watchdog=null; } this.ctx.op=null; };

  EditorFSM.prototype._enqueueOrIgnore = function(event, payload){
    // Only one long-running op at a time
    if(this.ctx.op){ this._queued = { event:event, payload:payload }; this.log('Op in-flight; queued latest', event); return true; }
    return false;
  };
  EditorFSM.prototype._drainQueue = function(){ var q = this._queued; this._queued=null; if(q){ this.log('Draining queued', q.event); this.send(q.event, q.payload); } };

  EditorFSM.prototype.rehydrate = function(){
    var self=this; var fx=this.effects;
    return (fx && fx.rehydrate ? fx.rehydrate() : Promise.resolve({ running:false }))
      .then(function(r){
        if(r && r.running){
          self.state = 'TIMER.RUNNING';
          self.ctx.postId = r.postId||self.ctx.postId;
          self.ctx.running = true;
          self.ctx.runningSessionIndex = r.sessionIndex;
          self.ctx.runningStartUtc = r.startUtc;
        } else {
          self.state = 'IDLE';
          self.ctx.running = false; self.ctx.runningSessionIndex=null; self.ctx.runningStartUtc=null;
        }
        self._updateUI(); self.log('REHYDRATED', self.state, self.ctx);
      });
  };

  EditorFSM.prototype.send = function(event, payload){
    var s=this.state; var e=event; this.log('EVENT', e, 'in', s, payload||'');
    // Ignored-event debounced messaging (Guardrail C)
    var polite = function(state){
      if(state.indexOf('OPERATING.VALIDATING')===0 || state.indexOf('OPERATING.SAVING')===0 || state.indexOf('OPERATING.AUTO_SAVING')===0){ return 'Working on your changes… Please wait.'; }
      if(state.indexOf('TIMER.STARTING')===0 || state.indexOf('TIMER.STOPPING')===0){ return 'Timer is changing state… Please wait.'; }
      if(state==='EDITING'){ return 'Please complete or cancel your current edit before doing that.'; }
      if(state==='TIMER.RUNNING'){ return 'Stop the current timer before performing that action.'; }
      return 'Action not available right now.';
    };

    switch(e){
      case 'EDIT_FIELD':
        // Update context with field changes
        this.ctx.isDirty = true;
        this.ctx.postId = (payload && payload.postId) || this.ctx.postId;
        this.ctx.sessionIndex = (payload && payload.sessionIndex != null) ? payload.sessionIndex : this.ctx.sessionIndex;
        this.ctx.pendingChanges = Object.assign({}, this.ctx.pendingChanges, (payload && payload.changes) || {});
        this.state = 'EDITING.DIRTY';
        this._updateUI();
        this.log('Field edited:', payload && payload.fieldName, '=', payload && payload.value);
        return;

      case 'VALIDATE':
        if(this._enqueueOrIgnore(e, payload)) return;
        this.state = 'OPERATING.VALIDATING'; this._startWatchdog('VALIDATING', 20000);
        var self=this;
        (this.effects.validateSession ? this.effects.validateSession(this.ctx) : Promise.resolve({ valid:true }))
          .then(function(res){ self._clearWatchdog(); self.ctx.validationErrors = (res && res.errors)||[]; self.state='EDITING'; self._updateUI(); })
          .catch(function(err){ self._clearWatchdog(); self.state='EDITING'; self._error(err && err.message ? err.message : String(err)); self._updateUI(); })
          .finally(function(){ self._drainQueue(); });
        return;

      case 'SAVE':
        if(this._enqueueOrIgnore(e, payload)) return;
        this.state = 'OPERATING.SAVING'; this._startWatchdog('SAVING', 30000);
        var selfS=this;
        (this.effects.saveSession ? this.effects.saveSession(this.ctx) : Promise.resolve({ ok:true }))
          .then(function(){ selfS._clearWatchdog(); selfS.ctx.isDirty=false; selfS.ctx.pendingChanges={}; selfS.state='IDLE'; selfS._updateUI(); })
          .catch(function(err){ selfS._clearWatchdog(); selfS.state='EDITING'; selfS._error(err && err.message ? err.message : String(err)); selfS._updateUI(); })
          .finally(function(){ selfS._drainQueue(); });
        return;

      case 'START_TIMER':
        // Bridge AUTO_SAVING when dirty
        var selfT=this; var doStart=function(){
          if(selfT._enqueueOrIgnore('START_TIMER', payload)) return;
          selfT.state='TIMER.STARTING'; selfT._startWatchdog('TIMER.STARTING', 20000);
          var postId = (payload && (payload.postId||payload.taskId)) || selfT.ctx.postId;
          var sessionIndex = (payload && payload.sessionIndex != null) ? payload.sessionIndex : selfT.ctx.runningSessionIndex;
          // Guard: do not start on a non-empty session row
          var rowState = (selfT.effects.getSessionRowState ? selfT.effects.getSessionRowState({ postId: postId, sessionIndex: sessionIndex }) : 'UNKNOWN');
          if (rowState && rowState !== 'EMPTY') { selfT._clearWatchdog(); selfT.state = selfT.ctx.isDirty ? 'EDITING.DIRTY' : 'EDITING.SAVED'; selfT._error('This session already has time recorded. Please add a new session row instead.'); selfT._updateUI(); return Promise.resolve(); }
          return (selfT.effects.startTimer ? selfT.effects.startTimer({ postId: postId, sessionIndex: sessionIndex, title: (payload&&payload.title)||null }) : Promise.reject('No startTimer'))
            .then(function(res){ selfT._clearWatchdog(); selfT.state='TIMER.RUNNING'; selfT.ctx.running=true; selfT.ctx.postId=res.postId||postId; selfT.ctx.runningSessionIndex=(res.sessionIndex!=null?res.sessionIndex:sessionIndex); selfT.ctx.runningStartUtc=res.startUtc||selfT.ctx.runningStartUtc; selfT._updateUI(); })
            .catch(function(err){ selfT._clearWatchdog(); selfT.state='IDLE'; selfT._error(err && err.message ? err.message : String(err)); selfT._updateUI(); })
            .finally(function(){ selfT._drainQueue(); }); };
        if(this.ctx.isDirty){
          this.state='OPERATING.AUTO_SAVING'; this._startWatchdog('AUTO_SAVING', 20000);
          var selfA=this;
          (this.effects.saveSession ? this.effects.saveSession(this.ctx) : Promise.resolve({ ok:true }))
            .then(function(){ selfA._clearWatchdog(); selfA.ctx.isDirty=false; selfA.ctx.pendingChanges={}; return doStart(); })
            .catch(function(err){ selfA._clearWatchdog(); selfA.state='EDITING'; selfA._error(err && err.message ? err.message : String(err)); selfA._updateUI(); })
            .finally(function(){ /* queue drained by doStart or on error */ });
        } else { doStart(); }
        return;

      case 'STOP_TIMER':
        if(this._enqueueOrIgnore(e, payload)) return;
        this.state='TIMER.STOPPING'; this._startWatchdog('TIMER.STOPPING', 20000);
        var selfP=this;
        var idx = (payload && payload.sessionIndex != null) ? payload.sessionIndex : this.ctx.runningSessionIndex;
        return (this.effects.stopTimer ? this.effects.stopTimer({ postId: this.ctx.postId, sessionIndex: idx }) : Promise.reject('No stopTimer'))
          .then(function(){ selfP._clearWatchdog(); selfP.state='IDLE'; selfP.ctx.running=false; selfP.ctx.runningSessionIndex=null; selfP.ctx.runningStartUtc=null; selfP._updateUI(); })
          .catch(function(err){ selfP._clearWatchdog(); selfP.state='TIMER.RUNNING'; selfP._error(err && err.message ? err.message : String(err)); selfP._updateUI(); })
          .finally(function(){ selfP._drainQueue(); });
    }

    // Default: politely ignore
    this.log('Ignored', e, 'in', s); this._info(polite(s));
  };

  root.PTT = root.PTT || {}; root.PTT.EditorFSM = EditorFSM;
})(window);

