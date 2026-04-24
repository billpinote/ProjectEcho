<?php

namespace App\Filament\Resources\Flights\Pages;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFlights extends ListRecords
{
    protected static string $resource = FlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importScanQr')
                ->label('Import / Scan QR')
                ->icon('heroicon-o-qr-code')
                ->url(ImportScanQr::getUrl()),
            CreateAction::make()
                ->label('New Flight Plan')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => FlightResource::getUrl('create')),
        ];
    }
}
