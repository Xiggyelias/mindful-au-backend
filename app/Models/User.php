<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Per-request cache to avoid repeated role queries.
     *
     * @var array{approved: array<string, bool>}|null
     */
    protected ?array $roleLookupCache = null;

    protected $fillable = [
        'email',
        'password',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'password' => 'hashed',
        'web_push_enabled' => 'boolean',
    ];

    public function profile()
    {
        return $this->hasOne(Profile::class);
    }

    public function getAnonymousName(): string
    {
        return 'Anonymous #' . str_pad((string) (($this->id ?? 0) % 10000), 4, '0', STR_PAD_LEFT);
    }

    public function roles()
    {
        return $this->hasMany(UserRole::class);
    }

    public function hasRole(string $role): bool
    {
        $lookup = $this->getRoleLookup();
        return isset($lookup['approved'][$role]);
    }

    /**
     * @return array{approved: array<string, bool>}
     */
    private function getRoleLookup(): array
    {
        if ($this->roleLookupCache !== null) {
            return $this->roleLookupCache;
        }

        $roleRows = $this->relationLoaded('roles')
            ? $this->roles
            : $this->roles()->get(['role', 'approved']);

        $approved = [];

        foreach ($roleRows as $roleRow) {
            $name = trim((string) ($roleRow->role ?? ''));
            if ($name === '') {
                continue;
            }

            if ((bool) ($roleRow->approved ?? false)) {
                $approved[$name] = true;
            }
        }

        $this->roleLookupCache = [
            'approved' => $approved,
        ];

        return $this->roleLookupCache;
    }

    public function studentSessions()
    {
        return $this->hasMany(CounselingSession::class, 'student_id');
    }

    public function counselorSessions()
    {
        return $this->hasMany(CounselingSession::class, 'counselor_id');
    }

    public function sentMessages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function appointmentsAsStudent()
    {
        return $this->hasMany(Appointment::class, 'student_id');
    }

    public function appointmentsAsCounselor()
    {
        return $this->hasMany(Appointment::class, 'counselor_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function pushSubscriptions()
    {
        return $this->hasMany(PushSubscription::class);
    }

    public function twoFactorMethods()
    {
        return $this->hasMany(UserTwoFactorMethod::class);
    }

    public function intakeSubmissions()
    {
        return $this->hasMany(IntakeSubmission::class);
    }

    public function referralsAsStudent()
    {
        return $this->hasMany(Referral::class, 'student_id');
    }

    public function referralsCreated()
    {
        return $this->hasMany(Referral::class, 'referred_by');
    }

    public function moodLogs()
    {
        return $this->hasMany(StudentMoodLog::class, 'student_id');
    }

    public function tipDeliveries()
    {
        return $this->hasMany(TipDelivery::class);
    }

    public function tipFavorites()
    {
        return $this->hasMany(TipFavorite::class);
    }
}
