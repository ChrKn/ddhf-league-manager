<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;

    /**
     * A key in clear, for this visit and no longer.
     *
     * Public so that Livewire carries it across the requests of one page - otherwise it would be
     * gone the first time anything on the form was touched. It is never read from the record,
     * because the record does not have it.
     */
    public ?string $freshKey = null;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        // Left here by the create page, which redirects straight to this one. Pulled rather than
        // read, so a reload shows the row without the key, which is the truth from then on.
        $this->freshKey = session()->pull('api-key.fresh.' . $this->record->getKey());

        if ($this->freshKey !== null) {
            $this->fillForm();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label('Neuen Schlüssel erzeugen')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Neuen Schlüssel erzeugen?')
                ->modalDescription('Der bisherige gilt ab sofort nicht mehr. Wer damit weiter anfragt, bekommt 401.')
                ->modalSubmitActionLabel('Erzeugen')
                ->action(function () {
                    $this->freshKey = $this->record->regenerate();

                    // The record on the screen is a step behind now - the prefix changed with it.
                    $this->fillForm();

                    Notification::make()
                        ->title('Neuer Schlüssel erzeugt')
                        ->body('Er steht oben im Formular und ist nur jetzt zu sehen. Der bisherige gilt nicht mehr.')
                        ->warning()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
