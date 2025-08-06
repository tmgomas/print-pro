<?php
// app/Events/PrintJobCompleted.php

namespace App\Events;

use App\Models\PrintJob;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PrintJobCompleted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public PrintJob $printJob;

    /**
     * Create a new event instance
     */
    public function __construct(PrintJob $printJob)
    {
        $this->printJob = $printJob->load([
            'invoice.customer', 
            'branch', 
            'assignedTo', 
            'productionStages'
        ]);
    }

    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('company.' . $this->printJob->company_id),
            new PrivateChannel('branch.' . $this->printJob->branch_id),
            new PrivateChannel('customer.' . $this->printJob->invoice->customer_id),
            new Channel('production-updates'),
            new Channel('delivery-notifications'),
        ];
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        $totalDuration = $this->printJob->productionStages
            ->where('actual_duration', '>', 0)
            ->sum('actual_duration');

        return [
            'event' => 'print-job-completed',
            'print_job' => [
                'id' => $this->printJob->id,
                'job_number' => $this->printJob->job_number,
                'job_type' => $this->printJob->job_type,
                'priority' => $this->printJob->priority,
                'production_status' => $this->printJob->production_status,
                'estimated_completion' => $this->printJob->estimated_completion?->format('Y-m-d H:i:s'),
                'actual_completion' => $this->printJob->actual_completion?->format('Y-m-d H:i:s'),
                'total_duration_minutes' => $totalDuration,
                'total_duration_formatted' => $this->formatDuration($totalDuration),
                'customer' => [
                    'id' => $this->printJob->invoice->customer->id,
                    'name' => $this->printJob->invoice->customer->name,
                    'email' => $this->printJob->invoice->customer->email,
                    'phone' => $this->printJob->invoice->customer->phone,
                ],
                'branch' => [
                    'id' => $this->printJob->branch->id,
                    'name' => $this->printJob->branch->branch_name,
                ],
                'assigned_to' => $this->printJob->assignedTo ? [
                    'id' => $this->printJob->assignedTo->id,
                    'name' => $this->printJob->assignedTo->name,
                ] : null,
                'invoice' => [
                    'id' => $this->printJob->invoice->id,
                    'invoice_number' => $this->printJob->invoice->invoice_number,
                    'total_amount' => $this->printJob->invoice->total_amount,
                ],
                'completed_stages' => $this->printJob->completed_stages,
                'total_stages' => $this->printJob->total_stages,
                'completion_percentage' => 100,
            ],
            'message' => "Print job {$this->printJob->job_number} has been completed and is ready for delivery",
            'timestamp' => now()->toISOString(),
            'requires_delivery' => true,
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'print-job.completed';
    }

    /**
     * Format duration in minutes to human readable format
     */
    private function formatDuration(?int $minutes): ?string
    {
        if (!$minutes) {
            return null;
        }

        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        $hours = intval($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($remainingMinutes === 0) {
            return $hours . ' hour' . ($hours > 1 ? 's' : '');
        }

        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ' . $remainingMinutes . ' minutes';
    }

    /**
     * Determine if this event should broadcast
     */
    public function shouldBroadcast(): bool
    {
        return config('broadcasting.default') !== 'null';
    }
}