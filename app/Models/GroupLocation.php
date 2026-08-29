<?php

namespace App\Models;

use App\Data\Countries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A town a club trains in.
 *
 * A club may have several, which is why this is not a column on `groups`. The country is held
 * here as well as on the club, because a club can train across a border - the Austrian INDES
 * runs locations in Germany.
 */
class GroupLocation extends Model
{
    protected $fillable = [
        'group_id',
        'locality',
        'region',
        'country',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * "Kassel, Hessen" - and just the town where the region says nothing more.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->region === null || $this->region === '' || $this->region === $this->locality) {
            return $this->locality;
        }

        return $this->locality . ', ' . $this->region;
    }

    public function getCountryNameAttribute(): ?string
    {
        return Countries::label($this->country);
    }
}
