<?php

namespace App\Models;

use App\Traits\GenerateRandomString;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringMatrix extends Model
{
    use GenerateRandomString;

    const RANDOMIZER_FIELD = 'public_id';

    const RANDOMIZER_LENGTH = 4;

    protected $fillable = [
        'name',
        'public_id',
        'matrix',
    ];

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class);
    }
}
