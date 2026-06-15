<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Message extends Model
{
    use HasFactory;

    private static ?array $databaseColumns = null;

    protected static function booted(): void
    {
        static::creating(function (self $message): void {
            $hasCaseId = self::databaseHasColumn('case_id');
            $hasSenderRole = self::databaseHasColumn('sender_role');
            $hasSenderNameSnapshot = self::databaseHasColumn('sender_name_snapshot');

            if ($hasCaseId && empty($message->case_id) && ! empty($message->session_id)) {
                $message->case_id = (int) $message->session_id;
            }

            if (
                (! $hasSenderRole || ! empty($message->sender_role))
                && (! $hasSenderNameSnapshot || ! empty($message->sender_name_snapshot))
            ) {
                return;
            }

            $session = $message->relationLoaded('session')
                ? $message->session
                : CounselingSession::query()->find($message->session_id);
            $sender = $message->relationLoaded('sender')
                ? $message->sender
                : User::query()->with(['profile', 'roles'])->find($message->sender_id);

            if ($hasSenderRole && empty($message->sender_role)) {
                $message->sender_role = self::resolveSenderRoleSnapshot($sender, $session, (int) $message->sender_id);
            }

            if ($hasSenderNameSnapshot && empty($message->sender_name_snapshot)) {
                $message->sender_name_snapshot = self::resolveSenderNameSnapshot($sender, (string) $message->sender_role);
            }
        });

        static::created(function (self $message): void {
            CounselingSession::query()->whereKey((int) $message->session_id)->update([
                'updated_at' => now(),
            ]);
        });
    }

    protected $fillable = [
        'case_id',
        'session_id',
        'sender_id',
        'sender_role',
        'sender_name_snapshot',
        'recipient_id',
        'content',
        'message_type',
        'file_url',
        'has_file',
        'is_encrypted',
        'sent_as_anonymous',
        'seen_at',
    ];

    protected $casts = [
        'has_file' => 'boolean',
        'is_encrypted' => 'boolean',
        'seen_at' => 'datetime',
    ];

    public static function databaseHasColumn(string $column): bool
    {
        if (self::$databaseColumns === null) {
            try {
                self::$databaseColumns = Schema::getColumnListing((new self)->getTable());
            } catch (\Throwable) {
                self::$databaseColumns = [];
            }
        }

        return in_array($column, self::$databaseColumns, true);
    }

    public static function selectableColumns(array $columns): array
    {
        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => self::databaseHasColumn($column)
        ));
    }

    private static function resolveSenderRoleSnapshot(?User $sender, ?CounselingSession $session, int $senderId): string
    {
        if ($session) {
            if ((int) $session->student_id === $senderId) {
                return 'student';
            }
            if ((int) $session->peer_counselor_id === $senderId) {
                return 'peer_counselor';
            }
            if ((int) $session->counselor_id === $senderId) {
                return 'counselor';
            }
        }

        if ($sender?->hasRole('admin')) {
            return 'admin';
        }
        if ($sender?->hasRole('counselor')) {
            return 'counselor';
        }
        if ($sender?->hasRole('peer_counselor')) {
            return 'peer_counselor';
        }

        return 'student';
    }

    private static function resolveSenderNameSnapshot(?User $sender, string $senderRole): string
    {
        $profileName = trim((string) ($sender?->profile?->full_name ?? ''));
        if ($profileName !== '') {
            return $profileName;
        }

        $email = trim((string) ($sender?->email ?? ''));
        if ($email !== '') {
            return Str::before($email, '@');
        }

        return match ($senderRole) {
            'admin' => 'Admin',
            'counselor' => 'Counselor',
            'peer_counselor' => 'Peer Counselor',
            default => 'Student',
        };
    }

    public function session()
    {
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function chatFile()
    {
        return $this->hasOne(ChatFile::class, 'message_id');
    }

    public function getSentAsAnonymousAttribute(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($normalized !== null) {
            return $normalized;
        }

        return (bool) (int) $value;
    }
}
