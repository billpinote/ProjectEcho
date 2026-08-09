<x-filament-widgets::widget class="echo-pilot-dashboard-widget">
    <div class="space-y-6 bg-echo-background">
        <x-filament::section>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $readiness['greeting'] }},
                    </p>

                    <h2 class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">
                        {{ $readiness['friendly_name'] }}
                    </h2>

                    <p class="mt-2 text-sm font-medium text-gray-600 dark:text-gray-300">
                        {{ $readiness['licence'] }} &middot; {{ $readiness['operator'] }}
                    </p>

                    @if (! empty($readiness['attention']))
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach ($readiness['attention'] as $message)
                                <x-filament::badge color="warning">
                                    {{ $message }}
                                </x-filament::badge>
                            @endforeach
                        </div>
                    @endif
                </div>

                <x-filament::button
                    tag="a"
                    :href="$fileFlightPlanUrl"
                    icon="heroicon-o-plus"
                    size="lg"
                >
                    File Flight Plan
                </x-filament::button>
            </div>

            <div class="mt-5 border-t border-gray-200 pt-4 dark:border-white/10">
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-300">Licence</span>
                        <x-filament::badge :color="$readiness['licence_status']['color']">
                            {{ $readiness['licence_status']['label'] }}
                        </x-filament::badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-300">Medical</span>
                        <x-filament::badge :color="$readiness['medical_status']['color']">
                            {{ $readiness['medical_status']['label'] }}
                        </x-filament::badge>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium text-gray-600 dark:text-gray-300">Qualifications</span>

                        @if (empty($readiness['qualifications']))
                            <span class="text-gray-500 dark:text-gray-400">None active</span>
                        @else
                            @foreach ($readiness['qualifications'] as $qualification)
                                <x-filament::badge :color="$qualification['color']">
                                    {{ $qualification['code'] }}
                                </x-filament::badge>
                            @endforeach
                        @endif
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Current Flights</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Most relevant operational flight plans.</p>
                </div>

                <x-filament::link :href="$allFlightsUrl">
                    View all flights
                </x-filament::link>
            </div>

            <div class="mt-5">
                @if (empty($currentFlights))
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600 dark:border-white/20 dark:text-gray-400">
                        No current flights.
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($currentFlights as $flight)
                            <x-pilot.flight-ticket-card :flight="$flight" />
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent Flights</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Latest completed flight activity.</p>
                </div>

                <x-filament::link :href="$allFlightsUrl">
                    Full history
                </x-filament::link>
            </div>

            <div class="mt-5">
                @if (empty($recentFlights))
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600 dark:border-white/20 dark:text-gray-400">
                        No flights yet. File your first flight plan when you are ready.
                    </div>
                @else
                    <div class="grid gap-3 md:grid-cols-2">
                        @foreach ($recentFlights as $flight)
                            <x-pilot.flight-ticket-card :flight="$flight" />
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
