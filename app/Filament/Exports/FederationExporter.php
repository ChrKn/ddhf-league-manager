<?php

namespace App\Filament\Exports;

use App\Models\Federation;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class FederationExporter extends Exporter
{
    protected static ?string $model = Federation::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('is_active')
                ->label('Aktiv')
                ->formatStateUsing(fn (bool $state): string => $state ? 'Ja' : 'Nein'),
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('name')
                ->label('Name'),
            ExportColumn::make('english_name')
                ->label('Englischer Name'),
            ExportColumn::make('abbreviation')
                ->label('Abkürzung'),
            ExportColumn::make('country')
                ->label('Land (Ländercode nach ISO 3166-1 Alpha-2)'),
            ExportColumn::make('website_url')
                ->label('Website URL'),
            ExportColumn::make('logo_url')
                ->label('Logo URL'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your federation export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
