<?php

namespace App\Filament\Panels\Pilot\Resources\MyFlightPlans\Pages;

use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlansResource;
use App\Filament\Shared\Resources\Flights\Pages\EditFlight;

class EditMyFlightPlan extends EditFlight
{
    protected static string $resource = MyFlightPlansResource::class;
}
