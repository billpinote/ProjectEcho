<?php

namespace App\Filament\Resources\MyFlightPlans\Pages;

use App\Filament\Resources\MyFlightPlans\MyFlightPlansResource;
use App\Filament\Resources\Flights\Pages\EditFlight;

class EditMyFlightPlan extends EditFlight
{
    protected static string $resource = MyFlightPlansResource::class;
}
