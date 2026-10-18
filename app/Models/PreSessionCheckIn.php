<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PreSessionCheckIn extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'counselor_id',
        'appointment_id',
        'session_id',
        'mood',
        'mood_score',
        'energy_level',
        'stress_level',
        'primary_concerns',
        'session_goal',
        'student_notes',
        'is_crisis',
    ];

    protected $casts = [
        'mood_score' => 'integer',
        'energy_level' => 'integer',
        'stress_level' => 'integer',
        'primary_concerns' => 'array',
        'is_crisis' => 'boolean',
    ];

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function session()
    {
        return $this->belongsTo(CounselingSession::class, 'session_id');
    }

    /**
     * Generate clinical subjective note format for counselor's ease of documentation.
     */
    public function toClinicalSummary(): string
    {
        $concerns = ! empty($this->primary_concerns) ? implode(', ', $this->primary_concerns) : 'General check-in';
        $goalLabels = [
            'coping_tools' => 'Practical coping tools & strategies',
            'venting' => 'Safe space to express feelings & be heard',
            'action_plan' => 'Guidance & action plan on a specific challenge',
            'grounding' => 'Grounding & emotional de-escalation',
        ];
        $goal = $goalLabels[$this->session_goal] ?? ($this->session_goal ?: 'General exploration');

        $lines = [
            "S (Pre-Session Pulse): Mood reported as '{$this->mood}' (Score {$this->mood_score}/5).",
            "Energy: {$this->energy_level}/5 | Stress: {$this->stress_level}/5.",
            "Primary Concerns: {$concerns}.",
            "Desired Session Goal: {$goal}.",
        ];

        if (! empty($this->student_notes)) {
            $lines[] = 'Student Note: "'.trim($this->student_notes).'"';
        }

        if ($this->is_crisis) {
            $lines[] = '⚠️ SAFETY ALERT: Student marked state as elevated crisis.';
        }

        return implode("\n", $lines);
    }
}
