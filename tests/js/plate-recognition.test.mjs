import { test } from 'node:test';
import assert from 'node:assert/strict';
import { cameraCrop, normalizePlate } from '../../resources/js/plate-recognition.js';

test('extracts registrations from OCR containing country names and extra lines', () => {
    assert.equal(normalizePlate('GHANA\nGR 1234-24\nTOYOTA'), 'GR 1234-24');
    assert.equal(normalizePlate('GHANA\nGR123424'), 'GR 1234-24');
    assert.equal(normalizePlate('GR 1234 24'), 'GR 1234-24');
    assert.equal(normalizePlate('AS 1234 X'), 'AS 1234X');
    assert.equal(normalizePlate('WR 123'), 'WR 123');
    assert.equal(normalizePlate('gr 1234–24'), 'GR 1234-24');
    assert.equal(normalizePlate('GHANA\nTOYOTA'), null);
    assert.equal(normalizePlate('GR123456789'), null);
    assert.equal(normalizePlate('123424'), null);
});

test('maps portrait preview guide to camera pixels without stretching the image', () => {
    const crop = cameraCrop(720, 1280,
        { left: 0, top: 0, width: 360, height: 400 },
        { left: 18, top: 128, width: 324, height: 144 });
    assert.deepEqual(crop, { x: 36, y: 496, width: 648, height: 288 });
    assert.equal(crop.width / crop.height, 324 / 144);
});

test('maps landscape guide including preview offset on the page', () => {
    const crop = cameraCrop(1280, 720,
        { left: 20, top: 100, width: 640, height: 360 },
        { left: 52, top: 215.2, width: 576, height: 129.6 });
    assert.equal(crop.x, 64);
    assert.ok(Math.abs(crop.y - 230.4) < 0.001);
    assert.equal(crop.width, 1152);
    assert.equal(crop.height, 259.2);
});
