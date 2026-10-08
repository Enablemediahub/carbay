<a class="carbay-mobile-header" href="{{ filament()->getCurrentPanel()->getUrl() }}">
    <img class="carbay-mobile-header-logo-light" src="{{ \App\Models\PlatformSetting::appearance()->assetUrl('logo') }}" alt="">
    <img class="carbay-mobile-header-logo-dark" src="{{ \App\Models\PlatformSetting::appearance()->assetUrl('dark-logo') }}" alt="">
    <span>{{ filament()->getCurrentPanel()->getBrandName() }}</span>
</a>
