<?php

namespace App\Filament\Exports;

use App\Models\Event;
use Carbon\CarbonInterface as Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class EventExporter extends Exporter
{
    protected static ?string $model = Event::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Name'),
            // ISO, because this file is meant to be read back in - and asked of the date rather
            // than cut off the front of whatever it stringifies to. The old version sliced ten
            // characters off "2026-05-09 00:00:00" and happened to be right; it would have been
            // silently wrong the day the cast or the locale changed.
            ExportColumn::make('start_date')
                ->formatStateUsing(fn (?Carbon $state): ?string => $state?->format('Y-m-d'))
                ->label('Startdatum'),
            ExportColumn::make('end_date')
                ->formatStateUsing(fn (?Carbon $state): ?string => $state?->format('Y-m-d'))
                ->label('Enddatum'),
            ExportColumn::make('location')
                ->label('Ort'),
            ExportColumn::make('organizers.public_id')
                ->label('Ausrichter-ID'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your event export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
