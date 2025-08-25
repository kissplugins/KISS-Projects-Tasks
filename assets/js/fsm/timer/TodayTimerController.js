(function(root){
  function init(){
    if(!root.PTT_FSM_ENABLED || !root.PTT_FSM_TODAY_ENABLED) return;
    if(!root.PTT || !root.PTT.TimerFSM || !root.PTT.TodayEffects) return;
    var effects = new root.PTT.TodayEffects();
    var fsm = new root.PTT.TimerFSM(effects, { debug: true }); // Always debug while FSM is enabled

    // --- Semi‑permanent debug panel (Do NOT remove without explicit product approval) ---
    // This panel helps internal testers visualize FSM state and logs.
    // Maintainers/LLMs: Do not remove or disable unless explicitly instructed by the product owner.
    var storageKey = 'ptt_fsm_debug_today_visible';
    function ensureDebugPanel(){
      var $ = jQuery; if (!$('#ptt-fsm-debug-today').length) {
        var visible = (localStorage.getItem(storageKey)!=='0');
        var html = '\n<div id="ptt-fsm-debug-today" style="position:fixed;bottom:12px;right:12px;z-index:99999;max-width:360px;font:12px/1.4 monospace;background:#111;color:#eee;border:1px solid #444;border-radius:6px;box-shadow:0 2px 8px rgba(0,0,0,.3);">\n  <div class="hdr" style="display:flex;align-items:center;justify-content:space-between;padding:6px 8px;background:#222;border-bottom:1px solid #333;">\n    <span><strong>PTT FSM</strong> · Today · <span class="state">IDLE</span></span>\n    <label style="cursor:pointer;color:#ccc;font-size:11px;">Show\n      <input type="checkbox" class="toggle" style="vertical-align:middle;margin-left:6px;" '+(visible?'checked':'')+'>\n    </label>\n  </div>\n  <pre class="logs" style="margin:0;max-height:180px;overflow:auto;padding:8px;display:'+(visible?'block':'none')+';"></pre>\n</div>';
        jQuery('body').append(html);
        jQuery('#ptt-fsm-debug-today .toggle').on('change', function(){
          var vis = jQuery(this).is(':checked');
          localStorage.setItem(storageKey, vis?'1':'0');
          jQuery('#ptt-fsm-debug-today .logs').css('display', vis?'block':'none');
        });
      }
    }
    function setStateLabel(){ var $p=jQuery('#ptt-fsm-debug-today .state'); if($p.length){ $p.text(fsm.state); } }
    function appendLog(){
      var $pre = jQuery('#ptt-fsm-debug-today .logs'); if(!$pre.length) return;
      var args = Array.prototype.slice.call(arguments);
      var ts = new Date().toISOString().split('T')[1].replace('Z','');
      $pre.append('['+ts+'] '+args.join(' ')+'\n'); $pre.scrollTop($pre[0].scrollHeight);
    }
    ensureDebugPanel();

    // Wrap fsm.log to also write to panel
    var _log = fsm.log.bind(fsm);
    fsm.log = function(){ _log.apply(null, arguments); appendLog.apply(null, arguments); setStateLabel(); };

    // Wrap effects.updateTimerUI to refresh header state
    if (typeof effects.updateTimerUI === 'function'){
      var _ui = effects.updateTimerUI.bind(effects);
      effects.updateTimerUI = function(state, ctx){ _ui(state, ctx); setStateLabel(); };
    }

    // Rehydrate on load
    fsm.rehydrate();

    // Bind buttons
    jQuery(document).on('click', '#ptt-today-start-stop-btn', function(e){
      e.preventDefault();
      if(fsm.state==='RUNNING'){ fsm.transition('STOP_TIMER'); }
      else {
        var taskId = jQuery('#ptt-today-task-select').val();
        var title  = jQuery('#ptt-today-session-title').val() || 'New Session';
        if(taskId){ fsm.transition('START_TIMER', { taskId: taskId, title: title }); }
      }
    });
    root.PTT_TodayFSM = fsm;
  }
  if(document.readyState==='loading'){ document.addEventListener('DOMContentLoaded', init); } else { init(); }
})(window);

