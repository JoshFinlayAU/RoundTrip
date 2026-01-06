<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PingResultRecorded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $targetId,
        public string $ts,
        public ?float $minMs,
        public ?float $avgMs,
        public ?float $maxMs,
        public ?float $lossPct,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('targets'),
            new Channel("target.{$this->targetId}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ping.recorded';
    }

    public function broadcastWith(): array
    {
        return [
            'target_id' => $this->targetId,
            'ts' => $this->ts,
            'min_ms' => $this->minMs,
            'avg_ms' => $this->avgMs,
            'max_ms' => $this->maxMs,
            'loss_pct' => $this->lossPct,
        ];
    }
}
