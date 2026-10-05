<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Worker sign in · Carbay+</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #173c32; background: #f4f5ec; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: radial-gradient(ellipse at top left, #e4eddc, transparent 48%), #f4f5ec; }
        main { width: min(100%, 460px); padding: clamp(26px, 7vw, 48px); border: 1px solid #e7e9df; border-radius: 24px; background: #fff; box-shadow: 0 24px 70px #193f3018; }
        .brand { display: flex; align-items: center; gap: 12px; font-size: 21px; font-weight: 800; letter-spacing: -.04em; }
        .mark { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 14px; color: #153e33; background: #f5d574; }
        h1 { margin: 34px 0 8px; font-size: 30px; letter-spacing: -.04em; }
        .intro { margin: 0 0 28px; color: #68766f; line-height: 1.6; }
        label { display: block; margin: 18px 0 7px; font-size: 13px; font-weight: 700; }
        input { display: block; width: 100%; height: 48px; padding: 0 13px; border: 1px solid #d7ded8; border-radius: 10px; font: inherit; color: inherit; }
        input:focus { outline: 3px solid #e7bf5940; border-color: #bb8823; }
        button { width: 100%; height: 50px; margin-top: 25px; border: 0; border-radius: 11px; color: #fff; background: #155642; font: inherit; font-weight: 750; cursor: pointer; }
        button:hover { background: #104535; }
        .error { margin-top: 15px; padding: 12px 14px; border-radius: 10px; color: #932e28; background: #fff0ed; font-size: 14px; }
        .privacy { margin: 24px 0 0; color: #7b8780; font-size: 12px; line-height: 1.6; text-align: center; }
    </style>
</head>
<body>
<main>
    <div class="brand"><span class="mark" aria-hidden="true">✦</span><span>carbay+</span></div>
    <h1>Team sign in</h1>
    <p class="intro">Use your company email, registered phone number and worker PIN to view your wash activity.</p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('worker.login.submit') }}">
        @csrf
        <label for="company_email">Company email</label>
        <input id="company_email" name="company_email" type="email" value="{{ old('company_email') }}" autocomplete="organization" required>

        <label for="phone">Your registered phone number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required>

        <label for="pin">Worker PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,12}" minlength="4" maxlength="12" autocomplete="current-password" required>

        <button type="submit">Sign in securely</button>
    </form>
    <p class="privacy">Your PIN is verified securely and never displayed. Ask your manager if you need a PIN reset.</p>
</main>
</body>
</html>
