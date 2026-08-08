<?php

namespace App\Filament\Shared\Resources\AvsecProfiles;

use App\Filament\Shared\Resources\AvsecProfiles\Pages\CreateAvsecProfile;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\EditAvsecProfile;
use App\Filament\Shared\Resources\AvsecProfiles\Pages\ListAvsecProfiles;
use App\Models\AvsecProfile;
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

class AvsecProfileResource extends Resource
{
    protected static ?string $model = AvsecProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $navigationLabel = 'AVSEC Profiles';

    protected static string|UnitEnum|null $navigationGroup = 'Users & Organizations';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Select::make('user_id')
                ->label('User')
                ->relationship('user', 'name')
                ->searchable()
                ->required(),
            TextInput::make('security_certification')->label('Security Certification'),
            TextInput::make('certification_expiry')->label('Certification Expiry'),
            TextInput::make('security_clearance_level')->label('Security Clearance Level'),
            TextInput::make('position')->label('Position'),
            Textarea::make('remarks')->label('Remarks'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('user.name')->label('User')->sortable()->searchable(),
            TextColumn::make('security_certification')->label('Certification')->sortable(),
            TextColumn::make('certification_expiry')->label('Expiry')->sortable(),
            TextColumn::make('security_clearance_level')->label('Clearance Level')->sortable(),
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
            'index' => ListAvsecProfiles::route('/'),
            'create' => CreateAvsecProfile::route('/create'),
            'edit' => EditAvsecProfile::route('/{record}/edit'),
        ];
    }
}
