<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class CounselingSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'counselor_id',
        'peer_counselor_id',
        'assigned_by',
        'assigned_role',
        'is_anonymous',
        'anonymous_id',
        'identity_revealed_at',
        'identity_revealed_by',
        'status',
        'session_type',
        'notes',
        'ai_summary',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'identity_revealed_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public static function syncOpenStudentChatsAnonymity(int $studentUserId, bool $isAnonymous): void
    {
        $sessions = static::query()
            ->where('student_id', $studentUserId)
            ->where('session_type', 'chat')
            ->whereIn('status', ['pending', 'active'])
            ->get();

        foreach ($sessions as $session) {
            $session->is_anonymous = $isAnonymous;
            if ($isAnonymous) {
                if ($session->anonymous_id === null || trim((string) $session->anonymous_id) === '') {
                    $session->anonymous_id = static::generateUniqueAnonymousId();
                }
            } else {
                $session->anonymous_id = null;
            }
            $session->save();
        }
    }

    /**
     * Unique handle shown to counselors for anonymous threads (matches SessionController semantics).
     */
    public static function generateUniqueAnonymousId(): string
    {
        do {
            $candidate = 'User_' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        } while (static::query()->where('anonymous_id', $candidate)->exists());

        return $candidate;
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function peerCounselor()
    {
        return $this->belongsTo(User::class, 'peer_counselor_id');
    }

    public function assignedByUser()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function identityRevealedByUser()
    {
        return $this->belongsTo(User::class, 'identity_revealed_by');
    }

    public function messages()
    {
        return $this->hasMany(Message::class, 'session_id');
    }

    public function aiDiagnostics()
    {
        return $this->hasMany(AiDiagnostic::class, 'session_id');
    }

    public function setNotesAttribute($value): void
    {
        $this->attributes['notes'] = $this->encryptSensitiveValue($value);
    }

    public function getNotesAttribute($value): ?string
    {
        return $this->decryptSensitiveValue($value);
    }

    public function setAiSummaryAttribute($value): void
    {
        $this->attributes['ai_summary'] = $this->encryptSensitiveValue($value);
    }

    public function getAiSummaryAttribute($value): ?string
    {
        return $this->decryptSensitiveValue($value);
    }

    private function encryptSensitiveValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;
        if ($stringValue === '') {
            return '';
        }

        if (str_starts_with($stringValue, 'enc::')) {
            return $stringValue;
        }

        return 'enc::' . Crypt::encryptString($stringValue);
    }

    private function decryptSensitiveValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $stringValue = (string) $value;
        if ($stringValue === '' || !str_starts_with($stringValue, 'enc::')) {
            return $stringValue;
        }

        try {
            return Crypt::decryptString(substr($stringValue, 5));
        } catch (\Throwable $exception) {
            Log::warning('Unable to decrypt encrypted counseling session field.', [
                'session_id' => $this->id,
                'field' => 'sensitive',
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
