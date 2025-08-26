(function(root){
  var ajax = function(action, data){
    data = data || {}; data.action = action; data.nonce = (root.ptt_ajax_object && root.ptt_ajax_object.nonce) || '';
    return new Promise(function(resolve, reject){
      jQuery.post((root.ptt_ajax_object && root.ptt_ajax_object.ajax_url) || '', data).done(function(resp){
        if(resp && resp.success){ resolve(resp.data || {}); } else { reject(resp && resp.data ? resp.data.message : 'Request failed'); }
      }).fail(function(xhr){ reject(xhr && xhr.responseText || 'Network error'); });
    });
  };
  function TodayEffects(opts){ this.opts = opts||{}; }
  TodayEffects.prototype.startTimer = function(taskId, title){ return ajax('ptt_start_timer', { task_id: taskId, title: title }); };
  TodayEffects.prototype.stopTimer  = function(postId){ return ajax('ptt_stop_timer', { post_id: postId }); };
  TodayEffects.prototype.rehydrate  = function(){ return ajax('ptt_rehydrate_timer', {}); };
  TodayEffects.prototype.updateTimerUI = function(state, ctx){
    var $btn = jQuery('#ptt-today-start-stop-btn');
    if(!$btn.length) return;
    if(state==='RUNNING') { $btn.text('Stop').addClass('running').removeClass('button-primary').addClass('button-secondary'); }
    else { $btn.text('Start').removeClass('running').addClass('button-primary').removeClass('button-secondary'); }
  };
  TodayEffects.prototype.showError = function(msg){ if(root.console) console.warn('[PTT TodayEffects]', msg); };
  root.PTT = root.PTT || {}; root.PTT.TodayEffects = TodayEffects;
})(window);

