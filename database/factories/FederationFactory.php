<?php

namespace Database\Factories;

use App\Models\Federation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Federation>
 */
class FederationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id'     => fake()->unique()->regexify('[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}'),
            'name'          => fake()->company(),
            'abbreviation'  => fake()->regexify('[A-Z]{3}'),
            'website_url'   => 'https://' . fake()->domainName(),
            'logo_url'      => 'https://' . fake()->domainName(),
        ];
    }
}
