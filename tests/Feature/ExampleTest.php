<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    // The page counts what is on record, so it needs a database - it used to be a static
    // welcome screen that needed none.
    use RefreshDatabase;

    /**
     * That the application boots and answers at all.
     *
     * It used to point at the data browser's front page, which no longer answers to a request
     * with nobody behind it - the browser is part of the admin panel now. The root is the public
     * site, which is the thing anybody can ask for; what it shows is PublicSiteTest's business.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertStatus(200);
    }
}
