<?php

namespace App\Filament\Panels\Avsec\Resources\AvsecVerifications;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ListAvsecVerifications;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ViewAvsecVerification;
use App\Models\AvsecVerification;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AvsecVerificationResource extends Resource
{
    protected static ?string $model = AvsecVerification::class;

    protected static ?string $navigationLabel = 'RPUS AVSEC Clearance Register';

    protected static ?string $modelLabel = 'AVSEC confirmation';

    public static function canViewAny(): bool
    {
        return AvsecVerificationService::eligibleOfficer(auth()->user());
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny() && $record->station === 'RPUS'
            && AvsecVerificationService::requiredFor($record->flight);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('flight')->where('station', 'RPUS')
            ->whereHas('flight', fn (Builder $query) => $query->whereRaw('UPPER(TRIM(departure_aerodrome)) = ?', ['RPUS']))
            ->when(! static::canViewAny(), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('verified_at', 'desc')->columns([
            TextColumn::make('flight.aircraft_identification')->label('Callsign')->searchable()->sortable(),
            TextColumn::make('flight.date_of_flight')->label('Flight date')->date()->sortable(),
            TextColumn::make('verifier_name')->label('Verified in Echo by')->searchable(),
            TextColumn::make('verified_at')->label('Confirmed (Asia/Manila)')->dateTime('Y-m-d H:i:s', timezone: 'Asia/Manila')->sortable(),
            TextColumn::make('flight.status')->label('Flight status')->badge(),
            TextColumn::make('confirmation_status')->label('AVSEC status')->state('Confirmed · Frozen'),
        ])->filters([
            SelectFilter::make('flight_status')->options(collect(FlightPlanStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all())
                ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $status) => $query->whereHas('flight', fn (Builder $query) => $query->where('status', $status)))),
        ])->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('flight.aircraft_identification')->label('Callsign'),
            TextEntry::make('flight_id'), TextEntry::make('flight_revision'),
            TextEntry::make('flight.pilot_in_command')->label('PIC'),
            TextEntry::make('flight.type_of_aircraft')->label('Aircraft'),
            TextEntry::make('verifier_name')->label('Verified in Echo by'),
            TextEntry::make('verified_at')->label('Confirmed (Asia/Manila)')->dateTime('Y-m-d H:i:s', timezone: 'Asia/Manila'),
            TextEntry::make('pnp_reference_number')->placeholder('Not provided'),
            TextEntry::make('content_sha256')->label('Approved submission SHA-256'),
            TextEntry::make('confirmation')->state(AvsecVerificationService::CONFIRMATION),
            TextEntry::make('scope')->state('Offline PNP procedures confirmed in Echo. Submission permanently frozen. This is not ATC acceptance or departure authorization.'),
            TextEntry::make('flight_link')->label('Flight plan')->state('Open authoritative flight plan')->url(fn (AvsecVerification $record) => route('flights.view', $record->flight)),
            TextEntry::make('manifest_link')->label('Passenger / cargo manifest')->state('Open preserved manifest')->url(fn (AvsecVerification $record) => route('flights.manifest.pdf', $record->flight)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAvsecVerifications::route('/'), 'view' => ViewAvsecVerification::route('/{record}')];
    }
}
