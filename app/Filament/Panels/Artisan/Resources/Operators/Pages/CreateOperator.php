<?php
namespace App\Filament\Panels\Artisan\Resources\Operators\Pages;
use App\Filament\Panels\Artisan\Resources\Operators\OperatorResource;
use App\Filament\Shared\Resources\Operators\Pages\CreateOperator as SharedCreateOperator;
class CreateOperator extends SharedCreateOperator
{
    protected static string $resource = OperatorResource::class;
}