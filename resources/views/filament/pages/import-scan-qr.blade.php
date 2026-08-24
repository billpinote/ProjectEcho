<x-filament-panels::page>
    @php
        $statusBadgeClass = match ($matchedFlight['status_color'] ?? 'gray') {
            'warning' => 'echo-status-pending',
            'success' => 'echo-status-accepted',
            'info' => 'echo-status-active',
            'danger' => 'echo-status-rejected',
            default => 'echo-status-completed',
        };
    @endphp

    <style>
        #import-scan-qr-page button[disabled] {
            opacity: 0.55;
            cursor: not-allowed !important;
        }

        #import-scan-qr-page {
            max-width: 1180px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .echo-import-hero,
        .echo-import-panel,
        .echo-import-summary,
        .echo-import-empty {
            border: 1px solid var(--color-echo-border);
            border-radius: 1rem;
            background: var(--color-echo-card);
            box-shadow: 0 12px 32px rgba(10, 63, 50, 0.06);
        }

        .echo-import-hero {
            padding: 1.5rem;
            background:
                radial-gradient(circle at top right, color-mix(in srgb, var(--color-echo-accent) 12%, transparent), transparent 28%),
                linear-gradient(180deg, #fffdf7 0%, color-mix(in srgb, var(--color-echo-background) 72%, white) 100%);
        }

        .echo-import-hero-grid,
        .echo-import-layout {
            display: grid;
            gap: 1.25rem;
        }

        .echo-import-hero-grid {
            grid-template-columns: minmax(0, 1.2fr) minmax(280px, 0.8fr);
            align-items: start;
        }

        .echo-import-layout {
            grid-template-columns: minmax(0, 1.15fr) minmax(320px, 0.85fr);
        }

        .echo-import-kicker {
            margin: 0;
            color: var(--color-echo-primary);
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .echo-import-subtitle {
            margin: 0.75rem 0 0;
            max-width: 60ch;
            color: var(--color-echo-text-secondary);
        }

        .echo-payload-card {
            padding: 1rem 1.1rem;
            border: 1px solid var(--color-echo-border);
            border-radius: 0.9rem;
            background: rgba(255, 255, 255, 0.72);
        }

        .echo-import-panel,
        .echo-import-summary,
        .echo-import-empty {
            padding: 1.5rem;
        }

        .echo-import-stack {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }

        .echo-input-card,
        .echo-camera-card,
        .echo-summary-grid > div,
        .echo-workflow-step {
            border: 1px solid var(--color-echo-border);
            border-radius: 0.9rem;
            background: color-mix(in srgb, var(--color-echo-card) 76%, white);
        }

        .echo-input-card,
        .echo-camera-card {
            padding: 1rem;
        }

        .echo-summary-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .echo-summary-grid > div {
            padding: 0.9rem;
        }

        .echo-workflow {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .echo-workflow-step {
            padding: 0.9rem 1rem;
            border-inline-start: 4px solid var(--color-echo-pending);
        }

        .echo-field-label {
            display: block;
            margin-bottom: 0.75rem;
        }

        .echo-file-input,
        .echo-payload-textarea {
            width: 100%;
            border: 1px solid var(--color-echo-border);
            border-radius: 0.9rem;
            padding: 0.85rem 1rem;
            box-sizing: border-box;
            background: #fff;
            color: var(--color-echo-text-primary);
        }

        .echo-file-input {
            font-size: var(--text-echo-body);
            line-height: var(--text-echo-body--line-height);
        }

        .echo-payload-textarea {
            min-height: 7.5rem;
            resize: vertical;
        }

        .echo-file-input:focus,
        .echo-payload-textarea:focus {
            outline: none;
            border-color: var(--color-echo-primary);
            box-shadow: 0 0 0 3px color-mix(in srgb, var(--color-echo-primary) 18%, white);
        }

        .echo-panel-header,
        .echo-camera-header,
        .echo-camera-actions,
        .echo-action-row {
            display: flex;
            justify-content: space-between;
            align-items: start;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .echo-camera-actions,
        .echo-action-row {
            align-items: center;
        }

        .echo-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.85rem;
            padding: 0.8rem 1rem;
            border: 1px solid transparent;
            font-size: var(--text-echo-body);
            line-height: var(--text-echo-body--line-height);
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 180ms ease, border-color 180ms ease, color 180ms ease, box-shadow 180ms ease;
        }

        .echo-button-primary {
            background: var(--color-echo-primary);
            color: #fffdf7;
            box-shadow: 0 10px 24px rgba(15, 95, 74, 0.18);
        }

        .echo-button-primary:hover {
            background: var(--color-echo-primary-dark);
        }

        .echo-button-secondary {
            background: #fff;
            border-color: var(--color-echo-border);
            color: var(--color-echo-text-primary);
        }

        .echo-button-secondary:hover {
            background: color-mix(in srgb, var(--color-echo-background) 70%, white);
        }

        .echo-button-accent {
            background: var(--color-echo-pending);
            color: #fffdf7;
            box-shadow: 0 10px 24px rgba(245, 165, 36, 0.2);
        }

        .echo-button-accent:hover {
            background: color-mix(in srgb, var(--color-echo-pending) 88%, black 8%);
        }

        .echo-camera-card {
            background: linear-gradient(180deg, #ffffff 0%, color-mix(in srgb, var(--color-echo-background) 72%, white) 100%);
        }

        .echo-input-card {
            background: linear-gradient(180deg, #ffffff 0%, color-mix(in srgb, var(--color-echo-background) 72%, white) 100%);
        }

        #qr-reader {
            margin-top: 1rem;
            min-height: 280px;
            border: 1px dashed color-mix(in srgb, var(--color-echo-text-secondary) 35%, white);
            border-radius: 1rem;
            background: #ffffff;
            overflow: hidden;
            padding: 0.75rem;
        }

        .echo-summary-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .echo-summary-status {
            margin-top: 0.75rem;
        }

        .echo-empty-state {
            text-align: center;
            color: var(--color-echo-text-secondary);
        }

        @media (max-width: 900px) {
            .echo-import-hero-grid,
            .echo-import-layout,
            .echo-summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div id="import-scan-qr-page" data-admin-qr-page="true">
        <section class="echo-import-hero">
            <div class="echo-import-hero-grid">
                <div>
                    <p class="echo-import-kicker echo-label">Echo Flight Operations</p>
                    <h2 class="echo-display" style="margin: 0.5rem 0 0;">Open Flight Plan</h2>
                    <p class="echo-import-subtitle echo-body">
                        Scan the flight plan QR with your camera or upload an image to review, authorize, or process the flight.
                    </p>
                </div>
            </div>
        </section>

        <div class="echo-import-layout">
            <section class="echo-import-panel">
                <div class="echo-panel-header">
                    <div>
                        <h3 class="echo-heading" style="margin: 0;">Scan or Upload</h3>
                        <p class="echo-help" style="margin: 0.35rem 0 0;">Use either method below to open the flight plan.</p>
                    </div>
                </div>

                <form id="scan-qr-lookup-form" wire:submit="submit" class="echo-import-stack" style="margin-top: 1.25rem;">
                    
                    <div class="echo-camera-card">
                        <div class="echo-camera-header">
                            <div>
                                <div class="echo-title">Scan with Webcam</div>
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

                    <div class="echo-input-card">
                        <label for="qr-image-upload" class="echo-field-label echo-title">Upload QR Image</label>
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

                    <div>
                        @if($this->shouldShowRawPayload())
                            <label for="payload" class="echo-field-label echo-title">QR Payload</label>
                        @endif
                        <textarea
                            id="payload"
                            wire:model.live.debounce.300ms="payload"
                            rows="4"
                            autofocus
                            placeholder="Paste a flight plan QR value if needed"
                            class="echo-payload-textarea echo-mono {{ $this->shouldShowRawPayload() ? '' : 'sr-only' }}"
                            @if(! $this->shouldShowRawPayload()) aria-hidden="true" tabindex="-1" @endif
                        ></textarea>
                        @error('payload')
                            <p
                                id="qr-payload-error"
                                class="echo-help"
                                role="alert"
                                tabindex="-1"
                                style="margin: 0.75rem 0 0; color: var(--color-echo-rejected);"
                            >{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="echo-action-row">
                        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                            <button
                                type="submit"
                                wire:loading.attr="disabled"
                                wire:target="submit"
                                class="echo-button echo-button-accent"
                            >
                                <span wire:loading.remove wire:target="submit">Open Flight Plan</span>
                                <span wire:loading wire:target="submit">Loading...</span>
                            </button>

                            <button
                                type="button"
                                wire:click="$set('payload', '')"
                                class="echo-button echo-button-secondary"
                            >
                                Clear
                            </button>
                        </div>

                        <div class="echo-help" style="max-width: 32rem;">
                            Tip: scanning or uploading a valid QR opens the flight plan automatically.
                        </div>
                    </div>
                </form>
            </section>

            <div class="echo-import-stack">
                @if($matchedFlight)
                    <section id="matched-flight-plan" class="echo-import-summary">
                        <div class="echo-summary-header">
                            <div>
                                <div class="echo-label" style="color: var(--color-echo-primary);">Flight Plan Found</div>
                                <h3 class="echo-display" style="margin: 0.5rem 0 0; font-size: 1.5rem;">{{ $matchedFlight['aircraft_identification'] }}</h3>
                            </div>

                            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                                <span class="echo-status-badge {{ $statusBadgeClass }}">
                                    {{ $matchedFlight['status_label'] }}
                                </span>
                                <button type="button" wire:click="startOver" class="echo-button echo-button-secondary" style="padding: 0.55rem 0.75rem;">
                                    <x-filament::icon icon="heroicon-o-arrow-path" class="fi-size-4" />
                                    <span>Start Over</span>
                                </button>
                            </div>
                        </div>

                        <div class="echo-summary-grid">
                            <div>
                                <div class="echo-label" style="text-transform: uppercase; color: var(--color-echo-text-secondary);">DOF</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['date_of_flight'] }}</div>
                            </div>
                            <div>
                                <div class="echo-label" style="text-transform: uppercase; color: var(--color-echo-text-secondary);">PTD</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['proposed_time'] }}</div>
                            </div>
                            <div>
                                <div class="echo-label" style="text-transform: uppercase; color: var(--color-echo-text-secondary);">From</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['departure_aerodrome'] }}</div>
                            </div>
                            <div>
                                <div class="echo-label" style="text-transform: uppercase; color: var(--color-echo-text-secondary);">To</div>
                                <div class="echo-mono" style="margin-top: 0.35rem;">{{ $matchedFlight['destination_aerodrome'] }}</div>
                            </div>
                        </div>

                        <a
                            href="{{ $matchedFlight['view_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="echo-button echo-button-primary"
                            style="margin-top: 1rem;"
                        >
                            Open Flight Plan
                        </a>

                        @if($this->isPicAuthorizationPage())
                            @if($this->isPicAuthorizationPreparer())
                                <div style="margin-top: 1.25rem; padding: 1rem; border: 2px solid #718096; border-radius: 0.75rem; background: #edf2f7;">
                                    <strong>You prepared this flight plan</strong>
                                    <p style="margin: 0.4rem 0 0;">This authorization request must be acted on by another eligible PPL, CPL, or ATPL pilot from your operator.</p>
                                </div>
                            @elseif($this->isPicAuthorizationDeclined())
                                <div style="margin-top: 1.25rem; padding: 1rem; border: 2px solid #718096; border-radius: 0.75rem; background: #edf2f7;">
                                    <strong>{{ $this->isPicAuthorizationDeclineActor() ? 'You declined this flight plan' : 'PIC authorization declined' }}</strong>
                                    <p style="margin: 0.4rem 0 0;">This authorization decision is complete. A corrected or resubmitted revision is required before another PIC decision.</p>
                                </div>
                            @elseif($this->canAuthorizeMatchedFlight())
                                <div style="margin-top: 1.25rem; padding: 1rem; border: 2px solid #b7791f; border-radius: 0.75rem; background: #fff8e1;">
                                    <strong>PIC Authorization Required</strong>
                                    <p style="margin: 0.4rem 0 0;">Review this flight plan carefully. By authorizing it, you identify yourself as the Pilot-in-Command for this flight.</p>
                                </div>
                                <button type="button" wire:click="authorizeAsPic" wire:loading.attr="disabled" class="echo-button echo-button-accent" style="margin-top: 1rem;">
                                    Authorize as PIC
                                </button>
                            @else
                                <div style="margin-top: 1.25rem; padding: 1rem; border: 2px solid #718096; border-radius: 0.75rem; background: #edf2f7;">
                                    <strong>Awaiting PIC Authorization</strong>
                                    <p style="margin: 0.4rem 0 0;">This flight plan requires authorization from a verified PPL, CPL, or ATPL holder. You may review the flight plan, but you cannot authorize it.</p>
                                </div>
                            @endif

                            @if(! $this->isPicAuthorizationPreparer() && ! $this->isPicAuthorizationDeclined())
                                <button
                                    type="button"
                                    class="echo-button echo-button-secondary echo-decline-flight-trigger"
                                    data-callsign="{{ $matchedFlight['aircraft_identification'] }}"
                                    style="margin-top: 1rem;"
                                >
                                    Decline Flight Plan
                                    <span class="sr-only">Decline Authorization</span>
                                </button>
                                @error('declineReason')
                                    <div class="echo-help" style="margin-top: 0.5rem; color:#b91c1c;" role="alert">{{ $message }}</div>
                                @enderror
                            @endif
                        @endif
                    </section>
                @else
                    <section class="echo-import-empty echo-empty-state">
                        <div class="echo-title">No Flight Loaded Yet</div>
                        <div class="echo-help" style="margin-top: 0.5rem;">
                            Once a QR is scanned or uploaded successfully, the matched flight plan summary will appear here.
                        </div>
                    </section>
                @endif

                <section class="echo-import-panel">
                    <h3 class="echo-heading" style="margin: 0;">How it works</h3>

                    <div class="echo-workflow">
                        @foreach($this->workflowGuidance() as $step)
                            <div class="echo-workflow-step">
                                <div class="echo-label">{{ $step['title'] }}</div>
                                <div class="echo-help" style="margin-top: 0.3rem;">{{ $step['body'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>
        </div>
    </div>

    @vite('resources/js/app.js')
    <script>
        const scrollToPayloadError = () => {
            const payloadError = document.getElementById('qr-payload-error');

            if (!payloadError) {
                return false;
            }

            payloadError.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });
            payloadError.focus({ preventScroll: true });

            return true;
        };

        const scrollElementIntoView = (element) => {
            element.scrollIntoView({
                behavior: 'smooth',
                block: 'center',
            });

            if (typeof element.focus === 'function') {
                element.focus({ preventScroll: true });
            }
        };

        const scrollToMatchedFlight = () => {
            const tryScroll = () => {
                if (scrollToPayloadError()) {
                    return true;
                }

                const matchedFlightPanel = document.getElementById('matched-flight-plan');

                if (!matchedFlightPanel || !matchedFlightPanel.classList.contains('echo-import-summary')) {
                    return false;
                }

                scrollElementIntoView(matchedFlightPanel);

                return true;
            };

            [50, 200, 500, 900].forEach((delay) => {
                setTimeout(tryScroll, delay);
            });
        };

        window.initImportScanQrPage?.();
        window.setTimeout(() => window.initImportScanQrPage?.(), 250);
        scrollToPayloadError();
        scrollToMatchedFlight();

        document.addEventListener('livewire:updated', () => {
            if (!scrollToPayloadError()) {
                scrollToMatchedFlight();
            }
        });

        document.addEventListener('echo:qr-payload-loaded', scrollToMatchedFlight);

        document.addEventListener('click', async (event) => {
            const trigger = event.target.closest('.echo-decline-flight-trigger');

            if (!trigger || !window.EchoUiModal) {
                return;
            }

            event.preventDefault();
            const componentRoot = trigger.closest('[wire\\:id]');
            const component = componentRoot ? Livewire.find(componentRoot.getAttribute('wire:id')) : null;
            const reason = await window.EchoUiModal.prompt({
                heading: 'Decline Flight Plan',
                message: `You are declining the PIC authorization request for ${trigger.dataset.callsign || 'this aircraft'}.`,
                inputLabel: 'Reason for declining',
                inputPlaceholder: 'Explain what needs correction',
                inputMaxLength: 500,
                tone: 'danger',
                confirmLabel: 'Decline Flight Plan',
                cancelLabel: 'Cancel',
                confirmTone: 'danger',
            });

            if (reason !== null && component) {
                await component.call('declineAuthorization', reason);
            }
        });

        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', () => {
                if (!scrollToPayloadError()) {
                    scrollToMatchedFlight();
                }
            });
        });
    </script>
</x-filament-panels::page>
