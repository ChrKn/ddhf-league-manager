<?php

namespace App\Models;

use App\Import\ImportStatus;
use App\Import\MatchConfidence;
use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

/**
 * One run of the import: the files somebody uploaded together, and everything decided about them.
 *
 * All files of an event belong in one import. Someone who entered several tournaments appears in
 * several files, and the protection against creating that person twice reaches only within a
 * single run - which is now this record rather than a single command invocation.
 */
class ResultImport extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 8;

    protected $fillable = [
        'public_id',
        'user_id',
        'event_id',
        'status',
        'format',
        'remember_aliases',
        'applied_at',
    ];

    protected $casts = [
        'status'           => ImportStatus::class,
        'remember_aliases' => 'boolean',
        'applied_at'       => 'datetime',
    ];

    /**
     * The uploaded files go with the record.
     *
     * The sheets are removed by the database's cascade, which fires no model events, so the files
     * would otherwise stay in the store with nothing left pointing at them - result sheets full of
     * names, kept for no reason anybody could reconstruct.
     */
    protected static function booted(): void
    {
        static::deleting(function (ResultImport $import): void {
            foreach ($import->sheets as $sheet) {
                Storage::disk('local')->delete($sheet->stored_path);
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The one event all of these results were fenced at.
     *
     * It belongs to the import rather than to each file: what arrives together is the longsword,
     * the sabre and the rapier of the same weekend. What differs between those files is the
     * standing, and that is what the sheet still carries.
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sheets(): HasMany
    {
        return $this->hasMany(ResultImportSheet::class);
    }

    public function rows(): HasManyThrough
    {
        return $this->hasManyThrough(ResultImportRow::class, ResultImportSheet::class);
    }

    /** Whether every sheet knows where it is headed, which is what the planning step needs. */
    public function isMapped(): bool
    {
        return $this->event_id !== null
            && $this->sheets()->count() > 0
            && $this->sheets()->whereNull('season_id')->doesntExist();
    }

    /**
     * How many rows still wait for somebody to decide.
     *
     * Since the matcher fills in what it believes, this is now only the rows it could not form a
     * belief about at all - a placement that is not a placement. Those block the write.
     */
    public function openRows(): int
    {
        return $this->rows()->where(function ($query) {
            $query->whereNull('action')->orWhere('action', '');
        })->count();
    }

    /**
     * How many rows are filled in on the strength of a likeness rather than a certainty.
     *
     * These do not block anything - somebody has to be able to accept a whole field at once, or
     * the review is no faster than typing it in. They are counted so that accepting them is a
     * thing somebody did knowingly. The weaker band below this one does block: see
     * App\Import\ImportPlanner.
     */
    public function uncertainRows(): int
    {
        return $this->rows()
            ->where('action', '!=', 'skip')
            ->where(function ($query) {
                $query->where('fencer_confidence', MatchConfidence::Likely->value)
                    ->orWhere('group_confidence', MatchConfidence::Likely->value);
            })
            ->count();
    }

    /**
     * The rows in the shape the writer reads, in sheet and file order.
     *
     * @return list<array<string, string>>
     */
    public function planRows(): array
    {
        return $this->rows()
            ->with(['fencer', 'group', 'sheet.tournament'])
            ->orderBy('result_import_sheet_id')
            ->orderBy('sheet_row')
            ->get()
            ->map(fn (ResultImportRow $row) => $row->toPlanRow())
            ->all();
    }
}
