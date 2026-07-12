<?php

namespace Database\Factories;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        static $password;

        return [
            'external_id' => null,
            'uuid' => Uuid::uuid4()->toString(),
            'username' => $this->faker->userName.'_'.Str::random(10),
            'email' => Str::random(32).'@example.com',
            'name_first' => $this->faker->firstName,
            'name_last' => $this->faker->lastName,
            'password' => $password ?: $password = bcrypt('password'),
            'language' => 'en',
            'root_admin' => false,
            'use_totp' => false,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(['root_admin' => true]);
    }
}
