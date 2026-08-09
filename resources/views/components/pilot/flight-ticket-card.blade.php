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
    <div class="grid lg:grid-cols-[minmax(0,7fr)_minmax(14rem,3fr)]">
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
            <div class="flex h-full flex-col gap-4 sm:flex-row sm:items-center sm:justify-between lg:flex-col lg:items-start lg:justify-center">
                <x-filament::badge :color="$flight['status']['color']">
                    {{ $flight['status']['label'] }}
                </x-filament::badge>

                <x-filament::button
                    tag="a"
                    :href="$flight['action_url']"
                    color="gray"
                    size="sm"
                >
                    {{ $flight['action_label'] }}
                </x-filament::button>
            </div>
        </div>
    </div>
</div>
