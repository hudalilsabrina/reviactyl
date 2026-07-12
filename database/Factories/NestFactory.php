<?php

namespace Database\Factories;

use App\Models\Nest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Ramsey\Uuid\Uuid;

class NestFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Nest::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'uuid' => Uuid::uuid4()->toString(),
            'author' => 'testauthor@example.com',
            'name' => $this->faker->word,
            'description' => null,
        ];
    }
}
