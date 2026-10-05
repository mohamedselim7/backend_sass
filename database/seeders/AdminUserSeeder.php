<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@iden.app');
        $password = env('ADMIN_PASSWORD');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'iden Admin'),
                // Without ADMIN_PASSWORD the account is created locked and must
                // go through password reset — no default credential ever ships.
                'password' => $password ?: Str::random(40),
                'must_set_password' => $password === null,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(['admin']);

        if (! $password) {
            $this->command?->warn("Admin {$email} created without a password. Use the reset flow or set ADMIN_PASSWORD.");
        }
    }
}
