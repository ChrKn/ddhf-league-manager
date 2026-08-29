<?php

namespace App\Filament\Resources\Rulesets\Pages;

use App\Filament\Resources\Rulesets\RulesetResource;
use App\Filament\Support\GuardedDelete;
use Filament\Resources\Pages\EditRecord;

class EditRuleset extends EditRecord
{
    protected static string $resource = RulesetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GuardedDelete::make(),
        ];
    }
}
