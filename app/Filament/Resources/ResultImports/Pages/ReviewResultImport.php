<?php

namespace App\Filament\Resources\ResultImports\Pages;

use App\Filament\Resources\ResultImports\ResultImportResource;
use App\Import\ImportRunner;
use App\Import\ImportStatus;
use App\Import\ImportSummary;
use App\Import\ImportWriter;
use App\Import\MatchConfidence;
use App\Models\Fencer;
use App\Models\ResultImport;
use App\Models\ResultImportRow;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * The step this whole rebuild exists for: going through the suggestions with somebody.
 *
 * Every row arrives carrying what the matcher believes - the fencer it found, the club, and what
 * it means to do with them. Reviewing is then reading down a filled column and correcting what is
 * wrong, which is a different job from answering the same question once per result.
 *
 * The corrections happen in the table itself. A modal per row is an honest way to ask a question
 * and a miserable way to ask forty.
 */
class ReviewResultImport extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    protected static string $resource = ResultImportResource::class;

    protected string $view = 'filament.resources.result-imports.pages.review';

    protected static ?string $title = 'Zeilen prüfen';

    /** The bands that mean "found something similar" rather than "found it". */
    private const GUESSES = [MatchConfidence::Likely, MatchConfidence::Unsure];

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
    }

    public function getSubheading(): ?string
    {
        $import = $this->getRecord();

        if (!$import->status->isOpen()) {
            return 'Übernommen am ' . $import->applied_at?->format('d.m.Y H:i')
                . '. Ein Import wird nicht erneut geschrieben — zum Korrigieren erst zurücknehmen.';
        }

        $total = $import->rows()->count();
        $open = $import->openRows();
        $uncertain = $import->uncertainRows();

        $parts = [$total . ' Zeilen'];

        if ($open > 0) {
            $parts[] = "{$open} ohne Entscheidung — die halten den Import auf";
        }

        if ($uncertain > 0) {
            $parts[] = "{$uncertain} beruhen auf einem ungefähren Treffer";
        }

        if ($open === 0 && $uncertain === 0) {
            $parts[] = 'alles eindeutig zugeordnet';
        }

        return implode(', ', $parts) . '.';
    }

    private function editable(): bool
    {
        return $this->getRecord()->status->isOpen();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apply')
                ->label('Übernehmen')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->editable())
                ->disabled(fn (): bool => $this->getRecord()->openRows() > 0)
                ->requiresConfirmation()
                ->modalHeading('Ergebnisse übernehmen?')
                ->modalDescription(function (): string {
                    $uncertain = $this->getRecord()->uncertainRows();

                    $text = 'Je Datei entsteht ein Turnier, darunter die Ergebnisse. '
                        . 'Zurücknehmen geht danach, solange niemand die Ergebnisse von Hand '
                        . 'bearbeitet hat.';

                    // Named rather than left to be noticed. Accepting a whole field at once is
                    // the point of pre-filling it; doing so without being told how much of it is
                    // a guess is not.
                    return $uncertain > 0
                        ? $text . " {$uncertain} Zeilen beruhen dabei auf einem ungefähren Treffer."
                        : $text;
                })
                ->action(fn () => $this->apply(dryRun: false)),
            Action::make('dryRun')
                ->label('Probelauf')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->visible(fn (): bool => $this->editable())
                ->disabled(fn (): bool => $this->getRecord()->openRows() > 0)
                ->action(fn () => $this->apply(dryRun: true)),
            // Only after the fact, and only here, where whoever applied it is standing. The list
            // does not offer it: taking an import back is not something to reach for in passing.
            Action::make('withdrawDryRun')
                ->label('Rücknahme prüfen')
                ->icon('heroicon-o-beaker')
                ->color('gray')
                ->visible(fn (): bool => $this->getRecord()->status === ImportStatus::Applied)
                ->action(fn () => $this->withdraw(dryRun: true)),
            Action::make('withdraw')
                ->label('Import zurücknehmen')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('danger')
                ->visible(fn (): bool => $this->getRecord()->status === ImportStatus::Applied)
                ->requiresConfirmation()
                ->modalHeading('Diesen Import zurücknehmen?')
                ->modalDescription('Die Ergebnisse dieses Imports werden entfernt, und ein Turnier, '
                    . 'das dadurch leer zurückbleibt, ebenfalls. Angelegte Fechter und Vereine '
                    . 'bleiben bestehen. Danach steht der Import wieder in der Prüfung und lässt '
                    . 'sich erneut übernehmen.')
                ->modalSubmitActionLabel('Zurücknehmen')
                ->action(fn () => $this->withdraw(dryRun: false)),
            Action::make('back')
                ->label('Zur Zuordnung')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->editable())
                ->url(fn (): string => static::getResource()::getUrl('edit', ['record' => $this->getRecord()])),
        ];
    }

    private function withdraw(bool $dryRun): void
    {
        try {
            $summary = (new ImportRunner())->withdraw($this->getRecord(), $dryRun);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Nicht zurückgenommen')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $lines = [$summary->results . ' Ergebnisse entfernt'];

        if ($summary->tournaments !== []) {
            $lines[] = count($summary->tournaments) . ' Turnier(e) mit entfernt, weil leer: '
                . implode(', ', $summary->tournaments);
        }

        // Named rather than counted: these are the ones somebody may want to clear away, and a
        // number alone would not tell them which.
        foreach ([
            'Fechter ohne Ergebnis' => $summary->orphanedFencers,
            'Vereine ohne Ergebnis' => $summary->orphanedClubs,
        ] as $heading => $leftovers) {
            if ($leftovers !== []) {
                $lines[] = $heading . ': ' . implode(', ', $leftovers);
            }
        }

        Notification::make()
            ->title($dryRun ? 'Probelauf - nichts entfernt' : 'Zurückgenommen')
            ->body(implode(' · ', $lines))
            ->color($dryRun ? 'warning' : 'success')
            ->persistent()
            ->send();

        if (!$dryRun) {
            $this->redirect(static::getResource()::getUrl('review', ['record' => $this->getRecord()]));
        }
    }

    private function apply(bool $dryRun): void
    {
        try {
            $summary = (new ImportRunner())->apply($this->getRecord(), $dryRun);
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Nicht übernommen')
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title($dryRun ? 'Probelauf - nichts geschrieben' : 'Übernommen')
            ->body($this->summarise($summary))
            ->color($dryRun ? 'warning' : 'success')
            ->persistent()
            ->send();

        $this->redirect(static::getResource()::getUrl('review', ['record' => $this->getRecord()]));
    }

    private function summarise(ImportSummary $summary): string
    {
        $lines = [$summary->results . ' Ergebnisse'];

        if ($summary->fencers !== []) {
            $lines[] = count($summary->fencers) . ' Fechter neu angelegt';
        }

        if ($summary->clubs !== []) {
            $lines[] = count($summary->clubs) . ' Vereine neu angelegt';
        }

        if ($summary->aliases !== []) {
            $lines[] = count($summary->aliases) . ' Schreibweisen gemerkt';
        }

        if ($summary->skipped > 0) {
            $lines[] = $summary->skipped . ' Zeilen übersprungen';
        }

        if ($summary->duplicates > 0) {
            $lines[] = $summary->duplicates . ' Zeilen hatten das Ergebnis schon';
        }

        $text = implode(', ', $lines) . '.';

        // Spelled out rather than counted. A number would say that something happened without
        // saying what, and this is the one line here that asks somebody to go and look.
        foreach ($summary->aliasConflicts as $conflict) {
            $text .= "\n" . $conflict;
        }

        return $text;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => ResultImportRow::query()
                ->whereIn('result_import_sheet_id', $this->getRecord()->sheets()->select('id'))
                ->with(['sheet', 'fencer.group', 'group']))
            ->defaultSort('sheet_row')
            ->paginated([25, 50, 100, 'all'])
            ->defaultPaginationPageOption(50)
            ->columns([
                TextColumn::make('sheet.original_name')
                    ->label('Datei')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('sheet_row')
                    ->label('Zeile')
                    ->sortable(),
                TextColumn::make('placement')
                    ->label('Platz'),
                TextColumn::make('name')
                    ->label('Laut Datei')
                    ->description(fn (ResultImportRow $record): ?string => $record->club)
                    ->searchable(['name', 'club'])
                    ->wrap(),

                // The three editable columns. Changing one writes it straight away; there is no
                // save button to forget. The fencer and what is to be done with them sit next to
                // each other, because the action decides the fencer and nothing else.
                SelectColumn::make('fencer_id')
                    ->label('Fechter zuordnen')
                    ->optionsRelationship(
                        'fencer',
                        'last_name',
                        // An anonymised record has no identity left to attach a result to, and
                        // its placeholder name stands for nobody in particular.
                        fn (Builder $query) => $query->whereNull('anonymized_at'),
                    )
                    ->getOptionLabelFromRecordUsing(fn (Fencer $record): string => $record->display_name_with_group)
                    ->searchableOptions(['first_name', 'last_name'])
                    ->optionsLimit(20)
                    ->placeholder('Niemand zugeordnet')
                    ->disabled(fn (): bool => !$this->editable())
                    // Picking a person and leaving the row on "neu anlegen" would create a second
                    // record for somebody who is on the screen. The two belong together.
                    ->afterStateUpdated(fn (ResultImportRow $record, $state) => $record->update([
                        'action' => $state ? 'use' : 'create',
                    ])),
                SelectColumn::make('action')
                    ->label('Damit tun')
                    ->options(ImportWriter::ACTION_LABELS)
                    ->placeholder('Noch offen')
                    ->disabled(fn (): bool => !$this->editable()),
                TextColumn::make('fencer_quality')
                    ->label('Güte Fechter')
                    ->state(fn (ResultImportRow $record): string => self::quality(
                        $record->fencer_confidence,
                        $record->fencer_score,
                    ))
                    ->color(fn (ResultImportRow $record): string => self::colour($record->fencer_confidence)),

                // The club has no action of its own, and does not need one: a club that is picked
                // is used, and an empty field means the file's spelling becomes a new club. The
                // placeholder is where that is said.
                SelectColumn::make('group_id')
                    ->label('Verein zuordnen')
                    ->optionsRelationship('group', 'name')
                    ->searchableOptions()
                    ->optionsLimit(20)
                    ->placeholder('Neu anlegen aus der Datei')
                    ->disabled(fn (): bool => !$this->editable()),
                TextColumn::make('group_quality')
                    ->label('Güte Verein')
                    ->state(fn (ResultImportRow $record): string => self::quality(
                        $record->group_confidence,
                        $record->group_score,
                    ))
                    ->color(fn (ResultImportRow $record): string => self::colour($record->group_confidence)),
                TextColumn::make('note')
                    ->label('Hinweis')
                    ->placeholder('—')
                    ->color('warning')
                    ->wrap()
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('offen')
                    ->label('Nur ohne Entscheidung')
                    // Where the matcher would not commit: a placement it cannot read, or a match
                    // too weak to act on unseen. These hold the import up.
                    ->query(fn (Builder $query): Builder => $query->where(
                        fn (Builder $inner) => $inner->whereNull('action')->orWhere('action', ''),
                    )),
                Filter::make('ungefaehr')
                    ->label('Nur ungefähre Treffer')
                    // Filled in, but on a likeness rather than a certainty. Worth a second look
                    // without being worth stopping for.
                    ->query(fn (Builder $query): Builder => $query
                        ->where('action', '!=', 'skip')
                        ->where(fn (Builder $inner) => $inner
                            ->where('fencer_confidence', MatchConfidence::Likely->value)
                            ->orWhere('group_confidence', MatchConfidence::Likely->value))),
                SelectFilter::make('action')
                    ->label('Aktion')
                    ->options(ImportWriter::ACTION_LABELS),
                SelectFilter::make('result_import_sheet_id')
                    ->label('Datei')
                    ->options(fn (): array => $this->getRecord()->sheets()->pluck('original_name', 'id')->all()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::decide('bulkUse', 'use', 'Zugeordnete Fechter verwenden'),
                    self::decide('bulkCreate', 'create', 'Fechter neu anlegen'),
                    self::decide('bulkSkip', 'skip', 'Zeilen überspringen'),
                ])->label('Für die Auswahl'),
            ])
            ->emptyStateHeading('Keine Zeilen')
            ->emptyStateDescription('Erst abgleichen, dann steht hier je Ergebnis eine Zeile.');
    }

    /**
     * Setting the same answer on a selection.
     *
     * "use" is refused where nothing was found, because it would fail at the write with a message
     * about a row nobody would remember selecting.
     */
    private static function decide(string $name, string $action, string $label): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($action === 'skip' ? 'heroicon-o-minus-circle' : 'heroicon-o-check')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records) use ($action): void {
                $refused = 0;

                foreach ($records as $record) {
                    if ($action === 'use' && $record->fencer_id === null) {
                        $refused++;
                        continue;
                    }

                    // Whether a fencer is created or reused is decided by whether one is
                    // assigned, not by the word in this column. Asking for a new record while
                    // leaving the assignment in place would quietly reuse the assigned one, and
                    // the row would say the opposite of what it did.
                    $record->update([
                        'action'    => $action,
                        'fencer_id' => $action === 'create' ? null : $record->fencer_id,
                    ]);
                }

                if ($refused > 0) {
                    Notification::make()
                        ->title("{$refused} Zeilen ohne Vorschlag übergangen")
                        ->body('Für sie gibt es keinen Fechter zu verwenden. Sie brauchen "Neu anlegen" '
                            . 'oder eine Zuordnung von Hand.')
                        ->warning()
                        ->send();
                }
            });
    }

    private static function quality(?MatchConfidence $confidence, ?float $score): string
    {
        if ($confidence === null) {
            return '—';
        }

        return in_array($confidence, self::GUESSES, true)
            ? sprintf('%s (%.2f)', $confidence->label(), $score ?? 0)
            : $confidence->label();
    }

    private static function colour(?MatchConfidence $confidence): string
    {
        return match ($confidence) {
            MatchConfidence::Exact, MatchConfidence::Alias => 'success',
            MatchConfidence::Likely                        => 'info',
            MatchConfidence::Unsure                        => 'warning',
            default                                        => 'gray',
        };
    }

    /** Narrowed from the trait so the closures above do not have to keep asserting the type. */
    public function getRecord(): ResultImport
    {
        abort_unless($this->record instanceof ResultImport, 404);

        return $this->record;
    }
}
