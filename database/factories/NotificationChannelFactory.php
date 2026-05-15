<?php

// database/factories/NotificationChannelFactory.php

namespace Database\Factories;

use App\Models\Monitor;
use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationChannel>
 */
class NotificationChannelFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['email', 'webhook']);

        return [
            'monitor_id'         => Monitor::factory(),
            'type'               => $type,
            'value'              => $type === 'email'
                ? $this->faker->safeEmail()
                : $this->faker->url(),
            'notify_on_down'     => true,
            'notify_on_recovery' => true,
            'notify_on_degraded' => false,
        ];
    }
}