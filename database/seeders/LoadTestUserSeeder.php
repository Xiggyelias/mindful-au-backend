<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class LoadTestUserSeeder extends Seeder
{
    public function run(): void
    {
        $studentCount = max(1, (int) env('LOAD_TEST_STUDENTS', 20));
        $counselorCount = max(1, (int) env('LOAD_TEST_COUNSELORS', 5));
        $password = (string) env('LOAD_TEST_PASSWORD', 'password123');

        for ($i = 1; $i <= $counselorCount; $i++) {
            $email = sprintf('load.counselor%02d@example.com', $i);

            $user = User::updateOrCreate(
                ['email' => $email],
                ['password' => Hash::make($password)]
            );

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => sprintf('Load Counselor %02d', $i),
                    'id_number' => sprintf('LCO%04d', $i),
                ]
            );

            UserRole::updateOrCreate(
                ['user_id' => $user->id, 'role' => 'counselor'],
                ['approved' => true]
            );
        }

        for ($i = 1; $i <= $studentCount; $i++) {
            $email = sprintf('load.student%03d@example.com', $i);

            $user = User::updateOrCreate(
                ['email' => $email],
                ['password' => Hash::make($password)]
            );

            Profile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'full_name' => sprintf('Load Student %03d', $i),
                    'id_number' => sprintf('LST%05d', $i),
                ]
            );

            UserRole::updateOrCreate(
                ['user_id' => $user->id, 'role' => 'student'],
                ['approved' => true]
            );
        }

        $this->command?->info("Load test users prepared: {$studentCount} students, {$counselorCount} counselors.");
        $this->command?->info("Password: {$password}");
    }
}
