<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $plainPassword = 'admin@private';

        $user = User::updateOrCreate(
            ['email' => 'admin@vytech.co'],
            [
                'password' => Hash::make($plainPassword),
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'is_active' => true,
            ]
        );

        $user->assignRole('Super Admin');

        if (! Hash::check($plainPassword, $user->password)) {
            $message = 'Warning: seeded admin password verification failed.';
            $this->command?->warn($message);

            throw new RuntimeException($message);
        }
    }
}
