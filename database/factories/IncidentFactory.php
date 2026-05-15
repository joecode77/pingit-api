<?php

// database/factories/IncidentFactory.php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startedAt = $this->faker->dateTimeBetween('-30 days', '-1 hour');
        $endedAt   = $this->faker->boolean(80)
            ? $this->faker->dateTimeBetween($startedAt, 'now')
            : null;

        $durationSeconds = $endedAt
            ? $endedAt->getTimestamp() - $startedAt->getTimestamp()
            : null;

        return [
            'monitor_id'       => Monitor::factory(),
            'started_at'       => $startedAt,
            'ended_at'         => $endedAt,
            'duration_seconds' => $durationSeconds,
        ];
    }
}