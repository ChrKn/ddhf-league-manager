<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An alternative spelling under which a club appears in imported files.
 *
 * @see \App\Import\GroupResolver
 */
class GroupAlias extends Model
{
    protected $fillable = [
        'group_id',
        'alias',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }
}
