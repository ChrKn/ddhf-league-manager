<?php

namespace App\Models;

use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tournament extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 8;

    protected $fillable = [
        'public_id',
        'name',
        // The field: everybody who fenced at least one bout. Somebody who cancelled before their
        // first bout is not in it, whatever the registration list says. This is the N every point
        // is read at - see SeasonScoring.
        'participant_count',
        // How many had registered when registration closed. Evidence for a decision, shown next
        // to the field, and never part of a point.
        'registered_count',
        'event_id',
        'season_id',
        'ruleset_id',
        'region',
        'tournament_format_id',
        // By name, see format() below.
        'format',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'start_date'        => 'date',
        'end_date'          => 'date',
        'participant_count' => 'integer',
        'registered_count'  => 'integer',
    ];

    /**
     * How many registered and never fenced, where the registrations are on record.
     *
     * Only ever a number. Who they were is not kept - there is no result for them, so there is no
     * name - and why is not kept either: the reason is usually illness, which is health data.
     */
    public function noShows(): ?int
    {
        if ($this->registered_count === null || $this->participant_count === null) {
            return null;
        }

        return max(0, $this->registered_count - $this->participant_count);
    }

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

    /** Tournaments of a season that may be shown. See Season::isPublic(). */
    public function scopePublic(Builder $query): Builder
    {
        return $query->whereHas('season', fn (Builder $season) => $season->public());
    }

    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }

    /**
     * How it was fenced. Tournaments taken in before anybody recorded it carry nothing, which is
     * honest.
     */
    public function tournamentFormat(): BelongsTo
    {
        return $this->belongsTo(TournamentFormat::class);
    }

    /**
     * The system by its name, which is how every page, export and API response has always shown
     * it - the column used to hold the name itself.
     *
     * Setting it takes a name from the list and nothing else. A name that is not there is a
     * mistake to hear about, not a new entry: the list is kept in the panel.
     */
    protected function format(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->tournamentFormat?->name,
            set: function (?string $name): array {
                $name = trim((string) $name);

                if ($name === '') {
                    return ['tournament_format_id' => null];
                }

                $id = TournamentFormat::where('name', $name)->value('id')
                    ?? throw new InvalidArgumentException("Unbekanntes Turniersystem: {$name}");

                return ['tournament_format_id' => $id];
            },
        );
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
