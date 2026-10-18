<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Models\Flight;
use Filament\Pages\Page;
use Livewire\WithPagination;

class ListFlightManifests extends Page
{
    use WithPagination;

    protected static ?string $title = 'Flight Manifests';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'manifests';

    protected string $view = 'filament.pages.flight-manifests';

    protected function getViewData(): array
    {
        return [
            'flights' => Flight::query()->visibleTo(auth()->user())
                ->with('manifest')->orderByDesc('id')->paginate(20),
        ];
    }
}
