<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\FlightPlans\Enums\AvsecClearanceState;
use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FlightWorkflowService;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;

final class FlightStatusDisplay
{
    public const MANIFEST_REQUIRED = 'manifest_required';

    public const AWAITING_PIC = 'awaiting_pic';

    public const ACCEPTANCE_NOTICE = 'Your flight plan has been accepted. Contact San Fernando ATMO for startup clearance before starting your engine.';

    public const PIC_DECLINED = 'pic_declined';

    public const PENDING = 'pending';

    public const AWAITING_AVSEC = 'awaiting_avsec';

    public const PENDING_ATMO = 'pending_atmo';

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
        if ($flight->pic_authorization_status === 'declined') {
            return self::PIC_DECLINED;
        }

        if ($flight->status === FlightPlanStatus::Rejected) {
            return self::REJECTED;
        }

        if ($flight->status === FlightPlanStatus::Cancelled) {
            return self::CANCELLED;
        }

        if ($flight->isPendingExpired()) {
            return self::EXPIRED;
        }

        if (in_array($flight->status, [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending], true)) {
            return app(FlightWorkflowService::class)->pendingStage($flight);
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

        if ($flight->exists && AvsecVerificationService::requiredFor($flight)
            && in_array($status, [self::PENDING, self::AWAITING_AVSEC, self::PENDING_ATMO], true)) {
            $flight->loadMissing(['manifest.persons', 'filingAuthorizations.pilotSignature', 'avsecVerification', 'avsecDenials']);
            $clearance = app(AvsecVerificationService::class)->currentState($flight, loaded: true);
            if ($clearance === AvsecClearanceState::Denied) {
                return self::clearanceBadge($clearance, self::resolveViewerRole($viewerRole));
            }
        }

        return [
            'key' => $status,
            'label' => self::label($status, $viewerRole),
            'color' => self::color($status),
            'icon' => self::icon($status),
            'outlined' => self::isOutlined($status),
            'tooltip' => self::tooltip($status, $viewerRole),
        ];
    }

    /** @return array{key: string, label: string, color: string, icon: string, outlined: bool, tooltip: string} */
    public static function avsecBadge(Flight $flight): array
    {
        $service = app(AvsecVerificationService::class);
        $flight->loadMissing(['manifest.persons', 'filingAuthorizations.pilotSignature', 'avsecVerification', 'avsecDenials']);
        $clearance = $service->currentState($flight, loaded: true);
        if ($clearance !== AvsecClearanceState::Awaiting) {
            return self::clearanceBadge($clearance, UserRole::Avsec);
        }
        $key = self::status($flight);
        $label = self::label($key, UserRole::Avsec);
        $color = self::color($key);
        $icon = self::icon($key);
        $tooltip = self::tooltip($key, UserRole::Avsec);

        return [
            'key' => $key, 'label' => $label, 'color' => $color,
            'icon' => $icon, 'outlined' => false, 'tooltip' => $tooltip,
        ];
    }

    /** @return array{key: string, label: string, color: string, icon: string, outlined: bool, tooltip: string} */
    private static function clearanceBadge(AvsecClearanceState $state, ?UserRole $viewerRole = null): array
    {
        return [
            'key' => $state->value, 'label' => $state->label($viewerRole), 'color' => $state->color(),
            'icon' => $state->icon(), 'outlined' => $state === AvsecClearanceState::Awaiting,
            'tooltip' => match ($state) {
                AvsecClearanceState::Granted => 'Offline PNP-AVSEC clearance granted; ATMO acceptance remains separate.',
                AvsecClearanceState::Denied => 'Offline PNP-AVSEC clearance denied. Review the recorded reason.',
                AvsecClearanceState::Awaiting => 'Awaiting confirmation of offline PNP-AVSEC clearance.',
            },
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
        if (in_array($status, [self::AWAITING_AVSEC, self::PENDING_ATMO], true)) {
            return $status === self::AWAITING_AVSEC ? 'For AVSEC Clearance' : 'For ATMO Signature';
        }
        $role = self::resolveViewerRole($viewerRole);

        if ($role === UserRole::Pilot) {
            return match ($status) {
                self::MANIFEST_REQUIRED => 'Manifest Required',
                self::AWAITING_PIC => 'For PIC Signature',
                self::PIC_DECLINED => 'PIC Declined',
                self::PENDING => 'Awaiting Approval',
                self::ACCEPTED => 'Filed Successfully',
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
            self::MANIFEST_REQUIRED => 'Manifest Required',
            self::AWAITING_PIC => 'For PIC Signature',
            self::PIC_DECLINED => 'PIC Declined',
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
            self::AWAITING_AVSEC => 'warning',
            self::PENDING_ATMO => 'gray',
            self::MANIFEST_REQUIRED => 'warning',
            self::AWAITING_PIC => 'gray',
            self::PIC_DECLINED => 'danger',
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
            self::AWAITING_AVSEC => 'heroicon-o-shield-check',
            self::PENDING_ATMO => 'heroicon-o-clock',
            self::MANIFEST_REQUIRED => 'heroicon-o-document-text',
            self::AWAITING_PIC => 'heroicon-o-user-circle',
            self::PIC_DECLINED => 'heroicon-o-exclamation-triangle',
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
        return in_array($status, [self::AWAITING_PIC, self::PENDING, self::PENDING_ATMO, self::AWAITING_AVSEC, self::PIC_DECLINED], true);
    }

    public static function tooltip(string $status, UserRole|string|null $viewerRole = null): string
    {
        if ($status === self::AWAITING_AVSEC) {
            return 'PIC and manifest authorization complete; awaiting confirmation of offline PNP AVSEC procedures.';
        }
        if ($status === self::PENDING_ATMO) {
            return 'Required authorizations and clearance verification complete; awaiting ATMO review.';
        }
        $role = self::resolveViewerRole($viewerRole);

        if ($role === UserRole::Pilot) {
            return match ($status) {
                self::AWAITING_PIC => 'This flight plan is waiting for Pilot-in-Command authorization.',
                self::PIC_DECLINED => 'This filing was declined by the Pilot-in-Command. Its submitted contents are read-only.',
                self::PENDING => 'Your flight plan is waiting for ATC review.',
                self::ACCEPTED => self::ACCEPTANCE_NOTICE,
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
            self::AWAITING_PIC => 'Prepared and awaiting Pilot-in-Command authorization.',
            self::PIC_DECLINED => 'This historical version was declined by the Pilot-in-Command.',
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
