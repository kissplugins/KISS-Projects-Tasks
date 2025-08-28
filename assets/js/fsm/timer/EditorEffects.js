(function(root){
  var ajax = function(action, data){
    data = data || {}; data.action = action; data.nonce = (root.ptt_ajax_object && root.ptt_ajax_object.nonce) || '';
    return new Promise(function(resolve, reject){
      jQuery.post((root.ptt_ajax_object && root.ptt_ajax_object.ajax_url) || '', data).done(function(resp){
        if(resp && resp.success){ resolve(resp.data || {}); } else { reject(resp && resp.data ? resp.data.message : 'Request failed'); }
      }).fail(function(xhr){ reject(xhr && xhr.responseText || 'Network error'); });
    });
  };
  function EditorEffects(opts){ this.opts = opts||{}; }
  function fsmLog(){ try{ if(root.PTT_EditorFSM && root.PTT_EditorFSM.log){ root.PTT_EditorFSM.log.apply(root.PTT_EditorFSM, arguments); } }catch(e){} }
  // Post Editor uses session-level start/stop by row index
  EditorEffects.prototype.startTimer = function(params){
    var postId = (params && params.postId) || (window.jQuery && jQuery('#post_ID').val()) || null;
    var index = (params && params.sessionIndex != null) ? params.sessionIndex : 0;
    // If index not provided, find first session row without a start time; fallback to 0
    if (params == null || params.sessionIndex == null){
      var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
      $rows.each(function(i){ var v = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); if(!v){ index = i; return false; } });
    }
    fsmLog('AJAX -> ptt_start_session_timer', JSON.stringify({ post_id: postId, row_index: index }));
    return ajax('ptt_start_session_timer', { post_id: postId, row_index: index }).then(function(resp){
      fsmLog('AJAX ✓ ptt_start_session_timer', JSON.stringify({ start_time: resp.start_time, post_id: postId, row_index: index }));
      // Mirror into ACF inputs immediately so UI matches DB without reload
      try{
        var $row = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row').eq(index);
        $row.find('[data-key="field_ptt_session_start_time"] input').val(resp.start_time).trigger('change');
        $row.find('[data-key="field_ptt_session_stop_time"] input').val('').trigger('change');
      }catch(e){}
      return { postId: postId, sessionIndex: index, startUtc: resp.start_time };
    }).catch(function(err){ fsmLog('AJAX ✗ ptt_start_session_timer', String(err)); throw err; });
  };
  EditorEffects.prototype.stopTimer  = function(params){
    var postId = (params && params.postId) || (window.jQuery && jQuery('#post_ID').val()) || null;
    var index = (params && params.sessionIndex != null) ? params.sessionIndex : 0;
    if (params == null || params.sessionIndex == null){
      // Determine index from visible running row
      var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
      $rows.each(function(i){ var start = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); var stop = jQuery(this).find('[data-key="field_ptt_session_stop_time"] input').val(); if(start && !stop){ index = i; return false; } });
    }
    fsmLog('AJAX -> ptt_stop_session_timer', JSON.stringify({ post_id: postId, row_index: index }));
    return ajax('ptt_stop_session_timer', { post_id: postId, row_index: index }).then(function(resp){
      fsmLog('AJAX ✓ ptt_stop_session_timer', JSON.stringify({ stop_time: resp.stop_time, duration: resp.duration, post_id: postId, row_index: index }));
      // Mirror into ACF inputs immediately
      try{
        var $row = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row').eq(index);
        $row.find('[data-key="field_ptt_session_stop_time"] input').val(resp.stop_time).trigger('change');
        if(typeof resp.duration !== 'undefined'){
          $row.find('[data-key="field_ptt_session_calculated_duration"] input').val(resp.duration).trigger('change');
        }
      }catch(e){}
      return { stoppedUtc: resp.stop_time, sessionIndex: index, postId: postId, duration: resp.duration };
    }).catch(function(err){ fsmLog('AJAX ✗ ptt_stop_session_timer', String(err)); throw err; });
  };
  EditorEffects.prototype.rehydrate  = function(){
    // If a row has start and no stop, consider running
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    var running = false, postId = jQuery('#post_ID').val()||null, sessionIndex = null, startUtc=null;
    $rows.each(function(i){ var s = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); var e = jQuery(this).find('[data-key="field_ptt_session_stop_time"] input').val(); if(s && !e){ running=true; sessionIndex=i; startUtc=s; return false; } });
    return Promise.resolve(running ? { running:true, postId:postId, sessionIndex:sessionIndex, startUtc:startUtc } : { running:false });
  };
  // Phase 1: Real session validation and saving
  EditorEffects.prototype.validateSession = function(ctx){
    fsmLog('VALIDATE -> validateSession', JSON.stringify({ postId: ctx.postId, sessionIndex: ctx.sessionIndex, pendingChanges: ctx.pendingChanges }));

    var errors = [];
    var changes = ctx.pendingChanges || {};

    // Basic validation rules
    if (changes.session_title !== undefined && changes.session_title.trim() === '') {
      errors.push('Session title cannot be empty');
    }

    if (changes.session_start_time !== undefined && changes.session_stop_time !== undefined) {
      var start = new Date(changes.session_start_time);
      var stop = new Date(changes.session_stop_time);
      if (start >= stop) {
        errors.push('Start time must be before stop time');
      }
    }

    var isValid = errors.length === 0;
    fsmLog('VALIDATE ✓ validateSession', JSON.stringify({ valid: isValid, errors: errors }));

    return Promise.resolve({ valid: isValid, errors: errors });
  };

  EditorEffects.prototype.saveSession = function(ctx){
    fsmLog('SAVE -> saveSession', JSON.stringify({ postId: ctx.postId, sessionIndex: ctx.sessionIndex, pendingChanges: ctx.pendingChanges }));

    if (!ctx.postId || !ctx.pendingChanges || Object.keys(ctx.pendingChanges).length === 0) {
      fsmLog('SAVE ✓ saveSession (no changes)', 'Nothing to save');
      return Promise.resolve({ ok: true });
    }

    // Apply pending changes to ACF fields immediately (optimistic update)
    try {
      var sessionIndex = ctx.sessionIndex || 0;
      var $row = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row').eq(sessionIndex);

      Object.keys(ctx.pendingChanges).forEach(function(fieldName) {
        var value = ctx.pendingChanges[fieldName];
        var $field = $row.find('[data-key="field_ptt_' + fieldName + '"] input, [data-key="field_ptt_' + fieldName + '"] textarea');
        if ($field.length) {
          $field.val(value).trigger('change');
          fsmLog('SAVE -> Applied field', fieldName + ' = ' + value);
        }
      });

      // Trigger ACF save (this will persist to database)
      if (jQuery('#publish').length) {
        // Use ACF's built-in save mechanism
        fsmLog('SAVE -> Triggering ACF save via publish button');
        jQuery('#publish').trigger('click');
      }

      fsmLog('SAVE ✓ saveSession', 'Changes applied to ACF fields');
      return Promise.resolve({ ok: true });

    } catch (error) {
      fsmLog('SAVE ✗ saveSession', 'Error: ' + error.message);
      return Promise.reject(new Error('Failed to save session: ' + error.message));
    }
  };
  // UI hooks used by EditorFSM
  EditorEffects.prototype.updateUI = function(state, ctx){ /* no-op in Phase 1; debug panel shows state */ };
  // Lightweight introspection used by FSM guards
  EditorEffects.prototype.getSessionRowState = function(params){
    var index = (params && params.sessionIndex != null) ? params.sessionIndex : 0;
    var $row = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row').eq(index);
    if(!$row.length) return 'UNKNOWN';
    var s = $row.find('[data-key="field_ptt_session_start_time"] input').val();
    var e = $row.find('[data-key="field_ptt_session_stop_time"] input').val();
    if(!s && !e) return 'EMPTY';
    if(s && !e) return 'RUNNING';
    return 'COMPLETED';
  };
  EditorEffects.prototype.updateTimerUI = function(state, ctx){
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    if(state==='RUNNING'){
      var idx = (ctx && ctx.sessionIndex != null) ? ctx.sessionIndex : (ctx && ctx.runningSessionIndex != null ? ctx.runningSessionIndex : 0);
      var $row = $rows.eq(idx);
      var $controls = $row.find('.ptt-session-controls');
      $row.find('.ptt-session-start').hide();
      $row.find('.ptt-session-active-timer').css('display','inline-flex');
      // Start/update the shared live timer with the UTC start timestamp
      try { if (window.PTT_manageLiveTimer) { window.PTT_manageLiveTimer($controls, ctx && ctx.startUtc); } } catch(e){}
    } else {
      // Ensure any existing intervals are cleared and UI reset (across all rows)
      try {
        if (window.PTT_stopLiveTimer) {
          $rows.find('.ptt-session-controls').each(function(){ window.PTT_stopLiveTimer(jQuery(this)); });
        }
      } catch(e){}
      $rows.find('.ptt-session-start').show();
      $rows.find('.ptt-session-active-timer').hide();
    }
  };

  EditorEffects.prototype.showInfo = function(msg){ if(root.console) console.info('[PTT EditorEffects]', msg); };
  EditorEffects.prototype.showError = function(msg){ if(root.console) console.warn('[PTT EditorEffects]', msg); };
  root.PTT = root.PTT || {}; root.PTT.EditorEffects = EditorEffects;
})(window);

