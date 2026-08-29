<?php

namespace App\Filament\Resources\Results;

use App\Filament\Resources\Results\Pages\CreateResult;
use App\Filament\Resources\Results\Pages\EditResult;
use App\Filament\Resources\Results\Pages\ListResults;
use App\Filament\Resources\Results\Schemas\ResultForm;
use App\Filament\Resources\Results\Tables\ResultsTable;
use App\Models\Result;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ResultResource extends Resource
{
    protected static ?string $model = Result::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;
    protected static ?string $navigationLabel = 'Ergebnisse';
    protected static ?int $navigationSort = 600;
    protected static ?string $slug = 'ergebnisse';

    protected static ?string $modelLabel = 'Ergebnis';
    protected static ?string $pluralModelLabel = 'Ergebnisse';

    protected static ?string $recordTitleAttribute = 'Ergebnisse';

    public static function form(Schema $schema): Schema
    {
        return ResultForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ResultsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListResults::route('/'),
            'create' => CreateResult::route('/create'),
            'edit' => EditResult::route('/{record}/edit'),
        ];
    }
}
