<?php

namespace Database\Factories;

use App\Models\MacroLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MacroLog>
 */
class MacroLogFactory extends Factory
{
    protected $model = MacroLog::class;

    public function definition(): array
    {
        return [
            'user_id'     => User::factory(),
            'logged_at'   => fake()->dateTimeBetween('-90 days', 'now')->format('Y-m-d'),
            'protein_g'   => fake()->randomFloat(2, 10, 100),
            'carbs_g'     => fake()->randomFloat(2, 20, 200),
            'fat_g'       => fake()->randomFloat(2, 5, 80),
            'description' => null,
        ];
    }
}
