<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (!Schema::hasColumn('messages', 'case_id')) {
                $table->unsignedBigInteger('case_id')->nullable()->index('messages_case_id_index');
            }

            if (!Schema::hasColumn('messages', 'sender_role')) {
                $table->enum('sender_role', ['student', 'peer_counselor', 'counselor', 'admin'])
                    ->nullable()
                    ->index('messages_sender_role_index');
            }

            if (!Schema::hasColumn('messages', 'sender_name_snapshot')) {
                $table->string('sender_name_snapshot')->nullable();
            }
        });

        $this->backfillSnapshots();
    }

    public function down(): void
    {
        if (!Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (Schema::hasColumn('messages', 'sender_role')) {
                $table->dropIndex('messages_sender_role_index');
                $table->dropColumn('sender_role');
            }

            if (Schema::hasColumn('messages', 'sender_name_snapshot')) {
                $table->dropColumn('sender_name_snapshot');
            }

            if (Schema::hasColumn('messages', 'case_id')) {
                $table->dropIndex('messages_case_id_index');
                $table->dropColumn('case_id');
            }
        });
    }

    private function backfillSnapshots(): void
    {
        DB::table('messages')
            ->select(['id', 'session_id', 'sender_id'])
            ->orderBy('id')
            ->chunkById(500, function ($messages): void {
                $sessionIds = $messages->pluck('session_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();
                $senderIds = $messages->pluck('sender_id')->map(fn ($id) => (int) $id)->filter()->unique()->values();

                $sessions = DB::table('counseling_sessions')
                    ->whereIn('id', $sessionIds)
                    ->get(['id', 'student_id', 'counselor_id', 'peer_counselor_id'])
                    ->keyBy('id');

                $users = DB::table('users')
                    ->leftJoin('profiles', 'profiles.user_id', '=', 'users.id')
                    ->whereIn('users.id', $senderIds)
                    ->get(['users.id', 'users.email', 'profiles.full_name'])
                    ->keyBy('id');

                $roles = DB::table('user_roles')
                    ->whereIn('user_id', $senderIds)
                    ->where('approved', true)
                    ->get(['user_id', 'role'])
                    ->groupBy('user_id');

                foreach ($messages as $message) {
                    $senderId = (int) $message->sender_id;
                    $session = $sessions->get((int) $message->session_id);
                    $user = $users->get($senderId);
                    $userRoles = $roles->get($senderId, collect())->pluck('role')->map(fn ($role) => (string) $role)->all();
                    $senderRole = $this->resolveSenderRole($senderId, $session, $userRoles);
                    $senderName = $this->resolveSenderName($user, $senderRole, $senderId);

                    DB::table('messages')
                        ->where('id', (int) $message->id)
                        ->update([
                            'case_id' => (int) $message->session_id,
                            'sender_role' => $senderRole,
                            'sender_name_snapshot' => $senderName,
                        ]);
                }
            });
    }

    private function resolveSenderRole(int $senderId, mixed $session, array $roles): string
    {
        if ($session) {
            if ((int) $session->student_id === $senderId) {
                return 'student';
            }
            if ((int) ($session->peer_counselor_id ?? 0) === $senderId) {
                return 'peer_counselor';
            }
            if ((int) ($session->counselor_id ?? 0) === $senderId) {
                return 'counselor';
            }
        }

        foreach (['admin', 'counselor', 'peer_counselor', 'student'] as $role) {
            if (in_array($role, $roles, true)) {
                return $role;
            }
        }

        return 'student';
    }

    private function resolveSenderName(mixed $user, string $senderRole, int $senderId): string
    {
        $profileName = trim((string) ($user->full_name ?? ''));
        if ($profileName !== '') {
            return $profileName;
        }

        $email = trim((string) ($user->email ?? ''));
        if ($email !== '') {
            return Str::before($email, '@');
        }

        return match ($senderRole) {
            'admin' => 'Admin #' . $senderId,
            'counselor' => 'Counselor #' . $senderId,
            'peer_counselor' => 'Peer Counselor #' . $senderId,
            default => 'Student #' . $senderId,
        };
    }
};
