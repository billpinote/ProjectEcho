<?php

namespace App\Filament\Panels\Avsec\Resources\AvsecVerifications;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FlightPlanPdfService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ListAvsecVerifications;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\Pages\ViewAvsecVerification;
use App\Models\AvsecVerification;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AvsecVerificationResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $model = AvsecVerification::class;

    protected static ?string $navigationLabel = 'Cleared Flights';

    protected static ?string $pluralModelLabel = 'Cleared Flights';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'cleared flight';

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
        return parent::getEloquentQuery()->with([
            'flight.operator', 'flight.manifest.persons', 'flight.filingAuthorizations.pilotSignature',
            'flight.avsecVerification', 'flight.avsecDenials',
        ])->where('station', 'RPUS')
            ->whereHas('flight', fn (Builder $query) => $query->whereRaw('UPPER(TRIM(departure_aerodrome)) = ?', ['RPUS']))
            ->when(! static::canViewAny(), fn (Builder $query) => $query->whereRaw('1 = 0'));
    }

    public static function table(Table $table): Table
    {
        return $table->heading('Cleared Flights')
            ->description('Flights cleared by PNP AVSEC, including verification records from previous shifts.')
            ->defaultSort('verified_at', 'desc')->columns([
                TextColumn::make('flight.aircraft_identification')->label('CALLSIGN')->searchable()->sortable(),
                TextColumn::make('flight.date_of_flight')->label('DATE')->date('Y-m-d', timezone: 'UTC')->sortable(),
                TextColumn::make('flight.proposed_time')->label('PTD')->formatStateUsing(fn ($state) => filled($state) ? UtcFourDigitTime::formatForDisplay($state).'Z' : null)->sortable(),
                TextColumn::make('flight.pilot_in_command')->label('Pilot'),
                TextColumn::make('confirmation_status')->label('AVSEC STATUS')->badge()
                    ->state(fn (AvsecVerification $record) => FlightStatusDisplay::avsecBadge($record->flight)['label'])
                    ->color(fn (AvsecVerification $record) => FlightStatusDisplay::avsecBadge($record->flight)['color']),
            ])->filters([
                SelectFilter::make('flight_status')->options(collect(FlightPlanStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all())
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $status) => $query->whereHas('flight', fn (Builder $query) => $query->where('status', $status)))),
            ])->recordActions([])->recordAction(null)
            ->recordUrl(fn (AvsecVerification $record) => static::getUrl('view', ['record' => $record], panel: 'avsec'))->openRecordUrlInNewTab()
            ->recordClasses('echo-avsec-linked-row')
            ->extraRecordLinkAttributes(['class' => 'echo-avsec-record-link', 'title' => 'Opens in a new tab']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make(fn (AvsecVerification $record) => $record->flight->aircraft_identification)
                ->description(fn (AvsecVerification $record) => $record->flight->departure_aerodrome.' → '.$record->flight->destination_aerodrome)
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->schema([
                    TextEntry::make('flight.date_of_flight')->label('Flight date (UTC)')->date('Y-m-d', timezone: 'UTC'),
                    TextEntry::make('flight.proposed_time')->label('PTD (UTC)')->formatStateUsing(fn ($state) => filled($state) ? UtcFourDigitTime::formatForDisplay($state).'Z' : null),
                    TextEntry::make('confirmation_status')->label('PNP-AVSEC clearance')->badge()
                        ->state(fn (AvsecVerification $record) => FlightStatusDisplay::avsecBadge($record->flight)['label'])
                        ->color(fn (AvsecVerification $record) => FlightStatusDisplay::avsecBadge($record->flight)['color']),
                ])->columns(['default' => 1, 'md' => 3])->columnSpanFull(),
            Grid::make(['default' => 1, 'md' => 2])->schema([
                Section::make('Flight Plan')->description('Authoritative filing for this clearance record.')
                    ->schema([
                        TextEntry::make('flight_plan_status')->hiddenLabel()->badge()
                            ->state(fn (AvsecVerification $record) => app(FlightPlanPdfService::class)->storedPaths($record->flight)->isNotEmpty() ? 'Available' : 'PDF Unavailable')
                            ->color(fn (AvsecVerification $record) => app(FlightPlanPdfService::class)->storedPaths($record->flight)->isNotEmpty() ? 'success' : 'warning'),
                        TextEntry::make('owner_operator')->label('Owner / Operator')
                            ->state(fn (AvsecVerification $record) => collect([$record->flight->operator?->name, $record->flight->manifest?->aircraft_owner_name, $record->flight->operator?->icao_code])->first(fn ($value) => filled($value)))
                            ->placeholder('Not recorded')->weight(FontWeight::SemiBold)->size('lg'),
                        TextEntry::make('flight.type_of_aircraft')->label('Aircraft Type')->placeholder('Not recorded')->weight(FontWeight::SemiBold)->size('lg'),
                        TextEntry::make('flight.pilot_in_command')->label('Pilot')->placeholder('Not recorded')->weight(FontWeight::SemiBold)->size('lg'),
                    ])->footerActions([
                        Action::make('downloadFlight')->label('Download PDF')->button()->icon(null)
                            ->extraAttributes(['style' => 'min-height:44px;min-width:44px'])
                            ->url(fn (AvsecVerification $record) => route('flights.pdf.download', $record->flight))
                            ->visible(fn (AvsecVerification $record) => app(FlightPlanPdfService::class)->storedPaths($record->flight)->isNotEmpty()),
                    ]),
                Section::make('Passenger / Cargo Manifest')->description('Supporting document preserved with clearance.')
                    ->schema([
                        TextEntry::make('manifest_status')->hiddenLabel()->badge()
                            ->state(fn (AvsecVerification $record) => static::manifestDownloadAvailable($record) ? 'Available' : 'Manifest Required')
                            ->color(fn (AvsecVerification $record) => static::manifestDownloadAvailable($record) ? 'success' : 'warning'),
                        TextEntry::make('total_pob')->label('Total POB')
                            ->state(fn (AvsecVerification $record) => $record->flight->manifest?->persons->count())
                            ->placeholder('Not recorded')->weight(FontWeight::SemiBold)->size('lg'),
                        TextEntry::make('cargo_quantity')->label('Cargo Quantity')
                            ->state(fn (AvsecVerification $record) => static::cargoQuantity($record))
                            ->placeholder('Quantity not recorded')->weight(FontWeight::SemiBold)->size('lg'),
                        TextEntry::make('cargo_weight')->label('Cargo Weight')
                            ->state(function (AvsecVerification $record): ?string {
                                $weight = $record->flight->manifest?->total_cargo_weight;

                                return $weight === null ? null : rtrim(rtrim($weight, '0'), '.').' kg';
                            })
                            ->placeholder('Weight not recorded')->weight(FontWeight::SemiBold)->size('lg'),
                    ])->footerActions([
                        Action::make('downloadManifest')->label('Download PDF')->button()->icon(null)
                            ->extraAttributes(['style' => 'min-height:44px;min-width:44px'])
                            ->url(fn (AvsecVerification $record) => route('flights.manifest.pdf', ['flight' => $record->flight, 'download' => 1]))
                            ->visible(fn (AvsecVerification $record) => static::manifestDownloadAvailable($record)),
                    ]),
            ])->columnSpanFull(),
            Section::make('PNP-AVSEC Clearance Decision')->icon(Heroicon::OutlinedShieldCheck)
                ->description('Recorded from the signed offline clearance form. ATMO acceptance and Pilot authorization remain separate.')
                ->schema([
                    TextEntry::make('decision')->hiddenLabel()->state('Clearance Granted')->badge()->color('success')->columnSpanFull(),
                    TextEntry::make('verifier_name')->label('Recorded by'),
                    TextEntry::make('verified_at')->label('Decision time (Asia/Manila)')->dateTime('Y-m-d H:i:s', timezone: 'Asia/Manila'),
                    TextEntry::make('pnp_reference_number')->label('Clearance reference')->placeholder('Not provided')->columnSpanFull(),
                ])->columns(['default' => 1, 'md' => 2])->columnSpanFull(),
            Section::make('Record Integrity')->collapsed()->collapsible()->schema([
                TextEntry::make('flight_revision')->label('Flight revision'),
                TextEntry::make('content_sha256')->label('Preserved submission SHA-256')->columnSpanFull()->extraAttributes(['class' => 'break-all']),
                TextEntry::make('scope')->hiddenLabel()->state('Cleared documents are permanently protected from changes. This record does not authorize departure.')->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAvsecVerifications::route('/'), 'view' => ViewAvsecVerification::route('/{record}')];
    }

    private static function manifestDownloadAvailable(AvsecVerification $record): bool
    {
        return app(AvsecVerificationService::class)->manifestAvailable($record->flight)
            && filled($record->flight->manifest?->accepted_pdf_content)
            && filled($record->flight->manifest?->accepted_pdf_sha256);
    }

    private static function cargoQuantity(AvsecVerification $record): ?string
    {
        $manifest = $record->flight->manifest;
        $quantity = trim((string) $manifest?->cargo_quantity);
        if ($quantity !== '') {
            return $quantity;
        }

        return null;
    }
}
