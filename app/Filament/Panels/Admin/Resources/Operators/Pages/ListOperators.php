<?php
namespace App\Filament\Panels\Admin\Resources\Operators\Pages;
use App\Filament\Panels\Admin\Resources\Operators\OperatorResource;
use App\Filament\Shared\Resources\Operators\Pages\ListOperators as SharedListOperators;
class ListOperators extends SharedListOperators
{
    protected static string $resource = OperatorResource::class;
}