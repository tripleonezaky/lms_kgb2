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

// Auto hide alert after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
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

// Inject hamburger button into any page that has a .top-bar or .top-navbar and a .sidebar
(function(){
  try {
    document.addEventListener('DOMContentLoaded', function(){
      var sidebar = document.querySelector('.sidebar');
      var bars = document.querySelector('.top-bar') || document.querySelector('.top-navbar');
      if (!sidebar || !bars) return;
      // Avoid duplicate injection
      if (bars.querySelector('.hamburger-toggle')) return;
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
  } catch(e) { /* no-op */ }
})();

// Sidebar height helpers and auto-hide outside
function applySidebarHeight() {
  try {
    var sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    var vh = (window.visualViewport && window.visualViewport.height) ? window.visualViewport.height : window.innerHeight;
    sidebar.style.height = vh + 'px';
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

// Make chart containers collapsible on small screens to reduce UI clutter
(function(){
  function makeCollapsibleCharts(){
    try {
      var isMobile = (window.innerWidth || document.documentElement.clientWidth) <= 768;
      if (!isMobile) return;
      var charts = Array.from(document.querySelectorAll('.chart-container'));
      if (!charts || charts.length === 0) return;

      charts.forEach(function(chart, idx){
        // If already wrapped / toggled, skip
        if (chart.dataset.collapsible === '1') return;
        chart.dataset.collapsible = '1';
        // Create toggle button
        var btn = document.createElement('button');
        btn.className = 'chart-toggle show-on-mobile';
        btn.type = 'button';
        btn.textContent = (idx === 0) ? 'Tampilkan Statistik' : 'Tampilkan Bagian';
        btn.addEventListener('click', function(){
          if (chart.style.display === 'none' || getComputedStyle(chart).display === 'none'){
            chart.style.display = '';
            btn.textContent = 'Sembunyikan Statistik';
          } else {
            chart.style.display = 'none';
            btn.textContent = 'Tampilkan Statistik';
          }
        });
        // Initially hide chart and insert button before it
        chart.style.display = 'none';
        chart.parentNode.insertBefore(btn, chart);
      });
    } catch (e) { /* no-op */ }
  }

  document.addEventListener('DOMContentLoaded', makeCollapsibleCharts);
  window.addEventListener('resize', function(){ try { makeCollapsibleCharts(); } catch(e){} });
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