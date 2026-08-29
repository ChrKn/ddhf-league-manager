<?php

namespace App\Filament\Resources\ResultImports\Pages;

use App\Filament\Resources\ResultImports\ResultImportResource;
use App\Import\Formats\SheetFormats;
use App\Import\ImportStatus;
use App\Models\ResultImport;
use App\Models\ResultImportSheet;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CreateResultImport extends CreateRecord
{
    protected static string $resource = ResultImportResource::class;

    protected static ?string $title = 'Ergebnisse hochladen';

    /**
     * The files are read here rather than at the review step.
     *
     * A workbook that is not the template, or one holding two tournaments, is a mistake worth
     * hearing about while the upload dialog is still open. Waiting until the review would mean
     * choosing a season and an event for a file that was never going to be readable.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $paths = $data['uploads'] ?? [];
        $names = $data['upload_names'] ?? [];
        $format = SheetFormats::byKey($data['format']);

        unset($data['uploads'], $data['upload_names']);

        $sheets = [];

        foreach ($paths as $path) {
            // storeFileNamesIn keys the names it kept by the stored path, not by the key the
            // upload state uses. Looking them up by the latter silently found nothing, and every
            // file was recorded under the name the system gave it - the one nobody recognises.
            $name = $names[$path] ?? basename($path);

            try {
                $rows = $format::read(Storage::disk('local')->path($path));
            } catch (\RuntimeException $exception) {
                $this->discard($paths);

                throw ValidationException::withMessages([
                    'data.uploads' => "\"{$name}\": " . $exception->getMessage(),
                ]);
            }

            if ($rows === []) {
                $this->discard($paths);

                throw ValidationException::withMessages([
                    'data.uploads' => "\"{$name}\": die Datei enthält keine Ergebnisse.",
                ]);
            }

            if (isset($sheets[$name])) {
                // Their rows would otherwise share one mapping and land in the same tournament.
                $this->discard($paths);

                throw ValidationException::withMessages([
                    'data.uploads' => "Zwei Dateien tragen den Namen \"{$name}\".",
                ]);
            }

            $sheets[$name] = [
                'original_name' => $name,
                'stored_path'   => $path,
                'participants'  => (int) $rows[0]['participants'],
                'format'        => $rows[0]['system'] !== '' ? $rows[0]['system'] : null,
            ];
        }

        $import = ResultImport::create($data + ['status' => ImportStatus::Draft, 'user_id' => auth()->id()]);

        foreach ($sheets as $sheet) {
            ResultImportSheet::create($sheet + ['result_import_id' => $import->id]);
        }

        Notification::make()
            ->title(count($sheets) . ' Datei(en) gelesen')
            ->body('Jetzt je Datei Rangliste, Jahr und Veranstaltung wählen.')
            ->success()
            ->send();

        return $import;
    }

    /** Nothing was created, so nothing may be left lying in the file store either. */
    private function discard(array $paths): void
    {
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
