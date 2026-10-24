@props([
    'flight',
])

<div
    @class([
        'echo-flight-ticket-card overflow-hidden rounded-lg border shadow-sm',
        'border-gray-200 dark:border-white/10' => empty($flight['subdued']),
        'border-gray-200/80 opacity-90 dark:border-white/10' => ! empty($flight['subdued']),
    ])
>
    <div class="grid lg:grid-cols-[minmax(0,1fr)_auto]">
        <div class="p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                    <div class="truncate text-xl font-semibold tracking-wide text-gray-950 dark:text-white">
                        {{ $flight['callsign'] }}
                    </div>
                    <div class="mt-3 flex items-center gap-3 text-lg font-semibold text-gray-950 dark:text-white">
                        <span>{{ $flight['departure'] }}</span>
                        <span class="text-gray-400">-></span>
                        <span>{{ $flight['destination'] }}</span>
                    </div>
                </div>
            </div>

            <div class="mt-4 flex flex-wrap gap-x-5 gap-y-2 text-sm text-gray-600 dark:text-gray-300">
                <span>{{ $flight['date'] }} &middot; {{ $flight['time'] }}</span>
                <span>{{ $flight['aircraft_type'] }} &middot; {{ $flight['flight_rules'] }}</span>
            </div>
        </div>

        <div class="border-t border-dashed border-gray-300 p-4 dark:border-white/20 lg:border-l lg:border-t-0">
            <div class="flex h-full flex-col items-start justify-center gap-4">
                <x-filament::badge :color="$flight['status']['color']">
                    {{ $flight['status']['label'] }}
                </x-filament::badge>

                <div @class(['echo-flight-ticket-actions flex w-full flex-col gap-3', 'lg:w-auto lg:flex-row lg:items-center lg:justify-end lg:gap-2 lg:self-end' => empty($flight['subdued'])])>
                    <x-filament::button
                        tag="a"
                        :href="$flight['action_url']"
                        target="_blank"
                        rel="noopener noreferrer"
                        color="gray"
                        size="sm"
                        @class(['min-h-10 w-full', 'lg:min-h-8 lg:w-auto lg:px-2 lg:py-1' => empty($flight['subdued'])])
                    >
                        {{ $flight['action_label'] }}
                    </x-filament::button>
                    @if (empty($flight['subdued']))
                        <x-filament::dropdown placement="bottom-end" shift teleport width="echo-flight-attachments-panel" max-height="calc(100dvh - 2rem)" class="w-full lg:w-auto">
                            <x-slot name="trigger">
                                <x-filament::button color="gray" size="sm" class="min-h-10 w-full lg:min-h-8 lg:w-auto lg:px-2 lg:py-1">Attachments</x-filament::button>
                            </x-slot>
                            <x-filament::dropdown.list>
                                @if ($flight['manifest_url'] ?? null)
                                    <x-filament::dropdown.list.item tag="a" :href="$flight['manifest_url']" target="_blank" rel="noopener noreferrer">Manifest</x-filament::dropdown.list.item>
                                @else
                                    <x-filament::dropdown.list.item disabled>Manifest — not prepared</x-filament::dropdown.list.item>
                                @endif
                                <x-filament::dropdown.list.item disabled>AvSec Clearance — coming later</x-filament::dropdown.list.item>
                                <x-filament::dropdown.list.item disabled title="Pilot license, medical certificate, ELP, and NTC ID">License and Certs — coming later</x-filament::dropdown.list.item>
                            </x-filament::dropdown.list>
                        </x-filament::dropdown>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
