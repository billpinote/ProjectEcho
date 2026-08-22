<!DOCTYPE html>
<html lang="en-CA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flight Plan QR</title>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif
    <style>
        :root {
            --qr-bg: #eef3ee;
            --qr-ink: #162018;
            --qr-card: #fffdf7;
            --qr-accent: #0f5f4a;
            --qr-accent-dark: #0a3f32;
        }

        body {
            min-height: 100vh;
            margin: 0;
            color: var(--qr-ink);
            background: var(--qr-bg);
            font-family: Helvetica, Arial, sans-serif;
        }

        .qr-shell {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 18px;
            padding: 20px;
        }

        .qr-card {
            box-sizing: border-box;
            width: min(100%, 460px);
            border: 1px solid rgba(22, 32, 24, 0.16);
            border-radius: 22px;
            background: rgba(255, 253, 247, 0.94);
            box-shadow: 0 24px 70px rgba(22, 32, 24, 0.18);
            padding: 18px;
            text-align: center;
        }

        .qr-eyebrow {
            margin: 0 0 6px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
            color: var(--qr-accent-dark);
        }

        .qr-title {
            margin: 0;
            font-size: clamp(28px, 7vw, 36px);
            line-height: 1;
            letter-spacing: -0.03em;
        }

        .qr-status {
            margin: 8px 0 0;
            color: var(--qr-accent);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: 0.12em;
        }

        .qr-route,
        .qr-datetime,
        .qr-reference {
            margin: 8px 0 0;
            font-weight: 700;
        }

        .qr-route {
            font-size: 18px;
        }

        .qr-datetime {
            color: rgba(22, 32, 24, 0.66);
            font-size: 13px;
        }

        .qr-reference {
            margin: 8px 0 0;
            color: rgba(22, 32, 24, 0.46);
            font-size: 10px;
            font-weight: 400;
            letter-spacing: 0.04em;
            line-height: 1.3;
        }

        .qr-subtitle {
            margin: 12px auto 14px;
            max-width: 31rem;
            color: rgba(22, 32, 24, 0.72);
            font-size: 14px;
            line-height: 1.45;
        }

        .qr-frame {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 14px auto 12px;
            border-radius: 18px;
            background: #fff;
            padding: clamp(14px, 4vw, 22px);
            box-shadow: inset 0 0 0 1px rgba(22, 32, 24, 0.12);
            overflow: visible;
        }

        .qr-frame img {
            display: block;
            width: min(82vw, 350px);
            height: min(82vw, 350px);
            object-fit: contain;
            aspect-ratio: 1 / 1; /* ensures square */
        }

        .qr-download {
            display: flex;
            width: 100%;
            min-height: 52px;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: var(--qr-accent);
            color: #fff;
            font-family: Helvetica, Arial, sans-serif;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-decoration: none;
            text-transform: uppercase;
            box-shadow: 0 14px 26px rgba(15, 95, 74, 0.28);
        }

        .qr-download:hover {
            background: var(--qr-accent-dark);
        }

        .qr-actions {
            box-sizing: border-box;
            display: grid;
            width: min(100%, 460px);
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .qr-download-secondary {
            border: 1px solid rgba(15, 95, 74, 0.26);
            background: #fff;
            color: var(--qr-accent-dark);
            cursor: pointer;
        }

        .qr-download-secondary:hover {
            background: rgba(15, 95, 74, 0.08);
        }

        .qr-share-button[hidden] {
            display: none;
        }

        .qr-back {
            color: var(--qr-accent-dark);
            background: transparent;
            border-color: rgba(15, 95, 74, 0.26);
            box-shadow: none;
        }

        .qr-download:disabled {
            cursor: wait;
            opacity: 0.72;
        }

        @media (max-width: 420px) {
            .qr-shell {
                align-items: center;
                justify-content: flex-start;
                padding: 12px;
            }

            .qr-card {
                border-radius: 22px;
                padding: 16px;
            }

        }

        @media (max-width: 360px) {
            .qr-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="qr-shell">
        <section id="flightplan-qr-card" class="qr-card" aria-labelledby="qr-title">
            <p class="qr-eyebrow">ECHO · FLIGHT PLAN</p>
            <p class="qr-status">FLIGHT PLAN READY</p>
            <h1 id="qr-title" class="qr-title">{{ $flight->aircraft_identification ?? 'N/A' }}</h1>
            <p class="qr-route">
                {{ $flight->departure_aerodrome ?? 'N/A' }} → {{ $flight->destination_aerodrome ?? 'N/A' }}
            </p>
            <p class="qr-datetime">
                {{ $flight->date_of_flight ? strtoupper(\Carbon\Carbon::parse($flight->date_of_flight)->format('d M Y')) : 'N/A' }} ·
                {{ \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? 'N/A' }}Z
            </p>

            <div class="qr-frame">
                <img src="{{ $qrCodeBase64 }}" alt="Flight plan QR code for {{ $flight->aircraft_identification ?? 'approved flight plan' }}">
            </div>

            <p class="qr-subtitle">Present to ATC for processing.</p>
            <p class="qr-reference">REV {{ (int) ($flight->revision_number ?? 1) }} · {{ $flight->aircraft_identification ?? 'FLIGHT' }}-{{ $flight->date_of_flight ? \Carbon\Carbon::parse($flight->date_of_flight)->format('md') : '----' }}</p>
        </section>

        <div class="qr-actions" aria-label="QR actions">
            <a class="qr-download" href="{{ $qrImageDownloadUrl }}">Save QR to Device</a>
            <button
                id="qr-share-button"
                type="button"
                class="qr-download qr-download-secondary qr-share-button"
                hidden
                data-qr-url="{{ $qrImageDownloadUrl }}"
                data-qr-filename="{{ $qrImageFileName }}"
            >Share</button>
            <a class="qr-download qr-download-secondary" href="{{ $pdfDownloadUrl }}">Download PDF</a>
            <a class="qr-download qr-download-secondary qr-back" href="{{ $backActionUrl }}">Back to Dashboard</a>
        </div>
    </main>
    <script>
        (() => {
            const shareButton = document.getElementById('qr-share-button');

            if (!shareButton || !navigator.share) {
                return;
            }

            shareButton.hidden = false;
            shareButton.addEventListener('click', async () => {
                shareButton.disabled = true;

                try {
                    const response = await fetch(shareButton.dataset.qrUrl, { credentials: 'same-origin' });
                    const blob = await response.blob();
                    const file = new File([blob], shareButton.dataset.qrFilename, { type: 'image/png' });
                    const shareData = { title: 'Echo Flight Plan', text: 'Flight plan QR for ATC processing.', files: [file] };

                    if (navigator.canShare && navigator.canShare({ files: [file] })) {
                        await navigator.share(shareData);
                    } else {
                        await navigator.share({ title: shareData.title, text: shareData.text, url: window.location.href });
                    }
                } catch (error) {
                    if (error?.name !== 'AbortError') {
                        shareButton.hidden = true;
                    }
                } finally {
                    shareButton.disabled = false;
                }
            });
        })();
    </script>
</body>
</html>
