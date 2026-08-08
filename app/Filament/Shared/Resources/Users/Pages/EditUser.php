<?php

namespace App\Filament\Shared\Resources\Users\Pages;

use App\Filament\Shared\Resources\Users\Support\UserAccountFormData;
use App\Filament\Shared\Resources\Users\UserResource;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use App\Services\ProfileUpdates\ArtisanProfileOverrideService;
use App\Services\ProfileUpdates\ProfileFieldRegistry;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

    protected function getHeaderActions(): array
    {
        return [
            Action::make('artisanOverride')
                ->label('ARTISAN OVERRIDE')
                ->color('danger')
                ->modalHeading('ARTISAN OVERRIDE')
                ->modalDescription('This bypasses the normal KYC approval workflow and will be permanently audited.')
                ->visible(fn (): bool => Auth::user()?->role === UserRole::Artisan)
                ->form(fn (): array => [
                    Select::make('field')
                        ->label('Protected Field')
                        ->options(fn (): array => ProfileFieldRegistry::optionsFor($this->getRecord()))
                        ->required(),
                    TextInput::make('new_value')
                        ->label('New Value')
                        ->required(),
                    Textarea::make('reason')
                        ->label('Emergency Override Reason')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (array $data): void {
                    /** @var User $record */
                    $record = $this->getRecord();
                    $artisan = Auth::user();
                    abort_unless($artisan instanceof User, 403);

                    app(ArtisanProfileOverrideService::class)->override(
                        $record,
                        $artisan,
                        [(string) $data['field'] => $data['new_value'] ?? null],
                        (string) ($data['reason'] ?? ''),
                    );

                    Notification::make()->title('Artisan override recorded.')->success()->send();
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]), navigate: true);
                }),
        ];
    }

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
                'source' => 'admin_direct_change',
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
