<?php
// app/Events/PrintJobCreated.php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PrintJob $printJob;

    /**
     * Create a new event instance
     */
    public function __construct(PrintJob $printJob)
    {
        $this->printJob = $printJob->load(['invoice.customer', 'branch', 'assignedTo']);
    }

    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.' . $this->printJob->company_id),
            new PrivateChannel('branch.' . $this->printJob->branch_id),
            new Channel('production-updates'),
        ];
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'print-job-created',
            'print_job' => [
                'id' => $this->printJob->id,
                'job_number' => $this->printJob->job_number,
                'job_type' => $this->printJob->job_type,
                'priority' => $this->printJob->priority,
                'production_status' => $this->printJob->production_status,
                'estimated_completion' => $this->printJob->estimated_completion?->format('Y-m-d H:i:s'),
                'customer' => [
                    'id' => $this->printJob->invoice->customer->id,
                    'name' => $this->printJob->invoice->customer->name,
                ],
                'branch' => [
                    'id' => $this->printJob->branch->id,
                    'name' => $this->printJob->branch->branch_name,
                ],
                'assigned_to' => $this->printJob->assignedTo ? [
                    'id' => $this->printJob->assignedTo->id,
                    'name' => $this->printJob->assignedTo->name,
                ] : null,
                'total_stages' => $this->printJob->total_stages,
                'completed_stages' => $this->printJob->completed_stages,
            ],
            'message' => "New print job {$this->printJob->job_number} created for {$this->printJob->invoice->customer->name}",
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'print-job.created';
    }

    /**
     * Determine if this event should broadcast
     */
    public function shouldBroadcast(): bool
    {
        return config('broadcasting.default') !== 'null';
    }
}