<?php

namespace App\Models;

use App\Standings\ScoringMode;
use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Season extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 8;

    protected $fillable = [
        'public_id',
        'standing_id',
        'scoring_matrix_id',
        'scoring_mode',
        'year',
        'division_choice_required',
    ];

    protected function casts(): array
    {
        return [
            'scoring_mode'             => ScoringMode::class,
            'division_choice_required' => 'boolean',
        ];
    }

    public function standing(): BelongsTo
    {
        return $this->belongsTo(Standing::class);
    }

    public function scoring_matrix(): BelongsTo
    {
        return $this->belongsTo(ScoringMatrix::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    /**
     * The fencers ranked in this standing rather than in the other category of the same weapon.
     * Only consulted where `division_choice_required` says a choice had to be made.
     */
    public function fencers(): BelongsToMany
    {
        return $this->belongsToMany(Fencer::class)->withTimestamps();
    }

    /**
     * The other categories of the same weapon and year - what the choice is between. A season with
     * no siblings offers no choice, which is why most of them never need one.
     */
    public function siblings(): Builder
    {
        return static::query()
            ->where('id', '<>', $this->id)
            ->where('year', $this->year)
            ->whereHas('standing', fn (Builder $query) => $query
                ->where('discipline_id', $this->standing?->discipline_id));
    }

    public function getDisplayNameAttribute(): string
    {
        return ($this->standing?->displayName ?? '?') . ' ' . ($this->year ?? '?');
    }
}
