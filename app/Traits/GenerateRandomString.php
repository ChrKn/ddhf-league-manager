<?php

namespace App\Traits;

use Random\RandomException;

trait GenerateRandomString
{
    /**
     * Generates a random string for the model. Length is defined in the
     * class constant RANDOMIZER_LENGTH. The field that should receive the
     * random string is defined in the class constant RANDOMIZER_FIELD.
     *
     * @return void
     * @throws RandomException
     */
    protected static function bootGenerateRandomString(): void
    {
        static::creating(function ($model) {
            $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            $length = defined('static::RANDOMIZER_LENGTH') ? static::RANDOMIZER_LENGTH : 8;
            $field = defined('static::RANDOMIZER_FIELD') ? static::RANDOMIZER_FIELD : 'public_id';

            if (!empty($model->$field)) {
                return;
            }

            do {
                $code = '';
                for ($i = 0; $i < $length; $i++) {
                    $code .= $characters[random_int(0, strlen($characters) - 1)];
                }
            } while (static::where($field, $code)->exists());

            $model->$field = $code;
        });
    }
}
