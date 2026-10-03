<?php

namespace App\Models;

use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Standing extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 8;

    protected $fillable = [
        'public_id',
        'discipline_id',
        'division_id',
    ];

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function discipline(): BelongsTo
    {
        return $this->belongsTo(Discipline::class);
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }

    /**
     * Standings with at least one season that may be shown. One whose years are all held back has
     * nothing to show yet, and listing it would only lead to an empty page.
     */
    public function scopePublic(Builder $query): Builder
    {
        return $query->whereHas('seasons', fn (Builder $season) => $season->public());
    }

    public function getDisplayNameAttribute(): string
    {
        return ($this->discipline?->name ?? '?') . ' ' . ($this->division?->name ?? '?');
    }
}
