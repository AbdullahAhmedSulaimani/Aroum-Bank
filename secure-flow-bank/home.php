<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aurum Bank - بنك آمن وموثوق</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:"Cairo",sans-serif;direction:rtl;background:#110810;color:#fff;overflow-x:hidden}
        a{text-decoration:none;color:inherit}

        /* NAV */
        nav{position:fixed;top:0;width:100%;z-index:100;padding:16px 60px;display:flex;align-items:center;justify-content:space-between;background:rgba(15,23,42,0.85);backdrop-filter:blur(12px);border-bottom:1px solid rgba(255,255,255,0.06)}
        .nav-logo{display:flex;align-items:center;gap:12px;font-size:20px;font-weight:800}
        .nav-logo .icon{width:40px;height:40px;background:linear-gradient(135deg,#f43f8a,#e11d6a);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:18px}
        .nav-links{display:flex;align-items:center;gap:8px}
        .nav-links a{padding:8px 18px;border-radius:8px;font-size:14px;font-weight:600;color:#94a3b8;transition:.2s}
        .nav-links a:hover{color:#fff;background:rgba(255,255,255,0.07)}
        .nav-links .btn-login{background:#f43f8a;color:#fff;padding:9px 22px;border-radius:8px}
        .nav-links .btn-login:hover{background:#c01558}

        /* HERO */
        .hero{min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;padding:120px 24px 80px;position:relative;overflow:hidden}
        .hero-bg{position:absolute;inset:0;background:radial-gradient(ellipse 80% 60% at 50% 0%,rgba(26,86,219,0.25),transparent),radial-gradient(ellipse 60% 40% at 80% 80%,rgba(59,130,246,0.1),transparent)}
        .hero-badge{display:inline-flex;align-items:center;gap:8px;background:rgba(244,63,138,0.15);border:1px solid rgba(26,86,219,0.3);border-radius:50px;padding:6px 18px;font-size:13px;color:#93c5fd;margin-bottom:28px}
        .hero h1{font-size:clamp(38px,6vw,72px);font-weight:900;line-height:1.15;margin-bottom:24px;background:linear-gradient(135deg,#fff 0%,#93c5fd 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
        .hero p{font-size:18px;color:#94a3b8;max-width:560px;margin:0 auto 40px;line-height:1.8}
        .hero-btns{display:flex;gap:14px;justify-content:center;flex-wrap:wrap}
        .btn-primary-lg{background:linear-gradient(135deg,#f43f8a,#e11d6a);color:#fff;padding:14px 36px;border-radius:12px;font-size:16px;font-weight:700;display:inline-flex;align-items:center;gap:10px;box-shadow:0 8px 30px rgba(244,63,138,0.40);transition:.2s}
        .btn-primary-lg:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(26,86,219,0.5)}
        .btn-outline-lg{background:transparent;color:#fff;padding:14px 36px;border-radius:12px;font-size:16px;font-weight:700;border:2px solid rgba(255,255,255,0.15);display:inline-flex;align-items:center;gap:10px;transition:.2s}
        .btn-outline-lg:hover{background:rgba(255,255,255,0.07);border-color:rgba(255,255,255,0.3)}

        /* STATS */
        .stats{padding:60px 60px;display:grid;grid-template-columns:repeat(4,1fr);gap:24px;max-width:1100px;margin:0 auto;width:100%}
        .stat-box{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:28px 24px;text-align:center;transition:.2s}
        .stat-box:hover{background:rgba(255,255,255,0.07)}
        .stat-box .num{font-size:36px;font-weight:800;color:#fff;margin-bottom:6px}
        .stat-box .lbl{font-size:13px;color:#64748b}

        /* FEATURES */
        .section{padding:80px 60px;max-width:1200px;margin:0 auto;width:100%}
        .section-badge{font-size:12px;font-weight:700;color:#f43f8a;text-transform:uppercase;letter-spacing:2px;margin-bottom:12px}
        .section-title{font-size:clamp(28px,4vw,44px);font-weight:800;margin-bottom:16px;line-height:1.2}
        .section-sub{font-size:16px;color:#64748b;max-width:500px;line-height:1.8}
        .features-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:24px;margin-top:48px}
        .feat-card{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:20px;padding:32px 28px;transition:.3s}
        .feat-card:hover{background:rgba(26,86,219,0.08);border-color:rgba(26,86,219,0.3);transform:translateY(-4px)}
        .feat-icon{width:52px;height:52px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:22px;margin-bottom:20px}
        .feat-card h3{font-size:18px;font-weight:700;margin-bottom:10px;color:#fff}
        .feat-card p{font-size:14px;color:#64748b;line-height:1.8}

        /* SERVICES */
        .services-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:24px;margin-top:48px}
        .srv-card{background:linear-gradient(135deg,rgba(26,86,219,0.1),rgba(59,130,246,0.05));border:1px solid rgba(26,86,219,0.2);border-radius:20px;padding:36px 32px;display:flex;gap:24px;align-items:flex-start;transition:.3s}
        .srv-card:hover{border-color:rgba(26,86,219,0.5);transform:translateY(-3px)}
        .srv-icon{width:60px;height:60px;background:linear-gradient(135deg,#f43f8a,#e11d6a);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:26px;flex-shrink:0}
        .srv-card h3{font-size:18px;font-weight:700;margin-bottom:8px}
        .srv-card p{font-size:14px;color:#64748b;line-height:1.7}

        /* SECURITY SECTION */
        .security-section{background:linear-gradient(135deg,rgba(26,86,219,0.08),rgba(15,23,42,0)),rgba(15,23,42,0.5);border:1px solid rgba(244,63,138,0.15);border-radius:28px;padding:60px;margin:80px 60px;display:grid;grid-template-columns:1fr 1fr;gap:60px;align-items:center}
        .security-list{list-style:none;margin-top:28px;display:flex;flex-direction:column;gap:16px}
        .security-list li{display:flex;align-items:center;gap:14px;font-size:15px;color:#94a3b8}
        .security-list li i{color:#10b981;font-size:18px;flex-shrink:0}
        .security-visual{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .sec-badge{background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:24px;text-align:center}
        .sec-badge i{font-size:32px;margin-bottom:10px;display:block}
        .sec-badge span{font-size:13px;color:#94a3b8;display:block;margin-top:4px}
        .sec-badge strong{font-size:15px;font-weight:700;color:#fff}

        /* CTA */
        .cta-section{text-align:center;padding:100px 60px;background:linear-gradient(135deg,rgba(244,63,138,0.15),rgba(59,130,246,0.05));margin:0 60px;border-radius:28px;border:1px solid rgba(244,63,138,0.15)}

        /* FOOTER */
        footer{padding:40px 60px;border-top:1px solid rgba(255,255,255,0.06);display:flex;justify-content:space-between;align-items:center;margin-top:80px}
        footer .copy{font-size:13px;color:#475569}
        footer .foot-links{display:flex;gap:24px}
        footer .foot-links a{font-size:13px;color:#475569;transition:.2s}
        footer .foot-links a:hover{color:#fff}

        @media(max-width:768px){
            nav{padding:14px 20px}
            .stats{grid-template-columns:repeat(2,1fr);padding:40px 20px}
            .section{padding:60px 20px}
            .features-grid{grid-template-columns:1fr}
            .services-grid{grid-template-columns:1fr}
            .security-section{grid-template-columns:1fr;padding:36px;margin:40px 20px}
            .cta-section{margin:0 20px;padding:60px 24px}
            footer{flex-direction:column;gap:16px;padding:30px 20px;text-align:center}
        }
    </style>
    <script>
        (function(){
            var t = localStorage.getItem('aub_theme') || 'dark';
            document.documentElement.setAttribute('data-theme', t);
        })();
    </script>
    <style>
        /* ── Toggle button ── */
        .home-theme-toggle {
            width:38px; height:38px; border-radius:10px; border:none; cursor:pointer;
            background:rgba(255,255,255,0.12); color:#fff; font-size:15px;
            display:flex; align-items:center; justify-content:center;
            transition:.2s; position:relative; flex-shrink:0;
        }
        .home-theme-toggle:hover { background:rgba(255,255,255,0.22); }
        .home-theme-toggle .icon-sun,
        .home-theme-toggle .icon-moon { position:absolute; transition:.3s; }
        [data-theme="dark"]  .home-theme-toggle .icon-sun  { opacity:1; transform:scale(1); }
        [data-theme="dark"]  .home-theme-toggle .icon-moon { opacity:0; transform:scale(0); }
        [data-theme="light"] .home-theme-toggle             { background:rgba(0,0,0,0.07); color:#2a1020; }
        [data-theme="light"] .home-theme-toggle:hover       { background:rgba(0,0,0,0.13); }
        [data-theme="light"] .home-theme-toggle .icon-sun  { opacity:0; transform:scale(0); }
        [data-theme="light"] .home-theme-toggle .icon-moon { opacity:1; transform:scale(1); }

        /* ── Light mode: full overrides ── */
        [data-theme="light"] body { background:#fff1f5; color:#1a0f12; }

        /* Nav */
        [data-theme="light"] nav {
            background:rgba(255,255,255,0.97);
            border-bottom:1px solid #cbd5e1;
            backdrop-filter:blur(12px);
        }
        [data-theme="light"] nav .nav-logo .logo-text { color:#1a0f12; }
        [data-theme="light"] nav .nav-links a { color:#334155; }
        [data-theme="light"] nav .nav-links a:hover { color:#1a0f12; background:#fde8f0; }
        [data-theme="light"] nav .nav-links .btn-login { background:#e11d6a; color:#fff; }
        [data-theme="light"] nav .nav-links .btn-login:hover { background:#c01558; }

        /* Hero — keep dark blue so it looks premium */
        [data-theme="light"] .hero { background:#110810; }
        [data-theme="light"] .hero h1 {
            background:linear-gradient(135deg,#fff 0%,#93c5fd 100%);
            -webkit-background-clip:text;
            -webkit-text-fill-color:transparent;
        }

        /* Stats bar */
        [data-theme="light"] .stats-section {
            background:#ffffff;
            border-top:1px solid #cbd5e1;
            border-bottom:1px solid #cbd5e1;
        }
        [data-theme="light"] .stat-box {
            background:#fff1f5;
            border:1px solid #cbd5e1;
        }
        [data-theme="light"] .stat-box:hover { background:#fde8f0; }
        [data-theme="light"] .stat-box .num { color:#1a0f12; }
        [data-theme="light"] .stat-box .lbl { color:#475569; }

        /* Features section */
        [data-theme="light"] .features-section { background:#fde8f0; }
        [data-theme="light"] .features-section h2 { color:#1a0f12; }
        [data-theme="light"] .features-section > p { color:#475569; }
        [data-theme="light"] .feat-card {
            background:#ffffff;
            border:1px solid #cbd5e1;
        }
        [data-theme="light"] .feat-card:hover {
            background:#ffeef5;
            border-color:#e11d6a;
        }
        [data-theme="light"] .feat-card h3 { color:#1a0f12; }
        [data-theme="light"] .feat-card p  { color:#475569; }

        /* Services section */
        [data-theme="light"] .services-section { background:#ffffff; }
        [data-theme="light"] .services-section h2 { color:#1a0f12; }
        [data-theme="light"] .services-section > p { color:#475569; }
        [data-theme="light"] .srv-card {
            background:#fff1f5;
            border:1px solid #cbd5e1;
        }
        [data-theme="light"] .srv-card:hover { border-color:#e11d6a; background:#ffeef5; }
        [data-theme="light"] .srv-card h3 { color:#1a0f12; }
        [data-theme="light"] .srv-card p  { color:#475569; }

        /* Security section — keep dark */
        [data-theme="light"] .security-section { background:#110810; }

        /* CTA — keep blue */
        [data-theme="light"] .cta-section { background:linear-gradient(135deg,#e11d6a,#a01248); }

        /* Footer — keep dark */
        [data-theme="light"] footer { background:#110810; }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-logo">
        <div class="icon"><i class="fas fa-shield-halved"></i></div>
        Aurum Bank
    </div>
    <div class="nav-links">
        <a href="#features">المميزات</a>
        <a href="#services">الخدمات</a>
        <a href="#security">الامان</a>
        <a href="register.php">انشاء حساب</a>
        <button class="home-theme-toggle" id="homeThemeToggle" title="تبديل الوضع">
                <i class="fas fa-sun icon-sun"></i>
                <i class="fas fa-moon icon-moon"></i>
            </button>
            <a href="login.php" class="btn-login"><i class="fas fa-arrow-right-to-bracket"></i> تسجيل الدخول</a>
    </div>
</nav>

<!-- HERO -->
<div class="hero">
    <div class="hero-bg"></div>
    <div style="position:relative;z-index:1">
        <div class="hero-badge"><i class="fas fa-shield-check"></i> بنك رقمي موثوق وآمن 100%</div>
        <h1>مصرفيتك الرقمية<br>الآمنة والذكية</h1>
        <p>إدر حساباتك وبطاقاتك وتحويلاتك بكل سهولة وأمان. تشفير عسكري وحماية متعددة الطبقات.</p>
        <div class="hero-btns">
            <a href="register.php" class="btn-primary-lg"><i class="fas fa-user-plus"></i> ابدأ مجاناً</a>
            <a href="login.php" class="btn-outline-lg"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
        </div>
    </div>
</div>

<!-- STATS -->
<div style="background:rgba(255,255,255,0.02);border-top:1px solid rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.05)">
<div class="stats">
    <div class="stat-box"><div class="num">+50K</div><div class="lbl">عميل نشط</div></div>
    <div class="stat-box"><div class="num">256</div><div class="lbl">تشفير AES-256</div></div>
    <div class="stat-box"><div class="num">99.9%</div><div class="lbl">وقت التشغيل</div></div>
    <div class="stat-box"><div class="num">24/7</div><div class="lbl">دعم فوري</div></div>
</div>
</div>

<!-- FEATURES -->
<div class="section" id="features">
    <div class="section-badge">المميزات</div>
    <div class="section-title">كل ما تحتاجه في مكان واحد</div>
    <div class="section-sub">منصة مصرفية متكاملة تجمع بين السهولة والامان العالي</div>
    <div class="features-grid">
        <div class="feat-card">
            <div class="feat-icon" style="background:rgba(244,63,138,0.15);color:#f43f8a"><i class="fas fa-arrow-right-arrow-left"></i></div>
            <h3>تحويلات فورية</h3>
            <p>حول الاموال بين الحسابات فوراً مع تأكيد بـ OTP في كل عملية لضمان الامان الكامل.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:rgba(16,185,129,0.15);color:#10b981"><i class="fas fa-credit-card"></i></div>
            <h3>بطاقات ذكية</h3>
            <p>اصدر بطاقات Visa وMastercard افتراضية مع تشفير كامل لبيانات البطاقة.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:rgba(245,158,11,0.15);color:#f59e0b"><i class="fas fa-chart-line"></i></div>
            <h3>تحليلات مالية</h3>
            <p>تتبع مصروفاتك ودخلك بتقارير تفصيلية وإحصاءات شهرية دقيقة.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:rgba(239,68,68,0.15);color:#ef4444"><i class="fas fa-shield-halved"></i></div>
            <h3>حماية متعددة الطبقات</h3>
            <p>CSRF، Brute Force Protection، 2FA وتشفير AES-256 لحماية بياناتك.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:rgba(139,92,246,0.15);color:#8b5cf6"><i class="fas fa-wallet"></i></div>
            <h3>حسابات متعددة</h3>
            <p>افتح حسابات جارية وتوفير واستثمار وادر جميعها من لوحة تحكم واحدة.</p>
        </div>
        <div class="feat-card">
            <div class="feat-icon" style="background:rgba(6,182,212,0.15);color:#06b6d4"><i class="fas fa-clock-rotate-left"></i></div>
            <h3>سجل كامل</h3>
            <p>راجع جميع معاملاتك السابقة مع تفاصيل دقيقة وامكانية التصفية والبحث.</p>
        </div>
    </div>
</div>

<!-- SERVICES -->
<div class="section" id="services" style="padding-top:20px">
    <div class="section-badge">الخدمات</div>
    <div class="section-title">خدمات مصرفية شاملة</div>
    <div class="section-sub">نقدم لك باقة متكاملة من الخدمات المالية الرقمية</div>
    <div class="services-grid">
        <div class="srv-card">
            <div class="srv-icon"><i class="fas fa-building-columns"></i></div>
            <div>
                <h3>الحسابات البنكية</h3>
                <p>افتح حسابات جارية وتوفير واستثمار بضغطة واحدة مع ارقام حساب سعودية IBAN.</p>
            </div>
        </div>
        <div class="srv-card">
            <div class="srv-icon"><i class="fas fa-money-bill-transfer"></i></div>
            <div>
                <h3>التحويلات الآنية</h3>
                <p>حول الاموال داخل المنصة فوراً مع رقم مرجعي لكل عملية وتأكيد بكود OTP.</p>
            </div>
        </div>
        <div class="srv-card">
            <div class="srv-icon"><i class="fas fa-credit-card"></i></div>
            <div>
                <h3>البطاقات الافتراضية</h3>
                <p>اصدر بطاقات Visa وMastercard لحساباتك مع تشفير كامل للبيانات الحساسة.</p>
            </div>
        </div>
        <div class="srv-card">
            <div class="srv-icon"><i class="fas fa-mobile-screen"></i></div>
            <div>
                <h3>المصادقة الثنائية</h3>
                <p>احمي حسابك بـ Google Authenticator وكودات OTP لكل عملية مالية مهمة.</p>
            </div>
        </div>
        <div class="srv-card">
            <div class="srv-icon" style="background:linear-gradient(135deg,#10b981,#059669)"><i class="fas fa-chart-line"></i></div>
            <div>
                <h3>الاستثمارات الذكية</h3>
                <p>استثمر في الأسهم السعودية، السندات الحكومية، الذهب والعقارات مع متابعة لحظية لأداء محفظتك.</p>
            </div>
        </div>
    </div>
</div>

<!-- SECURITY -->
<div class="security-section" id="security">
    <div>
        <div class="section-badge">الامان</div>
        <div class="section-title">حمايتك اولويتنا</div>
        <p style="color:#64748b;font-size:15px;line-height:1.8;margin-top:12px">نستخدم أحدث معايير الامان العالمية لحماية بياناتك وأموالك على مدار الساعة.</p>
        <ul class="security-list">
            <li><i class="fas fa-check-circle"></i> تشفير AES-256-CBC لجميع البيانات الحساسة</li>
            <li><i class="fas fa-check-circle"></i> حماية CSRF لمنع الطلبات المزيفة</li>
            <li><i class="fas fa-check-circle"></i> حد معدل الطلبات ضد هجمات Brute Force</li>
            <li><i class="fas fa-check-circle"></i> مصادقة ثنائية TOTP متوافقة مع Google Authenticator</li>
            <li><i class="fas fa-check-circle"></i> تأكيد بـ OTP لكل عملية تحويل مالي</li>
            <li><i class="fas fa-check-circle"></i> سجل امني كامل لجميع العمليات</li>
        </ul>
    </div>
    <div class="security-visual">
        <div class="sec-badge"><i class="fas fa-lock" style="color:#f43f8a"></i><strong>AES-256</strong><span>تشفير عسكري</span></div>
        <div class="sec-badge"><i class="fas fa-shield-halved" style="color:#10b981"></i><strong>2FA</strong><span>مصادقة ثنائية</span></div>
        <div class="sec-badge"><i class="fas fa-ban" style="color:#f59e0b"></i><strong>CSRF</strong><span>حماية الطلبات</span></div>
        <div class="sec-badge"><i class="fas fa-eye-slash" style="color:#8b5cf6"></i><strong>Privacy</strong><span>خصوصية تامة</span></div>
    </div>
</div>

<!-- CTA -->
<div class="cta-section">
    <div class="section-badge" style="color:#93c5fd">ابدأ الآن</div>
    <h2 style="font-size:clamp(28px,4vw,48px);font-weight:800;margin:12px 0 16px">جاهز لتجربة مصرفية آمنة؟</h2>
    <p style="color:#64748b;font-size:16px;margin-bottom:36px">انضم الآن واحصل على حساب مجاني مع جميع الميزات</p>
    <div class="hero-btns">
        <a href="register.php" class="btn-primary-lg"><i class="fas fa-user-plus"></i> انشاء حساب مجاني</a>
        <a href="login.php" class="btn-outline-lg"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="copy">2026 Aurum Bank. جميع الحقوق محفوظة.</div>
    <div class="foot-links">
        <a href="#">الشروط والاحكام</a>
        <a href="#">سياسة الخصوصية</a>
        <a href="login.php">تسجيل الدخول</a>
    </div>
</footer>

<script>
// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a=>{
    a.addEventListener('click',e=>{
        e.preventDefault();
        document.querySelector(a.getAttribute('href'))?.scrollIntoView({behavior:'smooth'});
    });
});
// Navbar scroll effect
window.addEventListener('scroll',()=>{
    document.querySelector('nav').style.background=
        window.scrollY>50?'rgba(15,23,42,0.97)':'rgba(15,23,42,0.85)';
});
</script>

<script>
    // Theme toggle for home page
    var homeToggle = document.getElementById('homeThemeToggle');
    if (homeToggle) {
        homeToggle.addEventListener('click', function(){
            var cur = document.documentElement.getAttribute('data-theme') || 'dark';
            var next = cur === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            localStorage.setItem('aub_theme', next);
        });
    }
</script>
</body>
</html>
