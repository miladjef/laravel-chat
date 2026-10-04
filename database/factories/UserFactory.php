<?php

namespace Database\Factories;

use App\Support\DisplayNameNormalizer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $displayName = mb_substr(fake()->unique()->userName(), 0, 16);

        return [
            'uuid' => (string) Str::uuid(),
            'display_name' => $displayName,
            'normalized_name' => DisplayNameNormalizer::normalize($displayName),
            'last_seen_at' => now(),
        ];
    }
}
