<?php

namespace Database\Factories;

use App\Models\Part;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Part>
 */
class PartFactory extends Factory
{
    protected $model = Part::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $labelSpecs = [
            'M83519/2-8',
            'M83519/2-12',
            'SAE AS81824/1-2',
            'SAE AS81824/2-4',
            'NAS1745-15',
            'NAS1745-20',
            'MS27488-20',
            'MS27488-16',
        ];

        return [
            'number' => 'PART-' . $this->faker->unique()->numerify('######'),
            'item_number' => 'ITEM-' . $this->faker->unique()->numerify('######'),
            'unit_of_measure' => $this->faker->randomElement(['PZA', 'KG', 'M', 'L', 'UN']),
            'active' => $this->faker->boolean(80),
            'description' => $this->faker->sentence(),
            'notes' => $this->faker->optional()->sentence(),
            'label_spec' => $this->faker->optional(0.6)->randomElement($labelSpecs),
        ];
    }

    /**
     * Indicate that the part is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => true,
        ]);
    }

    /**
     * Indicate that the part is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }
}
