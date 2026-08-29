<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded file, and the tournament it is headed for.
 *
 * The tournament is created from the season and the event when the import is applied, not when
 * the file is uploaded. An abandoned review then leaves nothing behind - no empty tournament
 * that later looks like a real one that nobody entered results for.
 */
class ResultImportSheet extends Model
{
    protected $fillable = [
        'result_import_id',
        'original_name',
        'stored_path',
        'season_id',
        'tournament_id',
        'participants',
        'format',
        'partial',
    ];

    protected $casts = [
        'participants' => 'integer',
        'partial'      => 'boolean',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ResultImport::class, 'result_import_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    /** The event is the import's, because every file of one import was fenced at the same one. */
    public function event(): ?Event
    {
        return $this->import?->event;
    }

    /** Filled at the moment the import is applied, and the record of what became of this file. */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    public function rows(): HasMany
    {
        return $this->hasMany(ResultImportRow::class);
    }

    /**
     * Whether a tournament of this standing already exists at this event.
     *
     * This is the guard against importing the same file twice. Since the importer creates the
     * tournament, a second run would not produce a duplicate result - it would produce a second
     * tournament with a full field, which is the worse mistake and the harder one to spot.
     */
    public function tournamentAlreadyThere(): ?Tournament
    {
        $eventId = $this->import?->event_id;

        if (!$this->season_id || !$eventId) {
            return null;
        }

        return Tournament::where('season_id', $this->season_id)
            ->where('event_id', $eventId)
            ->when($this->tournament_id, fn ($query) => $query->whereKeyNot($this->tournament_id))
            ->first();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->original_name;
    }
}
