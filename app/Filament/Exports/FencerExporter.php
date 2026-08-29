<?php

namespace App\Filament\Exports;

use App\Models\Fencer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class FencerExporter extends Exporter
{
    protected static ?string $model = Fencer::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('is_active')
                ->label('Aktiv')
                ->formatStateUsing(fn(bool $state): string => $state ? 'Ja' : 'Nein'),
            ExportColumn::make('public_id')
                ->label('ID'),
            ExportColumn::make('title')
                ->label('Titel'),
            ExportColumn::make('first_name')
                ->label('Vorname'),
            ExportColumn::make('last_name')
                ->label('Nachname'),
            ExportColumn::make('birth_name')
                ->label('Geburtsname'),
            ExportColumn::make('nationality')
                ->label('Nationalität (Ländercode nach ISO 3166-1 Alpha-2)'),
            ExportColumn::make('group.public_id')
                ->label('Gruppen-ID'),
            ExportColumn::make('group.name')
                ->label('Gruppen-Name'),
            ExportColumn::make('date_of_birth')
                ->label('Geburtsdatum'),
            ExportColumn::make('gender')
                ->label('Geschlecht (male, female, non_binary)'),
            // anonymization_reason is deliberately absent: exports get mailed around, internal
            // notes about a deletion request have no business travelling with them.
            ExportColumn::make('anonymized_at')
                ->label('Anonymisiert am'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your fencer export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
