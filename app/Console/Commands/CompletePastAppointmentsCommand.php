<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Models\CounselingSession;
use App\Support\AppointmentCallWindow;
use App\Support\AppointmentCompletion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Completes appointments whose session window has closed.
 *
 * Hanging up a call no longer completes the appointment — the slot has to stay callable for
 * the whole session, so a dropped connection can be dialled again. That leaves completion to
 * this sweep: once the window is past, the session is genuinely over whether or not anybody
 * pressed a button.
 */
class CompletePastAppointmentsCommand extends Command
{
    protected $signature = 'appointments:complete-past
                            {--dry-run : Report what would change without writing anything}';

    protected $description = 'Complete appointments (and their video sessions) whose call window has closed';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        // Only look back far enough to catch windows that closed recently plus a generous
        // margin, so this stays cheap on a large table.
        $earliestStart = now()->subDays(2);

        $candidates = Appointment::query()
            ->whereIn('status', ['scheduled', 'confirmed'])
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', $earliestStart)
            ->where('scheduled_at', '<=', now())
            ->orderBy('id')
            ->get()
            ->filter(fn (Appointment $appointment) => ! AppointmentCallWindow::isOpen($appointment));

        $completed = 0;

        foreach ($candidates as $appointment) {
            if ($dryRun) {
                $this->line("would complete appointment #{$appointment->id}");
                $completed++;

                continue;
            }

            if ($this->completeAppointment($appointment)) {
                $completed++;
            }
        }

        $this->info(
            $dryRun
                ? "{$completed} appointment(s) would be completed."
                : "{$completed} appointment(s) completed."
        );

        return self::SUCCESS;
    }

    private function completeAppointment(Appointment $appointment): bool
    {
        return DB::transaction(function () use ($appointment) {
            $locked = Appointment::query()->lockForUpdate()->find($appointment->id);

            if (! $locked || ! in_array((string) $locked->status, ['scheduled', 'confirmed'], true)) {
                return false;
            }

            $session = CounselingSession::query()
                ->where('student_id', $locked->student_id)
                ->where('counselor_id', $locked->counselor_id)
                ->where('session_type', 'video')
                ->where('is_anonymous', (bool) $locked->is_anonymous)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            // Close out a session left active by the last hang-up before deciding whether the
            // appointment itself is finished — shouldComplete() only accepts a completed one.
            if ($session && in_array((string) $session->status, ['pending', 'active'], true)) {
                $session->update([
                    'status' => 'completed',
                    'started_at' => $session->started_at ?? $locked->scheduled_at,
                    'ended_at' => $session->ended_at ?? AppointmentCallWindow::closesAt($locked) ?? now(),
                ]);
                $session = $session->fresh();
            }

            if (! AppointmentCompletion::shouldComplete($locked, $session, true)) {
                return false;
            }

            $locked->update(['status' => 'completed']);

            Log::info('[Appointments] Completed after session window closed', [
                'appointment_id' => (int) $locked->id,
                'session_id' => $session ? (int) $session->id : null,
            ]);

            return true;
        });
    }
}
