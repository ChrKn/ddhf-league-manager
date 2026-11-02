<?php

namespace App\Filament\Resources\TournamentFormats;

use App\Filament\Resources\TournamentFormats\Pages\CreateTournamentFormat;
use App\Filament\Resources\TournamentFormats\Pages\EditTournamentFormat;
use App\Filament\Resources\TournamentFormats\Pages\ListTournamentFormats;
use App\Filament\Resources\TournamentFormats\Schemas\TournamentFormatForm;
use App\Filament\Resources\TournamentFormats\Tables\TournamentFormatsTable;
use App\Models\TournamentFormat;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The tournament systems a tournament can be fenced in, which the import offers to choose from.
 */
class TournamentFormatResource extends Resource
{
    protected static ?string $model = TournamentFormat::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;
    protected static ?string $navigationLabel = 'Turniersysteme';
    protected static ?int $navigationSort = 450;
    protected static ?string $slug = 'turniersysteme';

    protected static ?string $modelLabel = 'Turniersystem';
    protected static ?string $pluralModelLabel = 'Turniersysteme';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TournamentFormatForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TournamentFormatsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListTournamentFormats::route('/'),
            'create' => CreateTournamentFormat::route('/create'),
            'edit'   => EditTournamentFormat::route('/{record}/edit'),
        ];
    }
}
