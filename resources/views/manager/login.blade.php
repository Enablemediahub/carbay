<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    @include('filament.pwa-head', ['manifestUrl' => route('pwa.manifest', ['workspace' => 'manager'], false)])
    <title>Manager sign in · Carbay+</title>
    <style>
        :root { color-scheme: light; font-family: Inter, ui-sans-serif, system-ui, sans-serif; color: #173c32; background: #f4f5ec; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; padding: 24px; background: radial-gradient(ellipse at top left, #e4eddc, transparent 48%), #f4f5ec; }
        main { width: min(100%, 460px); padding: clamp(26px, 7vw, 48px); border: 1px solid #e7e9df; border-radius: 24px; background: #fff; box-shadow: 0 24px 70px #193f3018; }
        .brand { display: flex; justify-content: center; }
        .brand-logo { display: block; width: min(100%, 190px); height: auto; }
        h1 { margin: 34px 0 8px; font-size: 30px; letter-spacing: -.04em; }
        .intro { margin: 0 0 28px; color: #68766f; line-height: 1.6; }
        label { display: block; margin: 18px 0 7px; font-size: 13px; font-weight: 700; }
        input { display: block; width: 100%; height: 48px; padding: 0 13px; border: 1px solid #d7ded8; border-radius: 10px; font: inherit; color: inherit; }
        select { display: block; width: 100%; height: 48px; padding: 0 13px; border: 1px solid #d7ded8; border-radius: 10px; background: #fff; font: inherit; color: inherit; }
        input:focus { outline: 3px solid #e7bf5940; border-color: #bb8823; }
        select:focus { outline: 3px solid #e7bf5940; border-color: #bb8823; }
        button { width: 100%; height: 50px; margin-top: 25px; border: 0; border-radius: 11px; color: #fff; background: #155642; font: inherit; font-weight: 750; cursor: pointer; }
        button:hover { background: #104535; }
        .error { margin-top: 15px; padding: 12px 14px; border-radius: 10px; color: #932e28; background: #fff0ed; font-size: 14px; }
        .privacy { margin: 24px 0 0; color: #7b8780; font-size: 12px; line-height: 1.6; text-align: center; }
    </style>
</head>
<body>
<main>
    <div class="brand"><img class="brand-logo" src="{{ \App\Models\PlatformSetting::appearance()->assetUrl('logo') }}" alt="Carbay+"></div>
    <h1>Manager sign in</h1>
    <p class="intro">Choose your company, then enter your registered phone number and manager PIN to manage your branch and workers.</p>

    @if ($errors->any())
        <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif

    <form method="post" action="{{ route('manager.login.submit', [], false) }}">
        @csrf
        <label for="company_id">Company</label>
        <select id="company_id" name="company_id" autocomplete="organization" required>
            <option value="">Choose your company</option>
            @foreach ($companies as $company)
                <option value="{{ $company->id }}" @selected((string) old('company_id') === (string) $company->id)>{{ $company->name }}</option>
            @endforeach
        </select>
        @if ($companies->isEmpty())
            <p class="privacy">No company is currently available. Contact your Admin / CEO.</p>
        @endif

        <label for="phone">Your registered phone number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required>

        <label for="pin">Manager PIN</label>
        <input id="pin" name="pin" type="password" inputmode="numeric" pattern="[0-9]{4,12}" minlength="4" maxlength="12" autocomplete="current-password" required>

        <button type="submit">Sign in securely</button>
    </form>
    <p class="privacy">Your PIN is verified securely and never displayed. Ask your Admin / CEO if you need a PIN reset.</p>
    @include('shared.developer-footer')
</main>
@include('shared.standalone-pwa-style')
@include('filament.pwa-register')
<script>
    window.addEventListener('pageshow', (event) => {
        if (event.persisted) window.location.reload();
    });
</script>
</body>
</html>
