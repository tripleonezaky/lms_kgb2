// =============================================
// LMS KGB2 - ADMIN PANEL JAVASCRIPT
// =============================================

// Toggle Sidebar (untuk mobile)
function toggleSidebar() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    sidebar.classList.toggle('active');
    if (sidebar.classList.contains('active')) {
        applySidebarHeight();
        // lock body scroll when sidebar open (mobile UX)
        document.body.classList.add('sidebar-open');
    } else {
        document.body.classList.remove('sidebar-open');
    }
}

// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('active');
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.classList.remove('active');
    }
}

// Confirm Delete
function confirmDelete(message) {
    return confirm(message || 'Apakah Anda yakin ingin menghapus data ini?');
}

// Auto hide alert after 5 seconds + global back button handler
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    // Global back button behavior
    try {
      document.body.addEventListener('click', function(e){
        const link = e.target.closest('.back-btn, a.btn-back, a.back, .btn-back, button.back');
        if (!link) return;
        if (link.hasAttribute('data-no-history')) return; // opt-out
        let href = link.getAttribute('href') || '';
        const text = (link.textContent || link.innerText || '').trim().toLowerCase();
        const isDashboardBack = link.dataset.dashboard || /kembali\s+ke\s+dashboard|back\s+to\s+dashboard/i.test(text);
        const roleFromAttr = (link.dataset.dashboard || '').toLowerCase();
        // Build dashboard URL based on role context or attribute
        function resolveDashboard(){
          if (roleFromAttr === 'admin') return '/lms_kgb2/admin/dashboard.php';
          if (roleFromAttr === 'guru') return '/lms_kgb2/guru/dashboard.php';
          if (roleFromAttr === 'siswa') return '/lms_kgb2/siswa/dashboard.php';
          const path = (window.location.pathname || '').toLowerCase();
          if (path.indexOf('/admin/') >= 0) return '/lms_kgb2/admin/dashboard.php';
          if (path.indexOf('/guru/') >= 0) return '/lms_kgb2/guru/dashboard.php';
          if (path.indexOf('/siswa/') >= 0) return '/lms_kgb2/siswa/dashboard.php';
          return '/lms_kgb2/';
        }
        e.preventDefault();
        // If this is an explicit dashboard back, go to dashboard directly
        if (isDashboardBack || /\/dashboard\.php$/i.test(href)) {
          const to = /\/dashboard\.php$/i.test(href) ? href : resolveDashboard();
          window.location.href = to;
          return;
        }
        // Otherwise normal back logic
        if (window.history && window.history.length > 1) { window.history.back(); }
        else if (href && href !== '#') { window.location.href = href; }
        else { window.location.href = resolveDashboard(); }
      }, false);
    } catch (e) { /* no-op */ }
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'fadeOut 0.5s ease';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });

    // Set favicon globally (works across nested paths) if not already set
    try {
        const head = document.head || document.getElementsByTagName('head')[0];
        if (head && !document.querySelector('link[rel="icon"]')) {
            const link = document.createElement('link');
            link.rel = 'icon';
            link.type = 'image/png';
            // Use absolute app base path to work in any nested page
            link.href = '/lms_kgb2/assets/img/logo-kgb2.png';
            head.appendChild(link);
        }
    } catch (e) {
        // no-op
    }
});

// Search Table
function searchTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const filter = input.value.toUpperCase();
    const table = document.getElementById(tableId);
    const tr = table.getElementsByTagName('tr');

    for (let i = 1; i < tr.length; i++) {
        let found = false;
        const td = tr[i].getElementsByTagName('td');
        
        for (let j = 0; j < td.length; j++) {
            if (td[j]) {
                const txtValue = td[j].textContent || td[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        
        tr[i].style.display = found ? '' : 'none';
    }
}

// Generate Password
function generatePassword(length = 8) {
    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    let password = '';
    for (let i = 0; i < length; i++) {
        password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
}

// Auto fill password
function autoGeneratePassword(inputId) {
    const input = document.getElementById(inputId);
    if (input) {
        input.value = generatePassword();
    }
}

// Preview Image
function previewImage(input, previewId) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById(previewId).src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Format Number
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
}

// Validate Email
function validateEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate Phone
function validatePhone(phone) {
    const re = /^[0-9]{10,13}$/;
    return re.test(phone);
}

// Form Validation
function validateForm(formId) {
    const form = document.getElementById(formId);
    const inputs = form.querySelectorAll('[required]');
    let isValid = true;

    inputs.forEach(input => {
        if (!input.value.trim()) {
            input.style.borderColor = '#e74c3c';
            isValid = false;
        } else {
            input.style.borderColor = '#e0e0e0';
        }
    });

    return isValid;
}

// Remove any hamburger icons on desktop (strict cleanup)
function removeHamburgersOnDesktop(){
  try {
    var vw = (window.innerWidth || document.documentElement.clientWidth || 1024);
    if (vw <= 768) return; // only act on desktop
    var scopes = [];
    var tb = document.querySelector('.top-bar');
    var tn = document.querySelector('.top-navbar');
    if (tb) scopes.push(tb);
    if (tn && tn !== tb) scopes.push(tn);
    // Remove injected .hamburger-toggle
    scopes.forEach(function(root){
      Array.from(root.querySelectorAll('.hamburger-toggle')).forEach(function(el){ try { el.remove(); } catch(e){} });
    });
    // Remove any element that visually is a hamburger (innerText === '☰') without class
    scopes.forEach(function(root){
      Array.from(root.querySelectorAll('button, a, i, span, div')).forEach(function(el){
        try {
          var txt = (el.textContent || el.innerText || '').trim();
          if (txt === '\u2630' || txt === '☰') { el.remove(); }
        } catch(e){}
      });
    });
  } catch(e) { /* no-op */ }
}

// Inject hamburger button into any page that has a .top-bar or .top-navbar and a .sidebar
(function(){
  try {
    document.addEventListener('DOMContentLoaded', function(){
      // Desktop cleanup first to avoid flashes
      removeHamburgersOnDesktop();
      var sidebar = document.querySelector('.sidebar');
      var bars = document.querySelector('.top-bar') || document.querySelector('.top-navbar');
      if (!sidebar || !bars) return;
      // Avoid duplicate injection
      if (bars.querySelector('.hamburger-toggle')) return;
      // Only inject on mobile/tablet (<= 768px)
      var vw = (window.innerWidth || document.documentElement.clientWidth || 1024);
      if (vw > 768) return;
      var btn = document.createElement('button');
      btn.className = 'hamburger-toggle show-on-mobile btn btn-primary';
      btn.type = 'button';
      btn.style.marginRight = '10px';
      btn.style.fontSize = '20px';
      btn.style.lineHeight = '1';
      btn.style.padding = '6px 10px';
      btn.setAttribute('aria-label', 'Toggle menu');
      btn.innerHTML = '\u2630'; // ☰
      btn.addEventListener('click', toggleSidebar);
      // Prefer place right next to the Dashboard title
      var titleH1 = bars.querySelector('.navbar-title h1') || bars.querySelector('h1');
      var titleWrap = bars.querySelector('.navbar-title');
      if (titleH1 && titleH1.parentNode) {
        titleH1.parentNode.insertBefore(btn, titleH1);
      } else if (titleWrap) {
        titleWrap.insertBefore(btn, titleWrap.firstChild);
      } else if (bars.firstElementChild && bars.firstElementChild.nodeType === 1) {
        bars.firstElementChild.insertBefore(btn, bars.firstElementChild.firstChild);
      } else {
        bars.insertBefore(btn, bars.firstChild);
      }
    });
    // Cleanup on resize: if viewport becomes desktop, remove any hamburger remnants
    window.addEventListener('resize', function(){ try { removeHamburgersOnDesktop(); } catch(e){} });
  } catch(e) { /* no-op */ }
})();

// Sidebar height helpers and auto-hide outside
function applySidebarHeight() {
  try {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    var vh = (window.visualViewport && window.visualViewport.height) ? Math.round(window.visualViewport.height) : window.innerHeight;
    // Set max/min to ensure scrollable area and account for mobile UI chrome
    sidebar.style.height = vh + 'px';
    sidebar.style.maxHeight = vh + 'px';
    sidebar.style.overflowY = 'auto';
    // Ensure iOS momentum scrolling
    sidebar.style.webkitOverflowScrolling = 'touch';
  } catch (e) {}
}

// Init on load and update on resize/orientation
(function(){
  try {
    document.addEventListener('DOMContentLoaded', applySidebarHeight);
    window.addEventListener('resize', applySidebarHeight);
    if (window.visualViewport && window.visualViewport.addEventListener) {
      window.visualViewport.addEventListener('resize', applySidebarHeight);
    }
    // Also update on orientation change
    window.addEventListener('orientationchange', function(){ setTimeout(applySidebarHeight, 200); });
  } catch (e) {}
})();

// Auto-hide sidebar when clicking outside it (mobile UX)
document.addEventListener('click', function(e) {
  try {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    if (!sidebar.classList.contains('active')) return;
    var clickedInsideSidebar = sidebar.contains(e.target);
    var clickedHamburger = e.target.closest && e.target.closest('.hamburger-toggle');
    if (!clickedInsideSidebar && !clickedHamburger) {
      sidebar.classList.remove('active');
    }
  } catch (e) {}
});

// Close on Escape
document.addEventListener('keydown', function(e){
  if (e.key === 'Escape' || e.keyCode === 27) {
    var sidebar = document.querySelector('.sidebar');
    if (sidebar && sidebar.classList.contains('active')) {
      sidebar.classList.remove('active');
    }
  }
});

// (Dynamic fallback removed) Rely on legacy template controls and centralized sync logic.

// Ensure PG Kompleks checkboxes are visible/enabled when tipe_soal is PG Kompleks
function ensurePGKVisibility() {
  try {
    var selects = document.querySelectorAll('select#tipe_soal, select#tipe_soal_edit, select[name="tipe_soal"]');
    var active = '';
    for (var i = 0; i < selects.length; i++) { if (selects[i] && selects[i].value) { active = selects[i].value; break; } }
    var isPGK = String(active || '').toLowerCase() === 'pilihan_ganda_kompleks';

    // Deteksi apakah form menggunakan kontrol legacy untuk PGK (jawaban_pgk[])
    var hasLegacyPGK = !!document.querySelector('input[name="jawaban_pgk[]"], input[name="jawaban_pgk_edit[]"]');
    var containers = Array.from(document.querySelectorAll('#kunci-pgk, #kunci-pgk-edit'));
    var checks = Array.from(document.querySelectorAll('input[name="jawaban_pgk[]"], input[name="jawaban_pgk_edit[]"]'));

    containers.forEach(function(c){
      try { c.style.display = isPGK ? 'block' : 'none'; } catch(e){}
    });
    checks.forEach(function(cb){
      try {
        if (isPGK) { cb.style.display = ''; cb.disabled = false; if (cb.parentElement) cb.parentElement.style.display = ''; }
        else { /* do not forcibly hide here to avoid interfering with other logic */ }
      } catch(e){}
    });
  } catch(e) { /* no-op */ }
}

document.addEventListener('DOMContentLoaded', function(){
  try {
    ensurePGKVisibility();
    var nodes = document.querySelectorAll('select#tipe_soal, select#tipe_soal_edit, select[name="tipe_soal"]');
    nodes.forEach(function(n){ n.addEventListener('change', ensurePGKVisibility); });
  } catch(e){}
});

// Publik: sinkronkan kontrol form butir soal berdasarkan tipe yang dipilih
window.syncQuestionTypeControls = function(val, formEl){
  try {
    var t = String(val||'');
    var root = formEl || document;
    var opsiRow = root.querySelector('#opsi-row');
    var kpg = root.querySelector('#kunci-pg') || root.querySelector('#kunci-pg-edit') || root.querySelector('#kunci-pg-fallback');
    var kpgk = root.querySelector('#kunci-pgk') || root.querySelector('#kunci-pgk-edit');
    var kbs = root.querySelector('#kunci-bs') || root.querySelector('#kunci-bs-edit') || root.querySelector('#kunci-bs-fallback');
    var bs = root.querySelector('select[name="jawaban_bs"], select[name="jawaban_bs_edit"], select#jawaban_bs, select#jawaban_bs_edit');

    function show(el, v){ if (!el) return; el.style.display = v? (el.tagName==='SELECT' || el.id.indexOf('kunci')>=0 ? 'block' : 'flex') : 'none'; }
    if (t === 'pilihan_ganda'){
      show(opsiRow, true); show(kpg, true); show(kpgk, false); show(kbs, false); if (bs) bs.required = false;
    } else if (t === 'pilihan_ganda_kompleks'){
      show(opsiRow, true); show(kpg, false); show(kpgk, true); show(kbs, false); if (bs) bs.required = false;
      // pastikan checkbox terlihat & aktif
      if (kpgk) {
        Array.from(kpgk.querySelectorAll('input[type="checkbox"]')).forEach(function(cb){ try{ cb.style.display=''; cb.disabled = false; if (cb.parentElement) cb.parentElement.style.display = ''; }catch(e){} });
      }
    } else if (t === 'benar_salah'){
      show(opsiRow, false); show(kpg, false); show(kpgk, false); show(kbs, true); if (bs) bs.required = true;
    } else {
      show(opsiRow, false); show(kpg, false); show(kpgk, false); show(kbs, false); if (bs) bs.required = false;
    }
  } catch(e) { /* no-op */ }
};

document.addEventListener('DOMContentLoaded', function(){ try{ var nodes = document.querySelectorAll('select#tipe_soal, select#tipe_soal_edit, select[name="tipe_soal"]'); nodes.forEach(function(n){ n.addEventListener('change', function(){ window.syncQuestionTypeControls(n.value, n.closest('form')); }); }); }catch(e){} });

// removed debug console log for production

// Remove old collapsible behavior for charts: always show charts across devices
(function(){
  function showAllCharts(){
    try {
      var charts = Array.from(document.querySelectorAll('.chart-container'));
      charts.forEach(function(chart){ chart.style.display = ''; });
      // Remove any previously injected toggle buttons
      var toggles = Array.from(document.querySelectorAll('.chart-toggle'));
      toggles.forEach(function(btn){ btn.remove(); });
    } catch(e){}
  }
  document.addEventListener('DOMContentLoaded', showAllCharts);
  window.addEventListener('resize', showAllCharts);
})();

// Auto-convert action links/buttons to icon-only by adding `icon-btn` class
document.addEventListener('DOMContentLoaded', function(){
  try {
    var patterns = [ /action=edit/i, /action=builder/i, /action=delete/i, /delete_force/i, /delete\?/i, /hapus/i, /preview/i, /take\.php/i, /export/i, /download/i, /import/i, /clone/i, /reset_password/i ];
    var anchors = Array.from(document.querySelectorAll('a'));
    anchors.forEach(function(a){
      try {
        var href = a.getAttribute('href') || '';
        var cls = a.getAttribute('class') || '';
        // only process anchors that look like actions or already have btn/btn-action
        if (!href) return;
        var isBtn = /\bbtn\b|\bbtn-action\b|\baction-btn\b|\bicon-btn\b/.test(cls);
        var matches = patterns.some(function(rx){ return rx.test(href); });
        if (matches || isBtn) {
          if (!/\bicon-btn\b/.test(cls)) {
            a.className = (cls + ' icon-btn').trim();
          }
        }
      } catch(e){}
    });
    
    // Also convert certain <button> elements (non-submit) that are clearly action buttons
    var actionButtonWords = ['Edit','Hapus','Hapus Paksa','Delete','Pratinjau','Preview','Export','Unduh','Import','Impor','Clone','Reset','Reset Password','Batal','Cetak'];
    var buttons = Array.from(document.querySelectorAll('button'));
    buttons.forEach(function(btn){
      try {
        var t = (btn.textContent || btn.innerText || '').trim();
        var type = (btn.getAttribute('type') || '').toLowerCase();
        if (!t) return;
        // Skip primary submit/save buttons
        if (type === 'submit' || /simpan|terapkan|kumpulkan|kembali|kirim/i.test(t)) return;
        // If exact match to action words (case-insensitive) then convert
        for (var i=0;i<actionButtonWords.length;i++){
          if (t.toLowerCase() === actionButtonWords[i].toLowerCase()) {
            var cls = btn.getAttribute('class') || '';
            if (!/\bicon-btn\b/.test(cls)) {
              btn.className = (cls + ' icon-btn').trim();
            }
            break;
          }
        }
      } catch(e){}
    });
  } catch(e){}
});