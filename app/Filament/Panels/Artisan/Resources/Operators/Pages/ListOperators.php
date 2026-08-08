<?php
namespace App\Filament\Panels\Artisan\Resources\Operators\Pages;
use App\Filament\Panels\Artisan\Resources\Operators\OperatorResource;
use App\Filament\Shared\Resources\Operators\Pages\ListOperators as SharedListOperators;
class ListOperators extends SharedListOperators
{
    protected static string $resource = OperatorResource::class;
}