<?php

namespace App\Filament\Resources\ScoringMatrices;

use App\Filament\Resources\ScoringMatrices\Pages\CreateScoringMatrix;
use App\Filament\Resources\ScoringMatrices\Pages\EditScoringMatrix;
use App\Filament\Resources\ScoringMatrices\Pages\ListScoringMatrices;
use App\Filament\Resources\ScoringMatrices\Schemas\ScoringMatrixForm;
use App\Filament\Resources\ScoringMatrices\Tables\ScoringMatricesTable;
use App\Models\ScoringMatrix;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ScoringMatrixResource extends Resource
{
    protected static ?string $model = ScoringMatrix::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTableCells;
    protected static ?string $navigationLabel = 'Punkteschlüssel';
    protected static ?int $navigationSort = 1100;
    protected static ?string $slug = 'punkteschluessel';

    protected static ?string $modelLabel = 'Punkteschlüssel';
    protected static ?string $pluralModelLabel = 'Punkteschlüssel';

    protected static ?string $recordTitleAttribute = 'Punkteschlüssel';

    public static function form(Schema $schema): Schema
    {
        return ScoringMatrixForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ScoringMatricesTable::configure($table);
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
            'index' => ListScoringMatrices::route('/'),
            'create' => CreateScoringMatrix::route('/create'),
            'edit' => EditScoringMatrix::route('/{record}/edit'),
        ];
    }
}
