<?php

namespace App\Filament\Resources\ResultImports\Pages;

use App\Filament\Resources\ResultImports\ImportMessages;
use App\Filament\Resources\ResultImports\ResultImportResource;
use App\Filament\Resources\ResultImports\Schemas\ResultImportForm;
use App\Import\ImportFiles;
use App\Import\ImportRunner;
use App\Import\SheetIntake;
use App\Models\ResultImport;
use App\Models\ResultImportSheet;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use RuntimeException;

class EditResultImport extends EditRecord
{
    protected static string $resource = ResultImportResource::class;

    protected static ?string $title = 'Dateien zuordnen';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('plan')
                ->label('Abgleichen')
                ->icon('heroicon-o-magnifying-glass')
                ->color('primary')
                // Planning again throws away decisions already taken, so it says so first.
                ->requiresConfirmation(fn (ResultImport $record): bool => $record->rows()->exists())
                ->modalHeading('Erneut abgleichen?')
                ->modalDescription('Für diesen Import liegen bereits geprüfte Zeilen vor. Ein neuer Abgleich '
                    . 'ersetzt sie, und die bisher getroffenen Entscheidungen gehen verloren.')
                ->action(function (ResultImport $record): void {
                    // Saved first: a season chosen a moment ago and not yet stored would
                    // otherwise be matched against the state before it.
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    $report = (new ImportRunner())->plan($record->refresh());

                    if (!$report->agrees()) {
                        ImportMessages::problems('Der Abgleich steht noch an', $report->problems);
                        ImportMessages::notes('Außerdem', $report->notes);

                        return;
                    }

                    ImportMessages::notes('Zur Kenntnis', $report->notes);

                    Notification::make()
                        ->title('Abgeglichen')
                        ->body($record->rows()->count() . ' Zeilen, davon ' . $record->openRows()
                            . ' ohne Entscheidung.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('review', ['record' => $record]));
                }),
            Action::make('addFile')
                ->label('Datei hinzufügen')
                ->icon('heroicon-o-document-plus')
                ->color('gray')
                ->visible(fn (ResultImport $record): bool => $record->status->isOpen())
                ->modalHeading('Weitere Ergebnisse dieser Veranstaltung')
                ->modalDescription(fn (ResultImport $record): string => self::resetWarning($record))
                ->schema([ResultImportForm::upload()->required()])
                ->action(function (array $data, ResultImport $record): void {
                    $this->takeIn($data, fn (array $uploads) => ImportFiles::add($record, $uploads));
                }),
            Action::make('replaceFile')
                ->label('Datei ersetzen')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->visible(fn (ResultImport $record): bool => $record->status->isOpen())
                ->modalHeading('Datei durch eine korrigierte ersetzen')
                ->modalDescription(fn (ResultImport $record): string => 'Rangliste, Turniersystem und eine von Hand '
                    . 'eingetragene Teilnehmerzahl bleiben für jedes Turnier erhalten, das auch in der neuen Datei '
                    . 'steht. ' . self::resetWarning($record))
                ->schema(fn (ResultImport $record): array => [
                    Select::make('file')
                        ->label('Zu ersetzen')
                        ->options(self::files($record))
                        ->default(count(self::files($record)) === 1 ? array_key_first(self::files($record)) : null)
                        ->required(),
                    ResultImportForm::upload()->maxFiles(1)->label('Korrigierte Datei')->required(),
                ])
                ->action(function (array $data, ResultImport $record): void {
                    $this->takeIn($data, fn (array $uploads) => ImportFiles::replace($record, $data['file'], $uploads));
                }),
            Action::make('review')
                ->label('Zur Prüfung')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('gray')
                ->visible(fn (ResultImport $record): bool => $record->rows()->exists())
                ->url(fn (ResultImport $record): string => static::getResource()::getUrl('review', ['record' => $record])),
            DeleteAction::make(),
        ];
    }

    /**
     * Hands the uploaded files to the import and says what came of it.
     *
     * @param  callable(array<string, string>): SheetIntake  $change
     */
    private function takeIn(array $data, callable $change): void
    {
        $names = $data['upload_names'] ?? [];
        $uploads = [];

        foreach ($data['uploads'] ?? [] as $path) {
            $uploads[$path] = $names[$path] ?? basename($path);
        }

        try {
            $intake = $change($uploads);
        } catch (RuntimeException $exception) {
            ImportMessages::problems('Nicht übernommen', [$exception->getMessage()]);

            return;
        }

        if (!$intake->passes()) {
            ImportMessages::problems('Die Datei lässt sich so nicht übernehmen', $intake->problems);

            return;
        }

        Notification::make()
            ->title(count($intake->sheets) . ' Turnier(e) übernommen')
            ->body('Bitte Rangliste, Turniersystem und Teilnehmerzahl prüfen und neu abgleichen.')
            ->success()
            ->send();

        ImportMessages::notes('Beim Zuordnen bitte ansehen', $intake->notes);

        // The form still shows the sheets as they were before. Read again, so the next save does
        // not write the old state back over the new one.
        $this->getRecord()->refresh();
        $this->fillForm();
    }

    /** @return array<string, string> stored path => the file name a person knows it by */
    private static function files(ResultImport $import): array
    {
        return $import->sheets
            ->unique('stored_path')
            ->mapWithKeys(fn (ResultImportSheet $sheet) => [$sheet->stored_path => $sheet->fileName()])
            ->all();
    }

    private static function resetWarning(ResultImport $import): string
    {
        $warning = 'Nicht gespeicherte Eingaben in der Maske gehen dabei verloren.';

        return $import->rows()->exists()
            ? 'Die bisher geprüften Zeilen werden verworfen, danach muss neu abgeglichen werden. ' . $warning
            : $warning;
    }
}
