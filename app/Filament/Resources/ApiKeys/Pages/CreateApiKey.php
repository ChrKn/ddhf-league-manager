<?php

namespace App\Filament\Resources\ApiKeys\Pages;

use App\Filament\Resources\ApiKeys\ApiKeyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    /**
     * Hand the key over before this page is done with it.
     *
     * It exists only on the instance that just made it - a moment later there is nothing left in
     * the database that could produce it again. The edit page is where it gets shown, so the way
     * out of here always leads there.
     */
    protected function afterCreate(): void
    {
        session()->flash('api-key.fresh.' . $this->record->getKey(), $this->record->key);
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
