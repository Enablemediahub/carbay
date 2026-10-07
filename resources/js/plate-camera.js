import { createWorker, PSM } from 'tesseract.js';

const platePattern = /^(?:[A-Z]{2}\s?\d{3,4}\s?[A-Z]?|[A-Z]{2}\s?\d{4}-\d{2}|[A-Z]{2}\s?\d{4}\s?[A-Z])$/;
const compactPlatePattern = /^([A-Z]{2})(\d{3,4})([A-Z]?)$|^([A-Z]{2})(\d{4})(\d{2})$|^([A-Z]{2})(\d{4})([A-Z])$/;
const activeScanners = new WeakMap();
const runningScanners = new Set();

function normalizePlate(text) {
    const compact = text.toUpperCase().replace(/[^A-Z0-9]/g, '');
    const match = compact.match(compactPlatePattern);

    if (! match) {
        return null;
    }

    let plate;

    if (match[1]) {
        plate = `${match[1]} ${match[2]}${match[3]}`;
    } else if (match[4]) {
        plate = `${match[4]} ${match[5]}-${match[6]}`;
    } else {
        plate = `${match[7]} ${match[8]}${match[9]}`;
    }

    return platePattern.test(plate) ? plate : null;
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

    while (scanner.scanning && scanner.worker && scanner.video.videoWidth > 0) {
        scanner.canvas.width = 1000;
        scanner.canvas.height = 260;
        const sourceWidth = scanner.video.videoWidth * 0.9;
        const sourceHeight = scanner.video.videoHeight * 0.36;
        const sourceX = (scanner.video.videoWidth - sourceWidth) / 2;
        const sourceY = (scanner.video.videoHeight - sourceHeight) / 2;

        context.drawImage(
            scanner.video,
            sourceX,
            sourceY,
            sourceWidth,
            sourceHeight,
            0,
            0,
            scanner.canvas.width,
            scanner.canvas.height,
        );

        try {
            const { data } = await scanner.worker.recognize(scanner.canvas);
            const plate = normalizePlate(data.text);

            if (plate && data.confidence >= 30) {
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
            scanner.scanning = false;
            await stopCamera(scanner);
            showError(scanner.container, 'The camera frame could not be read. Adjust the plate and try again.');
            console.error('On-device plate recognition failed.', error);

            return;
        }

        scanner.status.textContent = 'Scanning live… Keep the plate inside the guide and hold steady.';
        await new Promise((resolve) => window.setTimeout(resolve, 1200));
    }
}

async function startCamera(button) {
    const container = button.closest('.carbay-new-wash-job');
    const scanner = {
        container,
        startButton: button,
        modal: container.querySelector('[data-camera-modal]'),
        video: container.querySelector('[data-camera-video]'),
        status: container.querySelector('[data-camera-status]'),
        stream: null,
        worker: null,
        scanning: false,
        canvas: document.createElement('canvas'),
    };
    activeScanners.set(container, scanner);

    container.querySelector('[data-camera-error]').hidden = true;

    if (! navigator.mediaDevices?.getUserMedia) {
        showError(container, 'Live camera scanning requires HTTPS and a browser that supports camera access.');

        return;
    }

    runningScanners.add(scanner);
    button.disabled = true;
    button.textContent = 'Starting camera…';

    try {
        scanner.stream = await navigator.mediaDevices.getUserMedia({
            audio: false,
            video: {
                facingMode: { ideal: 'environment' },
                width: { ideal: 1280 },
                height: { ideal: 720 },
            },
        });
        scanner.video.srcObject = scanner.stream;
        scanner.modal.hidden = false;
        await scanner.video.play();
        scanner.status.textContent = 'Loading the on-device plate reader…';

        scanner.worker = await createWorker('eng', 1, {
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
        await scanner.worker.setParameters({
            tessedit_char_whitelist: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
            tessedit_pageseg_mode: PSM.SINGLE_LINE,
            preserve_interword_spaces: '0',
        });

        scanner.status.textContent = 'Center the plate inside the guide. Recognition stays on this device.';
        scanner.scanning = true;
        await scanFrames(scanner);
    } catch (error) {
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
});

document.addEventListener('livewire:navigating', () => {
    runningScanners.forEach((scanner) => {
        void stopCamera(scanner);
    });
});
