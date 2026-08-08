<?php

namespace App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages;

use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\ProfileUpdateRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListProfileUpdateRequests extends ListRecords
{
    protected static string $resource = ProfileUpdateRequestResource::class;
}
