<?php

namespace App\Filament\Resources\ResultImports\Schemas;

use App\Filament\Resources\ResultImports\ImportMessages;
use App\Import\Formats\SheetFormats;
use App\Models\Event;
use App\Models\ResultImport;
use App\Models\ResultImportSheet;
use App\Models\Season;
use App\Models\TournamentFormat;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

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

                self::upload()
                    ->required()
                    ->visibleOn('create'),

                Toggle::make('remember_aliases')
                    ->label('Korrigierte Vereinsschreibweisen merken')
                    ->default(true)
                    ->helperText('Wird ein Verein von Hand einem Datensatz zugeordnet, wird die Schreibweise '
                        . 'der Datei als Alias gespeichert und beim nächsten Import von allein erkannt.'),

                Repeater::make('sheets')
                    ->label('Turniere und ihre Rangliste')
                    ->relationship()
                    ->hiddenOn('create')
                    ->addable(false)
                    // Taking a tournament out is a correction while the import is open: a sheet
                    // somebody did not mean to send, or one to bring in later on its own.
                    ->deletable(fn (?ResultImport $record): bool => (bool) $record?->status->isOpen())
                    ->reorderable(false)
                    ->itemLabel(fn (array $state): ?string => $state['original_name'] ?? null)
                    ->columns(2)
                    ->schema([
                        Placeholder::make('gelesen')
                            ->label('Aus der Datei')
                            ->columnSpanFull()
                            ->content(fn (?ResultImportSheet $record): HtmlString|string => self::whatTheFileSaid($record)),
                        Select::make('season_id')
                            ->label('Rangliste und Jahr')
                            // Built as a list rather than pointed at the relationship: seasons are
                            // named by an accessor, so a search over the relationship looked at the
                            // id column and found nothing whatever was typed.
                            ->options(fn (): array => self::seasons())
                            ->searchable()
                            ->required(),
                        Select::make('tournament_format_id')
                            ->label('Turniersystem')
                            ->options(fn (): array => TournamentFormat::options())
                            ->required()
                            ->helperText('Steht in der Vorlage nur als Text in der Titelzeile. '
                                . 'Fehlt eins, lässt es sich unter „Turniersysteme“ anlegen.'),
                        TextInput::make('participants')
                            ->label('Teilnehmer')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->helperText('Daran hängen die Punkte jedes Ergebnisses. Vorgeschlagen ist, was die '
                                . 'Datei als Turniergröße nennt.'),
                        Toggle::make('partial')
                            ->label('Feld nur teilweise überliefert')
                            ->helperText('Nur ankreuzen, wenn die Datei weniger Ergebnisse listet, als das Feld '
                                . 'groß war — etwa bei einer Aufzeichnung, die alle ohne Platzierung weggelassen '
                                . 'hat. Die Teilnehmerzahl muss trotzdem stimmen, denn daran hängen die Punkte.'),
                    ])
                    ->helperText('Das Turnier entsteht beim Übernehmen aus Rangliste und Veranstaltung, mit dem '
                        . 'Turniersystem und der Teilnehmerzahl von hier. Eine fehlerhafte Datei lässt sich oben '
                        . 'mit „Datei ersetzen“ austauschen, ohne den Import neu anzulegen.'),
            ]);
    }

    /**
     * The upload field, shared by the first upload and by the actions that add or replace a file
     * later - so every way in accepts the same files.
     */
    public static function upload(bool $multiple = true): FileUpload
    {
        return FileUpload::make('uploads')
            ->label($multiple ? 'Ergebnisdateien' : 'Ergebnisdatei')
            ->multiple($multiple)
            // Outside the webroot, under a name the system chooses. The name it was sent under is
            // kept separately, because that is the only thing that still ties a row back to the
            // file a person recognises.
            ->disk('local')
            ->directory('ergebnisimport')
            ->storeFileNamesIn('upload_names')
            // The extension and the sent type are both easy to get wrong and easy to fake, so this
            // only keeps the obviously unintended out. What the file actually is gets decided by
            // reading it.
            ->acceptedFileTypes([
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/vnd.ms-excel',
                'text/csv',
                'text/plain',
                'application/csv',
            ])
            ->maxSize(5120)
            ->helperText('Eine Datei je Turnier oder eine Mappe mit einem Blatt je Turnier, als xlsx oder CSV. '
                . 'Ein Blatt „Beispiel“ aus der Vorlage wird übersprungen. Alle Turniere einer Veranstaltung '
                . 'gehören in einen Import — wer mehrere gefochten hat, steht in mehreren Listen, und nur '
                . 'innerhalb eines Imports wird diese Person einmal angelegt statt mehrfach.');
    }

    /** What reading the file found, next to the fields where it is settled. */
    private static function whatTheFileSaid(?ResultImportSheet $sheet): HtmlString|string
    {
        if (!$sheet) {
            return '—';
        }

        $facts = sprintf(
            '%s — %s Ergebnisse, Turniergröße laut Datei: %s',
            $sheet->worksheet === null ? $sheet->original_name : "Blatt „{$sheet->worksheet}“ aus {$sheet->fileName()}",
            $sheet->row_count ?? '?',
            $sheet->stated_participants ?? 'keine Angabe',
        );

        $notes = $sheet->notes ?? [];

        // Said here as well as on the field: the rows are what the field size is checked against
        // when the files are matched, and a mismatch is far cheaper to see now.
        if ($sheet->row_count !== null && $sheet->participants !== null && !$sheet->partial
            && $sheet->row_count !== $sheet->participants) {
            $notes[] = "Die Datei listet {$sheet->row_count} Ergebnisse, eingetragen sind {$sheet->participants} Teilnehmer.";
        }

        return $notes === []
            ? $facts
            : new HtmlString(e($facts) . ImportMessages::list($notes)->toHtml());
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
