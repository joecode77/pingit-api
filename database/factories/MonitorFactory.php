<?php

// database/factories/MonitorFactory.php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Monitor>
 */
class MonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'                    => User::factory(),
            'name'                       => $this->faker->company(),
            'url'                        => $this->faker->unique()->url(),
            'check_interval'             => $this->faker->numberBetween(1, 60),
            'threshold'                  => $this->faker->numberBetween(1, 5),
            'response_time_threshold_ms' => null,
            'http_method'                => 'GET',
            'follow_redirects'           => true,
            'custom_headers'             => null,
            'status'                     => 'pending',
            'is_checking'                => false,
            'consecutive_failures'       => 0,
            'last_checked_at'            => null,
            'next_check_at'              => null,
            'last_notified_at'           => null,
        ];
    }
}