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
        <script src="{{ asset('js/qr-image-decoder.js') }}"></script>
    @endif
    <link rel="stylesheet" href="{{ asset('css/flightplan.css') }}">
    <style>
        #qr-reader video {
            transform: scaleX(-1);
        }
    </style>
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
                    <h1 class="echo-display" style="margin: 0.5rem 0 0;">Scan or Upload</h1>
                    <p class="echo-import-subtitle echo-body">
                        Use any option below to open the flight plan.
                    </p>
                </div>
            </div>
        </section>

        <div class="echo-import-layout">
            <section class="echo-import-panel">
                <form id="scan-qr-lookup-form" action="{{ route('flightplan.scan-qr.lookup') }}" method="POST" class="echo-import-stack" style="margin-top: 1.25rem;">
                    @csrf

                    <div class="echo-qr-tabs" role="tablist" aria-label="QR input method">
                        <button id="qr-tab-webcam" type="button" role="tab" aria-selected="true" aria-controls="qr-panel-webcam" class="echo-qr-tab echo-qr-tab-active"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 7h2l1.2-2h3.6L15 7h2a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Zm5 9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/></svg>Scan with Camera</button>
                        <button id="qr-tab-upload" type="button" role="tab" aria-selected="false" aria-controls="qr-panel-upload" class="echo-qr-tab echo-qr-tab-inactive"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M12 16V4m0 0L8 8m4-4 4 4M5 13v4a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-4"/></svg>Upload QR Image</button>
                    </div>

                    <div id="qr-panel-upload" data-qr-tab-panel="upload" role="tabpanel" aria-labelledby="qr-tab-upload" hidden class="echo-input-card">
                        <label for="qr-image-upload" class="echo-field-label echo-title" style="text-transform: none;">Upload QR</label>
                        <input
                            id="qr-image-upload"
                            class="echo-file-input"
                            type="file"
                            accept=".png,.jpg,.jpeg,.webp,.heic,.heif,image/png,image/jpeg,image/webp,image/heic,image/heif"
                        >
                        <p class="echo-help" style="margin: 0.75rem 0 0;">
                            Upload a PNG, JPG, WEBP, or HEIC image that contains the Echo QR code.
                        </p>
                        <p id="qr-image-upload-status" class="echo-help" style="margin: 0.75rem 0 0; display: none;"></p>
                    </div>

                    <div id="qr-panel-webcam" data-qr-tab-panel="webcam" role="tabpanel" aria-labelledby="qr-tab-webcam" class="echo-camera-card">
                        <div class="echo-camera-header">
                            <div>
                                <div class="echo-camera-title"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M7 7h2l1.2-2h3.6L15 7h2a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H7a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Zm5 9a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z"/></svg>Scan with Camera</div>
                                <div class="echo-help" style="margin-top: 0.35rem;">
                                    Allow camera access, then position the QR code within the frame.
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

                        <div id="qr-reader"><div class="echo-qr-empty-state"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 9V5a1 1 0 0 1 1-1h4M15 4h4a1 1 0 0 1 1 1v4M20 15v4a1 1 0 0 1-1 1h-4M9 20H5a1 1 0 0 1-1-1v-4M8 8h3v3H8V8Zm5 5h3v3h-3v-3Z"/></svg><strong>Camera preview will appear here</strong><span>Place the QR code inside the frame</span></div></div>
                    </div>                    

                    <div style="margin-top: 0.25rem;">
                        <button id="manual-qr-toggle" type="button" aria-expanded="false" aria-controls="manual-qr-recovery" class="echo-qr-recovery-trigger"><svg aria-hidden="true" viewBox="0 0 24 24"><path d="M9 18h6m-5 3h4M8.5 14.5A6 6 0 1 1 16 14c-.8.5-1.2 1.2-1.4 2H9.4c-.2-.8-.6-1.1-.9-1.5Z"/></svg><span><strong>Can't scan the QR?</strong><small>Paste the QR data instead.</small></span><svg class="echo-qr-chevron" aria-hidden="true" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></button>
                        <div id="manual-qr-recovery" hidden class="echo-input-card" style="margin-top: 0.75rem;">
                            <textarea
                                id="manual-qr-payload"
                                rows="4"
                                placeholder="Paste the decoded QR text here..."
                                class="echo-payload-textarea echo-mono"
                            ></textarea>
                            <button
                                id="manual-qr-load"
                                type="button"
                                class="echo-button echo-button-primary"
                                style="margin-top: 0.75rem;"
                            >
                                Load Flight Plan
                            </button>
                            <p class="echo-qr-security-note">Your data is read-only and secure.</p>
                        </div>
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
                    </div>

                    @error('payload')
                        <p
                            id="qr-payload-error"
                            class="echo-help"
                            role="alert"
                            tabindex="-1"
                            style="margin: 0.75rem 0 0; color: #ef4444;"
                        >{{ $message }}</p>
                    @enderror

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
                            <div class="echo-help" style="margin-top: 0.3rem;">Use the camera or upload a saved QR image from a device.</div>
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
                const manualPayloadInput = document.getElementById('manual-qr-payload');
                const manualLoadButton = document.getElementById('manual-qr-load');
                const manualToggle = document.getElementById('manual-qr-toggle');
                const manualRecovery = document.getElementById('manual-qr-recovery');
                const tabWebcam = document.getElementById('qr-tab-webcam');
                const tabUpload = document.getElementById('qr-tab-upload');
                const tabPanels = document.querySelectorAll('[data-qr-tab-panel]');
                const scannerRegionId = 'qr-reader';

                lookupForm?.addEventListener('submit', () => {
                    importScanQrIsSubmitting = true;
                });

                manualLoadButton?.addEventListener('click', () => {
                    fillQrPayload(manualPayloadInput?.value || '');
                });

                const selectQrTab = (tab) => {
                    const webcamActive = tab === 'webcam';
                    tabPanels.forEach((panel) => { panel.hidden = panel.dataset.qrTabPanel !== tab; });
                    tabWebcam?.setAttribute('aria-selected', String(webcamActive));
                    tabUpload?.setAttribute('aria-selected', String(!webcamActive));
                    tabWebcam?.classList.toggle('echo-qr-tab-active', webcamActive);
                    tabWebcam?.classList.toggle('echo-qr-tab-inactive', !webcamActive);
                    tabUpload?.classList.toggle('echo-qr-tab-active', !webcamActive);
                    tabUpload?.classList.toggle('echo-qr-tab-inactive', webcamActive);
                };
                tabWebcam?.addEventListener('click', () => selectQrTab('webcam'));
                tabUpload?.addEventListener('click', () => selectQrTab('upload'));
                manualToggle?.addEventListener('click', () => {
                    const expanded = manualToggle.getAttribute('aria-expanded') === 'true';
                    manualToggle.setAttribute('aria-expanded', String(!expanded));
                    if (manualRecovery) manualRecovery.hidden = expanded;
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

                        const decodedText = await window.EchoQrImageDecoder(
                            file, scannerRegionId, window.Html5Qrcode);

                        fillQrPayload(decodedText);
                        setQrStatus('QR payload loaded from image. Verifying now...', 'success');
                    } catch (error) {
                        console.error('[Echo QR] upload decoding failed before payload production', error);
                    setQrStatus('Unable to decode that image. Try a clearer QR image or use the camera scanner.', 'danger');
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
            const payloadError = document.getElementById('qr-payload-error');

            if (payloadError) {
                payloadError.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });
                payloadError.focus({ preventScroll: true });

                return;
            }

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
