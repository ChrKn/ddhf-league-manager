<?php

namespace App\Models;

use App\Import\MatchConfidence;
use App\Import\Suggestion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an uploaded file: what it said, what it was matched to, and what is to happen.
 *
 * The row keeps the source spelling next to the match on purpose. A reviewer deciding whether
 * "M. Musterfrau" is the Maria Musterfrau in the database needs both in front of them, and the
 * raw spelling is also what ends up on the result as fencer_group_name.
 */
class ResultImportRow extends Model
{
    protected $fillable = [
        'result_import_sheet_id',
        'sheet_row',
        'placement',
        'name',
        'club',
        'federation',
        'fencer_id',
        'fencer_confidence',
        'fencer_score',
        'group_id',
        'group_confidence',
        'group_score',
        'action',
        'note',
        'result_id',
    ];

    protected $casts = [
        'sheet_row'         => 'integer',
        'fencer_score'      => 'float',
        'group_score'       => 'float',
        'fencer_confidence' => MatchConfidence::class,
        'group_confidence'  => MatchConfidence::class,
    ];

    public function sheet(): BelongsTo
    {
        return $this->belongsTo(ResultImportSheet::class, 'result_import_sheet_id');
    }

    public function fencer(): BelongsTo
    {
        return $this->belongsTo(Fencer::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** What this row became, once the import was applied. */
    public function result(): BelongsTo
    {
        return $this->belongsTo(Result::class);
    }

    /**
     * The attributes for a row, from what the planner produced for it.
     *
     * @param  array<string, string>  $planRow
     * @param  array{sheet: string, row: int, fencer: Suggestion, group: Suggestion}  $match
     * @return array<string, mixed>
     */
    public static function fromPlan(array $planRow, array $match): array
    {
        return [
            'sheet_row'         => $match['row'],
            'placement'         => $planRow['platz'],
            'name'              => $planRow['name_datei'],
            'club'              => $planRow['verein_datei'] !== '' ? $planRow['verein_datei'] : null,
            'federation'        => $planRow['verband_datei'] !== '' ? $planRow['verband_datei'] : null,
            'fencer_id'         => $match['fencer']->record?->id,
            'fencer_confidence' => $match['fencer']->confidence,
            'fencer_score'      => $match['fencer']->found() ? $match['fencer']->score : null,
            'group_id'          => $match['group']->record?->id,
            'group_confidence'  => $match['group']->confidence,
            'group_score'       => $match['group']->found() ? $match['group']->score : null,
            'action'            => $planRow['aktion'] !== '' ? $planRow['aktion'] : null,
            'note'              => $planRow['hinweis'] !== '' ? $planRow['hinweis'] : null,
        ];
    }

    /**
     * The row in the shape the writer reads.
     *
     * @return array<string, string>
     */
    public function toPlanRow(): array
    {
        return [
            'aktion'            => (string) $this->action,
            'datei'             => (string) $this->sheet?->original_name,
            'turnier'           => (string) $this->sheet?->tournament?->public_id,
            'turniersystem'     => (string) $this->sheet?->format,
            'platz'             => $this->placement,
            'name_datei'        => $this->name,
            'verein_datei'      => (string) $this->club,
            'verband_datei'     => (string) $this->federation,
            'fechter_id'        => (string) $this->fencer?->public_id,
            'fechter_vorschlag' => (string) $this->fencer?->display_name,
            'fechter_guete'     => (string) $this->fencer_confidence?->label(),
            'fechter_wert'      => $this->fencer_score === null ? '' : sprintf('%.2f', $this->fencer_score),
            'verein_id'         => (string) $this->group?->public_id,
            'verein_vorschlag'  => (string) $this->group?->name,
            'verein_guete'      => (string) $this->group_confidence?->label(),
            'verein_wert'       => $this->group_score === null ? '' : sprintf('%.2f', $this->group_score),
            'hinweis'           => (string) $this->note,
        ];
    }
}
