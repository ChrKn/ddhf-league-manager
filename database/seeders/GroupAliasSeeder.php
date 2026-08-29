<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupAlias;
use Illuminate\Database\Seeder;

/**
 * Club spellings that no similarity check can resolve, confirmed by hand.
 *
 * Deliberately not called from DatabaseSeeder: that one creates test data, this one touches
 * real records. Run it on its own:
 *
 *     php artisan db:seed --class=GroupAliasSeeder
 */
class GroupAliasSeeder extends Seeder
{
    /** @var array<string, list<string>> public_id of the club => spellings seen in files */
    private const ALIASES = [
        // Europäische Schwertkunst is one club with several locations. The location is part of
        // the spelling in every source file, but never part of the club.
        '37JP54' => ['ESK', 'ESK Augsburg', 'ESK Starnberg', 'ESK München', 'ESK Nürnberg'],

        // Same pattern: the local group is written with its region, the club is one.
        'BWL9R3' => ['Ochs Bayerwald'],
    ];

    public function run(): void
    {
        foreach (self::ALIASES as $publicId => $aliases) {
            $group = Group::where('public_id', $publicId)->first();

            if (!$group) {
                $this->command?->warn("Verein {$publicId} nicht gefunden, Aliase übersprungen.");
                continue;
            }

            foreach ($aliases as $alias) {
                $created = GroupAlias::firstOrCreate(['alias' => $alias], ['group_id' => $group->id]);

                if ($created->wasRecentlyCreated) {
                    $this->command?->line("  {$alias} -> {$group->name}");
                }
            }
        }
    }
}
