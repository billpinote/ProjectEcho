@php
    $activeNav = $activeNav ?? 'flight-plan';
    $showMobileViewToggle = $showMobileViewToggle ?? false;

    $navItems = [
        [
            'key' => 'flight-plan',
            'label' => 'Flight Plan',
            'href' => route('flightplan'),
            'icon' => 'paper-airplane',
            'disabled' => false,
        ],
        [
            'key' => 'manifest',
            'label' => 'Manifest',
            'href' => null,
            'icon' => 'clipboard',
            'disabled' => true,
        ],
        [
            'key' => 'scan-upload-qr',
            'label' => 'Scan / Upload QR',
            'href' => route('flightplan.scan-qr'),
            'icon' => 'qr',
            'disabled' => false,
        ],
    ];

    $iconSvg = static function (string $icon): string {
        return match ($icon) {
            'paper-airplane' => <<<'SVG'
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 11.5 20.5 4.5c.85-.34 1.69.49 1.34 1.34L14.9 23c-.37.9-1.66.85-1.96-.08l-2.02-6.27a1 1 0 0 0-.63-.63L4.02 14c-.93-.3-.98-1.59-.08-1.96L11.5 9" />
                </svg>
            SVG,
            'clipboard' => <<<'SVG'
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 4.75h6a2.25 2.25 0 0 1 2.25 2.25v11A2.25 2.25 0 0 1 15 20.25H9A2.25 2.25 0 0 1 6.75 18V7A2.25 2.25 0 0 1 9 4.75Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 3.75h4.5A1.5 1.5 0 0 1 15.75 5.25v.5h-7.5v-.5a1.5 1.5 0 0 1 1.5-1.5Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.5 10h5M9.5 13.5h5M9.5 17h3.25" />
                </svg>
            SVG,
            'qr' => <<<'SVG'
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.75 4.75h5.5v5.5h-5.5zM13.75 4.75h5.5v5.5h-5.5zM4.75 13.75h5.5v5.5h-5.5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 13.75h.25v.25H15zM18.75 13.75h.25v.25h-.25zM15 17.5h.25v.25H15zM17 15.75h2.25V18M18.75 18.75h.25v.25h-.25z" />
                </svg>
            SVG,
            default => '',
        };
    };
@endphp

<div class="flightplan-shell">
    <nav class="flightplan-nav" aria-label="Primary">
        <div class="flightplan-nav__bar">
            <div class="flightplan-nav__brand">
                <span class="flightplan-nav__eyebrow">Flight Plan Tool</span>
                <span class="flightplan-nav__title">Project Echo</span>
            </div>

            <div class="flightplan-nav__mobile-actions">
                @if ($showMobileViewToggle)
                    <label class="flightplan-view-switch" for="flightplan-mobile-toggle">
                        <span class="flightplan-view-switch__label">Mobile</span>
                        <input
                            id="flightplan-mobile-toggle"
                            type="checkbox"
                            class="flightplan-view-switch__input"
                            data-flightplan-mobile-toggle
                            aria-label="Toggle mobile form view"
                        >
                        <span class="flightplan-view-switch__track" aria-hidden="true">
                            <span class="flightplan-view-switch__thumb"></span>
                        </span>
                    </label>
                @endif

                <button type="button" class="flightplan-nav__toggle" data-flightplan-nav-toggle aria-expanded="false" aria-controls="flightplan-nav-mobile">
                    <span class="sr-only">Toggle navigation</span>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                        <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16" />
                    </svg>
                </button>
            </div>

            <div class="flightplan-nav__menu">
                @foreach ($navItems as $item)
                    @php
                        $isActive = $activeNav === $item['key'];
                    @endphp

                    @if ($item['disabled'])
                        <span class="flightplan-nav__button is-disabled" aria-disabled="true">
                            <span class="flightplan-nav__icon">{!! $iconSvg($item['icon']) !!}</span>
                            <span>{{ $item['label'] }}</span>
                        </span>
                    @else
                        <a href="{{ $item['href'] }}" @class(['flightplan-nav__link', 'is-active' => $isActive])>
                            <span class="flightplan-nav__icon">{!! $iconSvg($item['icon']) !!}</span>
                            <span>{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="flightplan-nav__mobile" id="flightplan-nav-mobile" data-flightplan-nav-mobile>
            @foreach ($navItems as $item)
                @php
                    $isActive = $activeNav === $item['key'];
                @endphp

                @if ($item['disabled'])
                    <span class="flightplan-nav__button is-disabled" aria-disabled="true">
                        <span class="flightplan-nav__icon">{!! $iconSvg($item['icon']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                    </span>
                @else
                    <a href="{{ $item['href'] }}" @class(['flightplan-nav__link', 'is-active' => $isActive])>
                        <span class="flightplan-nav__icon">{!! $iconSvg($item['icon']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
    </nav>
</div>

<script>
    (function () {
        const toggle = document.querySelector('[data-flightplan-nav-toggle]');
        const mobileMenu = document.querySelector('[data-flightplan-nav-mobile]');

        if (!toggle || !mobileMenu) {
            return;
        }

        toggle.addEventListener('click', function () {
            const isOpen = mobileMenu.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    })();
</script>
