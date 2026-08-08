<?php
namespace App\Filament\Panels\Admin\Resources\Operators;
use App\Filament\Shared\Resources\Operators\OperatorResource as SharedOperatorResource;
use App\Filament\Panels\Admin\Resources\Operators\Pages\ListOperators;
use App\Filament\Panels\Admin\Resources\Operators\Pages\CreateOperator;
use App\Filament\Panels\Admin\Resources\Operators\Pages\EditOperator;
class OperatorResource extends SharedOperatorResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListOperators::route('/'),
            'create' => CreateOperator::route('/create'),
            'edit' => EditOperator::route('/{record}/edit'),
        ];
    }
}