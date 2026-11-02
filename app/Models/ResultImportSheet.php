<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One tournament out of an uploaded file, and where it is headed.
 *
 * Usually that is the whole file. A workbook with one sheet per tournament gives one of these per
 * sheet, all sharing the stored file and told apart by `worksheet`.
 *
 * The tournament is created from the season and the event when the import is applied, not when
 * the file is uploaded. An abandoned review then leaves nothing behind - no empty tournament
 * that later looks like a real one that nobody entered results for. Its field size and system
 * are settled here first, by whoever maps the files: the file only proposes the size, and it
 * says nothing usable about the system.
 */
class ResultImportSheet extends Model
{
    protected $fillable = [
        'result_import_id',
        'original_name',
        'stored_path',
        'worksheet',
        'season_id',
        'tournament_id',
        'participants',
        'stated_participants',
        'row_count',
        'notes',
        'tournament_format_id',
        'partial',
    ];

    protected $casts = [
        'participants'        => 'integer',
        'stated_participants' => 'integer',
        'row_count'           => 'integer',
        'notes'               => 'array',
        'partial'             => 'boolean',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(ResultImport::class, 'result_import_id');
    }

    /**
     * A stored file may carry several tournaments, so it goes only with the last sheet reading it.
     * Removing a sheet in the mapping form comes through here; deleting the whole import is
     * ResultImport's business, because the cascade there fires no events.
     */
    protected static function booted(): void
    {
        static::deleted(function (ResultImportSheet $sheet): void {
            $sheet->releaseFile();
        });
    }

    /** Deletes the stored file once no sheet reads from it any more. */
    public function releaseFile(): void
    {
        if (!static::where('stored_path', $this->stored_path)->exists()) {
            Storage::disk('local')->delete($this->stored_path);
        }
    }

    /** The name of the file this came out of, as it was sent. */
    public function fileName(): string
    {
        $suffix = $this->worksheet === null ? '' : " · {$this->worksheet}";

        return $suffix !== '' && str_ends_with($this->original_name, $suffix)
            ? substr($this->original_name, 0, -strlen($suffix))
            : $this->original_name;
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function tournamentFormat(): BelongsTo
    {
        return $this->belongsTo(TournamentFormat::class);
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
