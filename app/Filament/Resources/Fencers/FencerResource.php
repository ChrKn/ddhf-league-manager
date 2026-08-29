<?php

namespace App\Filament\Resources\Fencers;

use App\Filament\Resources\Fencers\Pages\CreateFencer;
use App\Filament\Resources\Fencers\Pages\EditFencer;
use App\Filament\Resources\Fencers\Pages\ListFencers;
use App\Filament\Resources\Fencers\Schemas\FencerForm;
use App\Filament\Resources\Fencers\Tables\FencersTable;
use App\Models\Fencer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class FencerResource extends Resource
{
    protected static ?string $model = Fencer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static ?string $navigationLabel = 'Fechter';
    protected static ?int $navigationSort = 200;
    protected static ?string $slug = 'fechter';

    protected static ?string $modelLabel = 'Fechter';
    protected static ?string $pluralModelLabel = 'Fechter';

    protected static ?string $recordTitleAttribute = 'Fechter';

    public static function form(Schema $schema): Schema
    {
        return FencerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FencersTable::configure($table);
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
            'index' => ListFencers::route('/'),
            'create' => CreateFencer::route('/create'),
            'edit' => EditFencer::route('/{record}/edit'),
        ];
    }
}
