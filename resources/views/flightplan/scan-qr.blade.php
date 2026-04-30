<!DOCTYPE html>
<html lang="en-CA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scan / Upload QR</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script src="https://unpkg.com/html5-qrcode"></script>
    @endif
    <link rel="stylesheet" href="{{ asset('css/flightplan.css') }}">
</head>
<body class="bg-gray-100">
    @include('flightplan.partials.navbar', ['activeNav' => 'scan-upload-qr'])

    @php
        $statusBadgeClass = match ($matchedFlight['status_color'] ?? 'gray') {
            'warning' => 'echo-status-pending',
            'success' => 'echo-status-accepted',
            'info' => 'echo-status-active',
            'danger' => 'echo-status-rejected',
            default => 'echo-status-completed',
        };
    @endphp

    <div id="import-scan-qr-page" class="flightplan-import-page">
        <section class="echo-import-hero">
            <div class="echo-import-hero-grid">
                <div>
                    <p class="echo-import-kicker echo-label">Flight Plan Tool</p>
                    <h1 class="echo-display" style="margin: 0.5rem 0 0;">Scan / Upload QR</h1>
                    <p class="echo-import-subtitle echo-body">
                        Scan a live QR code from your camera or upload a QR image, then verify the signed Echo payload and open the flight plan record.
                    </p>
                </div>
            </div>
        </section>

        <div class="echo-import-layout">
            <section class="echo-import-panel">
                <div class="echo-panel-header">
                    <div>
                        <h2 class="echo-heading" style="margin: 0;">Scan or Upload</h2>
                        <p class="echo-help" style="margin: 0.35rem 0 0;">Use either method below to capture the QR payload.</p>
                    </div>
                </div>

                <form id="scan-qr-lookup-form" action="{{ route('flightplan.scan-qr.lookup') }}" method="POST" class="echo-import-stack" style="margin-top: 1.25rem;">
                    @csrf

                    <div class="echo-input-card">
                        <label for="qr-image-upload" class="echo-field-label echo-title" style="text-transform: none;">Upload QR</label>
                        <input
                            id="qr-image-upload"
                            class="echo-file-input"
                            type="file"
                            accept=".png,image/png,image/jpeg,image/jpg,image/webp"
                        >
                        <p class="echo-help" style="margin: 0.75rem 0 0;">
                            Upload a PNG, JPG, or WEBP image that contains the Echo QR code.
                        </p>
                        <p id="qr-image-upload-status" class="echo-help" style="margin: 0.75rem 0 0; display: none;"></p>
                    </div>

                    <div class="echo-camera-card">
                        <div class="echo-camera-header">
                            <div>
                                <div class="echo-title">Scan QR</div>
                                <div class="echo-help" style="margin-top: 0.35rem;">
                                    Allow camera access, then place the QR code inside the frame until it is detected.
                                </div>
                            </div>

                            <div class="echo-camera-actions">
                                <button
                                    id="start-qr-camera"
                                    type="button"
                                    class="echo-button echo-button-primary"
                                >
                                    Start Camera
                                </button>

                                <button
                                    id="stop-qr-camera"
                                    type="button"
                                    disabled
                                    class="echo-button echo-button-secondary"
                                >
                                    Stop Camera
                                </button>
                            </div>
                        </div>

                        <div id="qr-reader"></div>
                    </div>                    

                    <div style="display: none;">
                        <label for="payload" class="echo-field-label echo-title">QR Payload</label>
                        <textarea
                            id="payload"
                            name="payload"
                            rows="4"
                            autofocus
                            placeholder="ECHOFPL|2|OFFLINE|K1|S1|123|20260428T143000Z|..."
                            class="echo-payload-textarea echo-mono"
                        >{{ old('payload', $payload ?? '') }}</textarea>
                        @error('payload')
                            <p class="echo-help" style="margin: 0.75rem 0 0; color: #ef4444;">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="echo-action-row">
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <button type="submit" class="echo-button echo-button-accent">
                                Find Flight Plan
                            </button>

                            <button
                                type="button"
                                class="echo-button echo-button-secondary"
                                onclick="document.getElementById('payload').value = ''; document.getElementById('payload').focus();"
                            >
                                Clear
                            </button>
                        </div>

                        <div class="echo-help" style="max-width: 32rem;">
                            Tip: if a QR scanner pastes the full payload into the field, you can submit directly after it appears.
                        </div>
                    </div>
                </form>
            </section>

            <div class="echo-import-stack">
                @if($matchedFlight)
                    <section id="matched-flight-plan" class="echo-import-summary">
                        <div class="echo-summary-header">
                            <div>
                                <div class="echo-label" style="color: #0f5f4a;">Matched Flight Plan</div>
                                <h3 class="echo-display" style="margin: 0.5rem 0 0; font-size: 1.5rem;">{{ $matchedFlight['aircraft_identification'] }}</h3>
                            </div>

                            <span class="echo-status-badge {{ $statusBadgeClass }}">
                                {{ $matchedFlight['status_label'] }}
                            </span>
                        </div>

                        <div class="echo-summary-grid">
                            <div>
                                <div class="echo-label flightplan-muted-label">DOF</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['date_of_flight'] }}</div>
                            </div>
                            <div>
                                <div class="echo-label flightplan-muted-label">PTD</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['proposed_time'] }}</div>
                            </div>
                            <div>
                                <div class="echo-label flightplan-muted-label">From</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['departure_aerodrome'] }}</div>
                            </div>
                            <div>
                                <div class="echo-label flightplan-muted-label">To</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['destination_aerodrome'] }}</div>
                            </div>
                        </div>

                        @if($matchedFlight['can_open'])
                            <a
                                href="{{ $matchedFlight['view_url'] }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="echo-button echo-button-primary"
                                style="margin-top: 1rem;"
                            >
                                Open Flight Plan
                            </a>
                        @else
                            <div class="echo-help" style="margin-top: 1rem;">
                                This flight plan was found, but opening the full record requires the same browser session that created it or an authenticated ATC/admin account.
                            </div>
                        @endif

                            <form
                                action="{{ route('flightplan.edit-from-qr') }}"
                                method="POST"
                                style="margin-top: 1rem; display: inline;"
                            >
                                @csrf
                                <input type="hidden" name="payload" value="{{ $payload }}">
                                <button type="submit" class="echo-button echo-button-secondary">
                                    Edit Flight Plan
                                </button>
                            </form>

                    </section>
                @else
                    <section id="matched-flight-plan" class="echo-import-empty echo-empty-state">
                        <div class="echo-title">No Flight Loaded Yet</div>
                        <div class="echo-help" style="margin-top: 0.5rem;">
                            Once a QR is scanned or uploaded successfully, the matched flight plan summary will appear here.
                        </div>
                    </section>
                @endif

                <section class="echo-import-panel">
                    <h3 class="echo-heading" style="margin: 0;">How It Works</h3>

                    <div class="echo-workflow">
                        <div class="echo-workflow-step">
                            <div class="echo-label">1. Capture the QR</div>
                            <div class="echo-help" style="margin-top: 0.3rem;">Use the webcam or upload a saved QR image from a device.</div>
                        </div>

                        <div class="echo-workflow-step">
                            <div class="echo-label">2. Load the Echo record</div>
                            <div class="echo-help" style="margin-top: 0.3rem;">The page verifies the signature, then uses the embedded full record even if the live database copy is unavailable.</div>
                        </div>

                        <div class="echo-workflow-step">
                            <div class="echo-label">3. Open for review</div>
                            <div class="echo-help" style="margin-top: 0.3rem;">Open the reconstructed flight plan in a new tab for read-only review from the signed payload.</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <script>
        if (!window.initImportScanQrPage) {
            let importScanQrReader = null;
            let importScanQrAutoSubmitTimer = null;
            let importScanQrIsSubmitting = false;

            const getQrPayloadValue = () => {
                const payloadInput = document.getElementById('payload');

                return payloadInput ? payloadInput.value.trim() : '';
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

            const submitQrLookup = () => {
                const lookupForm = document.getElementById('scan-qr-lookup-form');

                if (!lookupForm || !getQrPayloadValue() || importScanQrIsSubmitting) {
                    return;
                }

                importScanQrIsSubmitting = true;
                lookupForm.submit();
            };

            const queueQrLookupSubmit = (delay = 150) => {
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
                    queueQrLookupSubmit();
                }
            };

            const stopImportScanQrCamera = async () => {
                if (!importScanQrReader || !importScanQrReader.isScanning) {
                    return;
                }

                await importScanQrReader.stop();
                await importScanQrReader.clear();
            };

            window.initImportScanQrPage = () => {
                const page = document.getElementById('import-scan-qr-page');

                if (!page || page.dataset.initialized === 'true') {
                    return;
                }

                page.dataset.initialized = 'true';

                const lookupForm = document.getElementById('scan-qr-lookup-form');
                const payloadInput = document.getElementById('payload');
                const uploadInput = document.getElementById('qr-image-upload');
                const startButton = document.getElementById('start-qr-camera');
                const stopButton = document.getElementById('stop-qr-camera');
                const scannerRegionId = 'qr-reader';

                lookupForm?.addEventListener('submit', () => {
                    importScanQrIsSubmitting = true;
                });

                payloadInput?.addEventListener('input', () => {
                    if (getQrPayloadValue()) {
                        queueQrLookupSubmit(250);
                    }
                });

                payloadInput?.addEventListener('paste', () => {
                    window.setTimeout(() => {
                        if (getQrPayloadValue()) {
                            queueQrLookupSubmit(100);
                        }
                    }, 0);
                });

                uploadInput?.addEventListener('change', async (event) => {
                    const file = event.target.files?.[0];

                    if (!file) {
                        setQrStatus('');
                        return;
                    }

                    if (!window.Html5Qrcode) {
                        setQrStatus('QR decoding library is not available. Reload the page and try again.', 'danger');
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
                    if (!window.Html5Qrcode) {
                        setQrStatus('QR scanning library is not available. Reload the page and try again.', 'danger');
                        return;
                    }

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
                        if (stopButton) {
                            stopButton.disabled = false;
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
                        }

                        if (stopButton) {
                            stopButton.disabled = true;
                        }
                    }
                });
            };
        }

        window.initImportScanQrPage?.();
        window.setTimeout(() => window.initImportScanQrPage?.(), 250);

        window.requestAnimationFrame(() => {
            const matchedFlightPanel = document.getElementById('matched-flight-plan');

            if (!matchedFlightPanel || !matchedFlightPanel.classList.contains('echo-import-summary')) {
                return;
            }

            matchedFlightPanel.scrollIntoView({
                behavior: 'smooth',
                block: 'start',
            });
        });
    </script>
</body>
</html>
