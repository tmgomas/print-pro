<?php
// app/Http/Controllers/ProductionStageController.php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProductionStageRequest;
use App\Repositories\ProductionStageRepository;
use App\Services\ProductionStageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;

class ProductionStageController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private ProductionStageRepository $stageRepository,
        private ProductionStageService $stageService
    ) {}

    /**
     * Display pending approvals dashboard
     */
    public function approvals(Request $request): Response
    {
        $this->authorize('view production approvals');

        $user = auth()->user();
        $companyId = $user->company_id;

        $approvals = $this->stageService->getPendingApprovals($companyId);
        $stats = $this->stageRepository->getStageStats($companyId, $user->branch_id);

        return Inertia::render('Production/Approvals/Index', [
            'customer_approvals' => $approvals['customer_approvals'],
            'internal_approvals' => $approvals['internal_approvals'],
            'stats' => $stats,
            'can' => [
                'approve_stages' => $user->can('approve production stages'),
                'reject_stages' => $user->can('reject production stages'),
            ]
        ]);
    }

    /**
     * Update production stage status
     */
    public function update(UpdateProductionStageRequest $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        try {
            $success = $this->stageService->updateStage($stage, $request->getValidatedData());

            if ($success) {
                return response()->json([
                    'message' => 'Production stage updated successfully',
                    'stage' => $stage->fresh(['updatedBy', 'approvedBy'])
                ]);
            }

            return response()->json(['error' => 'Failed to update stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Start production stage
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $success = $this->stageService->startStage($stage, auth()->id(), $request->notes);

            if ($success) {
                return response()->json([
                    'message' => 'Production stage started successfully',
                    'stage' => $stage->fresh(['updatedBy'])
                ]);
            }

            return response()->json(['error' => 'Cannot start this stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Complete production stage
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        $request->validate([
            'notes' => 'nullable|string|max:500',
            'stage_data' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120'
        ]);

        try {
            // Handle file uploads
            $stageData = $request->stage_data ?? [];
            if ($request->hasFile('attachments')) {
                $attachments = [];
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('production-stages/' . $stage->id, 'public');
                    $attachments[] = [
                        'path' => $path,
                        'type' => $file->getClientMimeType(),
                        'original_name' => $file->getClientOriginalName(),
                        'size' => $file->getSize(),
                        'uploaded_at' => now()->toISOString(),
                        'uploaded_by' => auth()->id()
                    ];
                }
                $stageData['attachments'] = $attachments;
            }

            $success = $this->stageService->completeStage(
                $stage, 
                auth()->id(), 
                $request->notes, 
                $stageData
            );

            if ($success) {
                return response()->json([
                    'message' => 'Production stage completed successfully',
                    'stage' => $stage->fresh(['updatedBy', 'printJob'])
                ]);
            }

            return response()->json(['error' => 'Cannot complete this stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Put stage on hold
     */
    public function hold(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $success = $this->stageService->holdStage($stage, auth()->id(), $request->reason);

            if ($success) {
                return response()->json([
                    'message' => 'Production stage put on hold',
                    'stage' => $stage->fresh(['updatedBy'])
                ]);
            }

            return response()->json(['error' => 'Cannot put this stage on hold'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Resume stage from hold
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $success = $this->stageService->resumeStage($stage, auth()->id(), $request->notes);

            if ($success) {
                return response()->json([
                    'message' => 'Production stage resumed',
                    'stage' => $stage->fresh(['updatedBy'])
                ]);
            }

            return response()->json(['error' => 'Cannot resume this stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Approve stage (for customer approvals)
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('approve', $stage);

        $request->validate([
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $success = $this->stageService->approveStage($stage, auth()->id(), $request->notes);

            if ($success) {
                return response()->json([
                    'message' => 'Production stage approved',
                    'stage' => $stage->fresh(['updatedBy', 'approvedBy'])
                ]);
            }

            return response()->json(['error' => 'Cannot approve this stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject stage
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $success = $this->stageService->rejectStage($stage, auth()->id(), $request->reason);

            if ($success) {
                return response()->json([
                    'message' => 'Production stage rejected',
                    'stage' => $stage->fresh(['updatedBy'])
                ]);
            }

            return response()->json(['error' => 'Cannot reject this stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Skip stage
     */
    public function skip(Request $request, int $id): JsonResponse
    {
        $stage = $this->stageRepository->find($id);
        
        if (!$stage) {
            return response()->json(['error' => 'Production stage not found'], 404);
        }

        $this->authorize('update', $stage);

        $request->validate([
            'reason' => 'required|string|max:500'
        ]);

        try {
            $success = $stage->skip(auth()->id(), $request->reason);

            if ($success) {
                return response()->json([
                    'message' => 'Production stage skipped',
                    'stage' => $stage->fresh(['updatedBy'])
                ]);
            }

            return response()->json(['error' => 'Cannot skip this stage'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stage history/timeline
     */
    public function history(int $printJobId): JsonResponse
    {
        $this->authorize('view production stages');

        $stages = $this->stageRepository->getByPrintJob($printJobId);
        
        $timeline = $stages->map(function ($stage) {
            return [
                'id' => $stage->id,
                'stage_name' => $stage->stage_name_label,
                'status' => $stage->stage_status,
                'started_at' => $stage->started_at?->format('Y-m-d H:i:s'),
                'completed_at' => $stage->completed_at?->format('Y-m-d H:i:s'),
                'duration' => $stage->actual_duration_formatted,
                'updated_by' => $stage->updated_by_name,
                'notes' => $stage->notes,
                'is_current' => $stage->is_in_progress,
                'requires_approval' => $stage->requires_customer_approval,
                'attachments' => $stage->attachment_urls,
            ];
        });

        return response()->json(['timeline' => $timeline]);
    }

    /**
     * Bulk operations on stages
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $this->authorize('bulk edit production stages');

        $request->validate([
            'action' => 'required|in:hold,resume,skip',
            'stage_ids' => 'required|array|min:1',
            'stage_ids.*' => 'exists:production_stages,id',
            'reason' => 'required|string|max:500',
        ]);

        try {
            $success = match($request->action) {
                'hold' => $this->stageService->bulkHoldStages($request->stage_ids, $request->reason),
                'resume' => $this->stageService->bulkResumeStages($request->stage_ids, $request->reason),
                'skip' => $this->stageService->bulkSkipStages($request->stage_ids, $request->reason),
            };

            if ($success) {
                return response()->json(['message' => 'Stages updated successfully']);
            }

            return response()->json(['error' => 'Failed to update stages'], 400);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get stage analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $this->authorize('view analytics');

        $user = auth()->user();
        $companyId = $user->company_id;
        $branchId = $user->can('view all branches') ? $request->get('branch_id') : $user->branch_id;

        $filters = [
            'date_from' => $request->get('date_from', now()->subMonth()->toDateString()),
            'date_to' => $request->get('date_to', now()->toDateString()),
        ];

        $analytics = $this->stageService->getStageAnalytics($companyId, $branchId, $filters['date_from'], $filters['date_to']);
        $overdueStages = $this->stageRepository->getOverdueStages($companyId);

        return response()->json([
            'analytics' => $analytics,
            'overdue_stages' => $overdueStages
        ]);
    }

    /**
     * Get stages by status for Kanban view
     */
    public function kanban(Request $request): JsonResponse
    {
        $this->authorize('view production queue');

        $user = auth()->user();
        $branchId = $user->branch_id;

        $statuses = ['pending', 'in_progress', 'requires_approval', 'on_hold', 'completed'];
        $kanbanData = [];

        foreach ($statuses as $status) {
            $stages = $this->stageRepository->getStagesByStatus($branchId, $status);
            $kanbanData[$status] = $stages->map(function ($stage) {
                return [
                    'id' => $stage->id,
                    'stage_name' => $stage->stage_name_label,
                    'print_job' => [
                        'id' => $stage->printJob->id,
                        'job_number' => $stage->printJob->job_number,
                        'customer' => $stage->printJob->invoice->customer->name,
                        'priority' => $stage->printJob->priority,
                    ],
                    'started_at' => $stage->started_at?->format('Y-m-d H:i:s'),
                    'estimated_duration' => $stage->estimated_duration_formatted,
                    'is_overdue' => $stage->is_overdue,
                    'updated_by' => $stage->updated_by_name,
                ];
            });
        }

        return response()->json(['kanban' => $kanbanData]);
    }
}