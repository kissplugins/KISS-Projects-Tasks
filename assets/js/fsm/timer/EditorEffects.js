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
  // Post Editor uses session-level start/stop by row index
  EditorEffects.prototype.startTimer = function(taskId){
    // Find first session row without a start time; fallback to index 0
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    var index = 0;
    $rows.each(function(i){ var v = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); if(!v){ index = i; return false; } });
    return ajax('ptt_start_session_timer', { post_id: taskId, row_index: index });
  };
  EditorEffects.prototype.stopTimer  = function(postId){
    // Determine index from visible running row
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    var index = 0;
    $rows.each(function(i){ var start = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); var stop = jQuery(this).find('[data-key="field_ptt_session_stop_time"] input').val(); if(start && !stop){ index = i; return false; } });
    return ajax('ptt_stop_session_timer', { post_id: postId, row_index: index });
  };
  EditorEffects.prototype.rehydrate  = function(){
    // If a row has start and no stop, consider running
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    var running = false, postId = jQuery('#post_ID').val()||null, sessionIndex = null, startUtc=null;
    $rows.each(function(i){ var s = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); var e = jQuery(this).find('[data-key="field_ptt_session_stop_time"] input').val(); if(s && !e){ running=true; sessionIndex=i; startUtc=s; return false; } });
    return Promise.resolve(running ? { running:true, postId:postId, sessionIndex:sessionIndex, startUtc:startUtc } : { running:false });
  };
  EditorEffects.prototype.updateTimerUI = function(state, ctx){
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    if(state==='RUNNING'){
      var $row = $rows.eq(ctx.sessionIndex);
      $row.find('.ptt-session-start').hide();
      $row.find('.ptt-session-active-timer').css('display','inline-flex');
    } else {
      $rows.find('.ptt-session-start').show();
      $rows.find('.ptt-session-active-timer').hide();
    }
  };
  EditorEffects.prototype.showError = function(msg){ if(root.console) console.warn('[PTT EditorEffects]', msg); };
  root.PTT = root.PTT || {}; root.PTT.EditorEffects = EditorEffects;
})(window);

