<?php

namespace App\Data;

class Regions
{
    public static function all(): array
    {
        return [
            'north' => 'Norden',
            'east'  => 'Osten',
            'south' => 'Süden',
            'west'  => 'Westen',
        ];
    }

    public static function label(?string $region): ?string
    {
        if ($region === null) {
            return null;
        }

        return self::all()[$region] ?? $region;
    }
}
