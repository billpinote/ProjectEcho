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
            background:
                radial-gradient(circle at top left, rgba(15, 95, 74, 0.18), transparent 34rem),
                linear-gradient(145deg, #f8f4e8 0%, var(--qr-bg) 55%, #dfe9df 100%);
            font-family: Helvetica, Arial, sans-serif;
        }

        .qr-shell {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .qr-card {
            width: min(100%, 520px);
            border: 1px solid rgba(22, 32, 24, 0.16);
            border-radius: 28px;
            background: rgba(255, 253, 247, 0.94);
            box-shadow: 0 24px 70px rgba(22, 32, 24, 0.18);
            padding: 22px;
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
            font-size: clamp(28px, 8vw, 46px);
            line-height: 0.95;
            letter-spacing: -0.04em;
        }

        .qr-subtitle {
            margin: 12px auto 18px;
            max-width: 31rem;
            color: rgba(22, 32, 24, 0.72);
            font-size: 15px;
            line-height: 1.45;
        }

        .qr-frame {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            border-radius: 24px;
            background: #fff;
            padding: clamp(18px, 5vw, 28px);
            box-shadow: inset 0 0 0 1px rgba(22, 32, 24, 0.12);
            overflow: visible;
        }

        .qr-frame img {
            display: block;
            width: min(85vw, 370px);
            height: min(85vw, 370px);
            object-fit: contain;
            aspect-ratio: 1 / 1; /* ensures square */
        }

        .qr-meta {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 18px;
            text-align: left;
        }

        .qr-meta-item {
            border-radius: 16px;
            background: rgba(15, 95, 74, 0.08);
            padding: 12px;
        }

        .qr-meta-label {
            display: block;
            margin-bottom: 4px;
            color: rgba(22, 32, 24, 0.58);
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.1em;
            text-transform: uppercase;
        }

        .qr-meta-value {
            display: block;
            overflow-wrap: anywhere;
            font-size: 17px;
            font-weight: 700;
            text-align: center;
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
            display: grid;
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

        .qr-download:disabled {
            cursor: wait;
            opacity: 0.72;
        }

        @media (max-width: 420px) {
            .qr-shell {
                align-items: flex-start;
                padding: 12px;
            }

            .qr-card {
                border-radius: 22px;
                padding: 16px;
            }

            .qr-meta {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <main class="qr-shell">
        <section id="flightplan-qr-card" class="qr-card" aria-labelledby="qr-title">
            <p class="qr-eyebrow">Flight Plan Ready</p>
            <h1 id="qr-title" class="qr-title">Show This QR To ATC</h1>
            <p class="qr-subtitle">
                Present this screen to the air traffic controller for processing. Keep the QR code fully visible and your screen brightness high.
            </p>

            <div class="qr-frame">
                <img src="{{ $qrCodeBase64 }}" alt="Flight plan QR code for {{ $flight->aircraft_identification ?? 'approved flight plan' }}">
            </div>

            <div class="qr-meta" aria-label="Flight plan summary">
                <div class="qr-meta-item">
                    <span class="qr-meta-label">Call Sign</span>
                    <span class="qr-meta-value">{{ $flight->aircraft_identification ?? 'N/A' }}</span>
                </div>
                <div class="qr-meta-item">
                    <span class="qr-meta-label">DOF</span>
                    <span class="qr-meta-value">{{ $flight->date_of_flight ? \Carbon\Carbon::parse($flight->date_of_flight)->format('d M Y') : 'N/A' }}</span>
                </div>
                <div class="qr-meta-item">
                    <span class="qr-meta-label">Departure</span>
                    <span class="qr-meta-value">{{ $flight->departure_aerodrome ?? 'N/A' }}</span>
                </div>
                <div class="qr-meta-item">
                    <span class="qr-meta-label">PTD</span>
                    <span class="qr-meta-value">{{ \App\Rules\UtcFourDigitTime::formatForDisplay($flight->proposed_time) ?? 'N/A' }}&nbsp; Z</span>
                </div>
            </div>

            <div class="qr-actions">
                <a class="qr-download qr-download-secondary" href="{{ $qrImageDownloadUrl }}">
                    Download QR
                </a>
                <a class="qr-download" href="{{ $pdfDownloadUrl }}">
                    Download PDF
                </a>
            </div>
        </section>
    </main>
</body>
</html>
