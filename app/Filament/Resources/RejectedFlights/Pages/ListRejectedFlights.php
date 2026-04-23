<?php

namespace App\Filament\Resources\RejectedFlights\Pages;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRejectedFlights extends ListRecords
{
    protected static string $resource = RejectedFlightResource::class;

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
