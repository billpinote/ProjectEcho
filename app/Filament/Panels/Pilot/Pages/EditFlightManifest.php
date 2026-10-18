<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Http\Controllers\FlightManifestController;
use App\Models\Flight;
use Filament\Pages\Page;
use Livewire\Attributes\Locked;

class EditFlightManifest extends Page
{
    protected static ?string $title = 'Flight Manifest';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'flights/{flight}/manifest';

    protected string $view = 'filament.pages.flight-manifest';

    #[Locked]
    public int $flightId;

    public function mount(Flight $flight): void
    {
        $this->flightId = $flight->id;
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return app(FlightManifestController::class)->formData(request(), Flight::findOrFail($this->flightId));
    }
}
