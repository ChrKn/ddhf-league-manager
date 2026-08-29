<?php

namespace App\Models;

use App\Federations\FederationKind;
use App\Traits\GenerateRandomString;
use App\Traits\NameNormalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Group extends Model
{
    use GenerateRandomString, NameNormalization;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 6;

    protected $fillable = [
        'is_active',
        'public_id',
        'name',
        'abbreviation',
        'country',
        'website_url',
        'logo_url',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    /**
     * Whether this club may be named where the public can read it.
     *
     * Not is_active, which says whether the club still exists and is a statement of fact about
     * it; asking not to be named says nothing about whether it fences. Not anonymisation either -
     * a club is a group and not a person, so nothing is deleted and no attribute is cleared.
     *
     * The fencers stay either way. Somebody who fenced for this club keeps their placement and
     * their points, because those are theirs rather than the club's; their row simply shows no
     * club.
     */
    public function isPublic(): bool
    {
        return (bool) $this->is_public;
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** The name where a reader may see it, null where this club asked not to be shown. */
    public function getPublicNameAttribute(): ?string
    {
        return $this->isPublic() ? $this->name : null;
    }

    /**
     * Every federation this club belongs to, of either kind.
     *
     * Each one is stated here directly. Nothing is inherited: a club in INDES is in the DDHF only
     * if it says so itself, which is why INDES Salzburg - in INDES and in the ÖFHF - is not one of
     * ours even though INDES Kulmbach is.
     */
    public function federations(): BelongsToMany
    {
        return $this->belongsToMany(Federation::class)->withTimestamps()->orderBy('name');
    }

    /** The governing bodies among them. Usually one, sometimes none, rarely more. */
    public function nationalFederations(): BelongsToMany
    {
        return $this->federations()->where('kind', FederationKind::National);
    }

    /** The school networks among them, which decide nothing and cross borders. */
    public function associations(): BelongsToMany
    {
        return $this->federations()->where('kind', FederationKind::Association);
    }

    /**
     * The one national federation, where there is one to name.
     *
     * A club has at most one in practice; countries with two governing bodies - Poland, France,
     * Italy - are the exception, and there the first by name is as good an answer as a single
     * column can give. Nothing that matters hangs on the choice: see scoringFederation().
     */
    public function nationalFederation(): ?Federation
    {
        return $this->relationLoaded('federations')
            ? $this->federations->firstWhere('kind', FederationKind::National)
            : $this->nationalFederations()->first();
    }

    /**
     * The federation a result fenced for this club is written down under.
     *
     * Ours first, and that branch is the only one that decides anything: the sole use of
     * `results.federation_id` is the question "does this count towards a DDHF standing", and a
     * club that is a member answers it by being one. What the other branch returns is for the
     * reader - the national federation of a club that is not ours, or nothing where none is on
     * record.
     *
     * An association is never written down here. Belonging to INDES says nothing about who ranks
     * you, and recording it would make a result look like it was fenced for a body that ranks
     * nobody.
     */
    public function scoringFederation(): ?Federation
    {
        $own = Federation::own();

        if ($own !== null && $this->federations()->whereKey($own->getKey())->exists()) {
            return $own;
        }

        return $this->nationalFederation();
    }

    /**
     * Alternative spellings this club is imported under.
     */
    public function aliases(): HasMany
    {
        return $this->hasMany(GroupAlias::class);
    }

    /**
     * The towns this club trains in. Several is normal, none means nobody has looked it up.
     */
    public function locations(): HasMany
    {
        return $this->hasMany(GroupLocation::class)->orderBy('locality');
    }

    /**
     * Where the club trains, in one line: "Kassel, Hessen" or "München und 6 weitere".
     */
    public function getWhereItTrainsAttribute(): ?string
    {
        $locations = $this->locations;

        if ($locations->isEmpty()) {
            return null;
        }

        $first = $locations->first()->display_name;

        return $locations->count() === 1
            ? $first
            : $first . ' und ' . ($locations->count() - 1) . ' weitere';
    }

    /**
     * A fencer belongs to one club, through fencers.group_id. This was declared as a
     * belongsToMany against a fencer_group table that has never existed, so any call threw.
     */
    public function fencers(): HasMany
    {
        return $this->hasMany(Fencer::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }

}
