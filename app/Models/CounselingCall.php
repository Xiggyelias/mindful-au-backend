<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CounselingCall extends Model
{
    protected $table = 'calls';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    /** Student placed the call (counselor sees incoming). */
    public const CALLER_STUDENT = 'student';

    /** Counselor placed the call (student sees incoming). */
    public const CALLER_COUNSELOR = 'counselor';

    protected $fillable = [
        'appointment_id',
        'student_id',
        'counselor_id',
        'status',
        'call_type',
        'caller_role',
    ];

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}
