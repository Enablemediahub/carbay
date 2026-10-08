import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/service-worker.js', import.meta.url), 'utf8');
function setup(hostname) {
    const listeners = {};
    const requests = [];
    const origin = `https://${hostname}`;
    vm.runInNewContext(script, {
        self: { location: { hostname, origin }, addEventListener: (name, handler) => { listeners[name] = handler; }, skipWaiting() {}, clients: { claim() {} } },
        Request, Response, Headers, URL,
        fetch: async request => { requests.push(request); return new Response('image'); },
    });
    return { listeners, requests, origin };
}

test('ngrok public image requests receive the bypass header even with original no-cors mode', async () => {
    const { listeners, requests, origin } = setup('preview.ngrok-free.dev');
    let response;
    listeners.fetch({ request: new Request(`${origin}/carbay-favicon-512.png`, { mode: 'no-cors' }), respondWith: promise => { response = promise; } });
    await response;
    assert.equal(requests[0].headers.get('ngrok-skip-browser-warning'), '1');
    assert.equal(requests[0].mode, 'same-origin');
});

test('production requests remain unchanged and authenticated pages bypass public caching', async () => {
    const { listeners, requests, origin } = setup('carbay.example');
    const request = new Request(`${origin}/carbay-favicon-512.png`);
    let response;
    listeners.fetch({ request, respondWith: promise => { response = promise; } });
    await response;
    assert.equal(requests[0], request);
    listeners.fetch({ request: new Request(`${origin}/worker/wallet`), respondWith() { assert.fail('Private pages must bypass the public cache'); } });
    assert.equal(requests.length, 1);
});
