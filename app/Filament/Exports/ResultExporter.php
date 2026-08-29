<?php

namespace App\Filament\Exports;

use App\Models\Result;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ResultExporter extends Exporter
{
    protected static ?string $model = Result::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('tournament.display_name')
                ->label('Turnier'),
            ExportColumn::make('tournament.public_id')
                ->label('Turnier ID'),
            ExportColumn::make('fencer.display_name')
                ->label('Fechter'),
            ExportColumn::make('fencer.public_id')
                ->label('Fechter-ID'),
            ExportColumn::make('fencer_group_name')
                ->label('Gruppe'),
            ExportColumn::make('placement')
                ->label('Platzierung'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your result export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
