<?php

namespace App\Filament\Resources\ApiKeys\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Livewire\Component;

class ApiKeyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // The one moment the key is readable. It sits on the page rather than in a toast
                // because sixty-four characters want a field and a copy button, not a message that
                // fades - and it is gone on the next visit, see App\Models\ApiKey.
                TextInput::make('fresh_key')
                    ->label('Der neue Schlüssel')
                    ->readOnly()
                    ->copyable(copyMessage: 'Kopiert')
                    ->dehydrated(false)
                    ->visible(fn (Component $livewire) => filled(self::fresh($livewire)))
                    ->afterStateHydrated(fn (TextInput $component, Component $livewire) => $component->state(self::fresh($livewire)))
                    ->helperText('Jetzt kopieren und weitergeben. Gespeichert ist nur eine Prüfsumme — ein zweites Mal lässt er sich nicht anzeigen.')
                    ->columnSpanFull(),
                TextInput::make('name')
                    ->required()
                    ->label('Name'),
                TextInput::make('key_prefix')
                    ->label('API Schlüssel')
                    ->disabled()
                    ->dehydrated(false)
                    ->visibleOn('edit')
                    ->formatStateUsing(fn (?string $state) => $state === null ? null : $state . '…')
                    ->helperText('Nur der Anfang, damit sich die Zeile wiedererkennen lässt. Den ganzen Schlüssel kennt niemand mehr — wer ihn verloren hat, bekommt oben einen neuen.'),
                Select::make('scope')
                    ->options(['read' => 'Read', 'write' => 'Write', 'full' => 'Full'])
                    ->default('read')
                    ->required()
                    ->label('Zugriffsrechte'),
                Toggle::make('is_active')
                    ->required()
                    ->default(true)
                    ->label('Aktiv'),
            ]);
    }

    /**
     * The key the page is holding for this one visit, if it is a page that ever holds one.
     *
     * Asked of the component rather than of the record, because the record is exactly where it is
     * not: the create page has none to ask, and reading a missing property off a Livewire component
     * goes through __get and complains.
     */
    private static function fresh(Component $livewire): ?string
    {
        return property_exists($livewire, 'freshKey') ? $livewire->freshKey : null;
    }
}
