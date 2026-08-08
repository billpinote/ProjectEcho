<?php

namespace App\Filament\Shared\Resources\Operators\Pages;

use App\Filament\Shared\Resources\Operators\OperatorResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;

class CreateOperator extends CreateRecord
{
    protected static string $resource = OperatorResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create Operator');
    }
}
