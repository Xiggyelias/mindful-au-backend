<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class UpdateUserPassword extends Command
{
    protected $signature = 'user:update-password {email} {password}';

    protected $description = 'Update a user password';

    public function handle()
    {
        $email = $this->argument('email');
        $password = $this->argument('password');

        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: $email");

            return 1;
        }

        $user->update(['password' => Hash::make($password)]);

        $this->info("✓ Password updated for: $email");
        $this->info('✓ Password hash: '.substr($user->password, 0, 20).'...');

        return 0;
    }
}
