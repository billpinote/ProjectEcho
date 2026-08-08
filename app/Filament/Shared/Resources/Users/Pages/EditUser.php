<?php

namespace App\Filament\Shared\Resources\Users\Pages;

use App\Filament\Shared\Resources\Users\Support\UserAccountFormData;
use App\Filament\Shared\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getRecord();

        return UserAccountFormData::fillProfileData($user, $data);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        ['user' => $userData, 'profiles' => $profileData] = UserAccountFormData::split($data);
        $password = $userData['password'] ?? null;
        unset($userData['password']);

        /** @var User $record */
        $actor = Auth::user();
        $userChanges = UserAccountFormData::changesFor($record, $userData, [
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'display_name',
            'email',
            'username',
            'employee_id',
            'wiresign',
            'role',
            'station',
            'operator_id',
            'is_active',
        ]);

        $record->update($userData);

        UserAccountFormData::syncPasswordAuthAccount($record, $password);
        UserAccountFormData::syncAuthAccountIdentity($record);
        UserAccountFormData::recordUserChanges($record, $actor, $userChanges);

        if (filled($password)) {
            $record->auditLogs()->create([
                'performed_by_user_id' => $actor?->getKey(),
                'action' => 'updated',
                'auditable_type' => $record->getMorphClass(),
                'auditable_id' => $record->getKey(),
                'changes' => ['password' => ['changed' => true]],
                'description' => 'Password changed.',
            ]);
        }

        UserAccountFormData::syncProfile($record, $profileData, $actor);
        UserAccountFormData::syncKycDocuments($record, $actor, $profileData['kyc_documents'] ?? []);

        return $record;
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Save User');
    }
}
