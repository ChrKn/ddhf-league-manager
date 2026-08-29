<?php

namespace App\Filament\Resources\Groups\RelationManagers;

use App\Import\NameMatcher;
use App\Models\Group;
use App\Models\GroupAlias;
use Closure;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Alternative spellings this club is imported under.
 *
 * @see \App\Import\GroupResolver
 */
class AliasesRelationManager extends RelationManager
{
    protected static string $relationship = 'aliases';

    protected static ?string $title = 'Alternative Schreibweisen';

    protected static ?string $modelLabel = 'Schreibweise';

    protected static ?string $pluralModelLabel = 'Schreibweisen';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('alias')
                ->label('Schreibweise')
                ->helperText('Wie dieser Verein in Ergebnisdateien auftaucht, z. B. "ESK Augsburg".')
                ->required()
                ->unique(ignoreRecord: true)
                // unique() guards the string, the importer looks up the folded form. Without this
                // "ESK-Augsburg e. V." could be added here while "ESK Augsburg" sits on another
                // club, and the older entry would quietly stop resolving anything.
                ->rule(fn (?GroupAlias $record) => function (string $attribute, mixed $value, Closure $fail) use ($record) {
                    $owner = $this->conflictingOwner((string) $value, $record);

                    if ($owner) {
                        $fail("Diese Schreibweise gehört bereits zu \"{$owner->name}\". "
                            . 'Eine Schreibweise kann nur einen Verein bezeichnen.');
                    }
                })
                ->maxLength(255),
        ]);
    }

    /**
     * The club that already holds this spelling, folded the way the importer folds it, when that
     * club is not the one being edited. Null when the spelling is free or already ours.
     */
    private function conflictingOwner(string $alias, ?GroupAlias $record): ?Group
    {
        $key = NameMatcher::normalize($alias);
        $own = $this->getOwnerRecord()->getKey();

        if ($key === '') {
            return null;
        }

        $clash = GroupAlias::with('group')
            ->where('group_id', '!=', $own)
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->get()
            ->first(fn (GroupAlias $other) => NameMatcher::normalize($other->alias) === $key);

        if ($clash?->group) {
            return $clash->group;
        }

        // A club's own name wins over any alias when the importer looks one up, so a spelling
        // that is another club's name would sit here without ever resolving anything.
        return Group::whereKeyNot($own)->get()
            ->first(fn (Group $other) => NameMatcher::normalize($other->name) === $key);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('alias')
            ->columns([
                TextColumn::make('alias')
                    ->label('Schreibweise')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Angelegt')
                    ->dateTime('d.m.Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateHeading('Keine alternativen Schreibweisen')
            ->emptyStateDescription('Beim Import erkannte Schreibweisen können hier hinterlegt werden, damit sie künftig automatisch zugeordnet werden.');
    }
}
