<?php

namespace App\Filament\Resources\ResultImports\Schemas;

use App\Import\Formats\SheetFormats;
use App\Models\Event;
use App\Models\Season;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class ResultImportForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The event belongs to the import, not to each file: what arrives together is the
                // longsword, the sabre and the rapier of one weekend. Asking per file meant
                // giving the same answer four times over.
                Select::make('event_id')
                    ->label('Veranstaltung')
                    ->options(fn (): array => self::events())
                    ->searchable()
                    ->required()
                    ->helperText('Alle Dateien dieses Imports gehören zu dieser einen Veranstaltung. '
                        . 'Für eine zweite Veranstaltung einen zweiten Import anlegen.'),

                Select::make('format')
                    ->label('Dateiformat')
                    ->options(SheetFormats::options())
                    ->default(array_key_first(SheetFormats::options()))
                    ->required()
                    ->disabledOn('edit')
                    ->helperText('Heute gibt es nur die DDHF-Vorlage, als Excel-Mappe oder als CSV. '
                        . 'Ergebnisse in einem anderen Aufbau müssen vorher in die Vorlage kopiert werden.'),

                FileUpload::make('uploads')
                    ->label('Ergebnisdateien')
                    ->multiple()
                    ->required()
                    ->visibleOn('create')
                    // Outside the webroot, under a name the system chooses. The name it was sent
                    // under is kept separately, because that is the only thing that still ties a
                    // row back to the file a person recognises.
                    ->disk('local')
                    ->directory('ergebnisimport')
                    ->storeFileNamesIn('upload_names')
                    // The extension and the sent type are both easy to get wrong and easy to
                    // fake, so this only keeps the obviously unintended out. What the file
                    // actually is gets decided by reading it.
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                        'text/plain',
                        'application/csv',
                    ])
                    ->maxSize(5120)
                    ->helperText('Eine Datei je Turnier, als xlsx oder CSV. Alle Dateien einer Veranstaltung '
                        . 'gehören in einen Import — wer mehrere Turniere gefochten hat, steht in mehreren '
                        . 'Dateien, und nur innerhalb eines Imports wird diese Person einmal angelegt '
                        . 'statt mehrfach.'),

                Toggle::make('remember_aliases')
                    ->label('Korrigierte Vereinsschreibweisen merken')
                    ->default(true)
                    ->helperText('Wird ein Verein von Hand einem Datensatz zugeordnet, wird die Schreibweise '
                        . 'der Datei als Alias gespeichert und beim nächsten Import von allein erkannt.'),

                Repeater::make('sheets')
                    ->label('Dateien und ihre Rangliste')
                    ->relationship()
                    ->hiddenOn('create')
                    ->addable(false)
                    ->deletable(false)
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => $state['original_name'] ?? null)
                    ->schema([
                        Placeholder::make('gelesen')
                            ->label('Datei')
                            ->content(fn (Get $get): string => sprintf(
                                '%s — %s Teilnehmer, %s',
                                $get('original_name') ?: 'Unbenannt',
                                $get('participants') ?: '?',
                                $get('format') ?: 'kein Turniersystem angegeben',
                            )),
                        Select::make('season_id')
                            ->label('Rangliste und Jahr')
                            // Built as a list rather than pointed at the relationship: seasons are
                            // named by an accessor, so a search over the relationship looked at the
                            // id column and found nothing whatever was typed.
                            ->options(fn (): array => self::seasons())
                            ->searchable()
                            ->required(),
                        Toggle::make('partial')
                            ->label('Feld nur teilweise überliefert')
                            ->helperText('Nur ankreuzen, wenn die Datei weniger Ergebnisse listet, als das Feld '
                                . 'groß war — etwa bei einer Aufzeichnung, die alle ohne Platzierung weggelassen '
                                . 'hat. Die genannte Turniergröße muss trotzdem stimmen, denn daran hängen die Punkte.'),
                    ])
                    ->helperText('Das Turnier entsteht beim Übernehmen aus Rangliste und Veranstaltung. '
                        . 'Turniergröße und Turniersystem kommen aus der Datei.'),
            ]);
    }

    /**
     * The seasons, newest first and then by weapon.
     *
     * A result sheet on somebody's desk is almost always from this year or the last, so the years
     * count down; within one, the weapon is what the reader is actually looking for.
     *
     * @return array<int, string>
     */
    public static function seasons(): array
    {
        return Season::with('standing.discipline', 'standing.division')
            ->get()
            ->sortBy([
                fn (Season $a, Season $b) => $b->year <=> $a->year,
                fn (Season $a, Season $b) => ($a->standing?->discipline?->name ?? '') <=> ($b->standing?->discipline?->name ?? ''),
                fn (Season $a, Season $b) => ($a->standing?->division?->name ?? '') <=> ($b->standing?->division?->name ?? ''),
            ])
            ->mapWithKeys(fn (Season $season) => [$season->id => $season->display_name])
            ->all();
    }

    /**
     * The events, newest first - for the same reason.
     *
     * @return array<int, string>
     */
    public static function events(): array
    {
        return Event::orderByDesc('start_date')
            ->get()
            ->mapWithKeys(fn (Event $event) => [$event->id => $event->display_name])
            ->all();
    }
}
