<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Who may open the admin panel: anybody with an account.
     *
     * There are no roles yet, and an account is not something anybody can obtain - they are made
     * by hand with `php artisan make:filament-user`. Having one is therefore the whole
     * qualification, and this says so rather than leaving it to be inferred.
     *
     * It has to be said out loud. Filament's rule for a user model that does not implement this
     * interface is to allow access in `local` and refuse it everywhere else, so without this the
     * panel worked here and would have answered 403 to every single person on a deployed system,
     * including whoever set it up, on the first day.
     *
     * This is where readme point 5 begins: the question stops being "may you in" and becomes
     * "what may you do once you are".
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
