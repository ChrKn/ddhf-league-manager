<?php

namespace App\Filament\Resources\ResultImports;

use App\Filament\Resources\ResultImports\Pages\CreateResultImport;
use App\Filament\Resources\ResultImports\Pages\EditResultImport;
use App\Filament\Resources\ResultImports\Pages\ListResultImports;
use App\Filament\Resources\ResultImports\Pages\ReviewResultImport;
use App\Filament\Resources\ResultImports\Schemas\ResultImportForm;
use App\Filament\Resources\ResultImports\Tables\ResultImportsTable;
use App\Models\ResultImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Taking in the results of an event, from the panel rather than a terminal.
 *
 * An import brings in what is new: it creates one tournament per file and writes the results
 * underneath it. It never changes a result that is already there. Correcting something already
 * imported is a different job and will get its own form, so that whoever is at the screen can
 * tell from the screen which of the two they are doing.
 */
class ResultImportResource extends Resource
{
    protected static ?string $model = ResultImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static ?string $navigationLabel = 'Ergebnisimport';

    protected static ?int $navigationSort = 650;

    protected static ?string $slug = 'ergebnisimport';

    protected static ?string $modelLabel = 'Import';

    protected static ?string $pluralModelLabel = 'Importe';

    public static function form(Schema $schema): Schema
    {
        return ResultImportForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResultImportsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index'  => ListResultImports::route('/'),
            'create' => CreateResultImport::route('/create'),
            'edit'   => EditResultImport::route('/{record}/zuordnen'),
            'review' => ReviewResultImport::route('/{record}/pruefen'),
        ];
    }
}
