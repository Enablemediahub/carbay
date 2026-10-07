<script>
    let carbayInstallPrompt = null;

    const isCarbayStandalone = () => window.matchMedia('(display-mode: standalone)').matches
        || window.navigator.standalone === true;

    const isCarbayIos = () => /iphone|ipad|ipod/i.test(window.navigator.userAgent)
        || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

    function showCarbayInstallPrompt() {
        if (
            window.matchMedia('(max-width: 767px)').matches
            && ! isCarbayStandalone()
            && ! window.localStorage.getItem('carbay.installPrompt.dismissed')
        ) {
            const installPrompt = document.getElementById('carbay-install-prompt');
            if (installPrompt) {
                installPrompt.hidden = false;
            }
        }
    }

    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => navigator.serviceWorker.register('{{ asset('service-worker.js') }}'));
    }

    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        carbayInstallPrompt = event;
        showCarbayInstallPrompt();
    });

    window.addEventListener('appinstalled', () => {
        carbayInstallPrompt = null;
        const installPrompt = document.getElementById('carbay-install-prompt');
        if (installPrompt) {
            installPrompt.hidden = true;
        }
    });

    window.addEventListener('load', showCarbayInstallPrompt);

    document.addEventListener('click', async (event) => {
        const target = event.target;
        if (! (target instanceof Element)) {
            return;
        }

        if (target.closest('[data-carbay-install-dismiss]')) {
            window.localStorage.setItem('carbay.installPrompt.dismissed', '1');
            document.getElementById('carbay-install-prompt')?.setAttribute('hidden', '');

            return;
        }

        if (target.closest('[data-carbay-install-action]') && carbayInstallPrompt) {
            await carbayInstallPrompt.prompt();
            const choice = await carbayInstallPrompt.userChoice;
            carbayInstallPrompt = null;

            if (choice.outcome === 'accepted') {
                document.getElementById('carbay-install-prompt')?.setAttribute('hidden', '');
            }
        }
    });
</script>
<aside id="carbay-install-prompt" class="carbay-install-prompt" aria-label="Install Carbay+" hidden>
    <img src="{{ asset('carbay-favicon-192.png') }}" alt="">
    <div class="carbay-install-copy">
        <strong>Carbay+ works like an app</strong>
        <span class="carbay-install-android">Install it for quick, full-screen access.</span>
        <span class="carbay-install-ios">Add it to your Home Screen for quick, full-screen access.</span>
        <span class="carbay-install-ios-steps" hidden>Tap Share, then choose “Add to Home Screen”.</span>
    </div>
    <button type="button" class="carbay-install-action" data-carbay-install-action hidden>Install</button>
    <button type="button" class="carbay-install-ios-action" data-carbay-install-ios-action hidden>How</button>
    <button type="button" class="carbay-install-dismiss" data-carbay-install-dismiss aria-label="Dismiss install prompt">×</button>
</aside>
<script>
    (() => {
        const installPrompt = document.getElementById('carbay-install-prompt');
        if (! installPrompt) {
            return;
        }

        const ios = isCarbayIos();
        installPrompt.classList.toggle('is-ios', ios);
        const iosAction = installPrompt.querySelector('[data-carbay-install-ios-action]');
        if (iosAction) {
            iosAction.hidden = ! ios;
        }
        installPrompt.querySelector('[data-carbay-install-ios-action]')?.addEventListener('click', () => {
            const steps = installPrompt.querySelector('.carbay-install-ios-steps');
            const visible = steps?.hidden ?? true;
            if (steps) {
                steps.hidden = ! visible;
            }
        });
        window.addEventListener('beforeinstallprompt', () => {
            const button = installPrompt.querySelector('[data-carbay-install-action]');
            if (button) {
                button.hidden = false;
            }
        });
    })();
</script>
