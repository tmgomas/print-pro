<?php
// app/Events/ProductionStageUpdated.php

namespace App\Events;

use App\Models\ProductionStage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductionStageUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ProductionStage $stage;

    /**
     * Create a new event instance
     */
    public function __construct(ProductionStage $stage)
    {
        $this->stage = $stage->load(['printJob.invoice.customer', 'printJob.branch', 'updatedBy']);
    }

    /**
     * Get the channels the event should broadcast on
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('print-job.' . $this->stage->print_job_id),
            new PrivateChannel('branch.' . $this->stage->printJob->branch_id),
            new PrivateChannel('company.' . $this->stage->printJob->company_id),
            new Channel('production-updates'),
        ];
    }

    /**
     * Get the data to broadcast
     */
    public function broadcastWith(): array
    {
        return [
            'event' => 'stage-updated',
            'stage' => [
                'id' => $this->stage->id,
                'stage_name' => $this->stage->stage_name,
                'stage_name_label' => $this->stage->stage_name_label,
                'stage_status' => $this->stage->stage_status,
                'stage_order' => $this->stage->stage_order,
                'started_at' => $this->stage->started_at?->format('Y-m-d H:i:s'),
                'completed_at' => $this->stage->completed_at?->format('Y-m-d H:i:s'),
                'estimated_duration' => $this->stage->estimated_duration,
                'actual_duration' => $this->stage->actual_duration,
                'requires_customer_approval' => $this->stage->requires_customer_approval,
                'is_overdue' => $this->stage->is_overdue,
                'updated_by' => $this->stage->updatedBy ? [
                    'id' => $this->stage->updatedBy->id,
                    'name' => $this->stage->updatedBy->name,
                ] : null,
            ],
            'print_job' => [
                'id' => $this->stage->printJob->id,
                'job_number' => $this->stage->printJob->job_number,
                'production_status' => $this->stage->printJob->production_status,
                'completed_stages' => $this->stage->printJob->completed_stages,
                'total_stages' => $this->stage->printJob->total_stages,
                'customer' => [
                    'id' => $this->stage->printJob->invoice->customer->id,
                    'name' => $this->stage->printJob->invoice->customer->name,
                ],
            ],
            'message' => $this->generateMessage(),
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * The event's broadcast name
     */
    public function broadcastAs(): string
    {
        return 'production-stage.updated';
    }

    /**
     * Generate appropriate message based on stage status
     */
    private function generateMessage(): string
    {
        $stageName = $this->stage->stage_name_label;
        $jobNumber = $this->stage->printJob->job_number;
        $status = $this->stage->stage_status;

        return match($status) {
            'in_progress' => "Stage '{$stageName}' started for job {$jobNumber}",
            'completed' => "Stage '{$stageName}' completed for job {$jobNumber}",
            'on_hold' => "Stage '{$stageName}' put on hold for job {$jobNumber}",
            'requires_approval' => "Stage '{$stageName}' requires approval for job {$jobNumber}",
            'rejected' => "Stage '{$stageName}' rejected for job {$jobNumber}",
            'skipped' => "Stage '{$stageName}' skipped for job {$jobNumber}",
            default => "Stage '{$stageName}' updated for job {$jobNumber}",
        };
    }

    /**
     * Determine if this event should broadcast
     */
    public function shouldBroadcast(): bool
    {
        return config('broadcasting.default') !== 'null';
    }
}