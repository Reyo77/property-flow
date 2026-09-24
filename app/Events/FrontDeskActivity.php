<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * One activity line for the front-desk mode screen to show live: a package logged, a visitor
 * checked in, an incident filed, a key signed out, a patrol checkpoint scanned, and so on.
 */
class FrontDeskActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public readonly int $communityId,
        public readonly string $type,
        public readonly string $message,
    ) {}

    public function broadcastOn(): Channel
    {
        return new PrivateChannel("community.{$this->communityId}");
    }

    public function broadcastAs(): string
    {
        return 'front-desk-activity';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'type' => $this->type,
            'message' => $this->message,
            'occurred_at' => now()->toIso8601String(),
        ];
    }
}
