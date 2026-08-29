<?php

namespace App\Filament\Exports;

use App\Models\Standing;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class StandingExporter extends Exporter
{
    protected static ?string $model = Standing::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('discipline.public_id')
                ->label('Disziplin-ID'),
            ExportColumn::make('division.public_id')
                ->label('Abteilung-ID'),
            ExportColumn::make('discipline.name')
                ->label('Disziplin-Name'),
            ExportColumn::make('division.name')
                ->label('Abteilung-Name'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your standing export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
