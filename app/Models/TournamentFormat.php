<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How a tournament was fenced: all against all, a bracket, a bracket after pools.
 *
 * Since every result says for itself what it is - a rank, a round, a pool exit - this decides
 * nothing about the scoring. It stays because a list of rounds is unreadable without it, and
 * because a bracket with no rounds recorded is worth finding.
 *
 * A list rather than free text, so that the organisers' own words for the same thing do not turn
 * into five systems. New entries come through the panel.
 */
class TournamentFormat extends Model
{
    protected $fillable = [
        'name',
        'sort',
    ];

    protected $casts = [
        'sort' => 'integer',
    ];

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort')->orderBy('name');
    }

    /** @return array<int, string> id => name, in list order, for a select */
    public static function options(): array
    {
        return static::ordered()->pluck('name', 'id')->all();
    }
}
