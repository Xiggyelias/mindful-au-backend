<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;

class ApproveCounselorRole extends Command
{
    protected $signature = 'counselor:approve {email}';

    protected $description = 'Approve a counselor role by email';

    public function handle()
    {
        $email = $this->argument('email');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User not found: $email");

            return 1;
        }

        $role = UserRole::where('user_id', $user->id)
            ->where('role', 'counselor')
            ->first();

        if (! $role) {
            $this->error("Counselor role not found for: $email");

            return 1;
        }

        $role->update(['approved' => true]);

        $this->info("✓ Counselor role approved for: $email");

        return 0;
    }
}
