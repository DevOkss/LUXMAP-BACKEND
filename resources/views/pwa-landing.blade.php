<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Install LuxMap on your phone or computer — your school organizations, attendance, and payments.">
    <title>{{ $appName }} — Install the App</title>
    <link rel="icon" type="image/x-icon" href="/branding/luxmap.ico">
    <link rel="icon" type="image/png" sizes="192x192" href="/branding/luxmap.png">
    <link rel="apple-touch-icon" href="/branding/luxmap.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --green-900: #064E3B;
            --green-800: #065F46;
            --green-700: #047857;
            --green-600: #059669;
            --green-500: #10B981;
            --green-100: #D1FAE5;
            --green-50: #ECFDF5;
            --amber-500: #F59E0B;
            --amber-400: #FBBF24;
            --ink: #0F172A;
            --muted: #475569;
            --surface: #ffffff;
            --bg-soft: #F6FAF7;
            --border: #E2E8F0;
            --radius-lg: 24px;
            --radius-md: 16px;
            --shadow: 0 20px 50px -20px rgba(6,78,59,0.35);
            --shadow-sm: 0 10px 24px -12px rgba(6,78,59,0.25);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html { scroll-behavior: smooth; }
        body {
            font-family: "Plus Jakarta Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            color: var(--ink);
            background: var(--bg-soft);
            line-height: 1.55;
            overflow-x: hidden;
        }
        .container { width: 100%; max-width: 1120px; margin: 0 auto; padding: 0 24px; }
        section { padding: 72px 0; }

        /* ---------- Hero ---------- */
        .hero {
            position: relative;
            background:
                radial-gradient(1000px 500px at 85% -10%, rgba(16,185,129,0.25) 0%, transparent 60%),
                linear-gradient(160deg, #064E3B 0%, #065F46 45%, #047857 100%);
            color: #fff;
            padding: 96px 0 140px;
            overflow: hidden;
        }
        .hero::before, .hero::after {
            content: ""; position: absolute; border-radius: 50%;
            background: rgba(255,255,255,0.06);
        }
        .hero::before { width: 320px; height: 320px; left: -120px; top: -80px; }
        .hero::after { width: 420px; height: 420px; right: -160px; bottom: -160px; }
        .hero-grid { display: grid; grid-template-columns: 1fr 1.05fr; gap: 48px; align-items: center; position: relative; z-index: 1; }
        .badge {
            display: inline-flex; align-items: center; gap: 8px;
            background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.25);
            padding: 8px 14px; border-radius: 999px;
            font-size: 13px; font-weight: 600; color: #E7F6EE;
        }
        .badge .dot { width: 8px; height: 8px; border-radius: 50%; background: var(--amber-400); box-shadow: 0 0 10px var(--amber-400); }
        .hero h1 { font-size: clamp(34px, 5vw, 56px); font-weight: 800; line-height: 1.08; margin: 18px 0 18px; letter-spacing: -0.02em; }
        .hero h1 span { color: var(--amber-400); }
        .hero p.lead { font-size: 18px; color: #D9F2E6; max-width: 520px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 14px; margin-top: 30px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 10px;
            padding: 16px 26px; border-radius: 14px;
            font-family: inherit; font-size: 16px; font-weight: 700; cursor: pointer;
            text-decoration: none; border: none; transition: transform 0.15s, box-shadow 0.15s, background 0.2s;
        }
        .btn:active { transform: scale(0.98); }
        .btn-primary {
            background: var(--amber-500); color: #3B2504;
            box-shadow: 0 12px 28px -10px rgba(245,158,11,0.6);
        }
        .btn-primary:hover { background: var(--amber-400); transform: translateY(-2px); }
        .btn-ghost {
            background: rgba(255,255,255,0.12); color: #fff;
            border: 1px solid rgba(255,255,255,0.3);
        }
        .btn-ghost:hover { background: rgba(255,255,255,0.2); }
        .hero-meta { display: flex; flex-wrap: wrap; gap: 22px; margin-top: 32px; font-size: 13px; color: #C9EEDD; }
        .hero-meta b { display: block; font-size: 18px; color: #fff; }
        .hero-phones { display: flex; justify-content: center; align-items: center; position: relative; z-index: 1; }
        .phone {
            width: min(460px, 100%);
            background: transparent;
            border-radius: 24px;
            overflow: hidden;
        }
        .phone img { width: 100%; display: block; object-fit: contain; border-radius: 20px; }

        /* ---------- Section headers ---------- */
        .sec-head { text-align: center; max-width: 620px; margin: 0 auto 44px; }
        .sec-eyebrow { color: var(--green-700); font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; font-size: 13px; }
        .sec-head h2 { font-size: clamp(26px, 3.5vw, 38px); font-weight: 800; margin-top: 10px; letter-spacing: -0.02em; }
        .sec-head p { color: var(--muted); margin-top: 12px; font-size: 16px; }

        /* ---------- Features ---------- */
        .features-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; }
        .feature-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
            padding: 26px; box-shadow: var(--shadow-sm);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feature-card:hover { transform: translateY(-6px); box-shadow: var(--shadow); }
        .feature-icon {
            width: 52px; height: 52px; border-radius: 14px;
            background: var(--green-50); color: var(--green-700);
            display: flex; align-items: center; justify-content: center; margin-bottom: 16px;
        }
        .feature-icon svg { width: 26px; height: 26px; }
        .feature-card h3 { font-size: 17px; font-weight: 700; margin-bottom: 8px; }
        .feature-card p { font-size: 14px; color: var(--muted); }

        /* ---------- Screens showcase ---------- */
        .showcase { background: linear-gradient(180deg, #0B3B2C 0%, #064E3B 100%); color: #fff; }
        .showcase .sec-head p { color: #C9EEDD; }
        .showcase-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .shot {
            background: transparent; text-align: center;
        }
        .shot img {
            width: 100%; border-radius: 20px;
        }
        .shot p { margin-top: 14px; font-size: 14px; color: #D9F2E6; font-weight: 600; }

        /* ---------- How to install ---------- */
        .install-card {
            background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm); padding: 36px; max-width: 760px; margin: 0 auto;
        }
        .steps { display: grid; gap: 20px; }
        .step { display: flex; gap: 16px; align-items: flex-start; }
        .step-num {
            flex: 0 0 auto; width: 40px; height: 40px; border-radius: 12px;
            background: linear-gradient(135deg, var(--green-700), var(--green-600));
            color: #fff; font-weight: 800; font-size: 17px;
            display: flex; align-items: center; justify-content: center;
        }
        .step strong { display: block; font-size: 16px; }
        .step p { font-size: 14px; color: var(--muted); margin-top: 4px; }
        .install-cta { margin-top: 30px; display: flex; flex-direction: column; gap: 12px; align-items: center; text-align: center; }
        .install-cta .btn { width: 100%; max-width: 360px; }
        .note { font-size: 13px; color: var(--muted); max-width: 520px; }

        /* ---------- CTA band ---------- */
        .cta-band { background: linear-gradient(120deg, #F59E0B, #FBBF24); }
        .cta-band .inner {
            max-width: 760px; margin: 0 auto; text-align: center; color: #3B2504;
        }
        .cta-band h2 { font-size: clamp(24px, 3vw, 34px); font-weight: 800; }
        .cta-band p { margin-top: 10px; font-size: 16px; }
        .cta-band .btn { margin-top: 24px; background: var(--green-800); color: #fff; box-shadow: 0 12px 28px -10px rgba(6,78,59,0.5); }
        .cta-band .btn:hover { background: var(--green-700); }

        /* ---------- Footer ---------- */
        footer { background: #0B2C22; color: #9BC6B3; padding: 40px 0; text-align: center; font-size: 13px; }
        footer .url { color: #fff; font-weight: 600; word-break: break-all; }

        .toast {
            position: fixed; bottom: 24px; left: 50%; transform: translateX(-50%) translateY(20px);
            background: var(--ink); color: #fff; padding: 14px 22px; border-radius: 14px;
            font-size: 14px; font-weight: 600; opacity: 0; pointer-events: none;
            transition: opacity 0.25s, transform 0.25s; z-index: 50; max-width: calc(100% - 32px); text-align: center;
        }
        .toast.show { opacity: 1; transform: translateX(-50%) translateY(0); }

        @media (max-width: 960px) {
            .hero-grid { grid-template-columns: 1fr; }
            .hero-phones { margin-top: 48px; }
            .features-grid { grid-template-columns: repeat(2, 1fr); }
            .showcase-grid { grid-template-columns: 1fr; max-width: 420px; margin: 0 auto; }
            .hero { padding: 72px 0 110px; }
        }
        @media (max-width: 560px) {
            section { padding: 56px 0; }
            .features-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- ================= HERO ================= -->
    <header class="hero">
        <div class="container">
            <div class="hero-grid">
                <div>
                    <span class="badge"><span class="dot"></span> Available now as a web app</span>
                    <h1>Your campus life, <span>all in one app.</span></h1>
                    <p class="lead">
                        Attendance, fees, payments, and announcements for your student organizations —
                        beautifully simple and ready on any device.
                    </p>
                    <div class="hero-actions">
                        <a class="btn btn-primary" href="{{ $pwaUrl }}" target="_blank" rel="noopener">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v13m0 0l-5-5m5 5l5-5M4 21h16"/></svg>
                            Open {{ $appName }}
                        </a>
                        <button class="btn btn-ghost" id="install-btn" type="button">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v10m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                            Install App
                        </button>
                    </div>
                    <div class="hero-meta">
                        <div><b>Works offline</b>Attendance syncs when online</div>
                        <div><b>No store needed</b>Installs from your browser</div>
                    </div>
                </div>
                <div class="hero-phones">
                    <div class="phone">
                        <img src="/images/dashboard.png" alt="LuxMap dashboard preview" loading="lazy">
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- ================= FEATURES ================= -->
    <section id="features">
        <div class="container">
            <div class="sec-head">
                <span class="sec-eyebrow">Everything you need</span>
                <h2>Designed for students, built for everyday campus life.</h2>
                <p>From checking in at an event to paying your organization fees, {{ $appName }} keeps it all in one place.</p>
            </div>
            <div class="features-grid">
                @foreach ($features as $feature)
                    <div class="feature-card">
                        <div class="feature-icon">
                            @if ($feature['icon'] === 'qr')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><path d="M14 14h3v3h-3zM18 18h3v3h-3z"/></svg>
                            @elseif ($feature['icon'] === 'face')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0116 0"/></svg>
                            @elseif ($feature['icon'] === 'payments')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0112 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 003.4 0"/></svg>
                            @endif
                        </div>
                        <h3>{{ $feature['title'] }}</h3>
                        <p>{{ $feature['description'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= SCREENSHOTS ================= -->
    <section class="showcase" id="preview">
        <div class="container">
            <div class="sec-head">
                <span class="sec-eyebrow">Take a look</span>
                <h2>A clean, familiar experience.</h2>
                <p>Attendance, fees, and payments — clear, fast, and right where you expect them.</p>
            </div>
            <div class="showcase-grid">
                @foreach ($screens as $screen)
                    <div class="shot">
                        <img src="{{ $screen['src'] }}" alt="{{ $screen['alt'] }}" loading="lazy">
                        <p>{{ $screen['alt'] }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    <!-- ================= HOW TO INSTALL ================= -->
    <section id="install">
        <div class="container">
            <div class="sec-head">
                <span class="sec-eyebrow">Get started</span>
                <h2>Install {{ $appName }} in three steps.</h2>
                <p>No App Store, no Play Store — install it straight from your browser.</p>
            </div>
            <div class="install-card">
                <div class="steps">
                    @foreach ($steps as $step)
                        <div class="step">
                            <div class="step-num">{{ $loop->iteration }}</div>
                            <div>
                                <strong>{{ $step['title'] }}</strong>
                                <p>{{ $step['description'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="install-cta">
                    <a class="btn btn-primary" href="{{ $pwaUrl }}" target="_blank" rel="noopener">Open {{ $appName }}</a>
                    <button class="btn btn-ghost" id="install-btn-2" type="button" hidden>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v10m0 0l-4-4m4 4l4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg>
                        Install App
                    </button>
                    <p class="note">
                        Tip: open the app once while connected to install it. After that it can keep working offline —
                        attendance you record without a connection is saved and synced automatically when you are back online.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- ================= CTA BAND ================= -->
    <section class="cta-band">
        <div class="container">
            <div class="inner">
                <h2>Ready to make campus life easier?</h2>
                <p>Join your organizations, track attendance, and stay on top of payments with {{ $appName }}.</p>
                <a class="btn" href="{{ $pwaUrl }}" target="_blank" rel="noopener">Get {{ $appName }} Now</a>
            </div>
        </div>
    </section>

    <!-- ================= FOOTER ================= -->
    <footer>
        <div class="container">
            <p>Powered by {{ $appName }} · Install at <span class="url">{{ $pwaUrl }}</span></p>
        </div>
    </footer>

    <div class="toast" id="toast" role="status"></div>

    <script>
        (function () {
            var deferredPrompt = null;
            var toast = document.getElementById('toast');
            var installers = [document.getElementById('install-btn'), document.getElementById('install-btn-2')];

            function showToast(msg) {
                toast.textContent = msg;
                toast.classList.add('show');
                clearTimeout(showToast._t);
                showToast._t = setTimeout(function () { toast.classList.remove('show'); }, 3500);
            }

            function triggerInstall() {
                if (deferredPrompt) {
                    deferredPrompt.prompt();
                    deferredPrompt.userChoice.then(function (choice) {
                        if (choice.outcome === 'accepted') {
                            showToast('Installing {{ $appName }}...');
                        }
                        deferredPrompt = null;
                        installers.forEach(function (b) { if (b) b.hidden = true; });
                    });
                } else {
                    // Fallback: the PWA is served from the target origin; open it.
                    window.open('{{ $pwaUrl }}', '_blank', 'noopener');
                }
            }

            window.addEventListener('beforeinstallprompt', function (e) {
                e.preventDefault();
                deferredPrompt = e;
                installers.forEach(function (b) { if (b) b.hidden = false; });
            });

            window.addEventListener('appinstalled', function () {
                showToast('{{ $appName }} installed successfully!');
                installers.forEach(function (b) { if (b) b.hidden = true; });
            });

            installers.forEach(function (b) { if (b) b.addEventListener('click', triggerInstall); });
        })();
    </script>
</body>
</html>
