<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Filament\Shared\Pages\ImportScanQr;
use App\Filament\Shared\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Panel;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Route;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [];
        $currentPanel = Filament::getCurrentPanel();

        if ($currentPanel instanceof Panel) {
            $panelId = $currentPanel->getId();
            $importRouteName = "filament.{$panelId}.pages.import-scan-qr";

            if (Route::has($importRouteName)) {
                $actions[] = Action::make('importScanQr')
                    ->label('Import / Scan QR')
                    ->icon('heroicon-o-qr-code')
                    ->url(
                        fn (): string => ImportScanQr::getUrl(panel: $panelId)
                    );
            }
        }

        $actions[] = CreateAction::make()
            ->label('New Flight Plan')
            ->icon('heroicon-o-plus')
            ->url(fn (): string => FlightResource::getUrl('create'));

        return $actions;
    }
}
