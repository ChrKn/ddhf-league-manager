<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Somebody who may open the admin panel.
     *
     * The data browser used to answer to anybody who knew the address; it lives inside the panel
     * now, so a test that wants to look at it has to say who is looking. Here rather than in each
     * class because six of them do.
     */
    protected function actingAsMaintainer(): User
    {
        $user = User::create([
            'name'     => 'Prüferin',
            'email'    => 'pruefung@example.test',
            'password' => 'geheim',
        ]);

        $this->actingAs($user);

        return $user;
    }
}
