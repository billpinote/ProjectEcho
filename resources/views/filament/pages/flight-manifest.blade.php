<x-filament-panels::page>
    @vite('resources/js/app.js')
    <div class="flex flex-col gap-6" wire:ignore>
        @include('flightplan.partials.manifest-form')
    </div>
</x-filament-panels::page>
