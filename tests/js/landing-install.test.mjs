import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/landing-install.js', import.meta.url), 'utf8');
function setup({ ios = false, installed = false, secure = true, dismissed = false, popupEnabled = true, nativeCapable = false } = {}) {
    const listeners = {};
    const elements = Object.fromEntries(['install-app', 'install-status', 'install-instructions', 'install-ios', 'install-other', 'install-dialog', 'install-launch', 'install-later', 'install-help'].map(id => [id, {
        hidden: id.includes('instructions') || id.includes('ios') || id.includes('other'),
        textContent: '', setAttribute() {}, addEventListener(name, listener) { this[name] = listener; },
        open: false, showModal() { this.open = true; }, close() { this.open = false; },
    }]));
    elements['install-app'].dataset = { popupEnabled: String(popupEnabled), popupDelay: '2' };
    const navigator = { userAgent: ios ? 'iPhone' : 'Android', platform: '', maxTouchPoints: 0, serviceWorker: { register: async () => ({}) } };
    let timer;
    const storage = new Map(dismissed ? [['carbay.landing-install.dismissed', 'yes']] : []);
    const window = { isSecureContext: secure, sessionStorage: { getItem: key => storage.get(key), setItem: (key, value) => storage.set(key, value) },
        setTimeout(callback) { timer = callback; }, matchMedia: () => ({ matches: installed, addEventListener() {} }), addEventListener(name, listener) { listeners[name] = listener; } };
    if (nativeCapable) window.onbeforeinstallprompt = null;
    vm.runInNewContext(script, { navigator, window, document: { getElementById: id => elements[id] } });
    return { elements, listeners, triggerTimer: () => timer() };
}

test('Android uses its native prompt and reflects successful installation', async () => {
    const { elements, listeners } = setup({ nativeCapable: true });
    let prompted = 0;
    listeners.beforeinstallprompt({ preventDefault() {}, prompt: async () => { prompted++; }, userChoice: Promise.resolve({ outcome: 'accepted' }) });
    await elements['install-app'].click();
    assert.equal(prompted, 1);
    assert.match(elements['install-status'].textContent, /Installation requested/);
    listeners.appinstalled();
    assert.equal(elements['install-app'].hidden, true);
});

test('Chrome waits for readiness instead of sending the install click to instructions', async () => {
    const { elements, listeners } = setup({ nativeCapable: true });
    assert.equal(elements['install-app'].disabled, true);
    await elements['install-app'].click();
    assert.equal(elements['install-instructions'].hidden, true);
    listeners.beforeinstallprompt({ preventDefault() {} });
    assert.equal(elements['install-app'].disabled, false);
    elements['install-help'].click();
    assert.equal(elements['install-instructions'].hidden, false);
});

test('iOS receives Home Screen guidance when no native prompt exists', async () => {
    const { elements } = setup({ ios: true });
    await elements['install-app'].click();
    assert.equal(elements['install-instructions'].hidden, false);
    assert.equal(elements['install-ios'].hidden, false);
    assert.equal(elements['install-other'].hidden, true);
});

test('installed apps hide the install action', () => {
    const { elements, triggerTimer } = setup({ installed: true });
    triggerTimer();
    assert.equal(elements['install-app'].hidden, true);
    assert.equal(elements['install-dialog'].open, false);
});

test('popup appears automatically, can be dismissed and manually reopened', () => {
    const { elements, triggerTimer } = setup();
    triggerTimer();
    assert.equal(elements['install-dialog'].open, true);
    elements['install-later'].click();
    assert.equal(elements['install-dialog'].open, false);
    triggerTimer();
    assert.equal(elements['install-dialog'].open, false);
    elements['install-launch'].click();
    assert.equal(elements['install-dialog'].open, true);
});

test('dismissal prevents another automatic prompt during this session', () => {
    const { elements, triggerTimer } = setup({ dismissed: true });
    triggerTimer();
    assert.equal(elements['install-dialog'].open, false);
});

test('disabled automatic popups still allow manual installation', () => {
    const { elements, triggerTimer } = setup({ popupEnabled: false });
    triggerTimer();
    assert.equal(elements['install-dialog'].open, false);
    elements['install-launch'].click();
    assert.equal(elements['install-dialog'].open, true);
});

test('insecure mobile links explain the HTTPS requirement', async () => {
    const { elements } = setup({ secure: false });
    await elements['install-app'].click();
    assert.match(elements['install-status'].textContent, /HTTPS/);
});
