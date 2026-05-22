<?php

namespace Database\Factories;

use App\Models\StatusWO;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusWO>
 */
class StatusWOFactory extends Factory
{
    protected $model = StatusWO::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(2, true),
            'color' => $this->faker->hexColor(),
            'comments' => $this->faker->optional()->sentence(),
        ];
    }
}
