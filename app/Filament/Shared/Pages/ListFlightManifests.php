<?php

namespace App\Filament\Shared\Pages;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\FlightWorkflowService;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Models\Flight;
use App\Models\FlightManifest;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

abstract class ListFlightManifests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Flight Manifests';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'manifests';

    protected string $view = 'filament.pages.flight-manifests';

    abstract protected function createFlightUrl(): string;

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => app(FlightWorkflowService::class)->withStatusContext(
                FlightAccess::restrictQueryToVisibleManifestFlights(Flight::query(), auth()->user())
                    ->with(['manifest.creator', 'preparedBy', 'filedBy']),
            ))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')->fontFamily(FontFamily::Mono)->weight('bold')
                    ->searchable()->sortable()->alignCenter()->verticallyAlignCenter()->wrap(false)
                    ->extraHeaderAttributes(['class' => 'text-center']),
                TextColumn::make('date_of_flight')
                    ->label('DOF')->date('d M Y')->fontFamily(FontFamily::Mono)
                    ->sortable()->alignCenter()->verticallyAlignCenter()->wrap(false)
                    ->extraHeaderAttributes(['class' => 'text-center']),
                TextColumn::make('proposed_time')
                    ->label('PTD')->fontFamily(FontFamily::Mono)
                    ->state(fn (Flight $record): ?string => filled($record->proposed_time)
                        ? UtcFourDigitTime::formatForDisplay($record->proposed_time).'Z' : null)
                    ->placeholder('—')->sortable()->alignCenter()->verticallyAlignCenter()->wrap(false)
                    ->extraHeaderAttributes(['class' => 'text-center']),
                TextColumn::make('pilot_in_command')
                    ->label('PIC')->placeholder('—')->limit(24)
                    ->tooltip(fn (Flight $record): ?string => $record->pilot_in_command)
                    ->searchable()->alignCenter()->verticallyAlignCenter()->wrap(false)
                    ->extraHeaderAttributes(['class' => 'text-center']),
                TextColumn::make('manifest_prepared_by')
                    ->label('Prepared By')->placeholder('—')->limit(24)
                    ->state(fn (Flight $record): ?string => $this->preparerName($record))
                    ->tooltip(fn (Flight $record): ?string => $this->preparerName($record))
                    ->alignCenter()->verticallyAlignCenter()->wrap(false)
                    ->extraHeaderAttributes(['class' => 'text-center']),
                FlightStatusDisplay::tableColumn()->verticallyAlignCenter()->wrap(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('manifest')
                        ->label(fn (Flight $record): string => $record->manifest === null ? 'Prepare manifest'
                            : (auth()->user()->can('update', $record->manifest) ? 'Edit manifest' : 'View manifest'))
                        ->icon('heroicon-o-clipboard-document-list')
                        ->url(fn (Flight $record): string => route('flights.manifest.edit', $record))
                        ->visible(fn (Flight $record): bool => auth()->user()->can('create', [FlightManifest::class, $record])
                            || ($record->manifest !== null && auth()->user()->can('view', $record->manifest)))
                        ->grouped(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-o-squares-2x2')
                    ->button()
                    ->tooltip('Actions')
                    ->extraAttributes([
                        'aria-label' => 'Manifest actions',
                        'class' => 'echo-flight-row-actions-trigger',
                    ]),
            ])
            ->recordActionsColumnLabel('Actions')
            ->recordActionsAlignment('end')
            ->recordAction(null)
            ->recordUrl(null)
            ->stackedOnMobile(false)
            ->paginated([20, 50, 100])
            ->defaultPaginationPageOption(20)
            ->emptyStateHeading('No flight plans available')
            ->emptyStateDescription('You need an Echo flight plan before preparing a saved manifest.');
    }

    protected function getViewData(): array
    {
        return [
            'nativeManifestTable' => true,
            'createFlightUrl' => $this->createFlightUrl(),
        ];
    }

    private function preparerName(Flight $flight): ?string
    {
        return $flight->manifest !== null
            ? $flight->manifest->creator?->preparedByNameSnapshot()
            : ($flight->prepared_by_name ?: ($flight->preparedBy ?? $flight->filedBy)?->preparedByNameSnapshot());
    }
}
