<?php

namespace App\Filament\Resources\Announcements\Schemas;

use App\Models\Announcement;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    /**
     * Bold, italic, a link - and nothing that needs knowing what Markdown is. No underline, which
     * Markdown does not have and which on a web page reads as a link anyway.
     */
    private const TOOLBAR = [['bold', 'italic', 'link'], ['undo', 'redo']];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Deutsch')
                    ->schema([
                        TextInput::make('title')
                            ->label('Überschrift')
                            ->required()
                            ->maxLength(255),
                        MarkdownEditor::make('body')
                            ->label('Text')
                            ->toolbarButtons(self::TOOLBAR)
                            ->required(),
                    ])
                    ->columnSpanFull(),

                // Both or neither: the site only switches to English when there is a heading and a
                // text, so half a translation would be thrown away without anybody noticing.
                Section::make('Englisch (optional)')
                    ->description('Ohne Übersetzung zeigt die englische Seite den deutschen Text.')
                    ->schema([
                        TextInput::make('title_en')
                            ->label('Überschrift')
                            ->requiredWith('body_en')
                            ->maxLength(255),
                        MarkdownEditor::make('body_en')
                            ->label('Text')
                            ->toolbarButtons(self::TOOLBAR)
                            ->requiredWith('title_en'),
                    ])
                    ->collapsible()
                    ->columnSpanFull(),

                Select::make('user_id')
                    ->label('Verfasst von')
                    ->relationship('author', 'name')
                    ->default(fn () => auth()->id())
                    ->searchable()
                    ->preload()
                    ->helperText('Nur intern, wird auf der Seite nicht angezeigt.'),

                // Not a column: it only says what this one save is to do to revised_at. See
                // EditAnnouncement::mutateFormDataBeforeSave().
                Toggle::make('mark_revised')
                    ->label('Als Aktualisierung kennzeichnen')
                    ->dehydrated(false)
                    ->default(false)
                    ->visibleOn('edit')
                    ->helperText(fn (?Announcement $record) => self::revisedHint($record)),
            ]);
    }

    private static function revisedHint(?Announcement $record): string
    {
        $what = 'Zeigt auf der Seite „aktualisiert am“ mit dem heutigen Datum. Für Tippfehler nicht nötig.';

        if ($record?->revised_at === null) {
            return $what;
        }

        return $what . ' Bisher: ' . $record->revised_at->copy()->setTimezone(config('site.timezone'))->format('d.m.Y H:i') . '.';
    }
}
