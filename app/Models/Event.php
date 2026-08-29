<?php

namespace App\Models;

use App\Traits\GenerateRandomString;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    use GenerateRandomString;

    protected $fillable = [
        'public_id',
        'name',
        'start_date',
        'end_date',
        'location',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Group::class);
    }

    public function tournaments(): HasMany
    {
        return $this->hasMany(Tournament::class);
    }

    public function getDisplayNameAttribute(): string
    {
        $date = Carbon::createFromFormat ('Y-m-d H:i:s', $this->start_date ?? '1900-01-01 00:00:00');
        $display_name = $this->name ?? '?';
        $display_name .= ' (' . $date->format('Y') . ')';

        return $display_name;
    }
}
