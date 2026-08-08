<?php

namespace App\Filament\Panels\Admin\Resources\Users;

use App\Filament\Panels\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Panels\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Panels\Admin\Resources\Users\Pages\ListUsers;
use App\Filament\Shared\Resources\Users\UserResource as SharedUserResource;

class UserResource extends SharedUserResource
{
    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
