<?php

namespace App\Filament\Exports;

use App\Models\Group;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class GroupExporter extends Exporter
{
    protected static ?string $model = Group::class;

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
            ExportColumn::make('abbreviation')
                ->label('Abkürzung'),
            ExportColumn::make('country')
                ->label('Land (Ländercode nach ISO 3166-1 Alpha-2)'),
            ExportColumn::make('website_url')
                ->label('Website URL'),
            ExportColumn::make('logo_url')
                ->label('Logo URL'),
            // Several, separated by a comma - the same shape the importer reads back in.
            ExportColumn::make('federations.public_id')
                ->listAsJson(false)
                ->label('Verband-IDs')
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your group export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
