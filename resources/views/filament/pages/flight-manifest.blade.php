<x-filament-panels::page>
    @vite('resources/js/app.js')
    @if (auth()->user()?->isPilot() && ! $flight->isAvsecFrozen())
    <div class="flex flex-wrap gap-4">
        {{ $this->signFilingAction }}
    </div>
    @if($this->authorizationUnavailableReason())
        <p role="status">{{ $this->authorizationUnavailableReason() }}</p>
    @endif
    @endif
    @if (! $flight->hasBeenAccepted() && ! $flight->isPicAuthorizationDeclined())
        <p role="status">{{ \App\Domain\FlightPlans\Support\FlightStatusDisplay::badge($flight, auth()->user()?->role)['label'] }}</p>
    @endif
    <div class="flex flex-col gap-6" wire:ignore>
        @include('flightplan.partials.manifest-form')
    </div>
</x-filament-panels::page>
