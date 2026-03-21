<?php

namespace App\Console\Commands;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    protected $signature = 'admin:create
        {email? : Admin email address}
        {password? : Admin password}
        {--name= : Admin full name}
        {--id-number= : Admin ID number}';

    protected $description = 'Create or update an approved admin account';

    public function handle(): int
    {
        $email = $this->resolveEmail();
        $password = $this->resolvePassword();
        $name = $this->resolveName($email);
        $idNumber = $this->resolveIdNumber();

        if ($email === null || $password === null) {
            $this->error('Admin email and password are required. Pass them as arguments or set ADMIN_BOOTSTRAP_EMAIL and ADMIN_BOOTSTRAP_PASSWORD in backend/.env.');
            return self::FAILURE;
        }

        $user = User::query()->firstOrNew([
            'email' => $email,
        ]);

        $isNewUser = !$user->exists;

        $user->password = Hash::make($password);
        $user->last_seen_at = now();
        $user->save();

        Profile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $name,
                'id_number' => $idNumber,
                'anonymous_mode' => false,
            ]
        );

        UserRole::query()->updateOrCreate(
            ['user_id' => $user->id, 'role' => 'admin'],
            ['approved' => true]
        );

        $this->info($isNewUser ? 'Admin account created.' : 'Admin account updated.');
        $this->line("Email: {$email}");
        $this->line("Password: {$password}");
        $this->line("Name: {$name}");
        $this->line("ID Number: {$idNumber}");

        return self::SUCCESS;
    }

    private function resolveEmail(): ?string
    {
        $value = $this->argument('email') ?: env('ADMIN_BOOTSTRAP_EMAIL');
        $value = Str::lower(trim((string) $value));

        return filter_var($value, FILTER_VALIDATE_EMAIL) ? $value : null;
    }

    private function resolvePassword(): ?string
    {
        $value = trim((string) ($this->argument('password') ?: env('ADMIN_BOOTSTRAP_PASSWORD', '')));

        return $value !== '' ? $value : null;
    }

    private function resolveName(string $email): string
    {
        $optionName = trim((string) $this->option('name'));
        if ($optionName !== '') {
            return $optionName;
        }

        $envName = trim((string) env('ADMIN_BOOTSTRAP_NAME', ''));
        if ($envName !== '') {
            return $envName;
        }

        $localPart = Str::before($email, '@');
        $fallback = Str::of($localPart)
            ->replace(['.', '_', '-'], ' ')
            ->title()
            ->value();

        return $fallback !== '' ? $fallback : 'Administrator';
    }

    private function resolveIdNumber(): string
    {
        $optionId = trim((string) $this->option('id-number'));
        if ($optionId !== '') {
            return $optionId;
        }

        $envId = trim((string) env('ADMIN_BOOTSTRAP_ID_NUMBER', 'ADM001'));
        return $envId !== '' ? $envId : 'ADM001';
    }
}
