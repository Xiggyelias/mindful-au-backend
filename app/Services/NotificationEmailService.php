<?php

namespace App\Services;

use App\Mail\InAppNotificationMail;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class NotificationEmailService
{
    public function sendForNotification(Notification $notification): void
    {
        if (! $this->isEnabled() || $this->isExcluded($notification)) {
            return;
        }

        $notification->loadMissing('user.roles');
        $user = $notification->user;
        if (! $user instanceof User) {
            return;
        }

        if (! (bool) ($user->email_notifications_enabled ?? true)) {
            return;
        }

        $email = trim((string) $user->email);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        try {
            Mail::to($email)->queue(new InAppNotificationMail(
                $this->subjectFor($notification),
                $this->bodyFor($notification),
                $this->actionUrlFor($notification, $user),
            ));
        } catch (Throwable $exception) {
            Log::warning('notification email delivery failed', [
                'notification_id' => $notification->id,
                'user_id' => $notification->user_id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function isEnabled(): bool
    {
        return filter_var(config('notifications.email.enabled', false), FILTER_VALIDATE_BOOL);
    }

    private function isPrivacySafeMode(): bool
    {
        return filter_var(config('notifications.email.privacy_safe', true), FILTER_VALIDATE_BOOL);
    }

    private function isExcluded(Notification $notification): bool
    {
        $title = trim((string) $notification->title);
        foreach ((array) config('notifications.email.excluded_titles', []) as $excludedTitle) {
            if (strcasecmp($title, trim((string) $excludedTitle)) === 0) {
                return true;
            }
        }

        return false;
    }

    private function subjectFor(Notification $notification): string
    {
        if (! $this->isPrivacySafeMode()) {
            return $this->fallbackTitle($notification);
        }

        if ($this->isChatNotification($notification)) {
            return 'New Mindful AU message';
        }

        if ($this->isUrgentNotification($notification)) {
            return 'Urgent Mindful AU alert';
        }

        if ($this->isRiskNotification($notification)) {
            return 'Mindful AU wellness alert';
        }

        return $this->fallbackTitle($notification);
    }

    private function bodyFor(Notification $notification): string
    {
        if (! $this->isPrivacySafeMode()) {
            return $this->fallbackMessage($notification);
        }

        if ($this->isChatNotification($notification)) {
            return 'You have a new secure message in Mindful AU. Sign in to view and respond.';
        }

        if ($this->isUrgentNotification($notification)) {
            return 'An urgent support alert requires your attention in Mindful AU. Sign in to review details.';
        }

        if ($this->isRiskNotification($notification)) {
            return 'A wellness risk alert requires review in Mindful AU. Sign in to see the details and next steps.';
        }

        if ($this->isAppointmentNotification($notification)) {
            return 'There is an appointment update in Mindful AU. Sign in to view the details.';
        }

        return $this->fallbackMessage($notification);
    }

    private function actionUrlFor(Notification $notification, User $user): ?string
    {
        $meta = $this->meta($notification);
        $path = null;

        if (isset($meta['path']) && is_string($meta['path']) && trim($meta['path']) !== '') {
            $path = $meta['path'];
        } elseif ($this->isChatNotification($notification)) {
            $sessionId = (int) ($meta['chat_session_id'] ?? 0);
            $suffix = $sessionId > 0 ? '?session='.$sessionId : '';

            if ($user->hasRole('peer_counselor')) {
                $path = '/peer/chats'.$suffix;
            } elseif ($user->hasRole('counselor') || $user->hasRole('admin')) {
                $path = '/counselor/messages'.$suffix;
            } else {
                $path = '/student/chat'.$suffix;
            }
        } elseif ($this->isAppointmentNotification($notification)) {
            $path = $user->hasRole('counselor') || $user->hasRole('peer_counselor')
                ? '/counselor/appointments'
                : '/student/appointments';
        } elseif ($this->isUrgentNotification($notification) || $this->isRiskNotification($notification)) {
            if ($user->hasRole('admin')) {
                $path = '/admin/alerts';
            } elseif ($user->hasRole('counselor') || $user->hasRole('peer_counselor')) {
                $path = '/counselor/alerts';
            } else {
                $path = '/student/dashboard';
            }
        }

        $path ??= $this->defaultPathFor($user);

        return $this->absoluteFrontendUrl($path);
    }

    private function defaultPathFor(User $user): string
    {
        if ($user->hasRole('admin')) {
            return '/admin/dashboard';
        }

        if ($user->hasRole('peer_counselor')) {
            return '/peer/dashboard';
        }

        if ($user->hasRole('counselor')) {
            return '/counselor/dashboard';
        }

        return '/student/dashboard';
    }

    private function absoluteFrontendUrl(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $path) === 1) {
            return $path;
        }

        $base = rtrim((string) config('app.frontend_url'), '/');
        if ($base === '') {
            $base = rtrim((string) config('app.url'), '/');
        }

        if ($base === '') {
            return '/'.ltrim($path, '/');
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function fallbackTitle(Notification $notification): string
    {
        $title = trim((string) $notification->title);

        return $title !== '' ? $title : 'Mindful AU notification';
    }

    private function fallbackMessage(Notification $notification): string
    {
        $message = trim((string) $notification->message);

        return $message !== ''
            ? Str::limit($message, 400)
            : 'You have a new notification in Mindful AU.';
    }

    /**
     * @return array<string, mixed>
     */
    private function meta(Notification $notification): array
    {
        return is_array($notification->meta) ? $notification->meta : [];
    }

    private function isChatNotification(Notification $notification): bool
    {
        $meta = $this->meta($notification);

        return isset($meta['chat_session_id'])
            || isset($meta['chat_message_id'])
            || Str::contains(Str::lower((string) $notification->title), ['message']);
    }

    private function isAppointmentNotification(Notification $notification): bool
    {
        $meta = $this->meta($notification);
        $title = Str::lower((string) $notification->title);

        return isset($meta['appointment_id'])
            || Str::contains($title, ['appointment', 'session cancelled', 'session rescheduled']);
    }

    private function isUrgentNotification(Notification $notification): bool
    {
        $meta = $this->meta($notification);
        $title = Str::lower((string) $notification->title);

        return (string) $notification->type === 'panic'
            || isset($meta['emergency_request_id'])
            || Str::contains($title, ['emergency', 'panic', 'urgent']);
    }

    private function isRiskNotification(Notification $notification): bool
    {
        $title = Str::lower((string) $notification->title);

        return Str::contains($title, ['risk', 'diagnostic', 'assessment']);
    }
}
