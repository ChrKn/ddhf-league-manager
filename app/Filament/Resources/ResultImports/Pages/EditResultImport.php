<?php

namespace App\Filament\Resources\ResultImports\Pages;

use App\Filament\Resources\ResultImports\ResultImportResource;
use App\Import\ImportRunner;
use App\Models\ResultImport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
                        Notification::make()
                            ->title('Der Abgleich steht noch an')
                            ->body(implode("\n", $report->problems))
                            ->danger()
                            ->persistent()
                            ->send();

                        return;
                    }

                    foreach ($report->notes as $note) {
                        Notification::make()->title($note)->warning()->send();
                    }

                    Notification::make()
                        ->title('Abgeglichen')
                        ->body($record->rows()->count() . ' Zeilen, davon ' . $record->openRows()
                            . ' ohne Entscheidung.')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('review', ['record' => $record]));
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
}
