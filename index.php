<?php
// Set parameter cookie session lebih aman sebelum session_start
if (session_status() === PHP_SESSION_NONE) {
    $cookieParams = [
        'httponly' => true,
        'samesite' => 'Lax',
        // set 'secure' => true bila aplikasi berjalan di HTTPS
        'secure' => false
    ];
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params($cookieParams);
    } else {
        // fallback untuk PHP < 7.3 (tanpa samesite)
        session_set_cookie_params(0, '/', '', $cookieParams['secure'], $cookieParams['httponly']);
    }
}

session_start();

// Generate CSRF token jika belum ada
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Jika sudah login, redirect ke dashboard sesuai role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    switch($_SESSION['role']) {
        case 'admin':
            header("Location: admin/dashboard.php");
            break;
        case 'guru':
            header("Location: guru/dashboard.php");
            break;
        case 'siswa':
            header("Location: siswa/dashboard.php");
            break;
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LMS SMKS KGB2</title>
    <link rel="icon" type="image/png" href="/lms_kgb2/assets/img/logo-kgb2.png">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1e5ba8 0%, #3a7bc8 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px 20px 80px; /* keep space for footer but smaller */
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -webkit-text-size-adjust: 100%;
        }
        
        /* Background Pattern */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                repeating-linear-gradient(45deg, transparent, transparent 35px, rgba(255,255,255,.05) 35px, rgba(255,255,255,.05) 70px);
            pointer-events: none;
        }
        
        .login-container {
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 80px rgba(0,0,0,0.4);
            max-width: 1100px;
            width: 100%;
            display: grid;
            grid-template-columns: 1fr 1fr;
            align-items: center; /* center columns vertically for symmetry */
            min-height: 540px; /* ensure consistent vertical space on desktop */
            overflow: hidden;
            position: relative;
            z-index: 1;
        }
        
        /* Left Section - Login Form */
        .left-section {
            padding: 48px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 24px;
        }
        
        .logo-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            margin-bottom: 12px;
            flex-direction: column; /* place logo above the text */
        }
        
        .logo-img {
            width: 70px;
            height: 70px;
            object-fit: contain;
            margin-bottom: 6px;
        }
        
        .logo-text h1 {
            font-size: 28px; /* larger on desktop/laptop */
            color: #1e5ba8;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
            text-align: center;
        }
        
        .logo-text p {
            font-size: 12px;
            color: #666;
            margin: 5px 0 0 0;
            font-weight: 400;
        }
        
        .welcome-text {
            text-align: center;
            margin-bottom: 28px;
        }
        
        .welcome-text h2 {
            font-size: 24px; /* slightly smaller */
            color: #2C3E50;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .welcome-text p {
            font-size: 14px;
            color: #7f8c8d;
        }
        
        .form-group {
            margin-bottom: 18px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: #2C3E50;
            font-weight: 500;
            font-size: 14px;
        }
        
        .input-wrapper {
            position: relative;
        }
        
        .input-icon {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #95a5a6;
            font-size: 18px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 2px solid #e8ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.18s;
            font-family: 'Poppins', sans-serif;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #1e5ba8;
            box-shadow: 0 0 0 4px rgba(30, 91, 168, 0.1);
        }
        
        .form-group input::placeholder {
            color: #bdc3c7;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px 16px;
            background: linear-gradient(135deg, #1e5ba8 0%, #164a8a 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-top: 10px;
            box-shadow: 0 8px 20px rgba(30, 91, 168, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(30, 91, 168, 0.4);
            background: linear-gradient(135deg, #164a8a 0%, #1e5ba8 100%);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-error {
            background: #fee;
            color: #c33;
            border: 2px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 2px solid #cfc;
        }
        
        .login-info {
            margin-top: 25px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            font-size: 13px;
            color: #666;
            line-height: 1.8;
        }
        
        .login-info strong {
            color: #2C3E50;
        }
        
        /* Right Section - Slideshow */
        .right-section {
            background: #f8f9fa;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 48px 60px; /* match left-section vertical padding for symmetry */
            position: relative;
        }
        
        .slideshow-container {
            width: 100%;
            max-width: 450px;
            position: relative;
            border-radius: 20px;
            overflow: hidden;
            background: #fff; /* show white behind slideshow instead of gray */
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }
        
        .slide {
            display: none;
            width: 100%;
            animation: fadeIn 1s;
        }

        .slide.active {
            display: block;
        }

        .slide img {
            width: 100%;
            height: 400px;
            object-fit: cover;
            display: block;
        }
        
        @keyframes fadeIn {
            from { opacity: 0.4; }
            to { opacity: 1; }
        }
        
        .slide-caption {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
            color: white;
            padding: 30px 20px 20px;
            font-size: 14px;
            text-align: center;
        }
        /* Dots */
        .dots-container {
            text-align: center;
            margin-top: 20px;
        }
        
        .dot {
            height: 10px;
            width: 10px;
            margin: 0 5px;
            background-color: #bbb;
            border-radius: 50%;
            display: inline-block;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .dot.active {
            background-color: #1e5ba8;
            width: 30px;
            border-radius: 5px;
        }
        
        .dot:hover {
            background-color: #1e5ba8;
        }
        
        /* Responsive */
        @media (max-width: 968px) {
            .login-container {
                grid-template-columns: 1fr;
            }
            
            .right-section {
                display: none;
            }
            
            .left-section {
                padding: 40px 30px;
            }
        }
        
        @media (max-width: 480px) {
            .left-section {
                padding: 30px 20px;
            }
            
            .logo-img {
                width: 50px;
                height: 50px;
            }
            
            .logo-text h1 {
                font-size: 18px;
            }
            
            .welcome-text h2 {
                font-size: 18px;
            }
        }
    </style>
    <style>
        .site-footer { position: fixed; bottom: 0; left: 0; right: 0; padding: 10px 16px; text-align: center; color: #fff; font-size: 12px; z-index: 2; }
        .site-footer small { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="login-container">
        <!-- LEFT SECTION - LOGIN FORM -->
        <div class="left-section">
            <div class="logo-section">
                <div class="logo-wrapper">
                    <img src="assets/img/logo-kgb2.png" alt="Logo SMKS KGB2" class="logo-img">
                    <div class="logo-text">
                        <h1>SMKS Karya Guna Bhakti 2</h1>
                        <p>Portal Resmi Learning Management System (LMS)</p>
                    </div>
                </div>
            </div>
            
            <div class="welcome-text">
                <h2>Selamat Datang</h2>
                <p>Silakan login untuk melanjutkan</p>
            </div>
            
            <?php
            if (isset($_SESSION['error'])) {
                echo '<div class="alert alert-error">⚠️ ' . $_SESSION['error'] . '</div>';
                unset($_SESSION['error']);
            }
            if (isset($_SESSION['success'])) {
                echo '<div class="alert alert-success">✓ ' . $_SESSION['success'] . '</div>';
                unset($_SESSION['success']);
            }
            ?>
            
            <form action="login_process.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(($_SESSION['csrf_token']) ?? ''); ?>">
                <div class="form-group">
                    <label>Username</label>
                    <div class="input-wrapper">
                        <span class="input-icon">👤</span>
                        <input type="text" name="username" placeholder="Masukkan username Anda" required autofocus>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Password</label>
                    <div class="input-wrapper">
                        <span class="input-icon"><i class="fas fa-lock" aria-hidden="true"></i></span>
                        <input type="password" name="password" placeholder="Masukkan password Anda" required>
                    </div>
                </div>
                
                <button type="submit" class="btn-login">Login</button>
            </form>
            
                    </div>
        
        <!-- RIGHT SECTION - SLIDESHOW -->
        <div class="right-section">
            <div class="slideshow-container">
                <div class="slide active">
                    <img src="assets/img/kegiatan/foto1.jpeg" alt="Kegiatan Pembelajaran 1">
                    <div class="slide-caption">Seminar & angklung SMP/MTs</div>
                </div>
                
                <div class="slide">
                    <img src="assets/img/kegiatan/foto2.jpeg" alt="Kegiatan Pembelajaran 2">
                    <div class="slide-caption">Seminar & angklung SMP/MTs_2</div>
                </div>
                
                <div class="slide">
                    <img src="assets/img/kegiatan/foto3.jpeg" alt="Kegiatan Pembelajaran 3">
                    <div class="slide-caption">Seminar dan Praktik MS. Excel SMP/MTs</div>
                </div>
                
                <div class="slide">
                    <img src="assets/img/kegiatan/foto4.jpeg" alt="Kegiatan Pembelajaran 4">
                    <div class="slide-caption">Seminar Literasi Gen-Z dan Alfa</div>
                </div>
                
                <div class="slide">
                    <img src="assets/img/kegiatan/foto5.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Praktikum Kegiatan Seminar SMP/MTs</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto6.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Classmeeting Semester Genap</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto7.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Pemenang Classmeeting</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto8.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Seminar Bersama adik-adik SMP/MTs</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto9.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Opening Classmeeting #smtgenap</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto10.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Kampanye Calon Ketua OSIS</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto11.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">MUBES OSIS 2025</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto12.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Kegiatan Isro & Mi'roj Nabi Muhammad SAW</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto13.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Upacara Memperingati HARDIKNAS Kampus B</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto14.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Foto Bersama Memperingati HARDIKNAS 2025</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto15.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Upacara Memperingati HARDIKNAS Kampus A</div>
                </div>
                <div class="slide">
                    <img src="assets/img/kegiatan/foto16.jpeg" alt="Kegiatan Pembelajaran 5">
                    <div class="slide-caption">Kegiatan Uji Kompetensi Keahlian</div>
                </div>
            </div>
            
            <div class="dots-container" id="dots"></div>
        </div>
    </div>

    <footer class="site-footer">
        © 2025 Learning Management System<br>
        <small>by tripleone. All right reserved</small>
    </footer>
    
    <script>
        let slideIndex = 0;
        let slideTimer;

        function buildDots() {
            const slides = document.getElementsByClassName('slide');
            const dotsContainer = document.getElementById('dots');
            if (!dotsContainer) return;
            dotsContainer.innerHTML = '';
            for (let i = 0; i < slides.length; i++) {
                const span = document.createElement('span');
                span.className = 'dot' + (i === 0 ? ' active' : '');
                span.addEventListener('click', () => { currentSlide(i + 1); });
                dotsContainer.appendChild(span);
            }
        }
        
        // Auto slideshow
        function showSlides() {
            const slides = document.getElementsByClassName('slide');
            const dots = document.getElementsByClassName('dot');
            if (!slides.length) return;

            for (let i = 0; i < slides.length; i++) {
                slides[i].classList.remove('active');
            }
            for (let i = 0; i < dots.length; i++) {
                dots[i].classList.remove('active');
            }

            slideIndex++;
            if (slideIndex > slides.length) slideIndex = 1;

            slides[slideIndex - 1].classList.add('active');
            if (dots[slideIndex - 1]) dots[slideIndex - 1].classList.add('active');

            slideTimer = setTimeout(showSlides, 4000);
        }

        function currentSlide(n) {
            clearTimeout(slideTimer);
            slideIndex = n - 1;
            showSlides();
        }

        // Build dots and start slideshow after DOM ready
        document.addEventListener('DOMContentLoaded', () => {
            buildDots();
            showSlides();
        });
    </script>
</body>
</html>
