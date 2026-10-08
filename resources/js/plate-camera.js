import { createWorker, PSM } from 'tesseract.js';
import { cameraCrop, normalizePlate } from './plate-recognition';
import { createCameraZoom } from './camera-zoom';
const activeScanners = new WeakMap();
const runningScanners = new Set();

function dataStatus(text) {
    const lastRead = (text || '').trim().replace(/\s+/g, ' ').slice(0, 60);
    return lastRead
        ? `Read: ${lastRead}. Adjust the zoom and hold the plate steady inside the guide.`
        : 'Scanning live… Zoom in until the plate fills the guide and hold steady.';
}

function updateZoomControls(scanner, zoom) {
    const slider = scanner.modal.querySelector('[data-camera-zoom]');
    slider.min = zoom.min;
    slider.max = zoom.max;
    slider.step = zoom.step;
    slider.value = zoom.value;
    slider.setAttribute('aria-valuetext', `${zoom.value.toFixed(1)} times zoom`);
    scanner.modal.querySelector('[data-camera-zoom-value]').textContent = `${zoom.value.toFixed(1)}×`;
    scanner.modal.querySelector('[data-camera-zoom-out]').disabled = zoom.value <= zoom.min;
    scanner.modal.querySelector('[data-camera-zoom-in]').disabled = zoom.value >= zoom.max;
    scanner.zoomVersion++;
    scanner.matches = 0;
    scanner.lastCandidate = null;
}

function setZoom(scanner, value) {
    if (! scanner?.zoom || ! scanner.scanning) return;
    scanner.zoomVersion++;
    void scanner.zoom.set(value);
}

function showError(container, message) {
    const error = container.querySelector('[data-camera-error]');
    if (! error) {
        console.error(message);

        return;
    }

    error.textContent = message;
    error.hidden = false;
}

async function stopCamera(scanner) {
    scanner.scanning = false;
    scanner.video.srcObject = null;
    scanner.stream?.getTracks().forEach((track) => track.stop());
    scanner.stream = null;
    scanner.modal.hidden = true;
    runningScanners.delete(scanner);

    if (scanner.startButton.isConnected) {
        scanner.startButton.focus();
    }

    if (scanner.worker) {
        const worker = scanner.worker;
        scanner.worker = null;
        try {
            await worker.terminate();
        } catch (error) {
            console.error('Could not stop the on-device OCR worker.', error);
        }
    }
}

async function scanFrames(scanner) {
    const context = scanner.canvas.getContext('2d', { willReadFrequently: true });
    let attempt = 0;

    while (scanner.scanning && scanner.worker) {
        if (! scanner.video.videoWidth || scanner.video.readyState < 2) {
            scanner.status.textContent = 'Waiting for camera frames…';
            await new Promise((resolve) => window.setTimeout(resolve, 200));
            continue;
        }
        const crop = cameraCrop(
            scanner.video.videoWidth, scanner.video.videoHeight,
            scanner.video.getBoundingClientRect(),
            scanner.container.querySelector('.carbay-plate-camera-guide').getBoundingClientRect(),
        );
        const frameZoomVersion = scanner.zoomVersion;
        // Keep the plate's proportions on portrait phones as well as landscape cameras.
        scanner.canvas.width = 1400;
        scanner.canvas.height = Math.max(1, Math.round(1400 * crop.height / crop.width));

        context.drawImage(
            scanner.video,
            crop.x,
            crop.y,
            crop.width,
            crop.height,
            0,
            0,
            scanner.canvas.width,
            scanner.canvas.height,
        );

        try {
            // Try both the original frame and a contrast-enhanced frame.
            if (attempt % 2) {
                const pixels = context.getImageData(0, 0, scanner.canvas.width, scanner.canvas.height);
                for (let i = 0; i < pixels.data.length; i += 4) {
                    const grey = pixels.data[i] * 0.299 + pixels.data[i + 1] * 0.587 + pixels.data[i + 2] * 0.114;
                    const value = Math.max(0, Math.min(255, (grey - 128) * 1.6 + 128));
                    pixels.data[i] = pixels.data[i + 1] = pixels.data[i + 2] = value;
                }
                context.putImageData(pixels, 0, 0);
            }
            await scanner.worker.setParameters({
                tessedit_pageseg_mode: attempt % 2 ? PSM.SINGLE_LINE : PSM.SPARSE_TEXT,
            });
            const { data } = await scanner.worker.recognize(scanner.canvas);
            if (! scanner.scanning) return;
            if (frameZoomVersion !== scanner.zoomVersion) continue;
            scanner.lastText = data.text;
            const plate = normalizePlate(data.text);
            scanner.matches = plate && plate === scanner.lastCandidate ? (scanner.matches || 0) + 1 : (plate ? 1 : 0);
            scanner.lastCandidate = plate;

            // Whitelisted plate text can have a low overall OCR confidence even
            // when correct. Repeated readings can fill it for human confirmation.
            if (plate && (data.confidence >= 70 || scanner.matches >= 2)) {
                scanner.scanning = false;
                scanner.status.textContent = 'Plate found. Checking it now…';
                const confidence = Math.max(0, Math.min(1, data.confidence / 100));
                await stopCamera(scanner);
                scanner.container.dispatchEvent(new CustomEvent('carbay-plate-scan', {
                    bubbles: true,
                    detail: { plate, confidence },
                }));

                return;
            }
        } catch (error) {
            if (! scanner.scanning) return;
            scanner.scanning = false;
            await stopCamera(scanner);
            showError(scanner.container, 'The camera frame could not be read. Adjust the plate and try again.');
            console.error('On-device plate recognition failed.', error);

            return;
        }

        scanner.status.textContent = dataStatus(scanner.lastText);
        attempt++;
        await new Promise((resolve) => window.setTimeout(resolve, 1200));
    }
}

async function startCamera(button) {
    const container = button.closest('.carbay-new-wash-job');
    if (activeScanners.get(container)?.scanning) return;
    const scanner = {
        container,
        startButton: button,
        modal: container.querySelector('[data-camera-modal]'),
        video: container.querySelector('[data-camera-video]'),
        status: container.querySelector('[data-camera-status]'),
        stream: null,
        worker: null,
        scanning: true,
        zoomVersion: 0,
        canvas: document.createElement('canvas'),
    };
    activeScanners.set(container, scanner);

    container.querySelector('[data-camera-error]').hidden = true;

    if (! navigator.mediaDevices?.getUserMedia) {
        scanner.scanning = false;
        showError(container, 'Live camera scanning requires HTTPS and a browser that supports camera access.');

        return;
    }

    runningScanners.add(scanner);
    button.disabled = true;
    button.textContent = 'Starting camera…';

    try {
        const stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        });
        if (! scanner.scanning) {
            stream.getTracks().forEach((track) => track.stop());
            return;
        }
        scanner.stream = stream;
        scanner.video.srcObject = scanner.stream;
        scanner.modal.hidden = false;
        await scanner.video.play();
        scanner.zoom = createCameraZoom(scanner.video, stream.getVideoTracks()[0], (zoom) => updateZoomControls(scanner, zoom));
        scanner.status.textContent = 'Loading the on-device plate reader…';

        const worker = await createWorker('eng', 1, {
            workerPath: 'https://cdn.jsdelivr.net/npm/tesseract.js@7.0.0/dist/worker.min.js',
            corePath: 'https://cdn.jsdelivr.net/npm/tesseract.js-core@7.0.0',
            langPath: 'https://tessdata.projectnaptha.com/4.0.0',
            gzip: true,
            cachePath: 'carbay-plate-ocr',
            logger: (message) => {
                if (message.status === 'loading tesseract core') {
                    scanner.status.textContent = 'Preparing the on-device plate reader…';
                } else if (message.status === 'loading language traineddata') {
                    scanner.status.textContent = `Downloading English OCR data (${Math.round((message.progress || 0) * 100)}%)…`;
                }
            },
        });
        if (! scanner.scanning) {
            await worker.terminate();
            return;
        }
        scanner.worker = worker;
        await scanner.worker.setParameters({
            tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-',
            tessedit_pageseg_mode: PSM.SINGLE_LINE,
            preserve_interword_spaces: '1',
        });

        if (! scanner.scanning) return;
        scanner.status.textContent = 'Center the plate inside the guide. Recognition stays on this device.';
        await scanFrames(scanner);
    } catch (error) {
        if (! scanner.scanning) return;
        await stopCamera(scanner);
        showError(
            container,
            error?.name === 'NotAllowedError'
                ? 'Camera permission was denied. Allow camera access or enter the plate manually.'
                : 'The camera or local OCR could not start. Check your connection, then try again or enter the plate manually.',
        );
        console.error('Could not start the on-device plate scanner.', error);
    } finally {
        button.disabled = false;
        button.textContent = 'Scan plate live';
    }
}

document.addEventListener('click', (event) => {
    if (! (event.target instanceof Element)) {
        return;
    }

    const startButton = event.target.closest('[data-carbay-start-camera]');
    if (startButton && ! startButton.disabled) {
        void startCamera(startButton);

        return;
    }

    const closeButton = event.target.closest('[data-carbay-stop-camera]');
    if (closeButton) {
        const container = closeButton.closest('.carbay-new-wash-job');
        const scanner = container && activeScanners.get(container);

        if (scanner) {
            void stopCamera(scanner);
        }
    }

    const zoomButton = event.target.closest('[data-camera-zoom-in], [data-camera-zoom-out]');
    if (zoomButton && ! zoomButton.disabled) {
        const scanner = activeScanners.get(zoomButton.closest('.carbay-new-wash-job'));
        if (scanner?.zoom) {
            const direction = zoomButton.hasAttribute('data-camera-zoom-in') ? 1 : -1;
            setZoom(scanner, scanner.zoom.value + direction * Math.max(scanner.zoom.step, 0.5));
        }
    }
});

document.addEventListener('input', (event) => {
    if (event.target instanceof Element && event.target.matches('[data-camera-zoom]')) {
        setZoom(activeScanners.get(event.target.closest('.carbay-new-wash-job')), event.target.value);
    }
});

document.addEventListener('livewire:navigating', () => {
    runningScanners.forEach((scanner) => {
        void stopCamera(scanner);
    });
});

window.addEventListener('pagehide', () => {
    runningScanners.forEach((scanner) => void stopCamera(scanner));
});
