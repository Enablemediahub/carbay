<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ \App\Models\PlatformSetting::appearance()->theme_color ?: '#07518e' }}">
    <meta name="description" content="Choose your Carbay+ workspace to manage your wash bay or view your worker account.">
    <link rel="manifest" href="{{ route('pwa.manifest', ['workspace' => 'landing'], false) }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="{{ \App\Models\PlatformSetting::appearance()->brand_name ?: 'Carbay+' }}">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <link rel="apple-touch-icon" href="{{ \App\Models\PlatformSetting::appearance()->assetUrl('icon-192') }}">
    <script src="{{ asset('landing-install.js') }}" defer></script>
    <link rel="icon" href="{{ \App\Models\PlatformSetting::appearance()->assetUrl('icon-512') }}" type="image/png">
    <title>Welcome to {{ \App\Models\PlatformSetting::appearance()->brand_name ?: 'Carbay+' }}</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #142637; background: #f3f8fc; font-synthesis: none; text-rendering: optimizeLegibility; }
        * { box-sizing: border-box; }
        body { min-height: 100vh; margin: 0; padding: 24px; background: radial-gradient(ellipse at 50% -15%, #d9efff 0, transparent 45rem), #f3f8fc; }
        main { width: min(100%, 1040px); margin: 0 auto; }
        .brand { display: flex; align-items: center; justify-content: center; gap: 12px; margin: 18px auto 48px; color: #142637; font-size: 20px; font-weight: 800; letter-spacing: -.03em; }
        .brand img { width: 76px; height: 60px; object-fit: contain; }
        .intro { margin: 0 auto 34px; text-align: center; }
        .eyebrow { color: #0876c9; font-size: 12px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
        h1 { margin: 10px 0; color: #142637; font-size: clamp(30px, 6vw, 48px); letter-spacing: -.055em; line-height: 1.08; }
        .intro p { max-width: 550px; margin: 12px auto 0; color: #5b7081; font-size: 16px; line-height: 1.6; }
        .portals { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
        .portal { display: flex; min-width: 0; min-height: 270px; flex-direction: column; align-items: flex-start; padding: 25px; border: 1px solid #dce8f1; border-radius: 22px; background: #fff; box-shadow: 0 12px 32px #173c3208; color: inherit; text-decoration: none; transition: transform 160ms ease, box-shadow 160ms ease, border-color 160ms ease; }
        .portal:hover { transform: translateY(-3px); border-color: #83c5f2; box-shadow: 0 18px 38px #07518e18; }
        .icon { display: grid; width: 52px; height: 52px; place-items: center; border-radius: 16px; background: #e8f5ff; color: #0876c9; }
        .icon svg { width: 27px; height: 27px; }
        h2 { margin: 22px 0 8px; font-size: 21px; letter-spacing: -.03em; }
        .portal p { margin: 0; color: #5b7081; font-size: 14px; line-height: 1.55; }
        .action { display: inline-flex; align-items: center; gap: 8px; margin-top: auto; padding-top: 24px; color: #0876c9; font-size: 14px; font-weight: 800; }
        .action svg { width: 17px; height: 17px; transition: transform 160ms ease; }
        .portal:hover .action svg { transform: translateX(3px); }
        footer { margin: 32px 0 12px; color: #74889a; font-size: 12px; text-align: center; }
        @media (max-width: 760px) { body { padding: 18px; } .brand { margin: 8px auto 35px; } .intro { margin-bottom: 25px; } .portals { grid-template-columns: minmax(0, 1fr); gap: 12px; } .portal { min-height: 0; padding: 19px; border-radius: 18px; } .icon { width: 46px; height: 46px; border-radius: 14px; } h2 { margin-top: 15px; } .action { padding-top: 16px; } }
        @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; transition-duration: .01ms !important; } }
        body { position: relative; isolation: isolate; padding: clamp(22px, 4vw, 54px) 24px; background: #edf6ff; }
        .wallpaper { position: fixed; inset: 0; z-index: -2; background-position: center; background-size: cover; opacity: .35; pointer-events: none; }
        body::before { content: ''; position: fixed; inset: 0; z-index: -1; pointer-events: none; background: linear-gradient(150deg, #f3f9ffbf, #dceeff8c 55%, #ecf7ffbf); }
        .brand { flex-direction: column; gap: 8px; margin: 0 auto 36px; font-size: 28px; color: #064373; }
        .brand img { width: 108px; height: 84px; filter: drop-shadow(0 8px 16px #07518e18); }
        .intro { margin-bottom: 40px; }
        .eyebrow { display: inline-block; padding: 8px 14px; border: 1px solid #b9dcf4; border-radius: 999px; background: #ffffffa6; font-size: 10px; letter-spacing: .18em; }
        h1 { margin-top: 20px; color: #073c66; font-weight: 800; }
        .intro p { color: #3d5d76; }
        .portals { gap: 22px; }
        .portal { position: relative; overflow: hidden; min-height: 300px; padding: 30px; border-radius: 32px; border: 1px solid #ffffffb3; color: #fff; box-shadow: 0 16px 40px #08497526, inset 0 1px 0 #ffffff40; }
        .portal:nth-child(1) { background: linear-gradient(145deg, #127dae, #085781); }
        .portal:nth-child(2) { background: linear-gradient(145deg, #1461bc, #153e87); }
        .portal:nth-child(3) { background: linear-gradient(145deg, #233f84, #11264f); }
        .portal::after { content: ''; position: absolute; width: 180px; height: 180px; top: -80px; right: -65px; border: 26px solid #ffffff0a; border-radius: 50%; pointer-events: none; }
        .portal:hover { border-color: #ffffffc9; transform: translateY(-5px); box-shadow: 0 24px 48px #08497538; }
        .portal:focus-visible { outline: 3px solid #0096ff; outline-offset: 5px; }
        .icon { width: 58px; height: 58px; border-radius: 19px; border: 1px solid #ffffff38; background: #ffffff18; color: #fff; }
        .icon svg { width: 29px; height: 29px; }
        .portal h2 { margin-top: 26px; font-size: 24px; }
        .portal p { color: #e0efff; }
        .action { width: 100%; justify-content: space-between; color: #fff; }
        .portal .action svg { width: 30px; height: 30px; padding: 7px; border-radius: 50%; background: #ffffff1f; }
        .signin-note { margin: 30px auto 0; color: #47637a; font-size: 12px; line-height: 1.6; text-align: center; }
        .install-card { width: min(440px, calc(100vw - 36px)); max-height: calc(100dvh - 40px); margin: auto; padding: 28px; box-sizing: border-box; overflow-y: auto; border: 1px solid #c4dff3; border-radius: 28px; background: #fff; text-align: center; box-shadow: 0 24px 80px #05274640; }
        .install-card::backdrop { background: #05274680; backdrop-filter: blur(5px); }
        .install-logo { display: block; width: 118px; height: 94px; object-fit: contain; margin: 0 auto 16px; }
        .install-card .install-later { display: block; margin: 12px auto 0; background: transparent; color: #47637a; }
        .install-launch { display: block; margin: 22px auto 0; padding: 12px 22px; border: 1px solid #a2cbea; border-radius: 14px; background: #ffffffc9; color: #07518e; font: inherit; font-weight: 700; cursor: pointer; }
        .install-card h2 { margin: 0 0 8px; color: #073c66; }
        .install-card p { color: #47637a; font-size: 14px; line-height: 1.6; }
        .install-card button { border: 0; border-radius: 14px; background: #07518e; color: #fff; padding: 13px 24px; font: inherit; font-weight: 700; cursor: pointer; }
        .install-card button:focus-visible { outline: 3px solid #0096ff; outline-offset: 4px; }
        .install-card button:disabled { opacity: .6; cursor: wait; }
        #install-instructions { max-width: 520px; margin: 20px auto 0; padding: 18px; border-radius: 18px; background: #eef6ff; text-align: left; line-height: 1.7; }
        [hidden] { display: none !important; }
        .legal-links { display: flex; justify-content: center; gap: 24px; margin-top: 24px; font-size: 13px; }
        .legal-links a { color: #07518e; text-underline-offset: 4px; }
        @media (max-width: 760px) { body { padding: 24px 18px; } .brand { margin-bottom: 26px; } .brand img { width: 90px; height: 70px; } .intro { margin-bottom: 26px; } .portals { gap: 16px; } .portal { min-height: 240px; border-radius: 28px; padding: 24px; } .portal h2 { margin-top: 18px; } }
    </style>
</head>
<body>
@if ($wallpaperUrl ?? null)
    <div class="wallpaper" style="background-image: url('{{ $wallpaperUrl }}')" aria-hidden="true"></div>
@endif
<main>
    <div class="brand">
        <img src="{{ \App\Models\PlatformSetting::appearance()->assetUrl('logo') }}" alt="">
        <span>{{ \App\Models\PlatformSetting::appearance()->brand_name ?: 'Carbay+' }}</span>
    </div>
    <header class="intro">
        <span class="eyebrow">Welcome to Carbay+</span>
        <h1>{{ \App\Models\PlatformSetting::appearance()->landing_title ?: 'Where would you like to go?' }}</h1>
        <p>{{ \App\Models\PlatformSetting::appearance()->landing_subtitle ?: 'Choose your workspace to sign in and get straight to work.' }}</p>
    </header>
    <nav class="portals" aria-label="Choose your Carbay+ portal">
        <a class="portal" href="{{ route('worker.login') }}">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2m16 0v-2a4 4 0 0 0-3-3.87M14 3.13a4 4 0 0 1 0 7.75M14 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0Z"/></svg>
            </span>
            <h2>Worker</h2>
            <p>Check your assigned wash jobs, wallet earnings, payout schedule and work history.</p>
            <span class="action">Worker sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
        </a>
        <a class="portal" href="{{ route('manager.login') }}">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4m-4 1v.01M9 12v.01M9 15v.01M9 18v.01m4-10v.01m0 3v.01m0 3v.01m0 3v.01m4-5v.01m0 3v.01m0 3v.01"/></svg>
            </span>
            <h2>Manager</h2>
            <p>Open your branch dashboard, record wash jobs, manage workers and reconcile payments.</p>
            <span class="action">Manager sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
        </a>
        <a class="portal" href="{{ url('/app/login') }}">
            <span class="icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3 4 7v5c0 5 3.4 8 8 9 4.6-1 8-4 8-9V7l-8-4Zm-3 9 2 2 4-4"/></svg>
            </span>
            <h2>Admin / CEO</h2>
            <p>Manage your company, branches, services, subscriptions, reports and team access.</p>
            <span class="action">Admin / CEO sign in <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m-6-6 6 6-6 6"/></svg></span>
        </a>
    </nav>
    <p class="signin-note">Managers sign in with their phone number and PIN. Admin / CEOs use their company account.</p>
    <button class="install-launch" id="install-launch" type="button">Install Carbay+</button>
    <dialog class="install-card" id="install-dialog" aria-labelledby="install-heading" aria-describedby="install-description">
        <img class="install-logo" src="{{ \App\Models\PlatformSetting::appearance()->assetUrl('logo') }}" alt="Carbay+">
        <h2 id="install-heading">Your washing bay, one tap away</h2>
        <p id="install-description">Add Carbay+ to your Android, iPhone or iPad Home Screen.</p>
        <button id="install-app" type="button" data-popup-enabled="{{ (\App\Models\PlatformSetting::appearance()->install_popup_enabled ?? true) ? 'true' : 'false' }}" data-popup-delay="{{ \App\Models\PlatformSetting::appearance()->install_popup_delay ?? 2 }}" aria-controls="install-instructions" aria-expanded="false">Install Carbay+</button>
        <p id="install-status" role="status">Opens with your Worker, Manager and Admin / CEO workspaces.</p>
        <button class="install-later" id="install-later" type="button">Not now</button>
        <button class="install-later" id="install-help" type="button">Other installation options</button>
        <div id="install-instructions" hidden>
            <div id="install-ios" hidden><strong>On iPhone or iPad</strong><ol><li>Open this page in Safari.</li><li>Open the Share menu (or More, then Share).</li><li>Choose Add to Home Screen. Enable Open as Web App if offered, then tap Add.</li></ol></div>
            <div id="install-other" hidden><strong>Install from your browser</strong><ol><li>Open this page in Chrome or Edge.</li><li>Open the browser menu and choose Install app or Add to Home Screen.</li><li>Confirm, then open Carbay+ from your Home Screen.</li></ol><p>If the option is missing, open this link directly in your browser rather than inside a messaging or social app.</p></div>
        </div>
        <noscript><p>To install, use your browser's Install app or Add to Home Screen option. On iPhone/iPad use Safari's Share menu.</p></noscript>
    </dialog>
    <noscript><p class="signin-note">Install Carbay+ from your browser menu. On iPhone/iPad, open Safari, then Share → Add to Home Screen.</p></noscript>
    <nav class="legal-links" aria-label="Legal documents"><a href="{{ route('legal.agreement') }}">Service Agreement</a><a href="{{ route('legal.privacy') }}">Data Privacy</a></nav>
    @include('shared.developer-footer')
</main>
</body>
</html>
