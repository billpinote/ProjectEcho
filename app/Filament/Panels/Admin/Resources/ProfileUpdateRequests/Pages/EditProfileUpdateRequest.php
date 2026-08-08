<?php

namespace App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages;

use App\Domain\ProfileUpdateRequests\Enums\ProfileUpdateRequestStatus;
use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\ProfileUpdateRequestResource;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;

class EditProfileUpdateRequest extends EditRecord
{
    protected static string $resource = ProfileUpdateRequestResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => $this->getRecord()->status === ProfileUpdateRequestStatus::Pending)
                ->form([
                    Textarea::make('remarks')
                        ->label('Reviewer Remarks')
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    /** @var ProfileUpdateRequest $record */
                    $record = $this->getRecord();
                    $reviewer = Auth::user();
                    abort_unless($reviewer instanceof User, 403);

                    app(ProfileUpdateRequestService::class)->approve($record, $reviewer, $data['remarks'] ?? null);

                    Notification::make()->title('Profile update request approved.')->success()->send();
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]), navigate: true);
                }),
            Action::make('reject')
                ->label('Reject')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status === ProfileUpdateRequestStatus::Pending)
                ->form([
                    Textarea::make('reason')
                        ->label('Rejection Reason')
                        ->required()
                        ->rows(3),
                ])
                ->action(function (array $data): void {
                    /** @var ProfileUpdateRequest $record */
                    $record = $this->getRecord();
                    $reviewer = Auth::user();
                    abort_unless($reviewer instanceof User, 403);

                    app(ProfileUpdateRequestService::class)->reject($record, $reviewer, (string) ($data['reason'] ?? ''));

                    Notification::make()->title('Profile update request rejected.')->success()->send();
                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]), navigate: true);
                }),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->hidden();
    }

    protected function getCancelFormAction(): Action
    {
        return parent::getCancelFormAction()->label('Back');
    }
}
