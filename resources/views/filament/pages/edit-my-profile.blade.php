<x-filament-panels::page>
    @vite('resources/js/app.js')
    {{ $this->formContainer }}

    <x-filament-actions::modals />
</x-filament-panels::page>
