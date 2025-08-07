<?php
// app/Services/PrintJobService.php

namespace App\Services;

use App\Models\PrintJob;
use App\Models\Invoice;
use App\Repositories\PrintJobRepository;
use App\Repositories\ProductionStageRepository;
use App\Events\PrintJobCreated;
use App\Events\ProductionStageUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class PrintJobService extends BaseService
{
    protected ProductionStageRepository $stageRepository;

    public function __construct(
        PrintJobRepository $repository,
        ProductionStageRepository $stageRepository
    ) {
        parent::__construct($repository);
        $this->stageRepository = $stageRepository;
    }

    
/**
 * Start production for a print job with complete workflow
 */
public function startProduction(PrintJob $printJob): bool
{
     
    try {
        return DB::transaction(function () use ($printJob) {
           
            // 1. Update print job status
            $updated = $this->repository->update($printJob->id, [
                'production_status' => 'design_review',
                'started_at' => now(),
                'production_notes' => ($printJob->production_notes ?? '') . 
                    "\n" . now()->format('Y-m-d H:i:s') . ": Production started by " . auth()->user()->name
            ]);

            if (!$updated) {
                throw new \Exception('Failed to update print job status');
            }

            // Refresh the model to get latest data
            $printJob = $printJob->fresh(['productionStages']);

            // 2. Handle production stages
            if ($printJob->productionStages->isEmpty()) {
                // Create default stages if none exist
                $this->createProductionStages($printJob);
                $printJob = $printJob->fresh(['productionStages']);
            }

            // 3. Start the first production stage
            $firstStage = $printJob->productionStages()
                ->where('stage_status', 'pending')
                ->orderBy('stage_order')
                ->first();

            if ($firstStage) {
                // Update first stage to "ready" (ready to start)
                $stageUpdated = $this->stageRepository->update($firstStage->id, [
                    'stage_status' => 'ready',
                    'updated_by' => auth()->id(),
                    'notes' => ($firstStage->notes ?? '') . 
                        "\n" . now()->format('Y-m-d H:i:s') . ": Stage ready for production - " . 
                        $firstStage->stage_name
                ]);

                if (!$stageUpdated) {
                    throw new \Exception('Failed to start production stage');
                }

                \Log::info('Production started successfully', [
                    'print_job_id' => $printJob->id,
                    'first_stage_id' => $firstStage->id,
                    'first_stage_name' => $firstStage->stage_name,
                    'user_id' => auth()->id()
                ]);
            } else {
                \Log::warning('No production stages found for print job', [
                    'print_job_id' => $printJob->id
                ]);
            }

            // 4. Fire production started event (if event exists)
            try {
                if (class_exists('\App\Events\ProductionStarted')) {
                    event(new \App\Events\ProductionStarted($printJob->fresh()));
                }
            } catch (\Exception $e) {
                // Don't fail the entire operation if event fails
                \Log::warning('Failed to fire ProductionStarted event', [
                    'error' => $e->getMessage()
                ]);
            }

            return true;
        });
    } catch (\Exception $e) {
        \Log::error('Production start failed', [
            'print_job_id' => $printJob->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        
        throw $e;
    }
}

/**
 * Create default production stages for print job
 */
private function createProductionStages(PrintJob $printJob): void
{
    try {
        $defaultStages = $this->getDefaultStagesForJobType($printJob->job_type);
        
        foreach ($defaultStages as $index => $stage) {
            $this->stageRepository->create([
                'print_job_id' => $printJob->id,
                'stage_name' => $stage['name'],
                'stage_status' => 'pending',
                'stage_order' => $index + 1,
                'estimated_duration' => $stage['estimated_duration'],
                'requires_customer_approval' => $stage['requires_approval'] ?? false,
                'updated_by' => auth()->id()
            ]);
        }

        \Log::info('Default production stages created', [
            'print_job_id' => $printJob->id,
            'job_type' => $printJob->job_type,
            'stages_count' => count($defaultStages)
        ]);
        
    } catch (\Exception $e) {
        \Log::error('Failed to create production stages', [
            'print_job_id' => $printJob->id,
            'error' => $e->getMessage()
        ]);
        throw $e;
    }
}

/**
 * Get default stages for different job types
 */
private function getDefaultStagesForJobType(string $jobType): array
{
    $stageTemplates = [
        'business_cards' => [
            ['name' => 'design_review', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'customer_approval', 'estimated_duration' => 60, 'requires_approval' => true],
            ['name' => 'pre_press_setup', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'printing_process', 'estimated_duration' => 120, 'requires_approval' => false],
            ['name' => 'cutting', 'estimated_duration' => 60, 'requires_approval' => false],
            ['name' => 'quality_inspection', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'packaging', 'estimated_duration' => 30, 'requires_approval' => false],
        ],
        'brochures' => [
            ['name' => 'design_review', 'estimated_duration' => 60, 'requires_approval' => false],
            ['name' => 'customer_approval', 'estimated_duration' => 120, 'requires_approval' => true],
            ['name' => 'pre_press_setup', 'estimated_duration' => 90, 'requires_approval' => false],
            ['name' => 'printing_process', 'estimated_duration' => 180, 'requires_approval' => false],
            ['name' => 'folding', 'estimated_duration' => 90, 'requires_approval' => false],
            ['name' => 'quality_inspection', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'packaging', 'estimated_duration' => 45, 'requires_approval' => false],
        ],
        'flyers' => [
            ['name' => 'design_review', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'customer_approval', 'estimated_duration' => 60, 'requires_approval' => true],
            ['name' => 'pre_press_setup', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'printing_process', 'estimated_duration' => 90, 'requires_approval' => false],
            ['name' => 'cutting', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'quality_inspection', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'packaging', 'estimated_duration' => 30, 'requires_approval' => false],
        ],
        'posters' => [
            ['name' => 'design_review', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'customer_approval', 'estimated_duration' => 90, 'requires_approval' => true],
            ['name' => 'pre_press_setup', 'estimated_duration' => 60, 'requires_approval' => false],
            ['name' => 'printing_process', 'estimated_duration' => 120, 'requires_approval' => false],
            ['name' => 'cutting', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'quality_inspection', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'packaging', 'estimated_duration' => 30, 'requires_approval' => false],
        ],
        'banners' => [
            ['name' => 'design_review', 'estimated_duration' => 60, 'requires_approval' => false],
            ['name' => 'customer_approval', 'estimated_duration' => 120, 'requires_approval' => true],
            ['name' => 'material_preparation', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'printing_process', 'estimated_duration' => 180, 'requires_approval' => false],
            ['name' => 'finishing', 'estimated_duration' => 90, 'requires_approval' => false],
            ['name' => 'quality_inspection', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'packaging', 'estimated_duration' => 45, 'requires_approval' => false],
        ],
        'default' => [
            ['name' => 'design_review', 'estimated_duration' => 45, 'requires_approval' => false],
            ['name' => 'customer_approval', 'estimated_duration' => 90, 'requires_approval' => true],
            ['name' => 'pre_press_setup', 'estimated_duration' => 60, 'requires_approval' => false],
            ['name' => 'printing_process', 'estimated_duration' => 120, 'requires_approval' => false],
            ['name' => 'finishing', 'estimated_duration' => 60, 'requires_approval' => false],
            ['name' => 'quality_inspection', 'estimated_duration' => 30, 'requires_approval' => false],
            ['name' => 'packaging', 'estimated_duration' => 30, 'requires_approval' => false],
        ]
    ];

    return $stageTemplates[$jobType] ?? $stageTemplates['default'];
}
public function createManualJob(array $data): PrintJob
{
    try {
        return DB::transaction(function () use ($data) {
            $invoice = null;
            
            // If created from invoice
            if (!empty($data['invoice_id'])) {
                $invoice = \App\Models\Invoice::find($data['invoice_id']);
                if (!$invoice) {
                    throw new \Exception('Invoice not found');
                }
                
                return $this->createFromInvoice($invoice, $data);
            } 
            // Standalone job creation
            else {
                return $this->createStandaloneJob($data);
            }
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'manual print job creation');
        throw $e;
    }
}

/**
 * Create standalone print job (without invoice)
 */
public function createStandaloneJob(array $data): PrintJob
{
    try {
        return DB::transaction(function () use ($data) {
            $user = auth()->user();
            $branchId = $data['branch_id'] ?? $user->branch_id;
            $jobNumber = $this->generateJobNumber($branchId);
            
            $printJobData = [
                'invoice_id' => null, // Standalone job
                'branch_id' => $branchId,
                'company_id' => $user->company_id,
                'job_number' => $jobNumber,
                'job_type' => $data['job_type'],
                'job_title' => $data['job_title'] ?? null,
                'job_description' => $data['description'] ?? null,
                'specifications' => $this->formatManualSpecifications($data),
                'production_status' => 'pending',
                'priority' => $data['priority'] ?? 'normal',
                'assigned_to' => $data['assigned_to'] ?? null,
                'estimated_completion' => $data['estimated_completion'] 
                    ? \Carbon\Carbon::parse($data['estimated_completion'])
                    : $this->estimateCompletionForJobType($data['job_type']),
                'customer_instructions' => $data['customer_instructions'] ?? null,
                'estimated_cost' => $data['estimated_cost'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'design_files' => $data['design_files'] ?? null,
                'created_by' => $user->id,
            ];

            $printJob = $this->repository->create($printJobData);

            // Create production stages based on job type
            $this->createProductionStages($printJob);

            // Fire event
            event(new \App\Events\PrintJobCreated($printJob));

            return $printJob;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'standalone print job creation');
        throw $e;
    }
}

/**
 * Create print job from invoice with override data
 */
public function createFromInvoice(\App\Models\Invoice $invoice, array $overrideData = []): PrintJob
{
    try {
        return DB::transaction(function () use ($invoice, $overrideData) {
            // Ensure the invoice has items loaded
            if (!$invoice->relationLoaded('items')) {
                $invoice->load('items.product');
            }

            $jobNumber = $this->generateJobNumber($invoice->branch_id);
            
            $printJobData = [
                'invoice_id' => $invoice->id,
                'branch_id' => $invoice->branch_id,
                'company_id' => $invoice->company_id,
                'job_number' => $jobNumber,
                'job_type' => $overrideData['job_type'] ?? $this->determineJobType($invoice),
                'specifications' => $overrideData['specifications'] ?? $this->extractSpecifications($invoice),
                'production_status' => 'pending',
                'priority' => $overrideData['priority'] ?? $this->calculatePriority($invoice),
                'assigned_to' => $overrideData['assigned_to'] ?? null,
                'estimated_completion' => isset($overrideData['estimated_completion']) 
                    ? \Carbon\Carbon::parse($overrideData['estimated_completion'])
                    : $this->estimateCompletion($invoice),
                'customer_instructions' => $overrideData['customer_instructions'] ?? $invoice->notes,
                'design_files' => $overrideData['design_files'] ?? null,
                'created_by' => auth()->id(),
            ];

            $printJob = $this->repository->create($printJobData);

            // Create production stages
            $this->createProductionStages($printJob);

            // Fire event
            event(new \App\Events\PrintJobCreated($printJob));

            return $printJob;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'print job creation from invoice');
        throw $e;
    }
}

/**
 * Determine job type from invoice items
 */
private function determineJobType(Invoice $invoice): string
{
    // Ensure items are loaded
    if (!$invoice->relationLoaded('items')) {
        $invoice->load('items.product');
    }

    // Check if items exist
    if (!$invoice->items || $invoice->items->isEmpty()) {
        return 'general_printing';
    }

    $products = $invoice->items->pluck('product.name')->filter()->toArray();
    
    if (empty($products)) {
        return 'general_printing';
    }
    
    if (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'business card'))) {
        return 'business_cards';
    } elseif (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'brochure'))) {
        return 'brochures';
    } elseif (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'banner'))) {
        return 'banners';
    }
    
    return 'general_printing';
}

/**
 * Extract specifications from invoice
 */
private function extractSpecifications(Invoice $invoice): array
{
    $specifications = [];
    
    // Ensure items are loaded
    if (!$invoice->relationLoaded('items')) {
        $invoice->load('items.product');
    }

    // Check if items exist
    if (!$invoice->items || $invoice->items->isEmpty()) {
        return [
            'note' => 'No items found in invoice',
            'invoice_id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
        ];
    }
    
    foreach ($invoice->items as $item) {
        $specifications[] = [
            'product' => $item->product ? $item->product->name : $item->item_description,
            'quantity' => $item->quantity,
            'specifications' => $item->specifications ?? [],
            'weight' => $item->line_weight
        ];
    }
    
    return $specifications;
}

/**
 * Calculate priority based on various factors
 */
private function calculatePriority(Invoice $invoice): string
{
    $totalAmount = $invoice->total_amount;
    $dueDate = $invoice->due_date;
    
    // Load customer if not loaded
    if (!$invoice->relationLoaded('customer')) {
        $invoice->load('customer');
    }
    
    $customerType = optional($invoice->customer)->customer_type ?? 'regular';

    // VIP customers get high priority
    if ($customerType === 'vip') {
        return 'urgent';
    }

    // Large orders get high priority
    if ($totalAmount > 50000) {
        return 'high';
    }

    // Due soon gets medium priority
    if ($dueDate && $dueDate->diffInDays(now()) <= 2) {
        return 'medium';
    }

    return 'normal';
}

/**
 * Estimate completion time for invoice
 */
private function estimateCompletion(Invoice $invoice): \Carbon\Carbon
{
    $baseHours = 24; // Default 24 hours
    
    // Add time based on complexity
    $totalWeight = $invoice->total_weight ?? 0;
    if ($totalWeight > 10) {
        $baseHours += 12;
    }
    
    $totalAmount = $invoice->total_amount ?? 0;
    if ($totalAmount > 25000) {
        $baseHours += 8;
    }

    return now()->addHours($baseHours);
}


/**
 * Format manual specifications
 */
private function formatManualSpecifications(array $data): array
{
    $specifications = [];
    
    if (!empty($data['specifications'])) {
        $specifications = $data['specifications'];
    }
    
    // Add basic job info
    $specifications['job_details'] = [
        'job_type' => $data['job_type'],
        'description' => $data['description'] ?? null,
        'estimated_cost' => $data['estimated_cost'] ?? null,
        'manual_creation' => true,
        'created_at' => now()->toISOString(),
    ];
    
    return $specifications;
}

/**
 * Estimate completion time for job type
 */
private function estimateCompletionForJobType(string $jobType): \Carbon\Carbon
{
    $estimatedHours = match($jobType) {
        'business_cards' => 4,
        'flyers' => 6,
        'brochures' => 12,
        'posters' => 8,
        'banners' => 24,
        'booklets' => 48,
        'stickers' => 6,
        'custom' => 24,
        default => 12,
    };
    
    return now()->addHours($estimatedHours);
}


/**
 * Create print job from existing template
 */
public function createFromTemplate(int $templateId, array $overrideData = []): PrintJob
{
    try {
        return DB::transaction(function () use ($templateId, $overrideData) {
            $template = $this->getJobTemplate($templateId);
            
            if (!$template) {
                throw new \Exception('Job template not found');
            }
            
            $user = auth()->user();
            $branchId = $overrideData['branch_id'] ?? $user->branch_id;
            $jobNumber = $this->generateJobNumber($branchId);
            
            $printJobData = array_merge($template, [
                'job_number' => $jobNumber,
                'branch_id' => $branchId,
                'company_id' => $user->company_id,
                'created_by' => $user->id,
                'production_status' => 'pending',
                'estimated_completion' => $this->estimateCompletionForJobType($template['job_type']),
            ], $overrideData);

            $printJob = $this->repository->create($printJobData);

            // Create production stages
            $this->createProductionStages($printJob);

            // Fire event
            event(new \App\Events\PrintJobCreated($printJob));

            return $printJob;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'print job creation from template');
        throw $e;
    }
}

/**
 * Get job template by ID
 */
private function getJobTemplate(int $templateId): ?array
{
    // This could be stored in database or config
    $templates = [
        1 => [
            'job_type' => 'business_cards',
            'job_title' => 'Standard Business Cards',
            'specifications' => [
                'size' => '3.5" x 2"',
                'material' => '350gsm Card Stock',
                'finish' => 'Matte',
                'colors' => 'Full Color (CMYK)',
                'quantity' => 500,
            ],
            'priority' => 'normal',
        ],
        2 => [
            'job_type' => 'flyers',
            'job_title' => 'A4 Flyers',
            'specifications' => [
                'size' => 'A4 (210mm x 297mm)',
                'material' => '150gsm Gloss Paper',
                'finish' => 'Gloss',
                'colors' => 'Full Color (CMYK)',
                'sides' => 'Single sided',
            ],
            'priority' => 'normal',
        ],
        // Add more templates as needed
    ];
    
    return $templates[$templateId] ?? null;
}

/**
 * Duplicate existing print job
 */
public function duplicateJob(PrintJob $originalJob, array $overrideData = []): PrintJob
{
    try {
        return DB::transaction(function () use ($originalJob, $overrideData) {
            $user = auth()->user();
            $branchId = $overrideData['branch_id'] ?? $originalJob->branch_id;
            $jobNumber = $this->generateJobNumber($branchId);
            
            $duplicatedData = [
                'job_number' => $jobNumber,
                'branch_id' => $branchId,
                'company_id' => $user->company_id,
                'job_type' => $originalJob->job_type,
                'job_title' => $overrideData['job_title'] ?? ($originalJob->job_title . ' (Copy)'),
                'specifications' => $originalJob->specifications,
                'production_status' => 'pending',
                'priority' => $overrideData['priority'] ?? $originalJob->priority,
                'assigned_to' => $overrideData['assigned_to'] ?? null,
                'estimated_completion' => $overrideData['estimated_completion'] 
                    ? \Carbon\Carbon::parse($overrideData['estimated_completion'])
                    : $this->estimateCompletionForJobType($originalJob->job_type),
                'customer_instructions' => $overrideData['customer_instructions'] ?? $originalJob->customer_instructions,
                'customer_id' => $overrideData['customer_id'] ?? $originalJob->customer_id,
                'created_by' => $user->id,
                'invoice_id' => null, // Don't duplicate invoice link
            ];

            $printJob = $this->repository->create($duplicatedData);

            // Create production stages
            $this->createProductionStages($printJob);

            // Fire event
            event(new \App\Events\PrintJobCreated($printJob));

            return $printJob;
        });
    } catch (\Exception $e) {
        $this->handleException($e, 'print job duplication');
        throw $e;
    }
}

    /**
     * Assign print job to production staff
     */
    public function assignToStaff(PrintJob $printJob, int $staffId, ?string $notes = null): bool
    {
        try {
            return DB::transaction(function () use ($printJob, $staffId, $notes) {
                $updateData = [
                    'assigned_to' => $staffId,
                    'production_status' => 'assigned',
                ];

                if ($notes) {
                    $updateData['production_notes'] = $printJob->production_notes . 
                        "\n" . now()->format('Y-m-d H:i:s') . ": Assigned - " . $notes;
                }

                $updated = $this->repository->update($printJob->id, $updateData);

                if ($updated) {
                    // Update first pending stage to ready
                    $firstStage = $printJob->productionStages()
                        ->where('stage_status', 'pending')
                        ->orderBy('stage_order')
                        ->first();

                    if ($firstStage) {
                        $this->stageRepository->update($firstStage->id, [
                            'stage_status' => 'ready',
                            'updated_by' => Auth::id()
                        ]);
                    }
                }

                return $updated;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'print job assignment');
            throw $e;
        }
    }

    /**
     * Update production priority
     */
    public function updatePriority(PrintJob $printJob, string $priority, ?string $reason = null): bool
    {
        try {
            $updateData = ['priority' => $priority];

            if ($reason) {
                $updateData['production_notes'] = $printJob->production_notes . 
                    "\n" . now()->format('Y-m-d H:i:s') . ": Priority changed to {$priority} - " . $reason;
            }

            return $this->repository->update($printJob->id, $updateData);
        } catch (\Exception $e) {
            $this->handleException($e, 'priority update');
            throw $e;
        }
    }

    /**
     * Get production queue with priority ordering
     */
    public function getProductionQueue(int $branchId, ?int $assignedTo = null): array
    {
        $queue = $this->repository->getProductionQueue($branchId, $assignedTo);

        return $queue->groupBy('production_status')->toArray();
    }

    /**
     * Complete print job
     */
    public function completePrintJob(PrintJob $printJob, ?string $notes = null): bool
    {
        try {
            return DB::transaction(function () use ($printJob, $notes) {
                $updateData = [
                    'production_status' => 'completed',
                    'actual_completion' => now()
                ];

                if ($notes) {
                    $updateData['production_notes'] = $printJob->production_notes . 
                        "\n" . now()->format('Y-m-d H:i:s') . ": Completed - " . $notes;
                }

                $updated = $this->repository->update($printJob->id, $updateData);

                if ($updated) {
                    // Fire completion event
                    event(new \App\Events\PrintJobCompleted($printJob->fresh()));
                }

                return $updated;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'print job completion');
            throw $e;
        }
    }

    /**
     * Generate unique job number
     */
    private function generateJobNumber(int $branchId): string
    {
        $branch = \App\Models\Branch::find($branchId);
        $prefix = $branch->branch_code ?? 'JOB';
        $date = now()->format('Ymd');
        
        $lastJob = PrintJob::where('branch_id', $branchId)
            ->whereDate('created_at', now())
            ->orderBy('id', 'desc')
            ->first();

        $sequence = $lastJob ? (int)substr($lastJob->job_number, -3) + 1 : 1;
        
        return $prefix . '-' . $date . '-' . str_pad($sequence, 3, '0', STR_PAD_LEFT);
    }



/**
 * Create default production stages for print job
 */


  
}