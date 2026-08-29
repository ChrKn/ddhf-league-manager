<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * A key that lets a request at /api through.
 *
 * The key itself is not in this table. What is stored is a digest of it, so a copy of the rows -
 * a dump on the wrong disk, a backup in a mail attachment - hands nobody a working key. The price
 * is that a key can be shown exactly once, at the moment it is made, and never again: whoever
 * loses one gets a new one rather than having the old one looked up.
 *
 * The digest is an HMAC keyed with APP_KEY rather than a plain hash, which has a second effect
 * this project wants. A row carried to another installation is worthless there, because the secret
 * it was made with stayed behind - see README, "What must not travel". The other side of that coin
 * is that changing APP_KEY on a running installation invalidates every key at once.
 *
 * Sixty-four characters out of an alphabet of thirty-two is three hundred and twenty bits, which is
 * why the digest needs no salt and no slow hash: there is nothing to guess.
 */
class ApiKey extends Model
{
    /** No lower case and none of the letters that read as digits, so a key survives being read aloud. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    private const LENGTH = 64;

    /** How much of a key stays in clear, so that a person can tell two rows apart. */
    private const PREFIX_LENGTH = 8;

    protected $table = 'api_keys';

    protected $fillable = [
        'name',
        'key',
        'scope',
        'is_active',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'is_active'    => 'boolean',
    ];

    /** Nothing about the digest belongs in a payload or an export. */
    protected $hidden = [
        'key_hash',
    ];

    /** The key in clear - set only on the instance that just made or received it. */
    private ?string $plain = null;

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (!$model->getAttribute('key_hash')) {
                $model->key = self::generate();
            }
        });
    }

    /**
     * Writable, readable only for as long as this instance is the one that set it.
     *
     * Assigning a key is how the digest and the prefix come about; there is no way to write either
     * of them directly, so the two can never drift apart. Reading it back on a record loaded from
     * the database gives null, which is the whole point of the table.
     */
    protected function key(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->plain,
            set: function (string $key): array {
                $this->plain = $key;

                return [
                    'key_hash'   => self::digest($key),
                    'key_prefix' => substr($key, 0, self::PREFIX_LENGTH),
                ];
            },
        );
    }

    /** A fresh key, in clear. Nobody stores this - the caller shows it once and lets go. */
    public static function generate(): string
    {
        $key = '';

        for ($i = 0; $i < self::LENGTH; $i++) {
            $key .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $key;
    }

    /**
     * What goes in the table in place of the key.
     *
     * Deterministic, so the lookup stays a single hit on a unique index rather than a walk through
     * every row. The migration that emptied the plaintext column calls this too.
     */
    public static function digest(string $key): string
    {
        return hash_hmac('sha256', $key, self::secret());
    }

    /** The active key a request presented, or null if it presented nothing we know. */
    public static function forToken(string $token): ?self
    {
        return static::query()
            ->where('key_hash', self::digest($token))
            ->where('is_active', true)
            ->first();
    }

    /**
     * Replace the key with a new one and hand it back.
     *
     * The way out of the one corner this design has: somebody lost the key they were given, and
     * there is no copy of it anywhere to give them again.
     */
    public function regenerate(): string
    {
        $this->key = $key = self::generate();
        $this->save();

        return $key;
    }

    private static function secret(): string
    {
        $secret = (string) config('app.key');

        return str_starts_with($secret, 'base64:')
            ? base64_decode(substr($secret, 7))
            : $secret;
    }
}
