<x-filament-widgets::widget>
    <div class="space-y-6">
        <x-filament::section>
            <div class="flex flex-col gap-5 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        {{ $readiness['greeting'] }}, {{ $readiness['friendly_name'] }}
                    </p>

                    <div class="mt-2 flex flex-wrap items-baseline gap-x-4 gap-y-1">
                        <h2 class="text-2xl font-semibold text-gray-950 dark:text-white">
                            {{ $readiness['licence'] }}
                        </h2>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-300">
                            {{ $readiness['operator'] }}
                        </p>
                    </div>

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

            <div class="mt-6 grid gap-3 sm:grid-cols-3">
                <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Licence</div>
                    <div class="mt-2">
                        <x-filament::badge :color="$readiness['licence_status']['color']">
                            {{ $readiness['licence_status']['label'] }}
                        </x-filament::badge>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Medical</div>
                    <div class="mt-2">
                        <x-filament::badge :color="$readiness['medical_status']['color']">
                            {{ $readiness['medical_status']['label'] }}
                        </x-filament::badge>
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 px-4 py-3 dark:border-white/10">
                    <div class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Qualifications</div>
                    <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        {{ $readiness['active_qualifications'] }} Active
                    </div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Current Flights</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-400">Pending, accepted, and active flight plans.</p>
                </div>

                <x-filament::link :href="$allFlightsUrl">
                    View all flights
                </x-filament::link>
            </div>

            <div class="mt-5 space-y-5">
                @foreach ($flightSections as $section)
                    <div>
                        <div class="mb-3 flex items-center justify-between gap-3">
                            <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-600 dark:text-gray-300">
                                {{ $section['heading'] }}
                            </h3>
                        </div>

                        @if (empty($section['flights']))
                            <div class="rounded-lg border border-dashed border-gray-300 px-4 py-5 text-sm text-gray-600 dark:border-white/20 dark:text-gray-400">
                                {{ $section['empty'] }}
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($section['flights'] as $flight)
                                    <x-pilot.flight-ticket-card :flight="$flight" />
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
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
                        <div class="mt-3">
                            <x-filament::button
                                tag="a"
                                :href="$fileFlightPlanUrl"
                                icon="heroicon-o-plus"
                                size="sm"
                            >
                                File Flight Plan
                            </x-filament::button>
                        </div>
                    </div>
                @else
                    <div class="space-y-3">
                        @foreach ($recentFlights as $flight)
                            <x-pilot.flight-ticket-card :flight="$flight" />
                        @endforeach
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
