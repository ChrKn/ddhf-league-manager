<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnouncement extends EditRecord
{
    protected static string $resource = AnnouncementResource::class;

    /**
     * The public "updated on" moves only when somebody says so. A typo fixed is not news, and a
     * date that moves on every save would tell readers something changed when nothing did.
     *
     * The switch is not dehydrated, so it is read off the raw form state rather than $data, and
     * put back to off afterwards - it is a decision about this one save, not a setting.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->data['mark_revised'] ?? false) {
            $data['revised_at'] = now();
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $this->data['mark_revised'] = false;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
