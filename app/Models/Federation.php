<?php

namespace App\Models;

use App\Federations\FederationKind;
use App\Traits\GenerateRandomString;
use App\Traits\NameNormalization;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;

class Federation extends Model
{
    use GenerateRandomString, HasFactory, Notifiable, NameNormalization;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 6;

    /**
     * The federation these standings belong to. A standing is the DDHF's ranking, so only
     * results fenced for one of its member clubs count towards it.
     *
     * Hard coded on purpose, and meant to be read as a placeholder rather than as the model.
     * Co-operations with other federations are wanted later — the ÖFHF has been named — and a
     * single "this is us" flag would not survive them: what is needed then is a statement of
     * which federations count towards which standing, per standing.
     */
    const OWN = 'DDHF';

    protected $fillable = [
        'is_active',
        'public_id',
        'name',
        // A Dachverband or a Verbund - two things that live in this table and are not alike.
        // See App\Federations\FederationKind.
        'kind',
        // What the federation calls itself in English. Optional, and empty wherever the proper
        // name already reads in Latin script - it is there for the likes of ΕΟΙΕΠΤ.
        'english_name',
        'abbreviation',
        'country',
        'website_url',
        'logo_url',
        'is_public',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'kind'      => FederationKind::class,
    ];

    /**
     * Stated here as well as on the column, so a row just created knows what it is.
     *
     * A database default only lands in the model after a round trip, which would leave a freshly
     * made federation answering isNational() with false until somebody reloaded it.
     */
    protected $attributes = [
        'kind' => FederationKind::National->value,
    ];

    public function isNational(): bool
    {
        return $this->kind === FederationKind::National;
    }

    /** The governing bodies, as opposed to the school networks that sit alongside them. */
    public function scopeNational(Builder $query): Builder
    {
        return $query->where('kind', FederationKind::National);
    }

    public function scopeAssociations(Builder $query): Builder
    {
        return $query->where('kind', FederationKind::Association);
    }

    /**
     * Whether this federation may be named where the public can read it.
     *
     * Not is_active, which says whether it still exists, and not anonymisation, which deletes.
     * This only stops the name being shown: every result still points here and every standing is
     * computed from the same rows. Note that Federation::own() deliberately ignores it - hiding
     * a federation must not be able to change who scores points.
     */
    public function isPublic(): bool
    {
        return (bool) $this->is_public;
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    /** The name where a reader may see it, null where this federation asked not to be shown. */
    public function getPublicNameAttribute(): ?string
    {
        return $this->isPublic() ? $this->name : null;
    }

    /**
     * The clubs that belong here.
     *
     * Many-to-many since a club can be in a Dachverband and a Verbund at once. Nothing is
     * inherited across this relation: a club in INDES is not thereby in whatever INDES belongs to,
     * because INDES belongs to nothing - it is a federation row, and only clubs hold memberships.
     */
    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class)->withTimestamps();
    }

    /** The federation named by self::OWN, or null when it is not on record. */
    public static function own(): ?self
    {
        return static::where('abbreviation', self::OWN)->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->abbreviation ?: $this->name;
    }

}
