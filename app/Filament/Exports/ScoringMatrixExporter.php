<?php

namespace App\Filament\Exports;

use App\Models\ScoringMatrix;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ScoringMatrixExporter extends Exporter
{
    protected static ?string $model = ScoringMatrix::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('matrix')
                ->label('Punktematrix'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your scoring matrix export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
