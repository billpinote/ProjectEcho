<?php

namespace App\Filament\Panels\Admin\Resources\Users\Pages;

use App\Filament\Panels\Admin\Resources\Users\UserResource;
use App\Filament\Shared\Resources\Users\Pages\ListUsers as SharedListUsers;

class ListUsers extends SharedListUsers
{
    protected static string $resource = UserResource::class;
}
