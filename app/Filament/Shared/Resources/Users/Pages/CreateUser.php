<?php

namespace App\Filament\Shared\Resources\Users\Pages;

use App\Filament\Shared\Resources\Users\Support\UserAccountFormData;
use App\Filament\Shared\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static bool $canCreateAnother = false;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function handleRecordCreation(array $data): Model
    {
        ['user' => $userData, 'profiles' => $profileData] = UserAccountFormData::split($data);
        $password = $userData['password'] ?? null;
        $actor = Auth::user();
        $userData['created_by_user_id'] = $actor?->getKey();

        $user = User::create($userData);

        UserAccountFormData::recordCreated($user, $actor);
        UserAccountFormData::syncPasswordAuthAccount($user, $password);
        UserAccountFormData::syncProfile($user, $profileData, $actor);
        UserAccountFormData::syncKycDocuments($user, $actor, $profileData['kyc_documents'] ?? []);

        return $user;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Create User');
    }
}
