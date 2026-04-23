<?php

namespace App\Filament\Resources\Flights;

use App\Filament\Resources\Flights\Pages\CreateFlight;
use App\Filament\Resources\Flights\Pages\EditFlight;
use App\Filament\Resources\Flights\Pages\ListFlights;
use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Filament\Resources\Flights\Tables\FlightsTable;
use App\Models\Flight;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema as SchemaFacade;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class FlightResource extends Resource
{
    protected static ?string $model = Flight::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?string $recordTitleAttribute = 'aircraft_identification';

    protected static ?string $navigationLabel = 'Pending';

    protected static ?string $navigationParentItem = 'Flight Plan';

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

    protected static function getFlightPlanBaseQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    protected static function hasStatusColumn(): bool
    {
        return SchemaFacade::hasColumn((new Flight)->getTable(), 'status');
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
        $query = static::getModel()::query();

        $count = static::hasStatusColumn()
            ? $query->pendingActive()->count()
            : $query->whereNull('accepted_by_user_id')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New flight plans awaiting ATC review';
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

        foreach ($data as $field => $value) {
            if (is_string($value)) {
                $data[$field] = strtoupper(trim($value));
            }
        }

        foreach (['persons_on_board', 'dinghies_number', 'dinghies_capacity'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $data[$field] = (int) $data[$field];
            }
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeOtherInformation(array $data): array
    {
        $otherInformation = (string) ($data['other_information'] ?? '');

        if (blank($otherInformation) && filled($data['date_of_flight'] ?? null)) {
            $dateOfFlight = preg_replace('/\D/', '', (string) $data['date_of_flight']);

            if (strlen((string) $dateOfFlight) === 8) {
                $otherInformation = 'DOF/'.$dateOfFlight;
            }
        }

        $tagValues = [
            'other_info_dof' => self::extractOtherInfoTagValue('DOF', $otherInformation),
            'other_info_rmk' => self::extractOtherInfoTagValue('RMK', $otherInformation),
            'other_info_typ' => self::extractOtherInfoTagValue('TYP', $otherInformation),
            'other_info_dep' => self::extractOtherInfoTagValue('DEP', $otherInformation),
            'other_info_route' => self::extractOtherInfoTagValue('RTE', $otherInformation),
            'other_info_dest' => self::extractOtherInfoTagValue('DEST', $otherInformation),
            'other_info_altn_1' => self::extractOtherInfoTagValue('ALTN', $otherInformation),
            'other_info_altn_2' => self::extractOtherInfoTagValue('ALTN2', $otherInformation),
            'other_info_pbn' => self::extractOtherInfoTagValue('PBN', $otherInformation),
            'other_info_reg' => self::extractOtherInfoTagValue('REG', $otherInformation),
            'other_info_opr' => self::extractOtherInfoTagValue('OPR', $otherInformation),
        ];

        $data['other_information'] = self::reorganizeOtherInformationTags($tagValues);

        return [
            ...$data,
            ...$tagValues,
        ];
    }

    /**
     * @param  array<string, ?string>  $tagValues
     */
    private static function reorganizeOtherInformationTags(array $tagValues): string
    {
        $hierarchy = [
            'DOF' => 'other_info_dof',
            'RMK' => 'other_info_rmk',
            'TYP' => 'other_info_typ',
            'DEP' => 'other_info_dep',
            'RTE' => 'other_info_route',
            'DEST' => 'other_info_dest',
            'ALTN' => 'other_info_altn_1',
            'ALTN2' => 'other_info_altn_2',
            'PBN' => 'other_info_pbn',
            'REG' => 'other_info_reg',
            'OPR' => 'other_info_opr',
        ];

        $parts = [];

        foreach ($hierarchy as $tag => $key) {
            $value = $tagValues[$key] ?? null;

            if ($value !== null) {
                $parts[] = $tag.'/'.$value;
            }
        }

        return implode(' ', $parts);
    }

    private static function extractOtherInfoTagValue(string $tag, string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $tagWithSlash = $tag.'/';
        $tagPosition = stripos($text, $tagWithSlash);

        if ($tagPosition === false) {
            return null;
        }

        $remainingText = substr($text, $tagPosition + strlen($tagWithSlash));

        if (preg_match('/\s+[A-Z0-9]{2,5}\//i', $remainingText, $matches, PREG_OFFSET_CAPTURE)) {
            $value = substr($remainingText, 0, $matches[0][1]);
        } else {
            $value = $remainingText;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
