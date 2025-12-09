// Exam Lockdown Script
// Fitur: Fullscreen enforcement, blur/tab switch detection, keyboard shortcut blocking, context menu disable, autosave hook
(function(){
  let violationCount = 0;
  const maxViolations = 3; // after this, trigger auto submit
  const listeners = [];
  const body = document.body;

  function log(msg){
    console.log('[LOCKDOWN]', msg);
  }

  function post(url, data){
    return fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
      credentials: 'same-origin'
    });
  }

  function requestFullscreen(){
    const el = document.documentElement;
    if (el.requestFullscreen) return el.requestFullscreen();
    if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
    if (el.msRequestFullscreen) return el.msRequestFullscreen();
  }

  function exitFullscreen(){
    if (document.exitFullscreen) return document.exitFullscreen();
    if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
    if (document.msExitFullscreen) return document.msExitFullscreen();
  }

  function isFullscreen(){
    return document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement;
  }

  // Detect if current focus/target is within allowed media context (video/iframe player)
  function isMediaContext(el){
    try {
      el = el || document.activeElement;
      if (!el) return false;
      var isVid = (el.tagName === 'VIDEO') || (el.closest && !!el.closest('video'));
      var isIfr = (el.tagName === 'IFRAME') || (el.closest && !!el.closest('iframe'));
      var inAllowed = (el.hasAttribute && el.hasAttribute('data-allow-player')) || (el.closest && !!el.closest('[data-allow-player]'));
      var isYt = false;
      if (isIfr) {
        var node = (el.tagName === 'IFRAME') ? el : el.closest('iframe');
        try { var src = (node && node.getAttribute('src')) || ''; isYt = /youtube\.com|youtu\.be/i.test(src); } catch(e) {}
      }
      return isVid || inAllowed || isYt || isIfr;
    } catch(e){ return false; }
  }

  function warnOverlay(text){
    let overlay = document.getElementById('lockdown-overlay');
    if (!overlay){
      overlay = document.createElement('div');
      overlay.id = 'lockdown-overlay';
      overlay.style.position = 'fixed';
      overlay.style.inset = '0';
      overlay.style.background = 'rgba(0,0,0,.8)';
      overlay.style.color = '#fff';
      overlay.style.zIndex = '99999';
      overlay.style.display = 'flex';
      overlay.style.alignItems = 'center';
      overlay.style.justifyContent = 'center';
      overlay.style.textAlign = 'center';
      overlay.style.padding = '20px';
      document.body.appendChild(overlay);
    }
    overlay.innerHTML = '<div><h2>Perhatian</h2><p>'+text+'</p><button id="btn-reenter">Masuk Fullscreen</button></div>';
    const btn = document.getElementById('btn-reenter');
    if (btn) btn.onclick = ()=>{ requestFullscreen(); overlay.remove(); };
  }

  function incrementViolation(reason){
    violationCount++;
    log('Violation '+violationCount+': '+reason);
    try { post('take.php?action=violation', { reason }); } catch(e){}
    if (violationCount >= maxViolations){
      autoSubmit('Terlalu banyak pelanggaran: '+reason);
    } else {
      warnOverlay('Jangan meninggalkan halaman ujian atau keluar dari mode layar penuh. Pelanggaran: '+violationCount+'/'+maxViolations);
    }
  }

  function autoSubmit(reason){
    try { post('take.php?action=autosubmit', { reason }); } catch(e){}
    const form = document.getElementById('exam-form');
    if (form){
      const reasonInput = document.getElementById('auto-submit-reason');
      if (reasonInput) reasonInput.value = reason;
      form.submit();
    }
  }

  function blockKeys(e){
    // Block common shortcuts: Ctrl/Cmd+F,S,P,C,V,X; Alt key; F12
    // Allow media controls on video/iframe so students can play/pause without triggering violations
    const active = document.activeElement;
    const mediaCtx = isMediaContext(active);
    if (e.key === 'F12' || e.keyCode === 123) { e.preventDefault(); if (!mediaCtx) incrementViolation('devtools'); return false; }
    const ctrl = e.ctrlKey || e.metaKey;
    if (ctrl && ['f','s','p','c','v','x','a'].includes((e.key||'').toLowerCase())) { e.preventDefault(); if (!mediaCtx) return false; }
    if (e.key === 'Escape') { if (!mediaCtx) { e.preventDefault(); return false; } }
    if (e.altKey) { if (!mediaCtx) { e.preventDefault(); return false; } }
  }

  function handleVisibility(){
    if (document.hidden) {
      const el = document.activeElement;
      if (!isMediaContext(el)) incrementViolation('tab_hidden');
    }
  }

  function handleBlur(){
    if (!document.hidden) {
      const el = document.activeElement;
      if (!isMediaContext(el)) {
        incrementViolation('window_blur');
      }
    }
  }

  function onFullscreenChange(){
    if (!isFullscreen()){
      const el = document.activeElement;
      if (!isMediaContext(el)) {
        incrementViolation('exit_fullscreen');
        warnOverlay('Anda keluar dari fullscreen. Harap kembali ke fullscreen untuk melanjutkan.');
      }
    }
  }

  function disableContext(e){ e.preventDefault(); }

  function setupAutosave(){
    const form = document.getElementById('exam-form');
    if (!form) return;
    setInterval(()=>{
      const data = new FormData(form);
      fetch('take.php?action=autosave', { method:'POST', body: data, credentials: 'same-origin' });
    }, 20000);
  }

  function startTimer(durationSeconds){
    const el = document.getElementById('exam-timer');
    let remaining = durationSeconds;
    const iv = setInterval(()=>{
      if (!el) { clearInterval(iv); return; }
      const m = Math.floor(remaining/60);
      const s = remaining%60;
      el.textContent = m.toString().padStart(2,'0')+':'+s.toString().padStart(2,'0');
      if (remaining<=0){
        clearInterval(iv);
        autoSubmit('waktu_habis');
      }
      remaining--;
    }, 1000);
  }

  function initLockdown(options){
    options = options || {};
    violationCount = 0;

    if (!isFullscreen()){
      requestFullscreen();
      setTimeout(()=>{ if (!isFullscreen()) warnOverlay('Ujian mewajibkan mode layar penuh. Klik tombol untuk melanjutkan.'); }, 800);
    }

    document.addEventListener('visibilitychange', handleVisibility);
    window.addEventListener('blur', handleBlur);
    document.addEventListener('fullscreenchange', onFullscreenChange);
    document.addEventListener('webkitfullscreenchange', onFullscreenChange);
    document.addEventListener('msfullscreenchange', onFullscreenChange);
    document.addEventListener('contextmenu', disableContext);
    document.addEventListener('keydown', blockKeys, true);

    setupAutosave();

    // Mark common video players as allowed media contexts automatically
    document.addEventListener('DOMContentLoaded', function(){
      try {
        document.querySelectorAll('iframe[src*="youtube.com"], iframe[src*="youtu.be"], .video-embed iframe').forEach(function(ifr){
          ifr.setAttribute('data-allow-player', '1');
        });
        document.querySelectorAll('video').forEach(function(v){
          v.setAttribute('data-allow-player', '1');
        });
      } catch(e) {}
    });

    if (options.durationSeconds) startTimer(options.durationSeconds);

    window.addEventListener('beforeunload', function (e) {
      e.preventDefault();
      e.returnValue = '';
    });

    log('Lockdown initialized');
  }

  window.ExamLockdown = { init: initLockdown, startTimer };
})();
