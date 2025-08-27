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
    var title = '';
    $rows.each(function(i){
      var $row = jQuery(this);
      var v = $row.find('[data-key="field_ptt_session_start_time"] input').val();
      if(!v){ index = i; title = ($row.find('[data-key="field_ptt_session_title"] input').val()||'').trim(); return false; }
    });
    // If title is blank, auto-generate and set it on the input before sending
    if(!title){
      var now = new Date();
      var mm = ('0'+(now.getMonth()+1)).slice(-2);
      var dd = ('0'+now.getDate()).slice(-2);
      var yy = String(now.getFullYear()).slice(-2);
      var HH = ('0'+now.getHours()).slice(-2);
      var MM = ('0'+now.getMinutes()).slice(-2);
      title = 'Session ' + mm + '-' + dd + '-' + yy + ' ' + HH + ':' + MM;
      var $rowSet = $rows.eq(index);
      $rowSet.find('[data-key="field_ptt_session_title"] input').val(title).trigger('change');
    }
    return ajax('ptt_start_session_timer', { post_id: taskId, row_index: index, session_title: title })
      .then(function(data){
        // Normalize result for FSM: { startUtc, postId, sessionIndex }
        return { startUtc: data.start_time, postId: taskId, sessionIndex: (typeof data.row_index!=='undefined'? data.row_index : index) };
      });
  };
  EditorEffects.prototype.stopTimer  = function(postId){
    // Determine index from visible running row
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    var index = 0;
    $rows.each(function(i){ var start = jQuery(this).find('[data-key="field_ptt_session_start_time"] input').val(); var stop = jQuery(this).find('[data-key="field_ptt_session_stop_time"] input').val(); if(start && !stop){ index = i; return false; } });
    return ajax('ptt_stop_session_timer', { post_id: postId, row_index: index }).then(function(data){
      // Persist stop/duration to ACF inputs for this row
      var $row = $rows.eq(index);
      $row.find('[data-key="field_ptt_session_stop_time"] input').val(data.stop_time).trigger('change');
      $row.find('[data-key="field_ptt_session_calculated_duration"] input').val(data.duration).trigger('change');
      // Stop the live timer display if available
      if (window.PTT && typeof window.PTT.stopLiveTimer === 'function') {
        window.PTT.stopLiveTimer($row.find('.ptt-session-controls'));
      }
      // Trigger Update/Save to persist totals and fields
      var $saveButton = jQuery('#publish');
      if ($saveButton.length && $saveButton.is(':enabled')) {
        setTimeout(function(){ $saveButton.trigger('click'); }, 150);
      }
      return data; // allow FSM to proceed
    });
  };
  EditorEffects.prototype.rehydrate  = function(){
    // Ask server for user's active session; only activate if it's this post
    var postId = jQuery('#post_ID').val()||null;
    return new Promise(function(resolve){
      jQuery.post((root.ptt_ajax_object && root.ptt_ajax_object.ajax_url)||'', {
        action: 'ptt_get_active_session_for_user', nonce: (root.ptt_ajax_object && root.ptt_ajax_object.nonce)||''
      }).done(function(resp){
        if(resp && resp.success && resp.data){
          if(resp.data.running && postId && parseInt(resp.data.post_id,10)===parseInt(postId,10)){
            resolve({ running:true, postId: resp.data.post_id, sessionIndex: resp.data.session_index, startUtc: resp.data.start_time });
            return;
          }
        }
        resolve({ running:false });
      }).fail(function(){ resolve({ running:false }); });
    });
  };
  EditorEffects.prototype.updateTimerUI = function(state, ctx){
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    if(state==='RUNNING'){
      var $row = $rows.eq(ctx.sessionIndex);
      $row.find('.ptt-session-start').hide();
      $row.find('.ptt-session-active-timer').css('display','inline-flex');
      $row.find('.ptt-session-message').hide();
      // Ensure the start input reflects server UTC immediately for consistency
      $row.find('[data-key="field_ptt_session_start_time"] input').val(ctx.startUtc).trigger('change');
      // Kick off live ticking using shared helper
      if (root.PTT && typeof root.PTT.manageLiveTimer === 'function') {
        root.PTT.manageLiveTimer($row.find('.ptt-session-controls'), ctx.startUtc);
      }
    } else {
      if (root.PTT && typeof root.PTT.stopLiveTimer === 'function') {
        $rows.each(function(){ root.PTT.stopLiveTimer(jQuery(this).find('.ptt-session-controls')); });
      }
      // Handle all session states properly
      $rows.each(function(){
        var $row = jQuery(this);
        var startVal = $row.find('[data-key="field_ptt_session_start_time"] input').val();
        var stopVal = $row.find('[data-key="field_ptt_session_stop_time"] input').val();
        var override = $row.find('[data-key="field_ptt_session_manual_override"] input').prop('checked');
        var calcDur = parseFloat($row.find('[data-key="field_ptt_session_calculated_duration"] input').val() || '0');
        var manualDur = parseFloat($row.find('[data-key="field_ptt_session_manual_duration"] input').val() || '0');
        var $startBtn = $row.find('.ptt-session-start');
        var $activeTimer = $row.find('.ptt-session-active-timer');
        var $message = $row.find('.ptt-session-message');
        var $defaultTimer = $row.find('.ptt-session-elapsed-time');

        var hasManualTime = override && !isNaN(manualDur) && manualDur > 0;
        var hasCalcTime = !isNaN(calcDur) && calcDur > 0;
        var hasSavedTime = hasManualTime || hasCalcTime;

        if (startVal && stopVal) {
          // Completed session
          $startBtn.hide();
          $activeTimer.hide();
          if ($defaultTimer.length) $defaultTimer.hide();
          var duration = calcDur.toFixed(2);
          $message.text('Session completed. Duration: ' + duration + ' hrs.').show();
        } else if (hasSavedTime) {
          // Has manual time but no start/stop
          $startBtn.hide();
          $activeTimer.hide();
          if ($defaultTimer.length) $defaultTimer.hide();
          $message.hide();
        } else {
          // Available for new session
          $startBtn.show();
          $activeTimer.hide();
          $message.hide();
          if ($defaultTimer.length) $defaultTimer.show();
          if (override) {
            $startBtn.prop('disabled', true).attr('title', 'Manual time entry is enabled for this session.');
          } else {
            $startBtn.prop('disabled', false).removeAttr('title');
          }
        }
      });
    }
  };

  // Initialize UI state for all rows on load
  EditorEffects.prototype.initializeAllRows = function(){
    this.updateTimerUI('IDLE', {});
  };
  EditorEffects.prototype.showError = function(msg){ if(root.console) console.warn('[PTT EditorEffects]', msg); };
  root.PTT = root.PTT || {}; root.PTT.EditorEffects = EditorEffects;
})(window);

