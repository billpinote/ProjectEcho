<?php

namespace App\Filament\Panels\Admin\Resources\Users\Pages;

use App\Filament\Panels\Admin\Resources\Users\UserResource;
use App\Filament\Shared\Resources\Users\Pages\EditUser as SharedEditUser;

class EditUser extends SharedEditUser
{
    protected static string $resource = UserResource::class;
}
