<?php

namespace App\Filament\Resources\Operators;

use App\Filament\Resources\Operators\Pages\CreateOperator;
use App\Filament\Resources\Operators\Pages\EditOperator;
use App\Filament\Resources\Operators\Pages\ListOperators;
use App\Models\Operator;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use BackedEnum;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class OperatorResource extends Resource
{
    protected static ?string $model = Operator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice;

    protected static ?string $navigationLabel = 'Operators';

    protected static string|UnitEnum|null $navigationGroup = 'Users & Organizations';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            TextInput::make('name')->required()->label('Name'),
            TextInput::make('icao_code')->label('ICAO Code'),
            TextInput::make('certificate_number')->label('Certificate Number'),
            TextInput::make('address')->label('Address'),
            TextInput::make('contact_number')->label('Contact Number'),
            TextInput::make('email')->email()->label('Email'),
            Textarea::make('remarks')->label('Remarks'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->sortable()->searchable(),
            TextColumn::make('icao_code')->label('ICAO Code')->sortable(),
            TextColumn::make('certificate_number')->label('Certificate')->sortable(),
            TextColumn::make('email')->sortable()->searchable(),
            TextColumn::make('contact_number')->label('Contact Number'),
        ])->filters([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOperators::route('/'),
            'create' => CreateOperator::route('/create'),
            'edit' => EditOperator::route('/{record}/edit'),
        ];
    }
}
