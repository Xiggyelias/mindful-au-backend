<?php

namespace App\Events;

use App\Models\CounselingSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SessionStarted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $session;

    public function __construct(CounselingSession $session)
    {
        $this->session = $session->load(['student', 'counselor']);
    }

    public function broadcastOn(): array
    {
        $channels = [
            new PrivateChannel('user.'.$this->session->student_id),
        ];

        if ($this->session->counselor_id) {
            $channels[] = new PrivateChannel('user.'.$this->session->counselor_id);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'session.started';
    }
}
