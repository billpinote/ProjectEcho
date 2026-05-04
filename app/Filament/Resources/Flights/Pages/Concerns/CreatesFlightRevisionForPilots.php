<?php

namespace App\Filament\Resources\Flights\Pages\Concerns;

use App\Enums\FlightPlanStatus;
use App\Models\Flight;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait CreatesFlightRevisionForPilots
{
    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Auth::user();

        if (! $user?->createsFlightPlanRevisionsOnly()) {
            $record->update($data);

            return $record;
        }

        /** @var Flight $record */
        $revision = $record->replicate([
            'accepted_by_user_id',
            'accepted_by_wiresign',
            'rejected_by_wiresign',
            'rejection_reason',
            'received_by',
            'received_date',
            'received_time',
            'received_facility',
            'reviewed_at',
        ]);

        $revision->fill($data);
        $revision->forceFill([
            'status' => FlightPlanStatus::Pending,
            'accepted_by_user_id' => null,
            'accepted_by_wiresign' => null,
            'rejected_by_wiresign' => null,
            'rejection_reason' => null,
            'received_by' => null,
            'received_date' => null,
            'received_time' => null,
            'received_facility' => null,
            'reviewed_at' => null,
            'time_start_up' => null,
            'time_shutdown' => null,
            'time_block_off' => null,
            'time_block_on' => null,
            'time_airborne' => null,
            'time_touchdown' => null,
        ]);
        $revision->save();

        Notification::make()
            ->success()
            ->title('New flight plan created')
            ->body('The original flight plan was left unchanged.')
            ->send();

        return $revision;
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return Auth::user()?->createsFlightPlanRevisionsOnly()
            ? 'New flight plan created'
            : parent::getSavedNotificationTitle();
    }
}
