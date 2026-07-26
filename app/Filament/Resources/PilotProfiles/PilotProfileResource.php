<?php

namespace App\Filament\Resources\PilotProfiles;

use App\Filament\Resources\PilotProfiles\Pages\CreatePilotProfile;
use App\Filament\Resources\PilotProfiles\Pages\EditPilotProfile;
use App\Filament\Resources\PilotProfiles\Pages\ListPilotProfiles;
use App\Models\PilotProfile;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class PilotProfileResource extends Resource
{
    protected static ?string $model = PilotProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUser;

    protected static ?string $navigationLabel = 'Pilot Profiles';

    protected static string|UnitEnum|null $navigationGroup = 'Users & Organizations';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->required(),
            TextInput::make('license_number')->label('License Number'),
            TextInput::make('ratings')->label('Ratings'),
            TextInput::make('license_expiry_date')->label('License Expiry Date'),
            TextInput::make('medical_expiry_date')->label('Medical Expiry Date'),
            TextInput::make('home_base')->label('Home Base'),
            Textarea::make('remarks')->label('Remarks'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('User')->sortable()->searchable(),
            TextColumn::make('license_number')->label('License Number')->sortable(),
            TextColumn::make('ratings')->sortable(),
            TextColumn::make('license_expiry_date')->label('License Expiry')->sortable(),
            TextColumn::make('medical_expiry_date')->label('Medical Expiry')->sortable(),
            TextColumn::make('home_base')->label('Home Base')->sortable(),
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
