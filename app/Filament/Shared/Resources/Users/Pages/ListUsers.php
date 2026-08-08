<?php

namespace App\Filament\Shared\Resources\Users\Pages;

use App\Filament\Shared\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createUser')
                ->label('Create User')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => static::getResource()::getUrl('create')),
        ];
    }
}
