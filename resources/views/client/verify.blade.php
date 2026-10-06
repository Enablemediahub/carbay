<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify phone · {{ $tenant->name }}</title>
    <style>*{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px;background:#f4f5ec;color:#173c32;font:16px system-ui,sans-serif}main{max-width:430px;margin:8vh auto;padding:30px;background:#fff;border:1px solid #e4e9e1;border-radius:20px}h1{font-size:26px}.muted{color:#6c7972;font-size:14px}label{display:block;margin:20px 0 6px;font-weight:650}input{width:100%;padding:12px;border:1px solid #d6ddd6;border-radius:10px;font:inherit}button{width:100%;margin-top:20px;padding:13px;border:0;border-radius:10px;background:#155642;color:#fff;font:inherit;font-weight:700}.error{color:#a12d29}</style>
</head>
<body><main>
    <p class="muted">{{ $tenant->name }}</p><h1>Enter your code</h1>
    <p class="muted">A one-time code was requested for {{ $phone }}. It expires in five minutes.</p>
    @if (session('status'))<p>{{ session('status') }}</p>@endif
    <form method="post" action="{{ route('client.otp.verify', ['tenant' => $tenant->id]) }}">
        @csrf
        <input type="hidden" name="phone" value="{{ old('phone', $phone) }}">
        <input type="hidden" name="purpose" value="{{ old('purpose', $purpose) }}">
        <label for="code">6-digit code</label><input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" minlength="6" maxlength="6" required>
        @error('code')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Verify and continue</button>
    </form>
</main></body>
</html>
