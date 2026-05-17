<?php

// database/seeders/UserSeeder.php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@pingit.live'],
            [
                'name'     => 'Demo User',
                'email'    => 'demo@pingit.live',
                'password' => Hash::make('password'),
            ]
        );

        $this->command->info('Demo user created: demo@pingit.live / password');
    }
}
