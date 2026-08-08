<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

final class FlightStatusDisplay
{
    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const STARTED = 'started';

    public const AIRBORNE = 'airborne';

    public const LANDED = 'landed';

    public const COMPLETED = 'completed';

    public const REJECTED = 'rejected';

    public const CANCELLED = 'cancelled';

    public const EXPIRED = 'expired';

    /**
     * Resolve the operational display status of a flight.
     *
     * The database status records review state (pending, accepted, rejected),
     * while operational timestamps determine started, airborne, landed,
     * and completed states.
     */
    public static function status(Flight $flight): string
    {
        if ($flight->status === FlightPlanStatus::Rejected) {
            return self::REJECTED;
        }

        if ($flight->status === FlightPlanStatus::Cancelled) {
            return self::CANCELLED;
        }

        if ($flight->isPendingExpired()) {
            return self::EXPIRED;
        }

        if ($flight->status === FlightPlanStatus::Pending) {
            return self::PENDING;
        }

        if (filled($flight->time_touchdown)
            && (filled($flight->time_block_on) || filled($flight->time_shutdown))) {
            return self::COMPLETED;
        }

        if (filled($flight->time_touchdown)) {
            return self::LANDED;
        }

        if (filled($flight->time_airborne)) {
            return self::AIRBORNE;
        }

        if (filled($flight->time_start_up) || filled($flight->time_block_off)) {
            return self::STARTED;
        }

        return self::ACCEPTED;
    }

    /**
     * Return all presentation properties for a Filament status badge.
     *
     * @return array{
     *     key: string,
     *     label: string,
     *     color: string,
     *     icon: string,
     *     outlined: bool,
     *     description: string
     * }
     */
    public static function badge(Flight $flight, UserRole|string|null $viewerRole = null): array
    {
        $status = self::status($flight);

        return [
            'key' => $status,
            'label' => self::label($status, $viewerRole),
            'color' => self::color($status),
            'icon' => self::icon($status),
            'outlined' => self::isOutlined($status),
            'tooltip' => self::tooltip($status, $viewerRole),
        ];
    }

    public static function tableColumn(
        UserRole|string|null $viewerRole = null,
        string $name = 'status_presentation',
    ): TextColumn {
        return TextColumn::make($name)
            ->label('Status')
            ->state(fn (Flight $record): string => self::badge($record, $viewerRole)['label'])
            ->badge()
            ->icon(fn (Flight $record): string => self::badge($record, $viewerRole)['icon'])
            ->color(fn (Flight $record): string => self::badge($record, $viewerRole)['color'])
            ->tooltip(fn (Flight $record): ?string => self::badge($record, $viewerRole)['tooltip'] ?: null)
            ->alignCenter()
            ->extraHeaderAttributes(['class' => 'text-center'])
            ->extraCellAttributes(fn (Flight $record): array => [
                'class' => 'echo-flight-status-cell',
                'data-flight-status' => self::badge($record, $viewerRole)['key'],
                'data-flight-status-outlined' => self::badge($record, $viewerRole)['outlined'] ? 'true' : 'false',
            ])
            ->width('16px');
    }

    public static function label(string $status, UserRole|string|null $viewerRole = null): string
    {
        $role = self::resolveViewerRole($viewerRole);

        if ($role === UserRole::Pilot) {
            return match ($status) {
                self::PENDING => 'Awaiting Approval',
                self::ACCEPTED => 'Approved',
                self::STARTED => 'Preparing for Departure',
                self::AIRBORNE => 'In Flight',
                self::LANDED => 'Landed',
                self::COMPLETED => 'Flight Complete',
                self::REJECTED => 'Action Required',
                self::CANCELLED => 'Cancelled',
                self::EXPIRED => 'Expired',
                default => str($status)->headline()->toString(),
            };
        }

        return match ($status) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::STARTED => 'Started',
            self::AIRBORNE => 'Airborne',
            self::LANDED => 'Landed',
            self::COMPLETED => 'Completed',
            self::REJECTED => 'Rejected',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            default => str($status)->headline()->toString(),
        };
    }

    /**
     * Filament semantic color names.
     *
     * Pending uses gray plus an outlined style in the table configuration.
     * Accepted deliberately uses a darker gray so green remains reserved
     * for a fully completed flight.
     */
    public static function color(string $status): string
    {
        return match ($status) {
            self::PENDING => 'gray',
            self::ACCEPTED => 'slate',
            self::STARTED => 'indigo',
            self::AIRBORNE => 'info',
            self::LANDED => 'warning',
            self::COMPLETED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'gray',
            self::EXPIRED => 'charcoal',
            default => 'gray',
        };
    }

    public static function icon(string $status): string
    {
        return match ($status) {
            self::PENDING => 'heroicon-o-clock',
            self::ACCEPTED => 'heroicon-o-check',
            self::STARTED => 'heroicon-o-play',
            self::AIRBORNE => 'heroicon-o-paper-airplane',
            self::LANDED => 'heroicon-o-arrow-down-circle',
            self::COMPLETED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::CANCELLED => 'heroicon-o-no-symbol',
            self::EXPIRED => 'heroicon-o-archive-box',
            default => 'heroicon-o-question-mark-circle',
        };
    }

    public static function isOutlined(string $status): bool
    {
        return $status === self::PENDING;
    }

    public static function tooltip(string $status, UserRole|string|null $viewerRole = null): string
    {
        $role = self::resolveViewerRole($viewerRole);

        if ($role === UserRole::Pilot) {
            return match ($status) {
                self::PENDING => 'Your flight plan is waiting for ATC review.',
                self::ACCEPTED => 'Your flight plan has been approved.',
                self::STARTED => 'Startup or block-off has been recorded.',
                self::AIRBORNE => 'The aircraft is currently airborne.',
                self::LANDED => 'Touchdown has been recorded. Shutdown or block-on is still pending.',
                self::COMPLETED => 'The flight has completed its operational cycle.',
                self::REJECTED => 'The flight plan was rejected. Review the reason and submit a revision.',
                self::CANCELLED => 'This flight plan was cancelled by the pilot.',
                self::EXPIRED => 'The date of flight passed before the flight plan was accepted.',
                default => '',
            };
        }

        return match ($status) {
            self::PENDING => 'Filed and awaiting review.',
            self::ACCEPTED => 'Accepted and awaiting startup or block-off.',
            self::STARTED => 'Startup or block-off recorded; not yet airborne.',
            self::AIRBORNE => 'Airborne; touchdown not yet recorded.',
            self::LANDED => 'Touchdown recorded; awaiting block-on or shutdown.',
            self::COMPLETED => 'Touchdown and block-on or shutdown recorded.',
            self::REJECTED => 'Rejected during flight-plan review.',
            self::CANCELLED => 'Cancelled by the filing pilot.',
            self::EXPIRED => 'Pending flight plan whose date of flight has passed.',
            default => '',
        };
    }

    private static function resolveViewerRole(UserRole|string|null $viewerRole = null): ?UserRole
    {
        if ($viewerRole instanceof UserRole) {
            return $viewerRole;
        }

        if (filled($viewerRole)) {
            return UserRole::normalize($viewerRole);
        }

        return Auth::user()?->role instanceof UserRole
            ? Auth::user()->role
            : UserRole::normalize(Auth::user()?->role);
    }
}
