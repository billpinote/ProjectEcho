<?php
namespace App\Filament\Panels\Admin\Resources\Operators\Pages;
use App\Filament\Panels\Admin\Resources\Operators\OperatorResource;
use App\Filament\Shared\Resources\Operators\Pages\EditOperator as SharedEditOperator;
class EditOperator extends SharedEditOperator
{
    protected static string $resource = OperatorResource::class;
}