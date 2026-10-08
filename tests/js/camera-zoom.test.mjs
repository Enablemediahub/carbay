import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createCameraZoom } from '../../resources/js/camera-zoom.js';
import { cameraCrop } from '../../resources/js/plate-recognition.js';

test('digital zoom enlarges the preview and OCR reads the matching visible region', async () => {
    const video = { style: {} };
    const zoom = createCameraZoom(video, {}, () => {});
    await zoom.set(2);
    assert.equal(video.style.transform, 'scale(2)');
    const crop = cameraCrop(1280, 720,
        { left: -320, top: -180, width: 1280, height: 720 },
        { left: 32, top: 115.2, width: 576, height: 129.6 });
    assert.equal(crop.x, 352);
    assert.ok(Math.abs(crop.y - 295.2) < 0.001);
    assert.equal(crop.width, 576);
    assert.equal(crop.height, 129.6);
    await zoom.set(9);
    assert.equal(zoom.value, 4);
    await zoom.set(0);
    assert.equal(zoom.value, 1);
    assert.equal(video.style.transform, 'scale(1)');
});

test('uses native camera constraints when supported', async () => {
    let applied = 1;
    const video = { style: {} };
    const track = {
        getCapabilities: () => ({ zoom: { min: 1, max: 8, step: 0.1 } }),
        getSettings: () => ({ zoom: applied }),
        applyConstraints: async ({ advanced }) => { applied = advanced[0].zoom; },
    };
    const zoom = createCameraZoom(video, track, () => {});
    await zoom.set(3);
    assert.equal(applied, 3);
    assert.equal(zoom.value, 3);
    assert.equal(video.style.transform, 'scale(1)');
});

test('falls back to digital zoom if native constraints fail', async () => {
    const video = { style: {} };
    const zoom = createCameraZoom(video, {
        getCapabilities: () => ({ zoom: { min: 1, max: 8, step: 0.1 } }),
        getSettings: () => ({ zoom: 1 }),
        applyConstraints: async () => { throw new Error('Unsupported constraint'); },
    }, () => {});
    await zoom.set(3);
    assert.equal(zoom.native, false);
    assert.equal(zoom.value, 3);
    assert.equal(video.style.transform, 'scale(3)');
});

test('coalesces slider changes while a native zoom request is pending', async () => {
    const requests = [];
    let release;
    let applied = 1;
    const zoom = createCameraZoom({ style: {} }, {
        getCapabilities: () => ({ zoom: { min: 1, max: 8, step: 0.1 } }),
        getSettings: () => ({ zoom: applied }),
        applyConstraints: async ({ advanced }) => {
            requests.push(advanced[0].zoom);
            if (requests.length === 1) await new Promise(r => { release = r; });
            applied = advanced[0].zoom;
        },
    }, () => {});
    const first = zoom.set(2);
    zoom.set(3);
    zoom.set(4);
    release();
    await first;
    assert.deepEqual(requests, [2, 4]);
    assert.equal(zoom.value, 4);
});
