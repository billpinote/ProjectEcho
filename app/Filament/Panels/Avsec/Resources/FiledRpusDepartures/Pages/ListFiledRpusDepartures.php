<?php

namespace App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\Pages;

use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\FiledRpusDepartureResource;
use Filament\Resources\Pages\ListRecords;

class ListFiledRpusDepartures extends ListRecords
{
    protected static string $resource = FiledRpusDepartureResource::class;

    public function getHeading(): ?string
    {
        return null;
    }
}
