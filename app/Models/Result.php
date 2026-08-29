<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Result extends Model
{
    protected $fillable = [
        'tournament_id',
        'fencer_id',
        'group_id',
        'federation_id',
        'counted_on_request',
        'fencer_group_name',
        'placement',
    ];

    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function fencer(): BelongsTo
    {
        return $this->belongsTo(Fencer::class);
    }

    /**
     * The club the fencer competed for, as opposed to the one they belong to today. Null where
     * the source never named one; fencer_group_name keeps the raw spelling either way.
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** The federation that club belonged to at the time. Null means: no membership recorded. */
    public function federation(): BelongsTo
    {
        return $this->belongsTo(Federation::class);
    }

    /**
     * Whether the federation let this one count although the club was not a member yet.
     *
     * The Kulanzregelung, granted on application and never automatically. The column holds the
     * grounds rather than a yes, so an exception always says why it was made.
     */
    public function countsOnRequest(): bool
    {
        return filled($this->counted_on_request);
    }

    protected static function booted(): void
    {
        static::creating(function (Result $model) {
            if (empty($model->fencer_group_name) && $model->fencer_id) {
                $fencer = Fencer::with('group')->find($model->fencer_id);

                $model->fencer_group_name = $fencer?->group?->name;
            }
        });
    }
}
