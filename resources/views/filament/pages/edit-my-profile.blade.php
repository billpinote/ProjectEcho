<x-filament-panels::page>
    <form wire:submit="save" class="space-y-6">
        {{ $this->formContainer }}

        <div class="flex items-center justify-end gap-3">
            <x-filament::button
                color="gray"
                tag="a"
                :href="\App\Filament\Pages\MyProfilePage::getUrl(panel: 'pilot')"
            >
                Cancel
            </x-filament::button>

            <x-filament::button type="submit">
                Save Profile
            </x-filament::button>
        </div>
    </form>

    <x-filament-actions::modals />
</x-filament-panels::page>
