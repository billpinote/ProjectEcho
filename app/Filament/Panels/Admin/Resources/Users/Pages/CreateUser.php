<?php

namespace App\Filament\Panels\Admin\Resources\Users\Pages;

use App\Filament\Panels\Admin\Resources\Users\UserResource;
use App\Filament\Shared\Resources\Users\Pages\CreateUser as SharedCreateUser;

class CreateUser extends SharedCreateUser
{
    protected static string $resource = UserResource::class;
}
