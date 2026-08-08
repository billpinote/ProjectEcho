import './bootstrap';
import { Html5Qrcode } from 'html5-qrcode';

window.Html5Qrcode = Html5Qrcode;

let importScanQrReader = null;
let importScanQrAutoSubmitTimer = null;
let importScanQrIsSubmitting = false;

const getQrPayloadValue = () => {
    const payloadInput = document.getElementById('payload');

    return payloadInput?.value?.trim() || '';
};

const getImportScanQrComponent = () => {
    const lookupForm = document.getElementById('scan-qr-lookup-form');
    const componentRoot = lookupForm?.closest('[wire\\:id]');
    const componentId = componentRoot?.getAttribute('wire:id');

    if (!componentId || !window.Livewire?.find) {
        return null;
    }

    return window.Livewire.find(componentId);
};

const submitQrLookup = () => {
    const lookupForm = document.getElementById('scan-qr-lookup-form');

    if (!lookupForm || !getQrPayloadValue() || importScanQrIsSubmitting) {
        return;
    }

    importScanQrIsSubmitting = true;

    const component = getImportScanQrComponent();

    if (component?.call) {
        Promise.resolve(component.call('submit')).finally(() => {
            importScanQrIsSubmitting = false;
        });

        return;
    }

    lookupForm.requestSubmit();
};

const queueQrLookupSubmit = (delay = 450) => {
    if (!getQrPayloadValue()) {
        return;
    }

    if (importScanQrAutoSubmitTimer) {
        window.clearTimeout(importScanQrAutoSubmitTimer);
    }

    importScanQrAutoSubmitTimer = window.setTimeout(() => {
        setQrStatus('QR payload detected. Verifying now...', 'muted');
        submitQrLookup();
    }, delay);
};

const fillQrPayload = (value, autoSubmit = true) => {
    const payloadInput = document.getElementById('payload');

    if (!payloadInput) {
        return;
    }

    payloadInput.value = value;
    payloadInput.dispatchEvent(new Event('input', { bubbles: true }));
    payloadInput.dispatchEvent(new Event('change', { bubbles: true }));

    if (autoSubmit) {
        const component = getImportScanQrComponent();

        if (component?.set) {
            Promise.resolve(component.set('payload', value)).then(() => {
                queueQrLookupSubmit(50);
            });

            return;
        }

        queueQrLookupSubmit();
    }
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

    if (!page || page.dataset.adminQrPage !== 'true' || page.dataset.initialized === 'true') {
        return;
    }

    page.dataset.initialized = 'true';

    const uploadInput = document.getElementById('qr-image-upload');
    const lookupForm = document.getElementById('scan-qr-lookup-form');
    const payloadInput = document.getElementById('payload');
    const startButton = document.getElementById('start-qr-camera');
    const stopButton = document.getElementById('stop-qr-camera');
    const scannerRegionId = 'qr-reader';

    lookupForm?.addEventListener('submit', () => {
        importScanQrIsSubmitting = true;
    });

    payloadInput?.addEventListener('input', () => {
        importScanQrIsSubmitting = false;

        if (getQrPayloadValue()) {
            queueQrLookupSubmit(900);
        }
    });

    payloadInput?.addEventListener('paste', () => {
        window.setTimeout(() => {
            importScanQrIsSubmitting = false;

            if (getQrPayloadValue()) {
                queueQrLookupSubmit(450);
            }
        }, 0);
    });

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
            setQrStatus('QR payload loaded from image. Verifying now...', 'success');
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
                    setQrStatus('QR payload captured from camera. Verifying now...', 'success');
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
