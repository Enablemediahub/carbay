<style>
    .legal-document { max-width: 850px; margin: 0 auto; font-size: 15px; line-height: 1.8; }
    .legal-document h1 { font-size: clamp(28px, 5vw, 38px); line-height: 1.2; }
    .legal-document h2 { margin-top: 28px; font-size: 19px; font-weight: 700; }
    .legal-document p { margin: 12px 0; }
    .legal-document a { color: #0876c9; text-decoration: underline; text-underline-offset: 3px; }
    .legal-meta { font-size: 13px; opacity: .8; }
    .legal-review { padding: 16px; border-radius: 14px; border: 1px solid #d6a849; background: #fff7df; color: #604309; }
    .legal-contact { margin-top: 30px; padding-top: 18px; border-top: 1px solid #94a3b850; }
    .dark .legal-document a { color: #7dcfff; }
    @media print { .legal-document { max-width: none; font-size: 11pt; } }
</style>
<article class="legal-document">
    <h1>{{ $document['title'] }}</h1>
    <p class="legal-meta">Enabl Technologies · Abidale Group · Ghana · Version {{ config('legal.version') }}</p>
    @unless(\App\Models\PlatformSetting::appearance()->legal_reviewed ?? config('legal.reviewed'))
        <p class="legal-review">Draft for legal review. Provider details, contract terms and operational privacy arrangements must be confirmed before this document is adopted.</p>
    @endunless
    <p>{{ $document['intro'] }}</p>
    @foreach($document['sections'] as [$heading, $body])
        <section><h2>{{ $loop->iteration }}. {{ $heading }}</h2><p>{{ $body }}</p></section>
    @endforeach
    <section class="legal-contact">
        <h2>Provider contact</h2>
        <p>{{ config('legal.provider') }} through {{ config('legal.brand') }}</p>
        @php($legalEmail = \App\Models\PlatformSetting::appearance()->legal_email ?: config('legal.email'))
        @php($legalAddress = \App\Models\PlatformSetting::appearance()->legal_address ?: config('legal.address'))
        @if($legalEmail)<p>Email: <a href="mailto:{{ $legalEmail }}">{{ $legalEmail }}</a></p>@endif
        @if($legalAddress)<p>Address: {{ $legalAddress }}</p>@endif
        @if(!$legalEmail || !$legalAddress)<p>Provider contact details are awaiting confirmation. For staff or customer data requests, contact your washing bay administrator.</p>@endif
        <p><a href="{{ route('legal.agreement') }}">Service Agreement</a> · <a href="{{ route('legal.privacy') }}">Data Privacy</a></p>
        @if($document['title'] === 'Data Privacy')
            <p>Ghanaian reference: <a href="https://dpc.gov.gh/for-individuals/" target="_blank" rel="noopener noreferrer">Data Protection Commission: individual rights</a>, <a href="https://dpc.gov.gh/for-organisations/" target="_blank" rel="noopener noreferrer">organisation responsibilities</a>.</p>
        @endif
    </section>
</article>
