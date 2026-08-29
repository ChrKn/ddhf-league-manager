<?php

namespace App\Filament\Exports;

use App\Models\Season;
use App\Standings\ScoringMode;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class SeasonExporter extends Exporter
{
    protected static ?string $model = Season::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('standing.public_id')
                ->label('Rangliste-ID'),
            ExportColumn::make('standing.display_name')
                ->label('Rangliste'),
            ExportColumn::make('scoring_matrix.public_id')
                ->label('Punkteschlüssel-ID'),
            ExportColumn::make('scoring_matrix.name')
                ->label('Punkteschlüssel'),
            ExportColumn::make('scoring_mode')
                ->formatStateUsing(fn(?ScoringMode $state) => $state?->value)
                ->label('Auswertungssystem'),
            ExportColumn::make('year')
                ->label('Jahr'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your season export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
