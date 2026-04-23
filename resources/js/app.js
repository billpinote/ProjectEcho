import './bootstrap';
import { Html5Qrcode } from 'html5-qrcode';

window.Html5Qrcode = Html5Qrcode;

let importScanQrReader = null;

const fillQrPayload = (value) => {
    const payloadInput = document.getElementById('payload');

    if (!payloadInput) {
        return;
    }

    payloadInput.value = value;
    payloadInput.dispatchEvent(new Event('input', { bubbles: true }));
};

const setQrStatus = (message, tone = 'muted') => {
    const status = document.getElementById('qr-image-upload-status');

    if (!status) {
        return;
    }

    status.textContent = message;
    status.style.display = message ? 'block' : 'none';

    if (tone === 'success') {
        status.style.color = '#15803d';
        return;
    }

    if (tone === 'danger') {
        status.style.color = '#dc2626';
        return;
    }

    status.style.color = '#475569';
};

const stopImportScanQrCamera = async () => {
    if (!importScanQrReader || !importScanQrReader.isScanning) {
        return;
    }

    await importScanQrReader.stop();
    await importScanQrReader.clear();
};

const initImportScanQrPage = () => {
    const page = document.getElementById('import-scan-qr-page');

    if (!page || page.dataset.initialized === 'true') {
        return;
    }

    page.dataset.initialized = 'true';

    const uploadInput = document.getElementById('qr-image-upload');
    const startButton = document.getElementById('start-qr-camera');
    const stopButton = document.getElementById('stop-qr-camera');
    const scannerRegionId = 'qr-reader';

    uploadInput?.addEventListener('change', async (event) => {
        const file = event.target.files?.[0];

        if (!file) {
            setQrStatus('');
            return;
        }

        try {
            setQrStatus('Reading QR image...', 'muted');

            const fileReader = new Html5Qrcode(scannerRegionId);
            const decodedText = await fileReader.scanFile(file, true);

            fillQrPayload(decodedText);
            setQrStatus('QR payload loaded from image. Click "Find Flight Plan" to continue.', 'success');
        } catch (error) {
            setQrStatus('Unable to decode that image. Try a clearer QR image or use the webcam scanner.', 'danger');
        }
    });

    startButton?.addEventListener('click', async () => {
        try {
            setQrStatus('Starting camera...', 'muted');

            if (!importScanQrReader) {
                importScanQrReader = new Html5Qrcode(scannerRegionId);
            }

            await importScanQrReader.start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: { width: 220, height: 220 },
                },
                async (decodedText) => {
                    fillQrPayload(decodedText);
                    setQrStatus('QR payload captured from camera. Click "Find Flight Plan" to continue.', 'success');
                    await stopImportScanQrCamera();
                },
                () => {}
            );

            startButton.disabled = true;
            startButton.style.opacity = '0.55';
            if (stopButton) {
                stopButton.disabled = false;
                stopButton.style.opacity = '1';
            }

            setQrStatus('Camera is active. Hold the QR inside the frame.', 'muted');
        } catch (error) {
            setQrStatus('Unable to start the camera. Check browser permissions and HTTPS/local access.', 'danger');
        }
    });

    stopButton?.addEventListener('click', async () => {
        try {
            await stopImportScanQrCamera();
            setQrStatus('Camera stopped.', 'muted');
        } finally {
            if (startButton) {
                startButton.disabled = false;
                startButton.style.opacity = '1';
            }

            if (stopButton) {
                stopButton.disabled = true;
                stopButton.style.opacity = '0.55';
            }
        }
    });
};

window.initImportScanQrPage = initImportScanQrPage;

document.addEventListener('DOMContentLoaded', initImportScanQrPage);
document.addEventListener('livewire:navigated', initImportScanQrPage);
