
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold text-echo-primary">PROJECT ECHO</p>
            <h1 class="mt-1 text-3xl font-semibold">Flight Manifests</h1>
            <p class="mt-2 text-sm text-echo-text-secondary">Prepare your crew, passenger and cargo details for an Echo flight plan.</p>
        </div>
        @auth
            @if(auth()->user()->canCreateFlightPlans())
                <a href="{{ $createFlightUrl ?? (auth()->user()?->isPilot() ? \App\Filament\Panels\Pilot\Resources\Flights\Pages\CreateFlight::getUrl(panel: 'pilot') : route('flightplan')) }}" class="rounded-lg bg-echo-primary px-4 py-3 text-sm font-semibold text-white hover:bg-echo-primary-dark">Create a flight plan</a>
            @endif
        @endauth
    </header>
    @guest
        <section class="rounded-xl border border-echo-border bg-echo-card p-6">
            <h2 class="text-xl font-semibold">Sign in to prepare a manifest</h2>
            <p class="mt-2 text-sm text-echo-text-secondary">Saved manifests must be linked to your Echo flight plan. You can also prepare a guest PDF without saving a manifest.</p>
            <a href="{{ route('manifests.guest') }}" class="mt-5 inline-block text-sm font-semibold text-echo-primary">Prepare guest manifest PDF</a>
            <a href="{{ route('gateway') }}" class="mt-5 inline-block rounded-lg bg-echo-primary px-4 py-3 text-sm font-semibold text-white">Sign in</a>
        </section>
    @else
        <a href="{{ route('manifests.blank') }}" class="text-sm font-semibold text-echo-primary">Download blank CAAP manifest</a>
        @if($nativeManifestTable ?? false)
            {{ $this->table }}
        @else
        <div class="overflow-x-auto rounded-xl border border-echo-border bg-echo-card">
            <table aria-label="Flight manifests" class="w-full table-auto whitespace-nowrap text-left text-echo-table font-medium">
                <thead class="border-b border-echo-border text-echo-label uppercase tracking-wide text-echo-text-secondary">
                    <tr>
                        @foreach(['Callsign' => 'w-px', 'DOF' => 'w-px', 'PTD' => 'w-px', 'PIC' => '', 'Prepared By' => '', 'Status' => 'w-px'] as $heading => $width)
                            <th scope="col" class="{{ $width }} whitespace-nowrap px-3 py-2.5 font-semibold">{{ $heading }}</th>
                        @endforeach
                        <th scope="col" class="w-px px-3 py-2.5 text-right font-semibold">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-echo-border">
                    @forelse($flights as $flight)
                        @php
                            $manifest = $flight->manifest;
                            $canPrepare = auth()->user()->can('create', [\App\Models\FlightManifest::class, $flight]);
                            $canEdit = $manifest ? auth()->user()->can('update', $manifest) : $canPrepare;
                            $canView = $manifest && auth()->user()->can('view', $manifest);
                            $status = \App\Domain\FlightPlans\Support\FlightStatusDisplay::badge($flight, auth()->user()->role);
                            $statusClass = match ($status['color']) {
                                'warning' => 'echo-status-pending',
                                'success' => 'echo-status-accepted',
                                'info' => 'echo-status-active',
                                'danger' => 'echo-status-rejected',
                                default => 'echo-status-completed',
                            };
                            $preparedBy = $manifest
                                ? $manifest->creator?->preparedByNameSnapshot()
                                : ($flight->prepared_by_name ?: ($flight->preparedBy ?? $flight->filedBy)?->preparedByNameSnapshot());
                        @endphp
                        <tr class="align-middle">
                            <td class="whitespace-nowrap px-3 py-2.5 font-semibold">{{ $flight->aircraft_identification }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ $flight->date_of_flight ? \Carbon\Carbon::parse($flight->date_of_flight)->format('d M Y') : '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5">{{ filled($flight->proposed_time) ? \App\Domain\FlightPlans\Rules\UtcFourDigitTime::formatForDisplay($flight->proposed_time).'Z' : '—' }}</td>
                            <td class="max-w-28 truncate px-3 py-2.5 xl:max-w-40" title="{{ $flight->pilot_in_command }}">{{ $flight->pilot_in_command ?: '—' }}</td>
                            <td class="max-w-32 truncate px-3 py-2.5 xl:max-w-44" title="{{ $preparedBy }}">{{ $preparedBy ?: '—' }}</td>
                            <td class="whitespace-nowrap px-3 py-2.5"><span class="echo-status-badge {{ $statusClass }}">{{ $status['label'] }}</span></td>
                            <td class="whitespace-nowrap px-3 py-2.5 text-right">
                                @if($canView || $canPrepare)
                                    <a href="{{ route('flights.manifest.edit', $flight) }}" class="inline-flex items-center rounded-lg border border-echo-primary px-3 py-1.5 font-semibold text-echo-primary hover:bg-echo-background">{{ $canEdit ? ($manifest ? 'Edit manifest' : 'Prepare manifest') : 'View manifest' }}</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="p-6">
                                <h2 class="text-lg font-semibold">No flight plans available</h2>
                                <p class="mt-2 text-echo-text-secondary">You need an Echo flight plan before preparing a saved manifest.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $flights->links() }}
        @endif
    @endguest

