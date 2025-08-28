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

  // Auto-save WordPress post to ensure all changes are persisted before starting timer
  EditorEffects.prototype.autoSavePost = function(){
    return new Promise(function(resolve, reject){
      try {
        var $saveButton = jQuery('#publish, #save-post');
        var $form = jQuery('#post');

        // Check if there are unsaved changes
        var hasUnsavedChanges = false;

        // Check if WordPress thinks there are unsaved changes
        if(window.wp && window.wp.autosave && window.wp.autosave.server && window.wp.autosave.server.postChanged){
          hasUnsavedChanges = window.wp.autosave.server.postChanged();
        }

        // Check if ACF has unsaved changes
        if(window.acf && typeof window.acf.validation === 'object'){
          var $acfForm = jQuery('.acf-form, #post');
          if($acfForm.length && $acfForm.hasClass('acf-form-changed')){
            hasUnsavedChanges = true;
          }
        }

        // Check for any changed inputs
        if(!hasUnsavedChanges){
          var $changedInputs = jQuery('#post input[data-changed="1"], #post textarea[data-changed="1"], #post select[data-changed="1"]');
          hasUnsavedChanges = $changedInputs.length > 0;
        }

        if(!hasUnsavedChanges){
          // No changes to save, proceed immediately
          resolve({ saved: false, reason: 'no_changes' });
          return;
        }

        if($saveButton.length && $saveButton.is(':enabled') && !$saveButton.hasClass('disabled')){
          // Set up one-time listener for save completion
          var saveCompleted = false;
          var timeoutId = setTimeout(function(){
            if(!saveCompleted){
              saveCompleted = true;
              resolve({ saved: true, reason: 'timeout' });
            }
          }, 5000); // 5 second timeout

          // Listen for ACF save success
          if(window.acf && typeof window.acf.addAction === 'function'){
            var actionAdded = false;
            window.acf.addAction('submit_success', function(){
              if(!saveCompleted && !actionAdded){
                actionAdded = true;
                saveCompleted = true;
                clearTimeout(timeoutId);
                resolve({ saved: true, reason: 'acf_success' });
              }
            });
          }

          // Listen for WordPress save events
          $form.one('submit.pttAutoSave', function(){
            if(!saveCompleted){
              setTimeout(function(){
                if(!saveCompleted){
                  saveCompleted = true;
                  clearTimeout(timeoutId);
                  resolve({ saved: true, reason: 'wp_submit' });
                }
              }, 1000);
            }
          });

          // Trigger the save
          $saveButton.trigger('click');
        } else {
          // Save button not available, proceed anyway but warn
          console.warn('[PTT] Auto-save requested but save button not available');
          resolve({ saved: false, reason: 'no_save_button' });
        }
      } catch(err) {
        reject(err);
      }
    });
  };
  // Post Editor uses session-level start/stop by row index (FSM-centric)
  EditorEffects.prototype.startTimer = function(taskId){
    // Find the next available session using FSM logic (same as updateTimerUI)
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    var index = -1;
    var title = '';

    // Find the first available session that can accept a timer
    $rows.each(function(i){
      var $row = jQuery(this);
      var startVal = $row.find('[data-key="field_ptt_session_start_time"] input').val();
      var stopVal = $row.find('[data-key="field_ptt_session_stop_time"] input').val();
      var override = $row.find('[data-key="field_ptt_session_manual_override"] input').prop('checked');
      var calcDur = parseFloat($row.find('[data-key="field_ptt_session_calculated_duration"] input').val() || '0');
      var manualDur = parseFloat($row.find('[data-key="field_ptt_session_manual_duration"] input').val() || '0');

      var hasManualTime = override && !isNaN(manualDur) && manualDur > 0;
      var hasCalcTime = !isNaN(calcDur) && calcDur > 0;
      var hasSavedTime = hasManualTime || hasCalcTime;
      var isCompleted = startVal && stopVal;
      var isAvailable = !isCompleted && !hasSavedTime && !override;

      if(isAvailable && index === -1){
        index = i;
        title = ($row.find('[data-key="field_ptt_session_title"] input').val()||'').trim();
        return false; // Break out of each loop
      }
    });

    // Fallback to index 0 if no available session found (shouldn't happen with proper UI)
    if(index === -1){
      index = 0;
      title = ($rows.eq(0).find('[data-key="field_ptt_session_title"] input').val()||'').trim();
    }

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

    // Start the timer (auto-save is now handled by FSM coordination)
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
    console.log('PTT FSM: Rehydrating for post ID:', postId);
    return new Promise(function(resolve){
      jQuery.post((root.ptt_ajax_object && root.ptt_ajax_object.ajax_url)||'', {
        action: 'ptt_get_active_session_for_user', nonce: (root.ptt_ajax_object && root.ptt_ajax_object.nonce)||''
      }).done(function(resp){
        console.log('PTT FSM: Rehydration response:', resp);
        if(resp && resp.success && resp.data){
          if(resp.data.running && postId && parseInt(resp.data.post_id,10)===parseInt(postId,10)){
            var result = { running:true, taskId: resp.data.post_id, postId: resp.data.post_id, sessionIndex: resp.data.session_index, startUtc: resp.data.start_time };
            console.log('PTT FSM: Rehydrating to RUNNING state:', result);
            resolve(result);
            return;
          } else {
            console.log('PTT FSM: Active session not for this post or not running');
          }
        } else {
          console.log('PTT FSM: No active session data in response');
        }
        resolve({ running:false });
      }).fail(function(xhr, status, error){
        console.error('PTT FSM: Rehydration AJAX failed:', status, error);
        resolve({ running:false });
      });
    });
  };
  EditorEffects.prototype.updateTimerUI = function(state, ctx){
    var $rows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row');
    if(state==='STARTING'){
      // Show progress during timer start (auto-save handled by SessionFSM)
      $rows.each(function(){
        var $row = jQuery(this);
        $row.find('.ptt-session-start').prop('disabled', true).text('Starting Timer...');
        $row.find('.ptt-session-active-timer').hide();
        $row.find('.ptt-session-message').text('Starting timer...').show();
      });
    } else if(state==='RUNNING'){
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
      return; // Don't process other rows when timer is running
    } else {
      if (root.PTT && typeof root.PTT.stopLiveTimer === 'function') {
        $rows.each(function(){ root.PTT.stopLiveTimer(jQuery(this).find('.ptt-session-controls')); });
      }
      // Handle all session states properly with FSM-centric logic
      var nextAvailableIndex = -1;

      // First pass: identify the next available session for timer
      $rows.each(function(i){
        var $row = jQuery(this);
        var startVal = $row.find('[data-key="field_ptt_session_start_time"] input').val();
        var stopVal = $row.find('[data-key="field_ptt_session_stop_time"] input').val();
        var override = $row.find('[data-key="field_ptt_session_manual_override"] input').prop('checked');
        var calcDur = parseFloat($row.find('[data-key="field_ptt_session_calculated_duration"] input').val() || '0');
        var manualDur = parseFloat($row.find('[data-key="field_ptt_session_manual_duration"] input').val() || '0');

        var hasManualTime = override && !isNaN(manualDur) && manualDur > 0;
        var hasCalcTime = !isNaN(calcDur) && calcDur > 0;
        var hasSavedTime = hasManualTime || hasCalcTime;
        var isCompleted = startVal && stopVal;
        var isAvailable = !isCompleted && !hasSavedTime && !override;

        if(isAvailable && nextAvailableIndex === -1){
          nextAvailableIndex = i;
        }
      });

      // Second pass: update UI based on FSM logic
      $rows.each(function(i){
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
        var isCompleted = startVal && stopVal;
        var isAvailable = !isCompleted && !hasSavedTime && !override;
        var isNextAvailable = i === nextAvailableIndex;

        if (isCompleted) {
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
        } else if (isAvailable && isNextAvailable) {
          // This is the next available session for timer (FSM-centric: only one active)
          $startBtn.show();
          $activeTimer.hide();
          $message.hide();
          if ($defaultTimer.length) $defaultTimer.show();
          $startBtn.prop('disabled', false).removeAttr('title').text('Start Timer');
        } else if (isAvailable && !isNextAvailable) {
          // Available but not the next in line (FSM constraint)
          $startBtn.show();
          $activeTimer.hide();
          $message.text('Complete previous sessions first').show();
          if ($defaultTimer.length) $defaultTimer.hide();
          $startBtn.prop('disabled', true).attr('title', 'Complete the previous session before starting this one.');
        } else if (override) {
          // Manual override enabled
          $startBtn.hide();
          $activeTimer.hide();
          $message.text('Manual time entry enabled').show();
          if ($defaultTimer.length) $defaultTimer.hide();
        } else {
          // Fallback state
          $startBtn.hide();
          $activeTimer.hide();
          $message.hide();
          if ($defaultTimer.length) $defaultTimer.show();
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

