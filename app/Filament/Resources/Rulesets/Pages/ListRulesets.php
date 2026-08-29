<?php

namespace App\Filament\Resources\Rulesets\Pages;

use App\Filament\Exports\RulesetExporter;
use App\Filament\Resources\Rulesets\RulesetResource;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListRulesets extends ListRecords
{
    protected static string $resource = RulesetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ExportAction::make()
                ->exporter(RulesetExporter::class)
                ->columnMapping(false)
                ->label('Exportieren'),
        ];
    }
}
