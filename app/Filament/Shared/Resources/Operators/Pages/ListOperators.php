<?php

namespace App\Filament\Shared\Resources\Operators\Pages;

use App\Filament\Shared\Resources\Operators\OperatorResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperators extends ListRecords
{
    protected static string $resource = OperatorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create Operator'),
        ];
    }
}
