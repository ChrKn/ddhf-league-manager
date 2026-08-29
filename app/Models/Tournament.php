<?php

namespace App\Models;

use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 8;

    /**
     * How a tournament was fenced, as the "Turniersystem" column of the submission template
     * states it. Since every result says for itself what it is - a rank, a round, a pool exit -
     * this decides nothing about the scoring. It stays because a list of rounds is unreadable
     * without it, and because a bracket with no rounds recorded is worth finding.
     *
     * Tournaments taken in before the column existed carry nothing, which is honest.
     */
    const FORMATS = [
        'Alle gegen alle',
        'Turnierbaum',
        'Turnierbaum mit Vorrunde',
    ];

    protected $fillable = [
        'public_id',
        'name',
        'participant_count',
        'event_id',
        'season_id',
        'ruleset_id',
        'region',
        'format',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    /**
     * Whether this tournament says when it was fenced, rather than leaving it to the event.
     *
     * Almost none do. Where the answer is no, the event's dates are used and the tournament makes
     * no claim of its own - which matters for display: a two-day event does not mean a two-day
     * tournament, and showing its span against every one of six tournaments would say something
     * nobody established.
     */
    public function hasOwnDates(): bool
    {
        return $this->start_date !== null || $this->end_date !== null;
    }

    /**
     * The first day it was fenced on.
     *
     * A tournament that states its own dates speaks for itself, and the event is then not
     * consulted at all - a tournament with only a start date is a one-day tournament on that day,
     * not one running to the end of the event.
     */
    public function getHeldFromAttribute(): ?Carbon
    {
        return $this->hasOwnDates()
            ? ($this->start_date ?? $this->end_date)
            : $this->event?->start_date;
    }

    /** The last day it was fenced on, which for most tournaments is the same day. */
    public function getHeldToAttribute(): ?Carbon
    {
        return $this->hasOwnDates()
            ? ($this->end_date ?? $this->start_date)
            : ($this->event?->end_date ?? $this->event?->start_date);
    }

    /** Whether this tournament itself ran across more than one day. */
    public function spansSeveralDays(): bool
    {
        return $this->hasOwnDates()
            && $this->held_from !== null
            && $this->held_to?->gt($this->held_from);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(Result::class);
    }

    public function getDisplayNameAttribute(): string
    {
        return ($this->season?->standing?->discipline?->name ?? '?') . ' '
                . ($this->season?->standing?->division?->name ?? '?') . ' '
                . ($this->season?->year ?? '?') . ' (' . ($this->event?->name ?? '?') . ')';
    }
}
