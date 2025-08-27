(function(root){
  /**
   * SessionController - Integrates SessionFSM with CPT Task Editor
   * Coordinates between SessionFSM and TimerFSM
   */
  function init(){
    if(!root.PTT_FSM_ENABLED || !root.PTT_FSM_EDITOR_ENABLED) return;
    if(!root.PTT || !root.PTT.SessionFSM || !root.PTT.SessionEffects) return;
    if(!jQuery('body').hasClass('post-type-project_task')) return;

    var effects = new root.PTT.SessionEffects();
    var timerFSM = root.PTT_EditorFSM; // Get reference to existing TimerFSM
    var sessionFSM = new root.PTT.SessionFSM(effects, { debug: true, timerFSM: timerFSM });

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
      $pre.append('['+ts+'] [Session] '+args.join(' ')+'\n');
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
      if(!sessionFSM.canCreateSession()){
        e.preventDefault();
        e.stopPropagation();
        sessionFSM.transition('SESSION_ERROR', { message: 'Cannot create session in current state' });
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

    // Intercept session deletion
    jQuery(document).off('click.pttSessionDelete');
    jQuery(document).on('click.pttSessionDelete', '.acf-field[data-key="field_ptt_sessions"] [data-event="remove-row"], .acf-field[data-key="field_ptt_sessions"] .acf-icon.-minus', function(e){
      e.preventDefault();
      e.stopPropagation();

      var $row = jQuery(this).closest('.acf-row');
      var sessionIndex = $row.index();
      var postId = jQuery('#post_ID').val();

      if(!sessionFSM.canDeleteSession(sessionIndex)){
        sessionFSM.transition('SESSION_ERROR', { message: 'Cannot delete session in current state' });
        return false;
      }

      sessionFSM.transition('DELETE_SESSION', { sessionIndex: sessionIndex, postId: postId });
      return false;
    });

    // Intercept WordPress save to validate sessions
    jQuery(document).off('click.pttSessionSave');
    jQuery(document).on('click.pttSessionSave', '#publish', function(e){
      // Only intercept if we have unsaved session changes
      if(sessionFSM.hasUnsavedChanges()){
        e.preventDefault();
        e.stopPropagation();

        // Validate before save
        sessionFSM.transition('VALIDATE_SESSION').then(function(){
          // If validation passes, proceed with save
          if(sessionFSM.state === 'EDITING' && sessionFSM.ctx.validationErrors.length === 0){
            sessionFSM.transition('SAVE_SESSION');
          }
        });

        return false;
      }
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

    // Expose SessionFSM globally for debugging
    root.PTT_SessionFSM = sessionFSM;
    
    sessionFSM.log('SessionController initialized');
  }

  // Initialize when DOM is ready
  if(document.readyState==='loading'){ 
    document.addEventListener('DOMContentLoaded', init); 
  } else { 
    init(); 
  }
})(window);
