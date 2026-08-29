<?php

namespace App\Filament\Exports;

use App\Models\Tournament;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class TournamentExporter extends Exporter
{
    protected static ?string $model = Tournament::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('participant_count')
                ->label('Anzahl Teilnehmer'),
            ExportColumn::make('event.public_id')
                ->label('Veranstaltung-ID'),
            ExportColumn::make('event.name')
                ->label('Veranstaltung-Name'),
            ExportColumn::make('season.public_id')
                ->label('Saison-ID'),
            ExportColumn::make('season.display_name')
                ->label('Saison-Name'),
            ExportColumn::make('ruleset.public_id')
                ->label('Regelwerk-ID'),
            ExportColumn::make('ruleset.name')
                ->label('Regelwerk-Name'),
            ExportColumn::make('region')
                ->label('Region'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your tournament export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
