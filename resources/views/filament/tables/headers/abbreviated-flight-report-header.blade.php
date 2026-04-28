<div class="fi-ta-header">
    <div class="flex w-full items-center justify-between gap-4">
        <!-- Label + Dropdown -->
        <label class="flex items-center gap-2">
            <span class="fi-ta-header-cell-label">Date of Flight</span>

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="tableFilters.date_of_flight.value">
                    <option value="">{{ __('Select a date') }}</option>
                    @foreach ($dateOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>

        <!-- Button -->
        @if ($showTestAction)
            <x-filament::button
                color="gray"
                tag="a"
                :href="$reportUrl"
                target="_blank"
            >
                Generate PDF
            </x-filament::button>
        @endif
    </div>
</div>
