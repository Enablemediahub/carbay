<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#155642">
    <title>Client sign in · {{ $tenant->name }}</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px;background:#f4f5ec;color:#173c32;font:16px system-ui,sans-serif}main{max-width:460px;margin:8vh auto;padding:30px;background:white;border:1px solid #e4e9e1;border-radius:20px}h1{font-size:27px;margin:6px 0}.muted{color:#6c7972;font-size:14px}label{display:block;margin:18px 0 6px;font-weight:650}input{width:100%;padding:12px;border:1px solid #d6ddd6;border-radius:10px;font:inherit}button{width:100%;margin-top:21px;padding:13px;border:0;border-radius:10px;background:#155642;color:white;font:inherit;font-weight:700}.notice{padding:12px;border-radius:10px;background:#edf6ed}
    </style>
</head>
<body><main>
    <p class="muted">{{ $tenant->name }} · Client portal</p>
    <h1>Sign in with your phone</h1>
    <p class="muted">We will text you a one-time verification code.</p>
    @if (session('status'))<p class="notice">{{ session('status') }}</p>@endif
    <form method="post" action="{{ route('client.otp.request', ['tenant' => $tenant->id]) }}">
        @csrf
        <input type="hidden" name="purpose" value="login">
        <label for="phone">Phone number</label>
        <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required maxlength="30" placeholder="+233...">
        @error('phone')<p>{{ $message }}</p>@enderror
        <button type="submit">Send sign-in code</button>
    </form>
    <p class="muted">New here? <a href="{{ route('client.register', ['tenant' => $tenant->id]) }}">Create an account</a></p>
    @include('shared.developer-footer')
</main></body>
</html>
