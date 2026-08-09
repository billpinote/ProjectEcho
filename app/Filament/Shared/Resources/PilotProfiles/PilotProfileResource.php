<?php

namespace App\Filament\Shared\Resources\PilotProfiles;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Filament\Shared\Resources\PilotProfiles\Pages\CreatePilotProfile;
use App\Filament\Shared\Resources\PilotProfiles\Pages\EditPilotProfile;
use App\Filament\Shared\Resources\PilotProfiles\Pages\ListPilotProfiles;
use App\Models\Operator;
use App\Models\PilotProfile;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PilotProfileResource extends Resource
{
    protected static ?string $model = PilotProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Pilot Profiles';

    protected static string|UnitEnum|null $navigationGroup = 'Users & Organizations';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->required(),
            Select::make('license_type')
                ->label('Licence Type')
                ->options(PilotLicenseType::options())
                ->native(false),
            TextInput::make('license_number')->label('License Number'),
            DatePicker::make('license_expiry_date')
                ->label('License Expiry Date')
                ->native(false)
                ->displayFormat('F j, Y'),
            DatePicker::make('medical_expiry_date')
                ->label('Medical Expiry Date')
                ->native(false)
                ->displayFormat('F j, Y'),
            Select::make('operator_id')
                ->label('Operator')
                ->options(fn (): array => Operator::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->default(fn ($record): ?int => $record?->user?->operator_id),
            Textarea::make('remarks')->label('Remarks'),
            Repeater::make('qualifications')
                ->label('Ratings & Endorsements')
                ->relationship()
                ->schema([
                    Select::make('category')
                        ->label('Category')
                        ->options(PilotQualificationCategory::options())
                        ->native(false)
                        ->required(),
                    TextInput::make('code')
                        ->label('Code')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('description')
                        ->label('Description')
                        ->maxLength(255),
                    DatePicker::make('expiry_date')
                        ->label('Expiry Date')
                        ->native(false)
                        ->displayFormat('F j, Y'),
                    Textarea::make('remarks')
                        ->label('Remarks')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->default([])
                ->addActionLabel('Add Qualification')
                ->reorderable(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('User')->sortable()->searchable(),
            TextColumn::make('license_type')->label('Licence Type')->sortable(),
            TextColumn::make('license_number')->label('License Number')->sortable(),
            TextColumn::make('qualifications_count')->counts('qualifications')->label('Qualifications'),
            TextColumn::make('license_expiry_date')->label('License Expiry')->sortable(),
            TextColumn::make('medical_expiry_date')->label('Medical Expiry')->sortable(),
            TextColumn::make('user.operator.name')->label('Operator')->sortable(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPilotProfiles::route('/'),
            'create' => CreatePilotProfile::route('/create'),
            'edit' => EditPilotProfile::route('/{record}/edit'),
        ];
    }
}
