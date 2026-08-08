<?php

namespace App\Filament\Shared\Resources\AtcProfiles;

use App\Filament\Shared\Resources\AtcProfiles\Pages\CreateAtcProfile;
use App\Filament\Shared\Resources\AtcProfiles\Pages\EditAtcProfile;
use App\Filament\Shared\Resources\AtcProfiles\Pages\ListAtcProfiles;
use App\Models\AtcProfile;
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

class AtcProfileResource extends Resource
{
    protected static ?string $model = AtcProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $navigationLabel = 'ATC Profiles';

    protected static string|UnitEnum|null $navigationGroup = 'Users & Organizations';

    protected static ?int $navigationSort = 3;

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
            TextInput::make('wiresign')->label('Wiresign'),
            TextInput::make('facility')->label('Facility'),
            TextInput::make('position')->label('Position'),
            TextInput::make('endorsements')->label('Endorsements'),
            Textarea::make('remarks')->label('Remarks'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('User')->sortable()->searchable(),
            TextColumn::make('wiresign')->sortable(),
            TextColumn::make('facility')->sortable(),
            TextColumn::make('position')->sortable(),
            TextColumn::make('endorsements')->sortable(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAtcProfiles::route('/'),
            'create' => CreateAtcProfile::route('/create'),
            'edit' => EditAtcProfile::route('/{record}/edit'),
        ];
    }
}
