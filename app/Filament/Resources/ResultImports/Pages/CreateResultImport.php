<?php

namespace App\Filament\Resources\ResultImports\Pages;

use App\Filament\Resources\ResultImports\ImportMessages;
use App\Filament\Resources\ResultImports\ResultImportResource;
use App\Import\Formats\SheetFormats;
use App\Import\ImportStatus;
use App\Import\SheetIntake;
use App\Models\ResultImport;
use App\Models\ResultImportSheet;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateResultImport extends CreateRecord
{
    protected static string $resource = ResultImportResource::class;

    protected static ?string $title = 'Ergebnisse hochladen';

    /**
     * The files are read here rather than at the review step.
     *
     * A workbook that is not the template is a mistake worth hearing about while the upload
     * dialog is still open. Waiting until the review would mean choosing a season and an event
     * for a file that was never going to be readable. Everything wrong with every file is said at
     * once, so the next upload is the last one.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $names = $data['upload_names'] ?? [];
        $uploads = [];

        foreach ($data['uploads'] ?? [] as $path) {
            // storeFileNamesIn keys the names it kept by the stored path, not by the key the
            // upload state uses. Looking them up by the latter silently found nothing, and every
            // file was recorded under the name the system gave it - the one nobody recognises.
            $uploads[$path] = $names[$path] ?? basename($path);
        }

        unset($data['uploads'], $data['upload_names']);

        $intake = SheetIntake::read($uploads, SheetFormats::byKey($data['format']));

        if (!$intake->passes()) {
            SheetIntake::discard($uploads);

            ImportMessages::problems('Die Dateien lassen sich so nicht übernehmen', $intake->problems);

            throw ValidationException::withMessages([
                'data.uploads' => count($intake->problems) === 1
                    ? $intake->problems[0]
                    : count($intake->problems) . ' Probleme in den Dateien, alle in der Meldung oben rechts.',
            ]);
        }

        $import = ResultImport::create($data + ['status' => ImportStatus::Draft, 'user_id' => auth()->id()]);

        foreach ($intake->sheets as $sheet) {
            ResultImportSheet::create($sheet + ['result_import_id' => $import->id]);
        }

        Notification::make()
            ->title(count($intake->sheets) . ' Turnier(e) aus ' . count($uploads) . ' Datei(en) gelesen')
            ->body('Jetzt je Turnier Rangliste, Turniersystem und Zahl der Teilnehmenden festlegen.')
            ->success()
            ->send();

        ImportMessages::notes('Beim Zuordnen bitte ansehen', $intake->notes);

        return $import;
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
