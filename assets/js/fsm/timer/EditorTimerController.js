(function(root){
  function init(){
    if(!root.PTT_FSM_ENABLED || !root.PTT_FSM_EDITOR_ENABLED) return;
    if(!root.PTT || !root.PTT.TimerFSM || !root.PTT.EditorEffects) return;
    if(!jQuery('body').hasClass('post-type-project_task')) return;
    var effects = new root.PTT.EditorEffects();
    var fsm = new root.PTT.TimerFSM(effects, { debug: true }); // Always debug while FSM is enabled

    // --- Semi‑permanent debug panel (Do NOT remove without explicit product approval) ---
    // Maintainers/LLMs: Do not remove this panel unless explicitly instructed by the product owner.
    var storageKey = 'ptt_fsm_debug_editor_visible';
    function ensureDebugPanel(){
      if (!jQuery('#ptt-fsm-debug-editor').length) {
        var visible = (localStorage.getItem(storageKey)!=='0');
        var html = '\n<div id="ptt-fsm-debug-editor" style="position:fixed;bottom:12px;left:12px;z-index:99999;max-width:360px;font:12px/1.4 monospace;background:#111;color:#eee;border:1px solid #444;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.3);">\n  <div class="hdr" style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:#222;border-bottom:1px solid #333;">\n    <span><strong>PTT FSM</strong> · Editor · <span class="state">IDLE</span></span>\n    <label style="cursor:pointer;color:#ccc;font-size:11px;">Show\n      <input type="checkbox" class="toggle" style="vertical-align:middle;margin-left:6px;" '+(visible?'checked':'')+'>\n    </label>\n  </div>\n  <pre class="logs" style="margin:0;max-height:180px;overflow:auto;padding:8px;display:'+(visible?'block':'none')+';"></pre>\n</div>';
        jQuery('body').append(html);
        jQuery('#ptt-fsm-debug-editor .toggle').on('change', function(){
          var vis = jQuery(this).is(':checked');
          localStorage.setItem(storageKey, vis?'1':'0');
          jQuery('#ptt-fsm-debug-editor .logs').css('display', vis?'block':'none');
        });
      }
    }
    function setStateLabel(){ var $p=jQuery('#ptt-fsm-debug-editor .state'); if($p.length){ $p.text(fsm.state); } }
    function appendLog(){ var $pre = jQuery('#ptt-fsm-debug-editor .logs'); if(!$pre.length) return; var args = Array.prototype.slice.call(arguments); var ts = new Date().toISOString().split('T')[1].replace('Z',''); $pre.append('['+ts+'] '+args.join(' ')+'\n'); $pre.scrollTop($pre[0].scrollHeight); }
    ensureDebugPanel();

    var _log = fsm.log.bind(fsm);
    fsm.log = function(){ _log.apply(null, arguments); appendLog.apply(null, arguments); setStateLabel(); };
    if (typeof effects.updateTimerUI === 'function'){
      var _ui = effects.updateTimerUI.bind(effects);
      effects.updateTimerUI = function(state, ctx){ _ui(state, ctx); setStateLabel(); };
    }

    fsm.rehydrate();
    jQuery(document).on('click', '.ptt-session-start', function(e){ e.preventDefault(); var postId = jQuery('#post_ID').val(); fsm.transition('START_TIMER', { taskId: postId }); });
    jQuery(document).on('click', '.ptt-session-stop', function(e){ e.preventDefault(); fsm.transition('STOP_TIMER'); });
    root.PTT_EditorFSM = fsm;
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(window);

