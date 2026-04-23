<?php

namespace App\Filament\Resources\AcceptedFlights\Pages;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListAcceptedFlights extends ListRecords
{
    protected static string $resource = AcceptedFlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importScanQr')
                ->label('Import / Scan QR')
                ->icon('heroicon-o-qr-code')
                ->url(ImportScanQr::getUrl()),
        ];
    }
}
