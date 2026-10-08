<link rel="manifest" href="{{ $manifestUrl ?? route('pwa.manifest', ['workspace' => auth()->user()?->role === 'manager' ? 'manager' : 'admin'], false) }}">
<meta name="theme-color" content="{{ \App\Models\PlatformSetting::appearance()->theme_color ?: '#0096FF' }}">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="{{ \App\Models\PlatformSetting::appearance()->brand_name ?: 'Carbay+' }}">
<link rel="icon" href="{{ \App\Models\PlatformSetting::appearance()->assetUrl('icon-512') }}" type="image/png" sizes="512x512">
<link rel="apple-touch-icon" href="{{ \App\Models\PlatformSetting::appearance()->assetUrl('icon-192') }}">
