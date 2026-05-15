<?php

// database/factories/MonitorCheckFactory.php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitorCheck>
 */
class MonitorCheckFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $isUp = $this->faker->boolean(90); // 90% chance of being up

        return [
            'monitor_id'       => Monitor::factory(),
            'status_code'      => $isUp ? $this->faker->randomElement([200, 201, 301, 302]) : $this->faker->randomElement([0, 400, 404, 500, 503]),
            'response_time_ms' => $isUp ? $this->faker->numberBetween(50, 2000) : null,
            'is_up'            => $isUp,
            'checked_at'       => $this->faker->dateTimeBetween('-30 days', 'now'),
        ];
    }
}