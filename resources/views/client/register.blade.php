<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#155642">
    <title>Join {{ $tenant->name }} · Carbay+</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;padding:24px;background:#f4f5ec;color:#173c32;font:16px system-ui,sans-serif}main{max-width:480px;margin:5vh auto;padding:30px;background:white;border:1px solid #e4e9e1;border-radius:20px;box-shadow:0 18px 55px #173c3210}h1{font-size:27px;margin:6px 0}.muted{color:#6c7972;font-size:14px}label{display:block;margin:17px 0 6px;font-size:14px;font-weight:650}input{width:100%;padding:12px;border:1px solid #d6ddd6;border-radius:10px;font:inherit}button{width:100%;margin-top:21px;padding:13px;border:0;border-radius:10px;background:#155642;color:white;font:inherit;font-weight:700}.notice{margin:16px 0;padding:12px;border-radius:10px;background:#edf6ed}.error{color:#a12d29;font-size:13px}
    </style>
</head>
<body><main>
    <p class="muted">{{ $tenant->name }} · Client portal</p>
    <h1>Create your account</h1>
    <p class="muted">Verify your phone by SMS. Your number is used for account sign-in.</p>
    @if (session('status'))<p class="notice">{{ session('status') }}</p>@endif
    <form method="post" action="{{ route('client.otp.request', ['tenant' => $tenant->id]) }}">
        @csrf
        <input type="hidden" name="purpose" value="register">
        <label for="name">Name</label><input id="name" name="name" value="{{ old('name') }}" autocomplete="name" required maxlength="255">
        <label for="phone">Phone number</label><input id="phone" name="phone" type="tel" value="{{ old('phone') }}" autocomplete="tel" required maxlength="30" placeholder="+233...">
        <label for="birthday">Birthday (optional)</label><input id="birthday" name="birthday" type="date" value="{{ old('birthday') }}" max="{{ today()->toDateString() }}">
        <label style="display:flex;align-items:center;gap:9px;font-weight:500"><input type="checkbox" name="sms_marketing" value="1" style="width:auto"> I agree to receive offers and promotions by SMS.</label>
        @error('name')<p class="error">{{ $message }}</p>@enderror @error('phone')<p class="error">{{ $message }}</p>@enderror
        <button type="submit">Send verification code</button>
    </form>
    <p class="muted">Already registered? <a href="{{ route('client.login', ['tenant' => $tenant->id]) }}">Sign in</a></p>
    @include('shared.developer-footer')
</main></body>
</html>
