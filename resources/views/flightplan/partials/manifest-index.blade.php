
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
            <p class="mt-2 text-sm text-echo-text-secondary">Saved manifests must be linked to your Echo flight plan. Guest manifest preparation is not available yet.</p>
            <a href="{{ route('gateway') }}" class="mt-5 inline-block rounded-lg bg-echo-primary px-4 py-3 text-sm font-semibold text-white">Sign in</a>
        </section>
    @else
        <div class="flex flex-col gap-3">
            @forelse($flights as $flight)
                @php
                    $canPrepare = auth()->user()->can('create', [\App\Models\FlightManifest::class, $flight]);
                @endphp
                <article class="flex flex-col justify-between gap-4 rounded-xl border border-echo-border bg-echo-card p-5 sm:flex-row sm:items-center">
                    <div>
                        <h2 class="text-lg font-semibold">{{ $flight->aircraft_identification }} <span class="text-sm font-normal text-echo-text-secondary">Flight #{{ $flight->id }}</span></h2>
                        <p class="mt-1 text-sm">{{ $flight->departure_aerodrome }} &rarr; {{ $flight->destination_aerodrome }} · {{ $flight->date_of_flight }}</p>
                        <p class="mt-1 text-sm text-echo-text-secondary">{{ $flight->status?->label() }} · {{ $flight->manifest ? ($flight->hasBeenAccepted() ? 'Manifest locked' : 'Manifest saved') : 'No linked manifest' }}</p>
                    </div>
                    @if($flight->manifest || $canPrepare)
                        <a href="{{ route('flights.manifest.edit', $flight) }}" class="rounded-lg border border-echo-primary px-4 py-3 text-center text-sm font-semibold text-echo-primary hover:bg-echo-background">{{ $canPrepare ? ($flight->manifest ? 'Edit manifest' : 'Prepare manifest') : 'View manifest' }}</a>
                    @else
                        <p class="max-w-sm text-sm text-echo-text-secondary">{{ $flight->hasBeenAccepted() ? 'This flight was already accepted without a manifest. Create a new flight plan to prepare one.' : 'Manifest preparation unavailable for this filing.' }}</p>
                    @endif
                </article>
            @empty
                <section class="rounded-xl border border-echo-border bg-echo-card p-6">
                    <h2 class="text-lg font-semibold">No flight plans available</h2>
                    <p class="mt-2 text-sm text-echo-text-secondary">You need an Echo flight plan before preparing a saved manifest.</p>
                </section>
            @endforelse
        </div>
        {{ $flights->links() }}
    @endguest

