<?php

namespace App\Events;

use App\Models\Message;
use App\Support\ChatMessageData;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageDeleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message->loadMissing('chatFile');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('session.'.$this->message->session_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.deleted';
    }

    public function broadcastWith(): array
    {
        return ChatMessageData::make($this->message);
    }
}
