<?php

namespace App\Filament\Shared\Resources\Flights;

use App\Domain\FlightPlans\Support\FlightPlanDataNormalizer;
use App\Filament\Shared\Resources\Flights\Pages\CreateFlight;
use App\Filament\Shared\Resources\Flights\Pages\EditFlight;
use App\Filament\Shared\Resources\Flights\Pages\ListFlights;
use App\Filament\Shared\Resources\Flights\Schemas\FlightForm;
use App\Filament\Shared\Resources\Flights\Tables\FlightsTable;
use App\Models\Flight;
use BackedEnum;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use UnitEnum;

use function Filament\Support\original_request;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static ?string $recordTitleAttribute = 'aircraft_identification';

    protected static ?string $navigationLabel = 'Pending Flight Plans';

    protected static ?string $navigationParentItem = null;

    protected static string|UnitEnum|null $navigationGroup = 'Flight Operations';

    protected static ?string $modelLabel = 'pending flight plan';

    protected static ?string $pluralModelLabel = 'pending flight plans';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return FlightForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FlightsTable::configure($table, static::class);
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (static::isPendingFlightResource()) {
            return $user?->canReviewFlightPlans() ?? false;
        }

        return $user?->canViewFlightPlans() ?? false;
    }

    public static function canCreate(): bool
    {
        return Auth::user()?->canCreateFlightPlans() ?? false;
    }

    public static function canEdit($record): bool
    {
        return Auth::user()?->can('update', $record) ?? false;
    }

    public static function canDelete($record): bool
    {
        return Auth::user()?->can('delete', $record) ?? false;
    }

    protected static function getFlightPlanBaseQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(Auth::user());
    }

    protected static function hasStatusColumn(): bool
    {
        return SchemaFacade::hasColumn((new Flight)->getTable(), 'status');
    }

    protected static function hasReviewedAtColumn(): bool
    {
        return Flight::hasReviewedAtColumn();
    }

    public static function getEloquentQuery(): Builder
    {
        if (! static::hasStatusColumn()) {
            return static::getFlightPlanBaseQuery()->whereNull('accepted_by_user_id');
        }

        return static::getFlightPlanBaseQuery()->pendingActive();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getPendingNavigationCounts()['new'];

        return $count > 0 ? "New {$count}" : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unseen pending flight plans';
    }

    public static function getNavigationItems(): array
    {
        if (! static::hasPage('index') || ! static::canViewAny()) {
            return [];
        }

        $activeRoutePatterns = static::isPendingFlightResource()
            ? [
                static::getRouteBaseName().'.index',
                static::getRouteBaseName().'.edit',
            ]
            : static::getNavigationItemActiveRoutePattern();

        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->parentItem(static::getNavigationParentItem())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->isActiveWhen(fn (): bool => original_request()->routeIs($activeRoutePatterns))
                ->badge(static::getNavigationBadge(), color: static::getNavigationBadgeColor())
                ->badgeTooltip(static::getNavigationBadgeTooltip())
                ->extraAttributes(
                    static::isPendingFlightResource()
                        ? ['class' => 'echo-pending-nav-item']
                        : []
                )
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl()),
        ];
    }

    /**
     * @return array{new: int, total: int}
     */
    protected static function getPendingNavigationCounts(): array
    {
        $query = static::getModel()::query()->visibleTo(Auth::user());

        $total = static::hasStatusColumn()
            ? (clone $query)->pendingActive()->count()
            : (clone $query)->whereNull('accepted_by_user_id')->count();

        $new = static::hasReviewedAtColumn()
            ? (clone $query)->pendingUnreviewed()->count()
            : 0;

        return [
            'new' => $new,
            'total' => $total,
        ];
    }

    protected static function isPendingFlightResource(): bool
    {
        return static::class === self::class
            || str_ends_with(static::class, '\\Resources\\Flights\\FlightResource');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFlights::route('/'),
            'create' => CreateFlight::route('/create'),
            'edit' => EditFlight::route('/{record}/edit'),
        ];
    }

    /**
     * Keep admin-created records aligned with the public flight-plan flow.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeFormData(array $data): array
    {
        foreach (FlightForm::TIME_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = FlightForm::normalizeTimeForStorage($data[$field]);
            }
        }

        $data = self::normalizeOtherInformation($data);

        if (! (bool) ($data['dinghies_enabled'] ?? false)) {
            $data['dinghies_number'] = null;
            $data['dinghies_capacity'] = null;
            $data['dinghies_cover'] = null;
            $data['dinghies_color'] = null;
        }

        if (! (bool) ($data['authorized_representative_enabled'] ?? false)) {
            $data['authorized_representative_name'] = null;
            $data['authorized_representative_role'] = null;
            $data['authorized_representative_id_license'] = null;
            $data['authorized_representative_expiry_date'] = null;
        }

        $normalizer = new FlightPlanDataNormalizer;
        $data = $normalizer->uppercaseStringFlightFields($data);
        $data = $normalizer->normalizeNumericFlightFields($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeOtherInformation(array $data): array
    {
        $otherInformation = (string) ($data['other_information'] ?? '');

        if (
            filled($data['date_of_flight'] ?? null)
            && FlightPlanDataNormalizer::extractOtherInfoTagValue('DOF', $otherInformation) === null
        ) {
            $dateOfFlight = preg_replace('/\D/', '', (string) $data['date_of_flight']);

            if (strlen($dateOfFlight) === 8) {
                $otherInformation = trim($otherInformation.' DOF/'.$dateOfFlight);
            }
        }

        $tagValues = [
            'other_info_dof' => FlightPlanDataNormalizer::extractOtherInfoTagValue('DOF', $otherInformation),
            'other_info_rmk' => FlightPlanDataNormalizer::extractOtherInfoTagValue('RMK', $otherInformation),
            'other_info_typ' => FlightPlanDataNormalizer::extractOtherInfoTagValue('TYP', $otherInformation),
            'other_info_dep' => FlightPlanDataNormalizer::extractOtherInfoTagValue('DEP', $otherInformation),
            'other_info_route' => FlightPlanDataNormalizer::extractOtherInfoTagValue('RTE', $otherInformation),
            'other_info_dest' => FlightPlanDataNormalizer::extractOtherInfoTagValue('DEST', $otherInformation),
            'other_info_altn_1' => FlightPlanDataNormalizer::extractOtherInfoTagValue('ALTN', $otherInformation),
            'other_info_altn_2' => FlightPlanDataNormalizer::extractOtherInfoTagValue('ALTN2', $otherInformation),
            'other_info_pbn' => FlightPlanDataNormalizer::extractOtherInfoTagValue('PBN', $otherInformation),
            'other_info_reg' => FlightPlanDataNormalizer::extractOtherInfoTagValue('REG', $otherInformation),
            'other_info_opr' => FlightPlanDataNormalizer::extractOtherInfoTagValue('OPR', $otherInformation),
        ];

        $data['other_information'] = FlightPlanDataNormalizer::reorganizeOtherInformationTags($tagValues);

        return [
            ...$data,
            ...$tagValues,
        ];
    }

    /**
     * @param  array<string, ?string>  $tagValues
     */
}
