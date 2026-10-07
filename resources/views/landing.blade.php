<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07518e">
    <meta name="description" content="Choose your Carbay+ workspace to manage your wash bay or view your worker account.">
    <link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
    <link rel="icon" href="{{ asset('carbay-favicon-512.png') }}" type="image/png">
    <title>Welcome to Carbay+</title>
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
    </style>
</head>
<body>
<main>
    <div class="brand">
        <img src="{{ asset('carbay-logo.png') }}" alt="">
        <span>Carbay+</span>
    </div>
    <header class="intro">
        <span class="eyebrow">Welcome to Carbay+</span>
        <h1>Where would you like to go?</h1>
        <p>Choose your workspace to sign in and get straight to work.</p>
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
        <a class="portal" href="{{ url('/app/login') }}">
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
    <footer>Manager and Admin / CEO accounts use the company sign-in page. Your role determines the dashboard you can access.</footer>
</main>
</body>
</html>
