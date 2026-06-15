<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('password123');

        $student = User::updateOrCreate(
            ['email' => 'student@example.com'],
            ['password' => $passwordHash]
        );
        Profile::updateOrCreate(
            ['user_id' => $student->id],
            ['full_name' => 'Test Student', 'id_number' => 'STU001']
        );
        UserRole::updateOrCreate(
            ['user_id' => $student->id, 'role' => 'student'],
            ['approved' => true]
        );

        $counselor = User::updateOrCreate(
            ['email' => 'counselor@example.com'],
            ['password' => $passwordHash]
        );
        Profile::updateOrCreate(
            ['user_id' => $counselor->id],
            ['full_name' => 'Test Counselor', 'id_number' => 'COUN001']
        );
        UserRole::updateOrCreate(
            ['user_id' => $counselor->id, 'role' => 'counselor'],
            ['approved' => true]
        );

        $peerCounselorSeeded = false;
        if ($this->supportsRole('peer_counselor')) {
            $peerCounselor = User::updateOrCreate(
                ['email' => 'peer.counselor@example.com'],
                ['password' => $passwordHash]
            );
            Profile::updateOrCreate(
                ['user_id' => $peerCounselor->id],
                ['full_name' => 'Test Peer Counselor', 'id_number' => 'PEER001']
            );
            UserRole::updateOrCreate(
                ['user_id' => $peerCounselor->id, 'role' => 'peer_counselor'],
                ['approved' => true]
            );
            $peerCounselorSeeded = true;
        }

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            ['password' => $passwordHash]
        );
        Profile::updateOrCreate(
            ['user_id' => $admin->id],
            ['full_name' => 'Test Admin', 'id_number' => 'ADM001']
        );
        UserRole::updateOrCreate(
            ['user_id' => $admin->id, 'role' => 'admin'],
            ['approved' => true]
        );

        echo "Test users created successfully.\n";
        echo "  Student: student@example.com / password123\n";
        echo "  Counselor: counselor@example.com / password123\n";
        if ($peerCounselorSeeded) {
            echo "  Peer Counselor: peer.counselor@example.com / password123\n";
        } else {
            echo "  Peer Counselor: skipped for current database schema\n";
        }
        echo "  Admin: admin@example.com / password123\n";
    }

    private function supportsRole(string $role): bool
    {
        $driver = (string) DB::connection()->getDriverName();
        if ($driver !== 'sqlite') {
            return true;
        }

        $tableDefinition = DB::table('sqlite_master')
            ->where('type', 'table')
            ->where('name', 'user_roles')
            ->value('sql');

        if (! is_string($tableDefinition) || trim($tableDefinition) === '') {
            return true;
        }

        return str_contains(strtolower($tableDefinition), "'".strtolower($role)."'");
    }
}
