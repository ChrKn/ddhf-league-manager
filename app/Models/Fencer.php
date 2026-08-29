<?php

namespace App\Models;

use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fencer extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 10;

    /**
     * What an anonymised fencer is called wherever a name still has to be shown.
     *
     * Never stored. An anonymised record holds no name at all; this is what the code puts in its
     * place, so that every reader gets the same answer and no placeholder can be edited, exported
     * or matched against as though it were somebody's name.
     */
    public const ANONYMOUS_NAME = 'Anonymer Fechter';

    protected $fillable = [
        'is_active',
        'public_id',
        'title',
        'first_name',
        'last_name',
        'birth_name',
        'nationality',
        'group_id',
        'date_of_birth',
        'gender',
        'anonymized_at',
        'anonymization_reason',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'anonymized_at' => 'datetime',
    ];

    /**
     * Whether this fencer's personal data has been removed on request.
     *
     * Deliberately not derived from is_active or from the name: the first means something else
     * entirely, the second is display text.
     */
    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function scopeAnonymized(Builder $query): Builder
    {
        return $query->whereNotNull('anonymized_at');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    /**
     * The standings this fencer is ranked in where a category had to be chosen. This is a
     * declaration made per season and has nothing to do with `gender`, which says something else
     * entirely: the open category is open to everybody.
     */
    public function seasons(): BelongsToMany
    {
        return $this->belongsToMany(Season::class)->withTimestamps();
    }

    public function getDisplayNameWithGroupAttribute(): string
    {
        // No club either: anonymising clears it, and a leftover one would be the last trait that
        // still points at somebody.
        if ($this->isAnonymized()) {
            return self::ANONYMOUS_NAME;
        }

        $displayName = $this->first_name ?? '';
        $displayName .= ' ' . ($this->last_name ?? '');
        $displayName .= isset($this->group?->name) ? " ({$this->group?->name})" : '';

        return trim($displayName);
    }

    /**
     * The name to show. For an anonymised record that is the placeholder, derived from the flag
     * and not read from the columns - which are empty, and stay empty even if somebody types
     * something into them.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->isAnonymized()) {
            return self::ANONYMOUS_NAME;
        }

        $displayName = $this->first_name ?? '';
        $displayName .= ' ' . ($this->last_name ?? '');

        return trim($displayName);
    }
}
