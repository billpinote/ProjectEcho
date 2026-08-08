@php
    $operationalPortals = [
        [
            'label' => 'Pilot',
            'description' => 'File and track your flight plans.',
            'route' => 'filament.pilot.auth.login',
            'icon' => 'pilot',
        ],
        [
            'label' => 'Air Traffic Management Officer',
            'short' => 'ATMO',
            'description' => 'Review and manage operational flight plans.',
            'route' => 'filament.atmo.auth.login',
            'icon' => 'atmo',
        ],
        [
            'label' => 'Dispatch',
            'description' => 'Monitor assigned operator flight activity.',
            'route' => 'filament.dispatch.auth.login',
            'icon' => 'dispatch',
        ],
        [
            'label' => 'Aviation Security',
            'short' => 'AVSEC',
            'description' => 'Inspect authorized flight plan records.',
            'route' => 'filament.avsec.auth.login',
            'icon' => 'avsec',
        ],
        [
            'label' => 'ATS Headquarters',
            'description' => 'Open headquarters reporting and oversight.',
            'route' => 'filament.ats.auth.login',
            'icon' => 'ats',
        ],
    ];

    $systemPortals = [
        [
            'label' => 'Administrator',
            'description' => 'Manage users, profiles, and verification records.',
            'route' => 'filament.admin.auth.login',
            'icon' => 'admin',
        ],
        [
            'label' => 'Artisan',
            'description' => 'Technical and system access.',
            'route' => 'filament.artisan.auth.login',
            'icon' => 'artisan',
            'meta' => 'Technical access',
        ],
    ];

    $iconSvg = function (string $icon): string {
        return match ($icon) {
            'pilot' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10.5 21l2.1-7.2L4 11.1V8.9l9.6 1.2L16.5 3h2.1l-.9 7.6 3.3.4v2l-3.6.7-.9 7.3h-2l-.9-6.7L12.1 21h-1.6z"/></svg>',
            'atmo' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a7 7 0 0 0-7 7v3.5A3.5 3.5 0 0 0 8.5 17H10v-5H7v-2a5 5 0 1 1 10 0v2h-3v5h1.5a3.5 3.5 0 0 0 3.5-3.5V10a7 7 0 0 0-7-7zm-1 15h2v2h-2v-2z"/></svg>',
            'dispatch' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v12H7.7L4 19.2V4zm2 2v8.9l1-.9h11V6H6zm3 3h6v2H9V9zm0 3h4v2H9v-2z"/></svg>',
            'avsec' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l7 3v6c0 4.4-2.8 8.5-7 10-4.2-1.5-7-5.6-7-10V5l7-3zm0 2.2L7 6.4V11c0 3.2 1.9 6.3 5 7.7 3.1-1.4 5-4.5 5-7.7V6.4l-5-2.2zm-1 4.8h2v4h-2V9zm0 5h2v2h-2v-2z"/></svg>',
            'ats' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4V5zm2 2v10h12V7H6zm2 2h4v2H8V9zm0 4h8v2H8v-2zm7-4h2v2h-2V9z"/></svg>',
            'admin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4zm0 2c-4 0-7 2-7 4.5V20h14v-1.5C19 16 16 14 12 14zm6-6h2v2h2v2h-2v2h-2v-2h-2v-2h2V8z"/></svg>',
            default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2l8 4v6c0 4.6-3.2 8.6-8 10-4.8-1.4-8-5.4-8-10V6l8-4zm0 2.2L6 7.1V12c0 3.4 2.4 6.4 6 7.7 3.6-1.3 6-4.3 6-7.7V7.1l-6-2.9z"/></svg>',
        };
    };
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Project Echo Access</title>
    @include('partials.access-gateway-styles')
</head>
<body>
    <main class="gateway-shell">
        <header class="gateway-header">
            <div class="brand-mark" aria-hidden="true">PE</div>
            <div>
                <p class="eyebrow">Project Echo</p>
                <h1>Select your access portal</h1>
            </div>
        </header>

        <section class="gateway-section" aria-labelledby="operational-heading">
            <div class="section-heading">
                <h2 id="operational-heading">Operational Access</h2>
            </div>
            <div class="portal-grid">
                @foreach ($operationalPortals as $portal)
                    <a class="portal-card" href="{{ route($portal['route']) }}">
                        <span class="portal-icon">{!! $iconSvg($portal['icon']) !!}</span>
                        <span class="portal-copy">
                            <span class="portal-title">{{ $portal['short'] ?? $portal['label'] }}</span>
                            @isset($portal['short'])
                                <span class="portal-name">{{ $portal['label'] }}</span>
                            @endisset
                            <span class="portal-description">{{ $portal['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="gateway-section gateway-section--system" aria-labelledby="system-heading">
            <div class="section-heading">
                <h2 id="system-heading">System Administration</h2>
            </div>
            <div class="portal-grid portal-grid--system">
                @foreach ($systemPortals as $portal)
                    <a class="portal-card portal-card--system" href="{{ route($portal['route']) }}">
                        <span class="portal-icon">{!! $iconSvg($portal['icon']) !!}</span>
                        <span class="portal-copy">
                            <span class="portal-title">{{ $portal['label'] }}</span>
                            @isset($portal['meta'])
                                <span class="portal-name">{{ $portal['meta'] }}</span>
                            @endisset
                            <span class="portal-description">{{ $portal['description'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <footer class="gateway-footer">
            <a class="public-link" href="{{ route('flightplan') }}">Public Flight Plan</a>
        </footer>
    </main>
</body>
</html>
