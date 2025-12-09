<?php
/**
 * File: guru/dashboard.php
 * Fungsi: Dashboard utama untuk guru
 */

session_start();

// Proteksi sesi dan role
require_once '../includes/check_session.php';
require_once '../includes/check_role.php';
check_role(['guru']);

require_once '../config/database.php';
require_once '../includes/functions.php';

// Ambil data statistik guru
$guru_id = $_SESSION['user_id'];

// Ambil tahun ajaran aktif melalui helper
$tahun_ajaran_aktif = get_tahun_ajaran_aktif();

// Hitung total assignment (kelas yang diajar)
$sql_assignment = "SELECT COUNT(*) as total FROM assignment_guru WHERE guru_id = '$guru_id'";
if ($tahun_ajaran_aktif) {
    $sql_assignment .= " AND tahun_ajaran_id = '{$tahun_ajaran_aktif['id']}'";
}
$result_assignment = query($sql_assignment);
$total_assignment = fetch_assoc($result_assignment)['total'];

// Hitung total materi yang sudah diupload
$sql_materi = "SELECT COUNT(*) as total FROM materi m 
               JOIN assignment_guru ag ON m.assignment_id = ag.id 
               WHERE ag.guru_id = '$guru_id'";
$result_materi = query($sql_materi);
$total_materi = fetch_assoc($result_materi)['total'];

// Hitung total tugas yang sudah dibuat
$sql_tugas = "SELECT COUNT(*) as total FROM tugas t 
              JOIN assignment_guru ag ON t.assignment_id = ag.id 
              WHERE ag.guru_id = '$guru_id'";
$result_tugas = query($sql_tugas);
$total_tugas = fetch_assoc($result_tugas)['total'];

// Hitung tugas yang perlu diperiksa (status 'submitted')
$sql_pending = "SELECT COUNT(*) as total 
                FROM pengumpulan_tugas pt
                JOIN tugas t ON pt.tugas_id = t.id
                JOIN assignment_guru ag ON t.assignment_id = ag.id
                WHERE ag.guru_id = '$guru_id' AND pt.status = 'submitted'";
$result_pending = query($sql_pending);
$tugas_pending = fetch_assoc($result_pending)['total'];

// Ambil daftar assignment (kelas + mapel yang diajar)
$sql_kelas = "SELECT ag.id as assignment_id, 
                     mp.nama_mapel, mp.kode_mapel,
                     k.nama_kelas, k.tingkat,
                     j.nama_jurusan, j.singkatan,
                     ta.nama_tahun_ajaran
              FROM assignment_guru ag
              JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
              JOIN kelas k ON ag.kelas_id = k.id
              JOIN jurusan j ON k.jurusan_id = j.id
              JOIN tahun_ajaran ta ON ag.tahun_ajaran_id = ta.id
              WHERE ag.guru_id = '$guru_id'";
if ($tahun_ajaran_aktif) {
    $sql_kelas .= " AND ag.tahun_ajaran_id = '{$tahun_ajaran_aktif['id']}'";
}
$sql_kelas .= " ORDER BY k.tingkat, k.nama_kelas, mp.nama_mapel";
$result_kelas = query($sql_kelas);
$kelas_list = fetch_all($result_kelas);

// Ambil 5 tugas terbaru yang perlu diperiksa
$sql_tugas_pending = "SELECT pt.id, pt.tanggal_submit, pt.status,
                             t.judul_tugas, t.deadline,
                             u.nama_lengkap as nama_siswa, u.nisn,
                             k.nama_kelas,
                             mp.nama_mapel
                      FROM pengumpulan_tugas pt
                      JOIN tugas t ON pt.tugas_id = t.id
                      JOIN assignment_guru ag ON t.assignment_id = ag.id
                      JOIN users u ON pt.siswa_id = u.id
                      JOIN kelas k ON u.kelas_id = k.id
                      JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
                      WHERE ag.guru_id = '$guru_id' AND pt.status = 'submitted'
                      ORDER BY pt.tanggal_submit ASC
                      LIMIT 5";
$result_tugas_pending = query($sql_tugas_pending);
$tugas_pending_list = fetch_all($result_tugas_pending);

// Ambil 5 materi terbaru
$sql_materi_terbaru = "SELECT m.id, m.judul_materi, m.tanggal_upload,
                              mp.nama_mapel,
                              k.nama_kelas
                       FROM materi m
                       JOIN assignment_guru ag ON m.assignment_id = ag.id
                       JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
                       JOIN kelas k ON ag.kelas_id = k.id
                       WHERE ag.guru_id = '$guru_id'
                       ORDER BY m.tanggal_upload DESC
                       LIMIT 5";
$result_materi_terbaru = query($sql_materi_terbaru);
$materi_terbaru = fetch_all($result_materi_terbaru);
// Ambil daftar ujian terbaru untuk ditampilkan di dashboard (ringkasan)
$sql_ujian_dashboard = "SELECT s.id, s.judul_ujian, s.waktu_mulai, s.waktu_selesai, mp.nama_mapel, k.nama_kelas
                       FROM soal s
                       JOIN assignment_guru ag ON s.assignment_id = ag.id
                       JOIN mata_pelajaran mp ON ag.mapel_id = mp.id
                       JOIN kelas k ON ag.kelas_id = k.id
                       WHERE ag.guru_id = '$guru_id'
                       ORDER BY s.waktu_mulai DESC
                       LIMIT 6";
$ujian_dashboard = fetch_all(query($sql_ujian_dashboard));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Guru - LMS SMKS KGB2</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f5f7fa;
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 260px;
            background: linear-gradient(180deg, #1e5ba8 0%, #164a8a 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
            display: flex;
            flex-direction: column;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header img {
            width: 60px;
            height: 60px;
            margin-bottom: 10px;
            filter: drop-shadow(0 0 6px rgba(255,255,255,0.95)); /* shadow putih ditebalkan */
        }
        
        .sidebar-header h2 {
            font-size: 18px; /* kecilkan agar proporsional */
            font-weight: 700;
            letter-spacing: .2px;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            font-size: 12px;
            opacity: 0.8;
        }
        
        .sidebar-menu {
            padding: 20px 0;
            flex: 1 1 auto;
        }
        
        .menu-label {
            padding: 10px 25px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(255,255,255,0.5);
            font-weight: 600;
        }
        
        .menu-item {
            padding: 15px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .menu-item:hover,
        .menu-item.active {
            background: rgba(255,255,255,0.1);
            border-left-color: #D4AF37;
        }
        
        .menu-item i {
            font-size: 18px;
            width: 20px;
        }
        
        .menu-item span {
            font-size: 14px;
            font-weight: 500;
        }
        
        .menu-item .badge {
            margin-left: auto;
            background: #e74c3c;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .sidebar-footer {
            padding: 20px;
            background: rgba(0,0,0,0.2);
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: auto;
            text-align: center;
        }
        .sidebar-footer p {
            font-size: 12px;
            color: rgba(255, 252, 252, 1);
            margin: 0;
            line-height: 1.4;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        /* Top Navbar */
        .top-navbar {
            background: white;
            padding: 20px 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .navbar-title h1 {
            font-size: 24px;
            color: #2C3E50;
            font-weight: 600;
        }
        
        .navbar-title p {
            font-size: 13px;
            color: #7f8c8d;
            margin-top: 5px;
        }
        
        .navbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #1e5ba8, #3a7bc8);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 18px;
        }
        
        .user-details h4 {
            font-size: 14px;
            color: #2C3E50;
            font-weight: 600;
        }
        
        .user-details p {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .btn-logout {
            padding: 10px 20px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        
        /* Content Area */
        .content-area {
            padding: 30px;
            flex: 1;
        }
        
        /* Welcome Card */
        .welcome-card {
            background: linear-gradient(135deg, #1e5ba8 0%, #3a7bc8 100%);
            padding: 35px;
            border-radius: 20px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(30, 91, 168, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .welcome-content h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .welcome-content p {
            font-size: 14px;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        
        .tahun-ajaran-badge {
            background: rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 25px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 13px;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.12);
        }
        
        .stat-icon {
            width: 65px;
            height: 65px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }
        
        .stat-icon.blue { background: linear-gradient(135deg, #1e5ba8, #3a7bc8); }
        .stat-icon.green { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .stat-icon.orange { background: linear-gradient(135deg, #e67e22, #f39c12); }
        .stat-icon.red { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .stat-icon.purple { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        
        .stat-info h3 {
            font-size: 30px;
            color: #2C3E50;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            font-size: 13px;
            color: #7f8c8d;
            font-weight: 500;
        }
        
        /* Quick Actions */
        .quick-actions {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 18px;
            color: #2C3E50;
            font-weight: 600;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            padding: 18px 20px;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            text-decoration: none;
            color: #2C3E50;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .action-btn i {
            font-size: 20px;
            color: #1e5ba8;
        }
        
        .action-btn:hover {
            background: #1e5ba8;
            color: white;
            border-color: #1e5ba8;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(30, 91, 168, 0.3);
        }
        
        .action-btn:hover i {
            color: white;
        }
        
        /* Content Panels */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .content-panel {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .panel-header h3 {
            font-size: 16px;
            color: #2C3E50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .panel-header a {
            font-size: 13px;
            color: #1e5ba8;
            text-decoration: none;
            font-weight: 500;
        }
        
        .panel-header a:hover {
            text-decoration: underline;
        }
        
        /* Item List */
        .item-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #1e5ba8;
            transition: all 0.3s;
        }
        
        .item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 8px;
        }
        
        .item-title {
            font-weight: 600;
            color: #2C3E50;
            font-size: 14px;
        }
        
        .item-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-blue { background: #e3f2fd; color: #1e5ba8; }
        .badge-green { background: #e8f5e9; color: #27ae60; }
        .badge-red { background: #ffebee; color: #e74c3c; }
        .badge-orange { background: #fff3e0; color: #e67e22; }
        
        .item-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .item-meta span {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Kelas Cards */
        .kelas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 15px;
        }
        
        .kelas-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 18px;
            text-decoration: none;
            transition: all 0.3s;
            text-align: center;
        }
        
        .kelas-card:hover {
            border-color: #1e5ba8;
            background: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .kelas-card .mapel-name {
            font-size: 13px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 8px;
        }
        
        .kelas-card .kelas-name {
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .kelas-card .jurusan {
            font-size: 11px;
            color: #1e5ba8;
            font-weight: 500;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        .empty-state i {
            font-size: 50px;
            margin-bottom: 15px;
            opacity: 0.3;
        }
        
        .empty-state p {
            font-size: 14px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
            }
            
            .sidebar.active {
                width: 260px;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .top-navbar {
                padding: 15px 20px;
            }
            
            .navbar-title h1 {
                font-size: 20px;
            }
            
            .user-details {
                display: none;
            }
            
            .content-area {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
            }
            
            /* Compact Ujian Saya panel - ringkasan seperti Kelola Ujian */
            .content-panel { background: #fff; border-radius: 8px; padding: 12px; margin-bottom: 18px; border: 1px solid #eef2f6; }
            .content-panel .panel-header { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:8px; }
            .content-panel .panel-header h3 { margin:0; font-size:16px; }
            .content-panel .panel-header a { font-size:13px; color:#1f6feb; text-decoration:none; }
            .content-panel .item-list { display:flex; flex-direction:column; gap:8px; }
            .content-panel .item { display:flex; justify-content:space-between; align-items:center; padding:10px 12px; border-radius:6px; background:#fff; border:1px solid #f3f6fb; }
            .content-panel .item .item-title { font-weight:600; font-size:14px; }
            .content-panel .item .item-meta { font-size:12px; color:#6b7280; display:flex; gap:10px; align-items:center; }
            .content-panel .item .item-meta span { display:inline-flex; gap:6px; align-items:center; }
            .content-panel .item .item-meta i { color:#6b7280; font-size:12px; }
            .action-btn { display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:8px; border:1px solid #e6eefb; background:#fff; color:#2c7be5; text-decoration:none; }
            .action-btn i { font-size:14px; }
            @media (max-width:768px) {
                .content-panel .item { flex-direction:column; align-items:flex-start; gap:8px; }
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../assets/img/logo-kgb2.png" alt="Logo">
            <h2>LMS SMKS KGB2</h2>
            <p>Portal Guru</p>
        </div>
        <nav class="sidebar-menu">
            <div class="menu-label">Menu Utama</div>
            <a href="dashboard.php" class="menu-item active">
                <i class="fas fa-home"></i>
                <span>Dashboard</span>
            </a>
            
            <div class="menu-label">Pembelajaran</div>
            <a href="materi/index.php" class="menu-item">
                <i class="fas fa-book-open"></i>
                <span>Kelola Materi</span>
            </a>
            <a href="tugas/index.php" class="menu-item">
                <i class="fas fa-tasks"></i>
                <span>Kelola Tugas</span>
            </a>
            <a href="ujian/index.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Kelola Ujian</span>
            </a>
            
            <div class="menu-label">Wali Kelas</div>
            <?php
            // tampilkan menu Kontrol Akses Ujian bila guru ini adalah wali kelas
            $cek_wk = query("SELECT 1 FROM kelas WHERE wali_kelas_id=".$guru_id." LIMIT 1");
            if ($cek_wk && fetch_assoc($cek_wk)) {
            ?>
            <a href="ujian/akses.php" class="menu-item">
                <i class="fas fa-user-shield"></i>
                <span>Kontrol Akses Ujian</span>
            </a>
            <?php } ?>

            <div class="menu-label">Penilaian</div>
            <a href="nilai/tugas.php" class="menu-item">
                <i class="fas fa-clipboard-check"></i>
                <span>Penilaian Tugas</span>
                <?php if ($tugas_pending > 0): ?>
                    <span class="badge"><?php echo $tugas_pending; ?></span>
                <?php endif; ?>
            </a>
            <a href="nilai/ujian.php" class="menu-item">
                <i class="fas fa-spell-check"></i>
                <span>Penilaian Ujian</span>
            </a>
            <div class="menu-label">Kelola Nilai</div>
            <a href="nilai/rekap_uts.php" class="menu-item">
                <i class="fas fa-award"></i>
                <span>Nilai UTS</span>
            </a>
            <a href="nilai/rekap_uas.php" class="menu-item">
                <i class="fas fa-trophy"></i>
                <span>Nilai UAS</span>
            </a>
            <a href="nilai/rekap.php" class="menu-item">
                <i class="fas fa-chart-bar"></i>
                <span>Rekap Nilai</span>
            </a>
            <a href="../profile.php" class="menu-item">
                <i class="fas fa-user-cog"></i>
                <span>Profil Saya</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <p>Copyright © 2025 tripleone LMS<br>SMKS Karya Guna Bhakti 2</p>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navbar -->
        <nav class="top-navbar">
            <div class="navbar-title">
                <h1>Dashboard Guru</h1>
                <p>Kelola materi pembelajaran dan tugas siswa</p>
            </div>
            <div class="navbar-right">
                <div class="user-info">
                    <?php if (!empty($_SESSION['foto'])): ?>
                        <img src="../<?php echo htmlspecialchars(($_SESSION['foto']) ?? ''); ?>" alt="Avatar" style="width:45px; height:45px; border-radius:50%; object-fit:cover; border:2px solid #fff;">
                    <?php else: ?>
                        <div class="user-avatar">
                            <?php echo strtoupper(substr($_SESSION['nama_lengkap'], 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['kode_guru'] ?? 'Guru'); ?></p>
                    </div>
                </div>
                <a href="../logout.php" class="btn-logout">
                    <i class="fas fa-sign-out-alt"></i>
                    Logout
                </a>
            </div>
        </nav>

        <!-- Content Area -->
        <div class="content-area">
            <!-- Welcome Card -->
            <div class="welcome-card">
                <div class="welcome-content">
                    <h2><i class="fas fa-chalkboard-teacher" aria-hidden="true"></i> Selamat Datang, <?php echo htmlspecialchars(($_SESSION['nama_lengkap']) ?? ''); ?>!</h2>
                    <p>Kelola materi pembelajaran dan berikan tugas kepada siswa dengan mudah dan efisien.</p>
                    <?php if ($tahun_ajaran_aktif): ?>
                        <div class="tahun-ajaran-badge">
                            <i class="fas fa-calendar-alt"></i>
                            Tahun Ajaran <?php echo htmlspecialchars(($tahun_ajaran_aktif['nama_tahun_ajaran']) ?? ''); ?> - Semester <?php echo $tahun_ajaran_aktif['semester']; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple">
                        <i class="fas fa-chalkboard"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_assignment; ?></h3>
                        <p>Kelas Mengajar</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon blue">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_materi; ?></h3>
                        <p>Total Materi</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon green">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $total_tugas; ?></h3>
                        <p>Total Tugas</p>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon <?php echo $tugas_pending > 0 ? 'red' : 'orange'; ?>">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo (int)$tugas_pending; ?></h3>
                        <p>Tugas Menunggu Penilaian</p>
                    </div>
                </div>

                </div>

                <!-- Quick Actions -->
                <div class="quick-actions">
                    <div class="section-title"><i class="fas fa-bolt"></i> Aksi Cepat</div>
                    <div class="action-buttons">
                        <a href="materi/index.php?create=1" class="action-btn"><i class="fas fa-plus"></i> Tambah Materi</a>
                        <a href="tugas/index.php?create=1" class="action-btn"><i class="fas fa-plus-circle"></i> Buat Tugas</a>
                        <a href="ujian/index.php?create=1" class="action-btn"><i class="fas fa-file-circle-plus"></i> Buat Ujian</a>
                        <a href="nilai/rekap.php" class="action-btn"><i class="fas fa-chart-line"></i> Lihat Rekap Nilai</a>
                    </div>
                </div>

                <div class="content-grid">
                    <!-- Panel Kelas & Mapel -->
                    <div class="content-panel">
                        <div class="panel-header">
                            <h3><i class="fas fa-chalkboard-teacher"></i> Kelas yang Anda Ajar</h3>
                            <a href="assignment/index.php">Lihat semua</a>
                        </div>
                        <?php if (!empty($kelas_list)): ?>
                            <div class="kelas-grid">
                                <?php foreach ($kelas_list as $k): ?>
                                    <a class="kelas-card" href="materi/index.php?assignment_id=<?php echo (int)$k['assignment_id']; ?>">
                                        <div class="mapel-name"><?php echo htmlspecialchars(($k['nama_mapel']) ?? ''); ?> (<?php echo htmlspecialchars(($k['kode_mapel']) ?? ''); ?>)</div>
                                        <div class="kelas-name"><?php echo htmlspecialchars(($k['nama_kelas']) ?? ''); ?> - Tingkat <?php echo htmlspecialchars(($k['tingkat']) ?? ''); ?></div>
                                        <div class="jurusan"><?php echo htmlspecialchars(($k['singkatan']) ?? ''); ?> - TA <?php echo htmlspecialchars(($k['nama_tahun_ajaran']) ?? ''); ?></div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>Belum ada assignment pembelajaran untuk Anda.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Tugas Pending -->
                    <div class="content-panel">
                        <div class="panel-header">
                            <h3><i class="fas fa-clipboard-check"></i> Tugas Menunggu Penilaian</h3>
                            <a href="nilai/tugas.php">Lihat semua</a>
                        </div>
                        <?php if (!empty($tugas_pending_list)): ?>
                            <div class="item-list">
                                <?php foreach ($tugas_pending_list as $tp): ?>
                                    <div class="item">
                                        <div class="item-header">
                                            <div class="item-title"><?php echo htmlspecialchars(($tp['judul_tugas']) ?? ''); ?></div>
                                            <span class="item-badge badge-orange"><?php echo htmlspecialchars(($tp['status']) ?? ''); ?></span>
                                        </div>
                                        <div class="item-meta">
                                            <span><i class="fas fa-user-graduate"></i> <?php echo htmlspecialchars(($tp['nama_siswa']) ?? ''); ?> (<?php echo htmlspecialchars(($tp['nisn']) ?? ''); ?>)</span>
                                            <span><i class="fas fa-chalkboard"></i> <?php echo htmlspecialchars(($tp['nama_kelas']) ?? ''); ?></span>
                                            <span><i class="fas fa-book"></i> <?php echo htmlspecialchars(($tp['nama_mapel']) ?? ''); ?></span>
                                            <span><i class="fas fa-clock"></i> Diserahkan: <?php echo htmlspecialchars(($tp['tanggal_submit']) ?? ''); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-check-circle"></i>
                                <p>Tidak ada tugas yang menunggu penilaian.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Panel Materi Terbaru -->
                    <div class="content-panel">
                        <div class="panel-header">
                            <h3><i class="fas fa-book-open"></i> Materi Terbaru</h3>
                            <a href="materi/index.php">Lihat semua</a>
                        </div>
                        <?php if (!empty($materi_terbaru)): ?>
                            <div class="item-list">
                                <?php foreach ($materi_terbaru as $m): ?>
                                    <div class="item">
                                        <div class="item-header">
                                            <div class="item-title"><?php echo htmlspecialchars(($m['judul_materi']) ?? ''); ?></div>
                                            <span class="item-badge badge-blue">Kelas <?php echo htmlspecialchars(($m['nama_kelas']) ?? ''); ?></span>
                                        </div>
                                        <div class="item-meta">
                                            <span><i class="fas fa-book"></i> <?php echo htmlspecialchars(($m['nama_mapel']) ?? ''); ?></span>
                                            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars(($m['tanggal_upload']) ?? ''); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-info-circle"></i>
                                <p>Belum ada materi yang diupload.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Panel Ujian Saya (ringkasan) -->
                <div class="content-panel">
                    <div class="panel-header">
                        <h3><i class="fas fa-file-alt"></i> Ujian Saya</h3>
                        <a href="ujian/index.php">Kelola Ujian</a>
                    </div>
                    <?php if (!empty($ujian_dashboard)): ?>
                        <div class="item-list">
                            <?php foreach ($ujian_dashboard as $u): ?>
                                <div class="item">
                                    <div class="item-header">
                                        <div>
                                            <div class="item-title"><?php echo htmlspecialchars(($u['judul_ujian']) ?? ''); ?></div>
                                            <div class="item-meta">
                                                <span><i class="fas fa-book"></i> <?php echo htmlspecialchars(($u['nama_mapel']) ?? ''); ?></span>
                                                <span><i class="fas fa-chalkboard"></i> <?php echo htmlspecialchars(($u['nama_kelas']) ?? ''); ?></span>
                                                <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars(($u['waktu_mulai']) ?? ''); ?> - <?php echo htmlspecialchars(($u['waktu_selesai']) ?? ''); ?></span>
                                            </div>
                                        </div>
                                        <div style="display:flex;gap:8px;align-items:center">
                                            <a class="action-btn" href="ujian/index.php?action=builder&soal_id=<?php echo (int)$u['id']; ?>"><i class="fas fa-edit"></i></a>
                                            <a class="action-btn" href="ujian/index.php?action=delete_force&id=<?php echo (int)$u['id']; ?>" title="Hapus Paksa" onclick="return confirm('Hapus paksa?');"><i class="fas fa-trash" aria-hidden="true"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-info-circle"></i>
                            <p>Belum ada ujian yang Anda buat.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</body>
</html>


