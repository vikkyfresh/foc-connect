<?php
session_start();
if(isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoC Connect — Faculty of Computing</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700;900&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-deep:   #0A3528;
            --green-mid:    #0F4C3A;
            --green-bright: #2E7D64;
            --green-light:  #4CAF88;
            --green-pale:   #E8F5EE;
            --gold:         #C9A84C;
            --gold-light:   #F0D080;
            --white:        #FAFDF9;
            --text-dark:    #0D1F18;
            --text-mid:     #3A5448;
            --text-light:   #7A9E90;
        }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body { font-family: 'DM Sans', sans-serif; background: var(--white); color: var(--text-dark); overflow-x: hidden; }

        /* NAV */
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            padding: 20px 60px;
            display: flex; align-items: center; justify-content: space-between;
            background: rgba(10,53,40,0.9); backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(201,168,76,0.15);
        }
        .nav-logo { font-family: 'Playfair Display', serif; font-size: 22px; font-weight: 700; color: var(--white); }
        .nav-logo span { color: var(--gold); }
        .nav-links { display: flex; gap: 36px; align-items: center; }
        .nav-links a { color: rgba(250,253,249,0.7); text-decoration: none; font-size: 14px; font-weight: 500; transition: color 0.2s; }
        .nav-links a:hover { color: var(--white); }
        .nav-cta { background: var(--gold) !important; color: var(--green-deep) !important; padding: 10px 24px; border-radius: 100px; font-weight: 700 !important; }
        .nav-cta:hover { background: var(--gold-light) !important; }

        /* HERO */
        .hero { min-height: 100vh; background: var(--green-deep); position: relative; display: flex; align-items: center; overflow: hidden; }
        .hero-bg-circle { position: absolute; border-radius: 50%; }
        .hc1 { width: 600px; height: 600px; background: radial-gradient(circle, rgba(46,125,100,0.2) 0%, transparent 70%); top: -150px; right: -100px; animation: pulse 7s ease-in-out infinite; }
        .hc2 { width: 400px; height: 400px; border: 1px solid rgba(201,168,76,0.1); bottom: -100px; left: -100px; animation: spin 25s linear infinite; }
        .hc3 { width: 200px; height: 200px; border: 1px solid rgba(201,168,76,0.08); bottom: 0; left: 50px; animation: spin 15s linear infinite reverse; }
        @keyframes pulse { 0%,100%{opacity:.6;transform:scale(1)} 50%{opacity:1;transform:scale(1.08)} }
        @keyframes spin { from{transform:rotate(0)} to{transform:rotate(360deg)} }
        @keyframes fadeUp { from{opacity:0;transform:translateY(28px)} to{opacity:1;transform:translateY(0)} }
        @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }

        .hero-inner {
            position: relative; z-index: 2; max-width: 1200px; margin: 0 auto;
            padding: 120px 60px 80px;
            display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center;
        }
        .hero-badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(201,168,76,0.12); border: 1px solid rgba(201,168,76,0.25);
            color: var(--gold-light); padding: 6px 16px; border-radius: 100px;
            font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase;
            margin-bottom: 28px; animation: fadeUp 0.8s ease both;
        }
        .hero-title {
            font-family: 'Playfair Display', serif;
            font-size: clamp(44px, 5.5vw, 76px); font-weight: 900; line-height: 1.0;
            color: var(--white); margin-bottom: 24px;
            animation: fadeUp 0.8s 0.1s ease both;
        }
        .hero-title .accent { color: var(--gold); display: block; }
        .hero-desc {
            font-size: 17px; line-height: 1.75; color: rgba(250,253,249,0.6);
            margin-bottom: 40px; max-width: 460px;
            animation: fadeUp 0.8s 0.2s ease both;
        }
        .hero-btns { display: flex; gap: 14px; flex-wrap: wrap; animation: fadeUp 0.8s 0.3s ease both; }
        .btn-gold {
            background: var(--gold); color: var(--green-deep);
            padding: 15px 34px; border-radius: 100px; font-size: 15px; font-weight: 700;
            text-decoration: none; transition: all 0.25s;
            box-shadow: 0 8px 28px rgba(201,168,76,0.3);
        }
        .btn-gold:hover { background: var(--gold-light); transform: translateY(-2px); box-shadow: 0 14px 36px rgba(201,168,76,0.4); }
        .btn-outline {
            background: transparent; color: var(--white);
            padding: 15px 34px; border-radius: 100px; font-size: 15px; font-weight: 600;
            text-decoration: none; border: 1px solid rgba(250,253,249,0.22); transition: all 0.25s;
        }
        .btn-outline:hover { background: rgba(255,255,255,0.07); border-color: rgba(255,255,255,0.45); }

        /* Phone mockup */
        .hero-visual { display: flex; justify-content: center; position: relative; animation: fadeUp 0.8s 0.4s ease both; }
        .phone {
            width: 270px; background: #0A1F17; border-radius: 44px; padding: 14px;
            box-shadow: 0 40px 80px rgba(0,0,0,0.55), inset 0 1px 0 rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.07);
        }
        .phone-screen { background: #152A20; border-radius: 32px; overflow: hidden; height: 500px; position: relative; }
        .phone-hdr { background: var(--green-mid); padding: 14px 16px; display: flex; align-items: center; gap: 10px; }
        .p-av { width: 34px; height: 34px; background: var(--green-bright); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 15px; }
        .p-name { font-size: 13px; font-weight: 600; color: white; }
        .p-stat { font-size: 10px; color: #4CAF88; }
        .p-msgs { padding: 14px; display: flex; flex-direction: column; gap: 10px; }
        .m { max-width: 78%; padding: 9px 13px; border-radius: 18px; font-size: 11.5px; line-height: 1.5; color: rgba(255,255,255,0.85); }
        .m-r { background: #1E3A2E; border-bottom-left-radius: 4px; align-self: flex-start; }
        .m-s { background: #2E7D64; border-bottom-right-radius: 4px; align-self: flex-end; }
        .m-t { font-size: 9px; opacity: 0.45; margin-top: 3px; text-align: right; }
        .p-inp {
            position: absolute; bottom: 14px; left: 14px; right: 14px;
            background: #1E3A2E; border-radius: 100px; padding: 9px 14px;
            display: flex; align-items: center; gap: 8px;
        }
        .p-inp-txt { font-size: 11px; color: rgba(255,255,255,0.25); flex: 1; }
        .p-send { width: 26px; height: 26px; background: var(--green-bright); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 11px; }
        .fbadge {
            position: absolute; background: white; border-radius: 14px; padding: 8px 14px;
            box-shadow: 0 10px 36px rgba(0,0,0,0.18);
            display: flex; align-items: center; gap: 7px;
            font-size: 12px; font-weight: 600; color: var(--text-dark);
            animation: float 3s ease-in-out infinite;
        }
        .fb1 { top: 30px; left: -55px; animation-delay: 0s; }
        .fb2 { bottom: 70px; right: -45px; animation-delay: 1.5s; }
        .fdot { width: 8px; height: 8px; border-radius: 50%; background: #4CAF88; }

        /* STATS */
        .stats { background: var(--gold); padding: 20px 60px; display: flex; justify-content: center; gap: 80px; flex-wrap: wrap; }
        .stat { text-align: center; }
        .stat-n { font-family: 'Playfair Display', serif; font-size: 30px; font-weight: 900; color: var(--green-deep); }
        .stat-l { font-size: 11px; font-weight: 700; color: var(--green-mid); letter-spacing: 0.5px; text-transform: uppercase; margin-top: 2px; }

        /* FEATURES */
        .features { padding: 120px 60px; max-width: 1200px; margin: 0 auto; }
        .s-label { font-size: 11px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: var(--green-bright); margin-bottom: 14px; }
        .s-title { font-family: 'Playfair Display', serif; font-size: clamp(34px, 4vw, 52px); font-weight: 900; color: var(--text-dark); line-height: 1.15; max-width: 580px; margin-bottom: 14px; }
        .s-sub { font-size: 16px; color: var(--text-light); max-width: 500px; line-height: 1.7; margin-bottom: 60px; }
        .feat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
        .feat-card {
            background: var(--white); border: 1px solid #DDE8E3; border-radius: 24px; padding: 34px 30px;
            transition: all 0.3s; position: relative; overflow: hidden;
        }
        .feat-card:hover { transform: translateY(-5px); box-shadow: 0 18px 50px rgba(15,76,58,0.1); border-color: var(--green-bright); }
        .feat-card.big { background: var(--green-mid); border-color: var(--green-bright); grid-row: span 2; }
        .feat-card.big .feat-title { color: white; }
        .feat-card.big .feat-desc { color: rgba(255,255,255,0.6); }
        .f-icon { width: 50px; height: 50px; background: var(--green-pale); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 18px; }
        .feat-card.big .f-icon { background: rgba(255,255,255,0.1); }
        .feat-title { font-family: 'Playfair Display', serif; font-size: 19px; font-weight: 700; color: var(--text-dark); margin-bottom: 9px; }
        .feat-desc { font-size: 14px; color: var(--text-light); line-height: 1.7; }
        .feat-tag { display: inline-block; margin-top: 14px; font-size: 10px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: var(--gold); background: rgba(201,168,76,0.15); padding: 4px 12px; border-radius: 100px; }

        /* HOW */
        .how { background: var(--green-deep); padding: 120px 60px; position: relative; overflow: hidden; }
        .how::before { content: ''; position: absolute; width: 500px; height: 500px; border-radius: 50%; background: radial-gradient(circle, rgba(46,125,100,0.18) 0%, transparent 70%); bottom: -150px; right: -100px; }
        .how-inner { max-width: 1200px; margin: 0 auto; position: relative; z-index: 2; }
        .how .s-label { color: var(--gold); }
        .how .s-title { color: white; }
        .how .s-sub { color: rgba(255,255,255,0.45); }
        .steps { display: grid; grid-template-columns: repeat(4,1fr); gap: 28px; margin-top: 60px; }
        .step { text-align: center; position: relative; }
        .step:not(:last-child)::after { content: '→'; position: absolute; top: 22px; right: -18px; color: rgba(201,168,76,0.35); font-size: 18px; }
        .step-n { width: 48px; height: 48px; background: rgba(201,168,76,0.12); border: 1px solid rgba(201,168,76,0.25); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-family: 'Playfair Display', serif; font-size: 18px; font-weight: 700; color: var(--gold); margin: 0 auto 18px; }
        .step-t { font-size: 15px; font-weight: 600; color: white; margin-bottom: 7px; }
        .step-d { font-size: 13px; color: rgba(255,255,255,0.4); line-height: 1.6; }

        /* DEPTS */
        .depts { padding: 120px 60px; max-width: 1200px; margin: 0 auto; }
        .dept-grid { display: grid; grid-template-columns: repeat(5,1fr); gap: 14px; margin-top: 44px; }
        .dept-card { background: var(--green-pale); border: 1px solid #D0E8DC; border-radius: 20px; padding: 26px 18px; text-align: center; transition: all 0.25s; }
        .dept-card:hover { background: var(--green-bright); border-color: var(--green-bright); transform: translateY(-4px); box-shadow: 0 12px 32px rgba(46,125,100,0.2); }
        .dept-card:hover .d-code, .dept-card:hover .d-name { color: white; }
        .d-icon { font-size: 30px; margin-bottom: 10px; }
        .d-code { font-size: 10px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; color: var(--green-bright); margin-bottom: 5px; }
        .d-name { font-size: 13px; font-weight: 600; color: var(--text-dark); line-height: 1.4; }

        /* CTA */
        .cta { margin: 0 60px 120px; background: linear-gradient(135deg, var(--green-mid) 0%, var(--green-deep) 100%); border-radius: 40px; padding: 80px; text-align: center; position: relative; overflow: hidden; }
        .cta::before { content: ''; position: absolute; width: 350px; height: 350px; border-radius: 50%; background: radial-gradient(circle, rgba(201,168,76,0.12) 0%, transparent 70%); top: -80px; right: -80px; }
        .cta::after { content: ''; position: absolute; width: 280px; height: 280px; border-radius: 50%; background: radial-gradient(circle, rgba(76,175,136,0.12) 0%, transparent 70%); bottom: -70px; left: -70px; }
        .cta-in { position: relative; z-index: 2; }
        .cta-title { font-family: 'Playfair Display', serif; font-size: clamp(34px, 4vw, 54px); font-weight: 900; color: white; margin-bottom: 14px; line-height: 1.1; }
        .cta-title span { color: var(--gold); }
        .cta-desc { font-size: 16px; color: rgba(255,255,255,0.55); margin-bottom: 38px; max-width: 440px; margin-left: auto; margin-right: auto; line-height: 1.7; }
        .cta-btns { display: flex; gap: 14px; justify-content: center; flex-wrap: wrap; }

        /* FOOTER */
        footer { background: var(--green-deep); border-top: 1px solid rgba(255,255,255,0.06); padding: 44px 60px 28px; }
        .foot-in { max-width: 1200px; margin: 0 auto; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px; }
        .foot-logo { font-family: 'Playfair Display', serif; font-size: 19px; font-weight: 700; color: white; }
        .foot-logo span { color: var(--gold); }
        .foot-links { display: flex; gap: 26px; }
        .foot-links a { font-size: 13px; color: rgba(255,255,255,0.4); text-decoration: none; transition: color 0.2s; }
        .foot-links a:hover { color: white; }
        .foot-copy { font-size: 12px; color: rgba(255,255,255,0.3); }

        /* RESPONSIVE */
        @media (max-width: 900px) {
            nav { padding: 16px 24px; }
            .nav-links { display: none; }
            .hero-inner { grid-template-columns: 1fr; padding: 100px 24px 60px; gap: 40px; }
            .hero-visual { order: -1; }
            .phone { width: 220px; }
            .phone-screen { height: 400px; }
            .fbadge { display: none; }
            .features, .depts { padding: 80px 24px; }
            .feat-grid { grid-template-columns: 1fr; }
            .feat-card.big { grid-row: span 1; }
            .stats { gap: 36px; padding: 20px 24px; }
            .steps { grid-template-columns: repeat(2,1fr); }
            .step:not(:last-child)::after { display: none; }
            .dept-grid { grid-template-columns: repeat(2,1fr); }
            .how { padding: 80px 24px; }
            .cta { margin: 0 24px 80px; padding: 48px 24px; }
            footer { padding: 36px 24px 24px; }
            .foot-in { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>

<!-- NAV -->
<nav>
    <div class="nav-logo">FoC <span>Connect</span></div>
    <div class="nav-links">
        <a href="#features">Features</a>
        <a href="#how">How It Works</a>
        <a href="#departments">Departments</a>
        <a href="login.php" class="nav-cta">Login</a>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-bg-circle hc1"></div>
    <div class="hero-bg-circle hc2"></div>
    <div class="hero-bg-circle hc3"></div>
    <div class="hero-inner">
        <div>
            <div class="hero-badge">🌿 Faculty of Computing</div>
            <h1 class="hero-title">Your Faculty.<br><span class="accent">Connected.</span></h1>
            <p class="hero-desc">FoC Connect brings every student, lecturer, and department together — real-time chat, assignments, announcements, and more in one place.</p>
            <div class="hero-btns">
                <a href="register.php" class="btn-gold">Get Started Free →</a>
                <a href="login.php" class="btn-outline">Sign In</a>
            </div>
        </div>
        <div class="hero-visual">
            <div class="fbadge fb1"><div class="fdot"></div>24 students online</div>
            <div class="phone">
                <div class="phone-screen">
                    <div class="phone-hdr">
                        <div class="p-av">👥</div>
                        <div><div class="p-name">CS Department</div><div class="p-stat">● 12 members online</div></div>
                    </div>
                    <div class="p-msgs">
                        <div class="m m-r">Assignment 2 has been uploaded 📚<div class="m-t">10:24 AM</div></div>
                        <div class="m m-s">Thanks! Got it ✓✓<div class="m-t">10:25 AM</div></div>
                        <div class="m m-r">Submission deadline is Friday 🗓️<div class="m-t">10:26 AM</div></div>
                        <div class="m m-s">Already submitted mine 😅<div class="m-t">10:28 AM</div></div>
                        <div class="m m-r" style="background:none;padding:4px 0;font-size:20px;">🎉🎉</div>
                        <div class="m m-s">Can someone share the notes?<div class="m-t">10:30 AM</div></div>
                    </div>
                    <div class="p-inp">
                        <span class="p-inp-txt">Type a message...</span>
                        <div class="p-send">➤</div>
                    </div>
                </div>
            </div>
            <div class="fbadge fb2">📝 3 assignments due</div>
        </div>
    </div>
</section>

<!-- STATS -->
<div class="stats">
    <div class="stat"><div class="stat-n">5</div><div class="stat-l">Departments</div></div>
    <div class="stat"><div class="stat-n">4</div><div class="stat-l">Academic Levels</div></div>
    <div class="stat"><div class="stat-n">100%</div><div class="stat-l">Free to Use</div></div>
    <div class="stat"><div class="stat-n">Real-time</div><div class="stat-l">Messaging</div></div>
</div>

<!-- FEATURES -->
<section class="features" id="features">
    <div class="s-label">What We Offer</div>
    <h2 class="s-title">Everything your faculty needs, in one place</h2>
    <p class="s-sub">From real-time chat to assignment submission — built for the way you actually study and work.</p>
    <div class="feat-grid">
        <div class="feat-card big">
            <div class="f-icon">💬</div>
            <div class="feat-title">Real-Time Chat</div>
            <div class="feat-desc">Message anyone in your department instantly. Group chats for every level, department-wide channels, and private DMs — all with read receipts, typing indicators, and file sharing.</div>
            <div class="feat-tag">✦ Core Feature</div>
        </div>
        <div class="feat-card">
            <div class="f-icon">📢</div>
            <div class="feat-title">Announcements</div>
            <div class="feat-desc">Lecturers and HODs broadcast important notices directly. Emergency alerts stand out instantly.</div>
        </div>
        <div class="feat-card">
            <div class="f-icon">📚</div>
            <div class="feat-title">Course Materials</div>
            <div class="feat-desc">Access lecture notes, slides, and resources — organized by course and level.</div>
        </div>
        <div class="feat-card">
            <div class="f-icon">📝</div>
            <div class="feat-title">Assignments</div>
            <div class="feat-desc">Submit work, track deadlines, and receive grades without leaving the platform.</div>
        </div>
        <div class="feat-card">
            <div class="f-icon">🎓</div>
            <div class="feat-title">Alumni Network</div>
            <div class="feat-desc">Stay connected with graduates. Get mentorship from those who walked the same path.</div>
        </div>
        <div class="feat-card">
            <div class="f-icon">💼</div>
            <div class="feat-title">Job Board</div>
            <div class="feat-desc">Internships and graduate roles posted directly by alumni and faculty partners.</div>
        </div>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how" id="how">
    <div class="how-inner">
        <div class="s-label">Getting Started</div>
        <h2 class="s-title">Up and running in minutes</h2>
        <p class="s-sub">No complicated setup. Register and you're immediately connected to your department.</p>
        <div class="steps">
            <div class="step">
                <div class="step-n">1</div>
                <div class="step-t">Register</div>
                <div class="step-d">Sign up with your faculty email. Matric number is auto-generated.</div>
            </div>
            <div class="step">
                <div class="step-n">2</div>
                <div class="step-t">Join Your Dept</div>
                <div class="step-d">Auto-added to your department and level group chats instantly.</div>
            </div>
            <div class="step">
                <div class="step-n">3</div>
                <div class="step-t">Connect</div>
                <div class="step-d">Message classmates, lecturers, and course reps in real time.</div>
            </div>
            <div class="step">
                <div class="step-n">4</div>
                <div class="step-t">Succeed</div>
                <div class="step-d">Never miss an assignment, announcement, or opportunity again.</div>
            </div>
        </div>
    </div>
</section>

<!-- DEPARTMENTS -->
<section class="depts" id="departments">
    <div class="s-label">Our Departments</div>
    <h2 class="s-title">All departments, one platform</h2>
    <p class="s-sub">FoC Connect serves every department in the Faculty of Computing.</p>
    <div class="dept-grid">
        <div class="dept-card"><div class="d-icon">💻</div><div class="d-code">CS</div><div class="d-name">Computer Science</div></div>
        <div class="dept-card"><div class="d-icon">📊</div><div class="d-code">DS</div><div class="d-name">Data Science</div></div>
        <div class="dept-card"><div class="d-icon">🔐</div><div class="d-code">CY</div><div class="d-name">Cyber Security</div></div>
        <div class="dept-card"><div class="d-icon">⚙️</div><div class="d-code">SE</div><div class="d-name">Software Engineering</div></div>
        <div class="dept-card"><div class="d-icon">🌐</div><div class="d-code">ICT</div><div class="d-name">Info & Comm. Tech</div></div>
    </div>
</section>

<!-- CTA -->
<div class="cta">
    <div class="cta-in">
        <h2 class="cta-title">Ready to join<br><span>FoC Connect?</span></h2>
        <p class="cta-desc">Join students and staff already using FoC Connect to stay informed, collaborate, and succeed.</p>
        <div class="cta-btns">
            <a href="register.php" class="btn-gold">Create Your Account →</a>
            <a href="login.php" class="btn-outline" style="border-color:rgba(255,255,255,0.25);">Already have an account</a>
        </div>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="foot-in">
        <div class="foot-logo">FoC <span>Connect</span></div>
        <div class="foot-links">
            <a href="login.php">Login</a>
            <a href="register.php">Register</a>
            <a href="#features">Features</a>
            <a href="#departments">Departments</a>
        </div>
        <div class="foot-copy">© <?php echo date('Y'); ?> Faculty of Computing. All rights reserved.</div>
    </div>
</footer>

</body>
</html>
