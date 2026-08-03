<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\DB;

class MyProfilePage extends Page
{
    protected static ?string $title = 'My Profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected static ?string $navigationLabel = 'My Profile';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.my-profile';

    public ?string $first_name = null;

    public ?string $middle_name = null;

    public ?string $last_name = null;

    public ?string $email = null;

    public ?string $license_number = null;

    public ?string $ratings = null;

    public ?string $license_expiry_date = null;

    public ?string $operator = null;

    public ?string $medical_expiry_date = null;

    public ?string $remarks = null;

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user?->isPilot(), 403);

        $this->first_name = $user->first_name;
        $this->middle_name = $user->middle_name;
        $this->last_name = $user->last_name;
        $this->email = $user->email;
        $this->license_number = $user->pilotProfile?->license_number;
        $this->ratings = $user->pilotProfile?->ratings;
        $this->license_expiry_date = $user->pilotProfile?->license_expiry_date?->toDateString();
        $this->operator = $user->pilotProfile?->operator;
        $this->medical_expiry_date = $user->pilotProfile?->medical_expiry_date?->toDateString();
        $this->remarks = $user->pilotProfile?->remarks;
    }

    public function save(): void
    {
        $user = auth()->user();

        abort_unless($user?->isPilot(), 403);

        DB::transaction(function () use ($user): void {
            $user->forceFill([
                'first_name' => trim((string) ($this->first_name ?? '')),
                'middle_name' => trim((string) ($this->middle_name ?? '')),
                'last_name' => trim((string) ($this->last_name ?? '')),
                'email' => trim((string) ($this->email ?? '')),
                'name' => trim(implode(' ', array_filter([
                    $this->first_name,
                    $this->middle_name,
                    $this->last_name,
                ]))),
            ])->save();

            $profile = $user->pilotProfile()->firstOrCreate([]);
            $profile->forceFill([
                'license_number' => trim((string) ($this->license_number ?? '')) ?: null,
                'ratings' => trim((string) ($this->ratings ?? '')) ?: null,
                'license_expiry_date' => $this->license_expiry_date ?: null,
                'medical_expiry_date' => $this->medical_expiry_date ?: null,
                'operator' => trim((string) ($this->operator ?? '')) ?: null,
                'remarks' => trim((string) ($this->remarks ?? '')) ?: null,
            ])->save();
        });

        Notification::make()
            ->title('Profile updated successfully.')
            ->success()
            ->send();
    }
}
