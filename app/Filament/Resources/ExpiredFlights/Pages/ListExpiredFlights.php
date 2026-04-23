<?php

namespace App\Filament\Resources\ExpiredFlights\Pages;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\ExpiredFlights\ExpiredFlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListExpiredFlights extends ListRecords
{
    protected static string $resource = ExpiredFlightResource::class;

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
