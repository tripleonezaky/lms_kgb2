<?php
/**
 * File: admin/sidebar.php
 * Fungsi: Sidebar Navigation untuk Admin Panel
 * Features:
 * - Responsive Sidebar
 * - Active Menu Detection
 * - Icon-based Navigation
 * - Collapse/Expand Support
 */

// Get current page
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
.sidebar {
    width: 260px;
    background: linear-gradient(180deg, #356fc7ff 0%, #2a5298 100%);
    color: white;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    z-index: 1000;
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 25px 20px;
    background: rgba(0,0,0,0.2);
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: bold;
}

.sidebar-header p {
    margin: 5px 0 0 0;
    font-size: 13px;
    opacity: 0.8;
}

.sidebar-menu {
    padding: 20px 0;
}

.sidebar-scroll {
    flex: 1 1 auto;
    overflow-y: auto;
    min-height: 0;
}

.menu-item {
    display: flex;
    align-items: center;
    padding: 10px 16px;
    gap: 8px;
    color: rgba(255,255,255,0.95);
    text-decoration: none;
    transition: background 0.18s ease, transform 0.18s ease;
    border-left: 3px solid transparent;
}

.menu-item:hover {
    background: rgba(255,255,255,0.06);
    color: white;
    border-left-color: rgba(255,255,255,0.12);
}

.menu-item.active {
    background: rgba(255,255,255,0.12);
    color: white;
    border-left-color: rgba(255,255,255,0.18);
    font-weight: 700;
}

.menu-item .icon { display:none; }
.menu-item .text { font-size: 14px; font-weight:600; display: inline-block; }
/* Make menu items left-aligned (no justified spacing) */
.menu-item { display: flex; align-items: center; justify-content: flex-start; padding: 10px 16px; gap: 8px; }
/* Ensure label can size naturally next to icon (do not force flex:1) */
.sidebar .menu-item > .text { width: auto !important; flex: 0 1 auto !important; margin-left: 4px !important; white-space: nowrap; overflow: visible !important; color: inherit !important; text-indent: 0 !important; }
/* Minor submenu indent which still aligns left with parent items */
.submenu .menu-item { padding-left: 20px; }
/* Keep menu-items simple and aligned */
.menu-item { display: flex; align-items: center; padding: 10px 16px; gap: 8px; }
.menu-item .win11-icon { width: 28px; height: 28px; margin-right: 8px; flex-shrink: 0; }
/* Small submenu indent to nudge text slightly to the right for visual alignment */
.submenu .menu-item { padding-left: 20px; }

/* Icon (no background tile) - simple icon with thin white border */
.menu-item .win11-icon {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: transparent;
    border-radius: 6px;
    border: 2px solid rgba(255,255,255,0.20);
}
.menu-item .win11-icon i {
    font-size: 16px;
    color: rgba(255,255,255,0.95);
}

/* Subtle hover/active treatments for the icon (no heavy shadow) */
.menu-item:hover .win11-icon { transform: translateY(-2px); border-color: rgba(255,255,255,0.34); }
.menu-item.active .win11-icon { transform: translateY(-1px); border-color: rgba(255,255,255,0.42); }

/* Reduce visual noise on small screens */
@media (max-width: 768px) {
  .menu-item { padding: 10px 12px; }
  .menu-item .text { font-size: 13px; }
  .menu-item .win11-icon { width:40px; height:40px; }
}

.menu-divider {
    padding: 15px 20px 8px 20px;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 1px;
    color: #ffffffff;
    font-weight: bold;
    border-top: 1px solid rgba(255,255,255,0.1);
    margin-top: 10px;
    display: flex;
    align-items: center;
}
.menu-divider .chev {
    margin-left: auto;
    opacity: 0.9;
}

.menu-divider:first-of-type {
    border-top: none;
    margin-top: 0;
}

.sidebar-footer {
    padding: 20px;
    background: rgba(0,0,0,0.2);
    border-top: 1px solid rgba(255, 248, 248, 0.1);
    margin-top: auto;
}

/* Scrollbar styling */
.sidebar::-webkit-scrollbar {
    width: 6px;
}

.sidebar::-webkit-scrollbar-track {
    background: rgba(0,0,0,0.1);
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255,255,255,0.3);
    border-radius: 10px;
}

.sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255,255,255,0.5);
}
</style>

<div class="sidebar">
    <div class="sidebar-header">
        <img src="../assets/img/logo-kgb2.png" alt="Logo KGB2" style="width: 50px; height: 50px; margin-bottom: 10px; object-fit: contain; filter: drop-shadow(0 2px 4px rgba(255,255,255,0.9)) drop-shadow(0 -2px 4px rgba(255,255,255,0.6)) brightness(1.3) contrast(1.1);">
        <h2>Portal LMS SMKS KGB2</h2>
        <p>Admin Panel</p>
    </div>
    
    <div class="sidebar-scroll">
        <nav class="sidebar-menu">
        <a href="dashboard.php" class="menu-item <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <span class="win11-icon win11-blue"><i class="fas fa-home" aria-hidden="true"></i></span>
            <span class="text">Dashboard</span>
        </a>

        <div class="menu-divider" onclick="toggleSubmenu('md')" style="cursor:pointer;">Master Data <span class="chev">&#9660;</span></div>
        <div id="submenu-md" class="submenu" style="display: none;">
            <a href="kelola_tahun_ajaran.php" class="menu-item <?php echo $current_page == 'kelola_tahun_ajaran.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-purple"><i class="fas fa-calendar" aria-hidden="true"></i></span>
                <span class="text">Tahun Ajaran</span>
            </a>
            <a href="kelola_jurusan.php" class="menu-item <?php echo $current_page == 'kelola_jurusan.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-red"><i class="fas fa-book" aria-hidden="true"></i></span>
                <span class="text">Jurusan</span>
            </a>
            <a href="kelola_kelas.php" class="menu-item <?php echo $current_page == 'kelola_kelas.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-orange"><i class="fas fa-users" aria-hidden="true"></i></span>
                <span class="text">Kelas</span>
            </a>
            <a href="kelola_mapel.php" class="menu-item <?php echo $current_page == 'kelola_mapel.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-teal"><i class="fas fa-book-open" aria-hidden="true"></i></span>
                <span class="text">Mata Pelajaran</span>
            </a>
        </div>

        <div class="menu-divider" onclick="toggleSubmenu('um')" style="cursor:pointer;">User Management <span class="chev">&#9660;</span></div>
        <div id="submenu-um" class="submenu" style="display: none;">
            <a href="kelola_guru.php" class="menu-item <?php echo $current_page == 'kelola_guru.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-purple"><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i></span>
                <span class="text">Kelola Guru</span>
            </a>
            <a href="kelola_siswa.php" class="menu-item <?php echo $current_page == 'kelola_siswa.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-blue"><i class="fas fa-user-graduate" aria-hidden="true"></i></span>
                <span class="text">Kelola Siswa</span>
            </a>
            <a href="assignment_guru.php" class="menu-item <?php echo $current_page == 'assignment_guru.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-green"><i class="fas fa-tasks" aria-hidden="true"></i></span>
                <span class="text">Assignment Guru</span>
            </a>
            <a href="import_export.php" class="menu-item <?php echo $current_page == 'import_export.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-gray"><i class="fas fa-file-import" aria-hidden="true"></i></span>
                <span class="text">Import/Export</span>
            </a>
        </div>

        <div class="menu-divider" onclick="toggleSubmenu('nr')" style="cursor:pointer;">Nilai & Rapor <span class="chev">&#9660;</span></div>
        <div id="submenu-nr" class="submenu" style="display: none;">
            <a href="komponen_nilai.php" class="menu-item <?php echo $current_page == 'komponen_nilai.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-gray"><i class="fas fa-list" aria-hidden="true"></i></span>
                <span class="text">Komponen Nilai</span>
            </a>
            <a href="input_nilai.php" class="menu-item <?php echo $current_page == 'input_nilai.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-orange"><i class="fas fa-pen" aria-hidden="true"></i></span>
                <span class="text">Input Nilai</span>
            </a>
            <a href="cetak_rapor.php" class="menu-item <?php echo $current_page == 'cetak_rapor.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-teal"><i class="fas fa-print" aria-hidden="true"></i></span>
                <span class="text">Cetak Rapor</span>
            </a>
            <a href="rekap_nilai.php" class="menu-item <?php echo $current_page == 'rekap_nilai.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-blue"><i class="fas fa-table" aria-hidden="true"></i></span>
                <span class="text">Leger/Rekap Nilai</span>
            </a>
        </div>

        <div class="menu-divider" onclick="toggleSubmenu('lap')" style="cursor:pointer;">Laporan <span class="chev">&#9660;</span></div>
        <div id="submenu-lap" class="submenu" style="display: none;">
            <a href="laporan_guru.php" class="menu-item <?php echo $current_page == 'laporan_guru.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-green"><i class="fas fa-file-alt" aria-hidden="true"></i></span>
                <span class="text">Laporan Guru</span>
            </a>
            <a href="laporan_siswa.php" class="menu-item <?php echo $current_page == 'laporan_siswa.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-blue"><i class="fas fa-file-alt" aria-hidden="true"></i></span>
                <span class="text">Laporan Siswa</span>
            </a>
            <a href="laporan_assignment.php" class="menu-item <?php echo $current_page == 'laporan_assignment.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-teal"><i class="fas fa-file-alt" aria-hidden="true"></i></span>
                <span class="text">Laporan Assignment</span>
            </a>
        </div>

        <div class="menu-divider" onclick="toggleSubmenu('sys')" style="cursor:pointer;">Pengaturan <span class="chev">&#9660;</span></div>
        <div id="submenu-sys" class="submenu" style="display: none;">
            <a href="profil_sekolah.php" class="menu-item <?php echo $current_page == 'profil_sekolah.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-gray"><i class="fas fa-school" aria-hidden="true"></i></span>
                <span class="text">Profil Sekolah</span>
            </a>
            <a href="ubah_password.php" class="menu-item <?php echo $current_page == 'ubah_password.php' ? 'active' : ''; ?>">
                <span class="win11-icon win11-gray"><i class="fas fa-key" aria-hidden="true"></i></span>
                <span class="text">Ubah Password</span>
            </a>
        </div>

        <div class="menu-divider">System</div>
        <a href="../logout.php" class="menu-item" style="color: #ff6b6b;">
            <span class="win11-icon win11-red"><i class="fas fa-sign-out-alt" aria-hidden="true"></i></span>
            <span class="text">Logout</span>
        </a>
    </nav>
    </div>
    
    <div class="sidebar-footer">
            <p style="font-size: 12px; color: #ffffffff; text-align: center; margin: 0;">
            &copy; 2025 Learning Management System<br>
            by tripleone. All right reserved
        </p>
    </div>
</div>

<script>
// Toggle submenu - ensure only one open at a time
function toggleSubmenu(key) {
    var all = document.querySelectorAll('.submenu');
    var target = document.getElementById('submenu-' + key);
    if (!target) return;
    var willOpen = (target.style.display === 'none' || target.style.display === '');
    all.forEach(function(s){ s.style.display = 'none'; });
    target.style.display = willOpen ? 'block' : 'none';
}

// Auto-open submenu yang memuat halaman aktif (and close others)
(function(){
    var map = {
    'kelola_tahun_ajaran.php': 'md',
    'kelola_jurusan.php': 'md',
    'kelola_kelas.php': 'md',
    'kelola_mapel.php': 'md',
    'kelola_guru.php': 'um',
    'kelola_siswa.php': 'um',
    'assignment_guru.php': 'um',
        'komponen_nilai.php': 'nr',
        'input_nilai.php': 'nr',
        'cetak_rapor.php': 'nr',
        'rekap_nilai.php': 'nr',
        'laporan_guru.php': 'lap',
        'laporan_siswa.php': 'lap',
        'laporan_assignment.php': 'lap',
        'profil_sekolah.php': 'sys',
        'ubah_password.php': 'sys',
        'import_export.php': 'um'
    };
    var current = '<?php echo $current_page; ?>';
    var submenus = document.querySelectorAll('.submenu');
    submenus.forEach(function(s){ s.style.display = 'none'; });
    if(map[current]) {
        var el = document.getElementById('submenu-' + map[current]);
        if (el) el.style.display = 'block';
    }
})();
</script>
