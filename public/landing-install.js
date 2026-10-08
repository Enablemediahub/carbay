(() => {
    const button = document.getElementById('install-app');
    const message = document.getElementById('install-status');
    const instructions = document.getElementById('install-instructions');
    const dialog = document.getElementById('install-dialog');
    const launch = document.getElementById('install-launch');
    const help = document.getElementById('install-help');
    if (!button) return;
    let deferredPrompt = null;
    let installed = false;
    const dismissalKey = 'carbay.landing-install.dismissed';
    const dismissed = () => {
        try { return window.sessionStorage.getItem(dismissalKey) === 'yes'; } catch { return false; }
    };
    const dismiss = () => {
        try { window.sessionStorage.setItem(dismissalKey, 'yes'); } catch { /* Private browsers may block storage. */ }
        dialog?.close();
    };
    const open = () => {
        if (!installed && dialog && !dialog.open) dialog.showModal();
    };
    const standalone = window.matchMedia('(display-mode: standalone)');
    const ios = /iphone|ipad|ipod/i.test(navigator.userAgent)
        || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    const nativeCapable = !ios && 'onbeforeinstallprompt' in window;
    const installLabel = button.textContent || 'Install Carbay+';
    if (nativeCapable) {
        button.disabled = true;
        button.textContent = 'Preparing installation…';
        message.textContent = 'Keep this page open briefly. Install becomes available when Chrome is ready.';
    }
    const updateInstalled = () => {
        if (standalone.matches || navigator.standalone === true) {
            installed = true;
            button.hidden = true;
            if (launch) launch.hidden = true;
            dialog?.close();
            instructions.hidden = true;
            message.textContent = 'You are using the Carbay+ app.';
        }
    };
    launch?.addEventListener('click', open);
    document.getElementById('install-later')?.addEventListener('click', dismiss);
    dialog?.addEventListener('cancel', (event) => { event.preventDefault(); dismiss(); });
    dialog?.addEventListener('click', (event) => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dismiss();
    });
    const showInstructions = () => {
        instructions.hidden = false;
        button.setAttribute('aria-expanded', 'true');
        document.getElementById('install-ios').hidden = !ios;
        document.getElementById('install-other').hidden = ios;
    };
    help?.addEventListener('click', showInstructions);
    if ('serviceWorker' in navigator && window.isSecureContext) {
        navigator.serviceWorker.register('/service-worker.js').catch(() => {
            message.textContent = 'Open this page in your browser and use its Add to Home Screen option.';
        });
    }
    window.addEventListener('beforeinstallprompt', (event) => {
        event.preventDefault();
        deferredPrompt = event;
        button.disabled = false;
        button.textContent = installLabel;
        instructions.hidden = true;
        button.setAttribute('aria-expanded', 'false');
        message.textContent = 'Ready to install. Your app opens at the workspace chooser.';
    });
    button.addEventListener('click', async () => {
        if (!window.isSecureContext) {
            message.textContent = 'Open the secure HTTPS link to install Carbay+ on this device.';
            return;
        }
        if (!deferredPrompt) {
            if (nativeCapable) {
                message.textContent = 'Chrome is still preparing installation. Keep this page open, then tap Install when it becomes available.';
            } else {
                showInstructions();
            }
            return;
        }
        const prompt = deferredPrompt;
        deferredPrompt = null;
        button.disabled = true;
        try {
            await prompt.prompt();
            const choice = await prompt.userChoice;
            message.textContent = choice.outcome === 'accepted'
                ? 'Installation requested. Look for Carbay+ on your Home Screen.'
                : 'You can install later using your browser menu.';
            if (choice.outcome === 'accepted') {
                button.textContent = 'Installation requested';
            } else {
                button.textContent = 'Waiting for Chrome…';
            }
        } catch {
            showInstructions();
        } finally {
            button.disabled = nativeCapable && !deferredPrompt;
        }
    });
    window.addEventListener('appinstalled', () => {
        installed = true;
        deferredPrompt = null;
        button.hidden = true;
        if (launch) launch.hidden = true;
        dialog?.close();
        instructions.hidden = true;
        message.textContent = 'Carbay+ is installed. Open it from your Home Screen.';
    });
    standalone.addEventListener?.('change', updateInstalled);
    updateInstalled();
    const popupEnabled = button.dataset?.popupEnabled !== 'false';
    const delay = Math.max(0, Math.min(30, Number(button.dataset?.popupDelay ?? 2))) * 1000;
    window.setTimeout(() => { if (popupEnabled && !dismissed()) open(); }, delay);
})();
