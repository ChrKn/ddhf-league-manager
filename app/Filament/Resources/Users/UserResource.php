<?php

namespace App\Filament\Resources\Users;

use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|null|\BackedEnum $navigationIcon = Heroicon::OutlinedUserGroup;
    protected static string|null|\UnitEnum $navigationGroup = 'Systemverwaltung';
    protected static ?string $navigationLabel = 'Benutzer';
    protected static ?string $slug = 'benutzer';

    protected static ?string $modelLabel = 'Benutzer';
    protected static ?string $pluralModelLabel = 'Benutzer';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->label('Benutzername'),
            TextInput::make('email')
                ->email()
                ->required()
                ->unique(ignoreRecord: true)
                ->label('E-Mail'),
            TextInput::make('password')
                ->password()
                ->hint('Mindestens 12 Zeichen, Groß- und Kleinbuchstaben, Zahlen und Sonderzeichen.')
                ->dehydrateStateUsing(fn($state) => Hash::make($state))
                ->dehydrated(fn($state) => filled($state))
                ->required(fn(string $operation) => $operation === 'create')
                ->rules([
                    Password::min(12)
                        ->mixedCase()
                        ->numbers()
                        ->symbols()
                        ->uncompromised(),
                ])
                ->label('Passwort'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')
                ->label('Benutzername'),
            TextColumn::make('email')
                ->label('E-Mail'),
            TextColumn::make('created_at')
                ->dateTime()
                ->label('Erstellt am'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
