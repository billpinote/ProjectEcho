<?php

namespace App\Filament\Resources\DispatchProfiles;

use App\Filament\Resources\DispatchProfiles\Pages\CreateDispatchProfile;
use App\Filament\Resources\DispatchProfiles\Pages\EditDispatchProfile;
use App\Filament\Resources\DispatchProfiles\Pages\ListDispatchProfiles;
use App\Models\DispatchProfile;
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

class DispatchProfileResource extends Resource
{
    protected static ?string $model = DispatchProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Dispatch Profiles';

    protected static string|UnitEnum|null $navigationGroup = 'Users & Organizations';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->required(),
            TextInput::make('dispatcher_license_number')->label('Dispatcher License Number'),
            TextInput::make('dispatcher_certificate')->label('Dispatcher Certificate'),
            TextInput::make('department')->label('Department'),
            TextInput::make('position')->label('Position'),
            TextInput::make('office_phone')->label('Office Phone'),
            TextInput::make('mobile_number')->label('Mobile Number'),
            TextInput::make('shift')->label('Shift'),
            Textarea::make('remarks')->label('Remarks'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('User')->sortable()->searchable(),
            TextColumn::make('dispatcher_license_number')->label('License Number')->sortable(),
            TextColumn::make('dispatcher_certificate')->label('Certificate')->sortable(),
            TextColumn::make('department')->sortable(),
            TextColumn::make('position')->sortable(),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDispatchProfiles::route('/'),
            'create' => CreateDispatchProfile::route('/create'),
            'edit' => EditDispatchProfile::route('/{record}/edit'),
        ];
    }
}
