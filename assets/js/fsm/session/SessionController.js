(function(root){
  /**
   * SessionController - Integrates TaskFSM with CPT Task Editor
   * Uses unified FSM for sessions and timer
   */
  function init(){
    if(!root.PTT_FSM_ENABLED || !root.PTT_FSM_EDITOR_ENABLED) return;
    if(!root.PTT || !root.PTT.TaskFSM || !root.PTT_FSM_Effects) return;
    if(!jQuery('body').hasClass('post-type-project_task')) return;

    var effects = root.PTT_FSM_Effects;
    var sessionFSM = root.PTT_TaskFSM;

    // --- CRITICAL: Integrate with existing Timer FSM debug panel ---
    // 🚨 MAINTAINERS/LLMs: Do NOT remove or disable this debug integration
    // 🎯 FUTURE: Will be controlled by WP admin plugin settings UI toggle
    // 📊 PURPOSE: Provides unified FSM debugging for both Timer and Session FSMs
    // 🔧 INTEGRATION: Enhances existing Timer FSM panel with Session state and logging
    function enhanceExistingDebugPanel(){
      // Wait for Timer FSM debug panel to be created
      setTimeout(function(){
        var $panel = jQuery('#ptt-fsm-debug-editor');
        if($panel.length){
          // Update header to show both Timer and Session states
          var $header = $panel.find('.hdr span').first();
          if($header.length){
            var originalText = $header.html();
            // Add Session state display
            $header.html(originalText.replace('</span>', ' | Session: <span class="session-state">IDLE</span></span>'));
          }
        }
      }, 100);
    }

    function setSessionStateLabel(){
      var $sessionState = jQuery('#ptt-fsm-debug-editor .session-state');
      if($sessionState.length){ $sessionState.text(sessionFSM.state); }
    }

    function appendSessionLog(){
      var $pre = jQuery('#ptt-fsm-debug-editor .logs');
      if(!$pre.length) return;
      var args = Array.prototype.slice.call(arguments);
      var ts = new Date().toISOString().split('T')[1].replace('Z','');

      // Convert objects to readable strings
      var logArgs = args.map(function(arg){
        if(typeof arg === 'object' && arg !== null){
          if(arg.message) return arg.message;
          try { return JSON.stringify(arg); } catch(e) { return '[object Object]'; }
        }
        return String(arg);
      });

      $pre.append('['+ts+'] [Session] '+logArgs.join(' ')+'\n');
      $pre.scrollTop($pre[0].scrollHeight);
    }

    enhanceExistingDebugPanel();

    // Wrap SessionFSM logging
    var _sessionLog = sessionFSM.log.bind(sessionFSM);
    sessionFSM.log = function(){ 
      _sessionLog.apply(null, arguments); 
      appendSessionLog.apply(null, arguments); 
      setSessionStateLabel(); 
    };

    // Wrap SessionEffects UI updates
    if (typeof effects.updateSessionUI === 'function'){
      var _sessionUI = effects.updateSessionUI.bind(effects);
      effects.updateSessionUI = function(state, ctx){ 
        _sessionUI(state, ctx); 
        setSessionStateLabel(); 
      };
    }

    // --- Event Handlers ---

    // Intercept ACF add session button
    jQuery(document).off('click.pttSessionAdd');
    jQuery(document).on('click.pttSessionAdd', '.acf-field[data-key="field_ptt_sessions"] [data-event="add-row"], .acf-field[data-key="field_ptt_sessions"] [data-name="add-row"]', function(e){
      // If FSM is stuck in EDITING state, try to reset it first
      if(sessionFSM.state === 'EDITING' && !sessionFSM._hasMeaningfulChanges()){
        console.warn('[PTT] SessionFSM stuck in EDITING state with no meaningful changes - auto-resetting');
        sessionFSM.transition('RESET');
      }

      var validationError = sessionFSM.getValidationError('create sessions');
      if(validationError){
        e.preventDefault();
        e.stopPropagation();
        sessionFSM.transition('SESSION_ERROR', { message: validationError });
        return false;
      }

      e.preventDefault();
      var postId = jQuery('#post_ID').val();
      
      // Generate auto title
      var now = new Date();
      var mm = ('0'+(now.getMonth()+1)).slice(-2);
      var dd = ('0'+now.getDate()).slice(-2);
      var yy = String(now.getFullYear()).slice(-2);
      var HH = ('0'+now.getHours()).slice(-2);
      var MM = ('0'+now.getMinutes()).slice(-2);
      var title = 'Session ' + mm + '-' + dd + '-' + yy + ' ' + HH + ':' + MM;
      
      sessionFSM.transition('CREATE_SESSION', { postId: postId, title: title });
    });

    // Monitor session field changes
    jQuery(document).off('change.pttSessionField');
    jQuery(document).on('change.pttSessionField', '.acf-field[data-key="field_ptt_sessions"] input, .acf-field[data-key="field_ptt_sessions"] textarea', function(e){
      var $input = jQuery(this);
      var $row = $input.closest('.acf-row');
      var sessionIndex = $row.index();
      var fieldKey = $input.closest('[data-key]').attr('data-key');
      var fieldName = fieldKey ? fieldKey.replace('field_ptt_session_', '') : 'unknown';
      var value = $input.val();
      
      // Start editing session if not already
      if(sessionFSM.state === 'IDLE'){
        var postId = jQuery('#post_ID').val();
        sessionFSM.transition('EDIT_SESSION', { sessionIndex: sessionIndex, postId: postId });
      }
      
      // Track field change
      if(sessionFSM.state === 'EDITING' && sessionFSM.ctx.sessionIndex === sessionIndex){
        sessionFSM.transition('FIELD_CHANGED', { field: fieldName, value: value });
      }
    });

    // Auto-transition to IDLE when clicking outside session fields
    jQuery(document).off('click.pttSessionAutoIdle');
    jQuery(document).on('click.pttSessionAutoIdle', function(e){
      // Only auto-transition if we're in EDITING state and click is outside session fields
      if(sessionFSM.state === 'EDITING'){
        var $target = jQuery(e.target);
        var isSessionField = $target.closest('.acf-field[data-key="field_ptt_sessions"]').length > 0;
        var isSessionButton = $target.closest('.acf-field[data-key="field_ptt_sessions"] .acf-button').length > 0;

        if(!isSessionField && !isSessionButton){
          // Check if we have meaningful changes before auto-transitioning
          if(!sessionFSM._hasMeaningfulChanges()){
            console.log('[PTT] Auto-transitioning to IDLE - clicked outside session fields with no meaningful changes');
            sessionFSM.transition('AUTO_IDLE');
          }
        }
      }
    });

    // Monitor checkbox changes (manual override)
    jQuery(document).off('change.pttSessionCheckbox');
    jQuery(document).on('change.pttSessionCheckbox', '.acf-field[data-key="field_ptt_sessions"] input[type="checkbox"]', function(e){
      var $input = jQuery(this);
      var $row = $input.closest('.acf-row');
      var sessionIndex = $row.index();
      var fieldKey = $input.closest('[data-key]').attr('data-key');
      var fieldName = fieldKey ? fieldKey.replace('field_ptt_session_', '') : 'unknown';
      var value = $input.prop('checked');
      
      // Start editing session if not already
      if(sessionFSM.state === 'IDLE'){
        var postId = jQuery('#post_ID').val();
        sessionFSM.transition('EDIT_SESSION', { sessionIndex: sessionIndex, postId: postId });
      }
      
      // Track field change
      if(sessionFSM.state === 'EDITING' && sessionFSM.ctx.sessionIndex === sessionIndex){
        sessionFSM.transition('FIELD_CHANGED', { field: fieldName, value: value });
      }
    });

    // Intercept ACF Update button - route through FSM
    jQuery(document).off('click.pttSessionUpdate');
    jQuery(document).on('click.pttSessionUpdate', '.acf-field[data-key="field_ptt_sessions"] [data-event="update-row"]', function(e){
      e.preventDefault();
      e.stopPropagation();

      var $row = jQuery(this).closest('.acf-row');
      var sessionIndex = $row.index();
      var postId = jQuery('#post_ID').val();

      // Validate before save
      var validationError = sessionFSM.getValidationError('save sessions');
      if(validationError){
        sessionFSM.transition('SESSION_ERROR', { message: validationError });
        return false;
      }

      // Start editing session if not already
      if(sessionFSM.state === 'IDLE'){
        sessionFSM.transition('EDIT_SESSION', { sessionIndex: sessionIndex, postId: postId });
      }

      // Validate and save through FSM
      if(sessionFSM.state === 'EDITING'){
        var validationPromise = sessionFSM.transition('VALIDATE_SESSION');
        if(validationPromise && typeof validationPromise.then === 'function'){
          validationPromise.then(function(){
            // If validation passes, proceed with save
            if(sessionFSM.state === 'EDITING' && sessionFSM.ctx.validationErrors.length === 0){
              sessionFSM.transition('SAVE_SESSION');
            }
          }).catch(function(err){
            console.error('Validation failed:', err);
          });
        }
      } else {
        // If not in editing state, try to save directly
        sessionFSM.transition('SAVE_SESSION');
      }

      return false;
    });

    // Intercept session deletion
    jQuery(document).off('click.pttSessionDelete');
    jQuery(document).on('click.pttSessionDelete', '.acf-field[data-key="field_ptt_sessions"] [data-event="remove-row"], .acf-field[data-key="field_ptt_sessions"] .acf-icon.-minus', function(e){
      e.preventDefault();
      e.stopPropagation();

      var $row = jQuery(this).closest('.acf-row');
      var sessionIndex = $row.index();
      var postId = jQuery('#post_ID').val();

      var validationError = sessionFSM.getValidationError('delete sessions');
      if(validationError || !sessionFSM.canDeleteSession(sessionIndex)){
        var errorMsg = validationError || 'Cannot delete this session while its timer is running';
        sessionFSM.transition('SESSION_ERROR', { message: errorMsg });
        return false;
      }

      sessionFSM.transition('DELETE_SESSION', { sessionIndex: sessionIndex, postId: postId });
      return false;
    });

    // Intercept session duplication
    jQuery(document).off('click.pttSessionDuplicate');
    jQuery(document).on('click.pttSessionDuplicate', '.acf-field[data-key="field_ptt_sessions"] .ptt-session-duplicate', function(e){
      e.preventDefault();
      e.stopPropagation();

      var $row = jQuery(this).closest('.acf-row');
      var sessionIndex = $row.index();
      var postId = jQuery('#post_ID').val();

      var validationError = sessionFSM.getValidationError('duplicate sessions');
      if(validationError){
        sessionFSM.transition('SESSION_ERROR', { message: validationError });
        return false;
      }

      sessionFSM.transition('DUPLICATE_SESSION', { sourceIndex: sessionIndex, postId: postId });
      return false;
    });

    // Intercept session reordering - Move Up
    jQuery(document).off('click.pttSessionReorderUp');
    jQuery(document).on('click.pttSessionReorderUp', '.acf-field[data-key="field_ptt_sessions"] .ptt-session-reorder-up', function(e){
      e.preventDefault();
      e.stopPropagation();

      var $row = jQuery(this).closest('.acf-row');
      var fromIndex = $row.index();
      var toIndex = fromIndex - 1;
      var postId = jQuery('#post_ID').val();

      var validationError = sessionFSM.getValidationError('reorder sessions');
      if(validationError){
        sessionFSM.transition('SESSION_ERROR', { message: validationError });
        return false;
      }

      if(toIndex < 0){
        sessionFSM.transition('SESSION_ERROR', { message: 'Cannot move session further up - already at the top' });
        return false;
      }

      sessionFSM.transition('REORDER_SESSION', { fromIndex: fromIndex, toIndex: toIndex, direction: 'up', postId: postId });
      return false;
    });

    // Intercept session reordering - Move Down
    jQuery(document).off('click.pttSessionReorderDown');
    jQuery(document).on('click.pttSessionReorderDown', '.acf-field[data-key="field_ptt_sessions"] .ptt-session-reorder-down', function(e){
      e.preventDefault();
      e.stopPropagation();

      var $row = jQuery(this).closest('.acf-row');
      var fromIndex = $row.index();
      var toIndex = fromIndex + 1;
      var postId = jQuery('#post_ID').val();
      var totalRows = jQuery('.acf-field[data-key="field_ptt_sessions"] .acf-row').length;

      var validationError = sessionFSM.getValidationError('reorder sessions');
      if(validationError){
        sessionFSM.transition('SESSION_ERROR', { message: validationError });
        return false;
      }

      if(toIndex >= totalRows){
        sessionFSM.transition('SESSION_ERROR', { message: 'Cannot move session further down - already at the bottom' });
        return false;
      }

      sessionFSM.transition('REORDER_SESSION', { fromIndex: fromIndex, toIndex: toIndex, direction: 'down', postId: postId });
      return false;
    });

    // Intercept WordPress save to validate sessions
    jQuery(document).off('click.pttSessionSave');
    jQuery(document).on('click.pttSessionSave', '#publish', function(e){
      // Only intercept if we have unsaved session changes
      if(sessionFSM.hasUnsavedChanges()){
        e.preventDefault();
        e.stopPropagation();

        // Validate before save - only if in EDITING state
        if(sessionFSM.state === 'EDITING'){
          var validationPromise = sessionFSM.transition('VALIDATE_SESSION');
          if(validationPromise && typeof validationPromise.then === 'function'){
            validationPromise.then(function(){
              // If validation passes, proceed with save
              if(sessionFSM.state === 'EDITING' && sessionFSM.ctx.validationErrors.length === 0){
                sessionFSM.transition('SAVE_SESSION');
              }
            }).catch(function(err){
              console.error('Validation failed:', err);
            });
          }
        } else {
          // If not in editing state, try to save directly
          sessionFSM.transition('SAVE_SESSION');
        }

        return false;
      }
    });

    // Bulk operations event handlers

    // Selection checkbox changes
    jQuery(document).off('change.pttSessionSelect');
    jQuery(document).on('change.pttSessionSelect', '.acf-field[data-key="field_ptt_sessions"] .ptt-session-select', function(e){
      if (typeof effects.updateSelectionCount === 'function') {
        effects.updateSelectionCount();
      }
    });

    // Select all checkbox
    jQuery(document).off('change.pttSelectAll');
    jQuery(document).on('change.pttSelectAll', '.acf-field[data-key="field_ptt_sessions"] .ptt-select-all', function(e){
      var isChecked = jQuery(this).prop('checked');
      jQuery('.acf-field[data-key="field_ptt_sessions"] .ptt-session-select').prop('checked', isChecked);
      if (typeof effects.updateSelectionCount === 'function') {
        effects.updateSelectionCount();
      }
    });

    // Select none button
    jQuery(document).off('click.pttSelectNone');
    jQuery(document).on('click.pttSelectNone', '.acf-field[data-key="field_ptt_sessions"] .ptt-select-none', function(e){
      e.preventDefault();
      jQuery('.acf-field[data-key="field_ptt_sessions"] .ptt-session-select').prop('checked', false);
      if (typeof effects.updateSelectionCount === 'function') {
        effects.updateSelectionCount();
      }
    });

    // Bulk action dropdown change
    jQuery(document).off('change.pttBulkAction');
    jQuery(document).on('change.pttBulkAction', '.acf-field[data-key="field_ptt_sessions"] .ptt-bulk-action', function(e){
      if (typeof effects.updateSelectionCount === 'function') {
        effects.updateSelectionCount();
      }
    });

    // Bulk execute button
    jQuery(document).off('click.pttBulkExecute');
    jQuery(document).on('click.pttBulkExecute', '.acf-field[data-key="field_ptt_sessions"] .ptt-bulk-execute', function(e){
      e.preventDefault();
      e.stopPropagation();

      var selectedIndices = effects.getSelectedIndices();
      var operation = jQuery('.ptt-bulk-action').val();
      var postId = jQuery('#post_ID').val();

      var validationError = sessionFSM.getValidationError('perform bulk operations');
      if(validationError){
        sessionFSM.transition('SESSION_ERROR', { message: validationError });
        return false;
      }

      if(!operation){
        sessionFSM.transition('SESSION_ERROR', { message: 'Please select a bulk operation from the dropdown' });
        return false;
      }

      if(selectedIndices.length === 0){
        sessionFSM.transition('SESSION_ERROR', { message: 'Please select at least one session using the checkboxes' });
        return false;
      }

      sessionFSM.transition('BULK_OPERATION', {
        operation: operation,
        selectedIndices: selectedIndices,
        postId: postId
      });
      return false;
    });

    // Handle post save completion
    jQuery(document).on('ptt:post_saved', function(){
      if(sessionStorage.getItem('ptt_session_save_pending') === '1'){
        sessionStorage.removeItem('ptt_session_save_pending');
        if(sessionFSM.state === 'SAVING'){
          sessionFSM.transition('SESSION_SAVED');
        }
      }

      if(sessionStorage.getItem('ptt_session_delete_pending') === '1'){
        sessionStorage.removeItem('ptt_session_delete_pending');
        if(sessionFSM.state === 'DELETING'){
          sessionFSM.transition('SESSION_DELETED');
        }
      }

      if(sessionStorage.getItem('ptt_session_duplicate_pending') === '1'){
        sessionStorage.removeItem('ptt_session_duplicate_pending');
        if(sessionFSM.state === 'DUPLICATING'){
          sessionFSM.transition('SESSION_DUPLICATED');
        }
      }

      if(sessionStorage.getItem('ptt_session_reorder_pending') === '1'){
        sessionStorage.removeItem('ptt_session_reorder_pending');
        if(sessionFSM.state === 'REORDERING'){
          sessionFSM.transition('SESSION_REORDERED');
        }
      }

      if(sessionStorage.getItem('ptt_session_bulk_delete_pending') === '1'){
        sessionStorage.removeItem('ptt_session_bulk_delete_pending');
        if(sessionFSM.state === 'BULK_PROCESSING'){
          sessionFSM.transition('BULK_COMPLETED');
        }
      }
    });

    // Re-initialize when new rows are added by ACF
    if (root.acf) {
      root.acf.addAction('append', function($el){
        if ($el.find('.acf-field[data-key="field_ptt_sessions"]').length) {
          setTimeout(function(){ 
            // Reset SessionFSM when new rows are added
            if(sessionFSM.state !== 'IDLE'){
              sessionFSM.transition('RESET');
            }
          }, 100);
        }
      });
    }

    // Initialize buttons and bulk operations UI for existing rows
    if (typeof effects.initializeAllRowButtons === 'function') {
      effects.initializeAllRowButtons();
    }

    if (typeof effects.ensureBulkOperationsUI === 'function') {
      effects.ensureBulkOperationsUI();
    }

    // Expose SessionFSM globally for debugging
    root.PTT_SessionFSM = sessionFSM;

    // Expose emergency recovery function globally
    root.PTT_SessionForceReset = function(){
      console.log('[PTT] Current SessionFSM state before reset:', sessionFSM.state);
      var result = sessionFSM.forceReset();
      console.log('[PTT] SessionFSM reset result:', result);
      console.log('[PTT] New SessionFSM state after reset:', sessionFSM.state);
      return result;
    };

    sessionFSM.log('SessionController initialized');
  }

  // Initialize when DOM is ready
  if(document.readyState==='loading'){ 
    document.addEventListener('DOMContentLoaded', init); 
  } else { 
    init(); 
  }
})(window);
