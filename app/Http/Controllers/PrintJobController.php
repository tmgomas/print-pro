<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePrintJobRequest;
use App\Http\Requests\UpdatePrintJobRequest;
use App\Repositories\PrintJobRepository;
use App\Repositories\BranchRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\CustomerRepository;
use App\Services\PrintJobService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PrintJobController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private PrintJobRepository $printJobRepository,
        private BranchRepository $branchRepository,
        private InvoiceRepository $invoiceRepository,
        private CustomerRepository $customerRepository,
        private PrintJobService $printJobService
    ) {}

    /**
     * Display a listing of print jobs
     */
    public function index(Request $request): Response
    {
        $this->authorize('view print jobs');

        $user = auth()->user();
        $companyId = $user->company_id;

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'priority' => $request->get('priority'),
            'branch_id' => $request->get('branch_id'),
            'assigned_to' => $request->get('assigned_to'),
            'job_type' => $request->get('job_type'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'overdue' => $request->boolean('overdue'),
        ];

        // Apply branch restriction for non-admin users
        if (!$user->can('view all branches') && $user->branch_id) {
            $filters['branch_id'] = $user->branch_id;
        }

        $printJobs = $this->printJobRepository->searchAndPaginate($companyId, $filters, 15);
        $branches = $this->branchRepository->getForDropdown($companyId);
        $stats = $this->printJobRepository->getStats($companyId, $filters['branch_id']);

        // Get production staff for filters
        $productionStaff = \App\Models\User::whereHas('roles', function ($query) {
                $query->where('name', 'Production Staff');
            })
            ->where('status', 'active')
            ->where('company_id', $companyId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Production/PrintJobs/Index', [
            'printJobs' => [
                'data' => $printJobs->items(),
                'current_page' => $printJobs->currentPage(),
                'last_page' => $printJobs->lastPage(),
                'per_page' => $printJobs->perPage(),
                'total' => $printJobs->total(),
                'from' => $printJobs->firstItem(),
                'to' => $printJobs->lastItem(),
            ],
            'filters' => $filters,
            'branches' => $branches,
            'productionStaff' => $productionStaff,
            'stats' => $stats,
            'permissions' => [
                'create' => $user->can('create print jobs'),
                'edit' => $user->can('edit print jobs'),
                'delete' => $user->can('delete print jobs'),
                'view_all_branches' => $user->can('view all branches'),
                'manage_production' => $user->can('manage production'),
            ],
            'jobTypes' => [
                'business_cards' => 'Business Cards',
                'brochures' => 'Brochures',
                'flyers' => 'Flyers',
                'posters' => 'Posters',
                'banners' => 'Banners',
                'booklets' => 'Booklets',
                'stickers' => 'Stickers',
                'general_printing' => 'General Printing',
                'custom' => 'Custom Job',
            ]
        ]);
    }

    /**
     * Show the form for creating a new print job
     */
    public function create(Request $request): Response
    {
        $this->authorize('create print jobs');

        $user = auth()->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        // Get paid invoices that don't have print jobs yet
        $availableInvoices = $this->invoiceRepository->getPaidInvoicesWithoutPrintJobs($companyId, $branchId);
        
        // Get branches for selection (if user can view all branches)
        $branches = $user->can('view all branches') 
            ? $this->branchRepository->getForDropdown($companyId)
            : collect([['id' => $branchId, 'name' => $user->branch->branch_name]]);

        // Get production staff for assignment
        $productionStaff = $this->getProductionStaff($branchId);

        // Get job type options
        $jobTypes = [
            'business_cards' => 'Business Cards',
            'brochures' => 'Brochures',
            'flyers' => 'Flyers',
            'posters' => 'Posters',
            'banners' => 'Banners',
            'booklets' => 'Booklets',
            'stickers' => 'Stickers',
            'general_printing' => 'General Printing',
            'custom' => 'Custom Job',
        ];

        return Inertia::render('Production/PrintJobs/Create', [
            'available_invoices' => $availableInvoices,
            'branches' => $branches,
            'production_staff' => $productionStaff,
            'job_types' => $jobTypes,
            'priority_options' => [
                'low' => 'Low',
                'normal' => 'Normal', 
                'medium' => 'Medium',
                'high' => 'High',
                'urgent' => 'Urgent'
            ]
        ]);
    }

    /**
     * Store manually created print job
     */
    public function storeManual(CreatePrintJobRequest $request): RedirectResponse
    {
        try {
            $validatedData = $request->validated();
            
            // If creating from invoice
            if (!empty($validatedData['invoice_id'])) {
                // Load invoice with its items and related data
                $invoice = $this->invoiceRepository->findWithDetails($validatedData['invoice_id']);
                
                if (!$invoice) {
                    return back()->withErrors(['invoice_id' => 'Invoice not found']);
                }

                // Check if invoice belongs to user's company
                if ($invoice->company_id !== auth()->user()->company_id) {
                    return back()->withErrors(['invoice_id' => 'Unauthorized access to invoice']);
                }

                // Check if invoice is paid
                if ($invoice->payment_status !== 'paid') {
                    return back()->withErrors(['invoice_id' => 'Invoice must be paid before creating print job']);
                }

                // Check if print job already exists
                $existingJob = $this->printJobRepository->findByInvoice($invoice->id);
                if ($existingJob) {
                    return redirect()
                        ->route('production.print-jobs.show', $existingJob->id)
                        ->with('info', 'Print job already exists for this invoice');
                }

                // Ensure invoice has items loaded
                if (!$invoice->relationLoaded('items')) {
                    $invoice->load('items.product');
                }

                // Check if invoice has items
                if (!$invoice->items || $invoice->items->isEmpty()) {
                    return back()->withErrors(['invoice_id' => 'Invoice has no items to create a print job for']);
                }

                // Create print job from invoice with manual data
                $printJob = $this->printJobService->createFromInvoice($invoice, $validatedData);
            } 
            // Manual job creation without invoice
            else {
                $printJob = $this->printJobService->createManualJob($validatedData);
            }
            
            return redirect()
                ->route('production.print-jobs.show', $printJob->id)
                ->with('success', 'Print job created successfully');
                
        } catch (\Exception $e) {
            \Log::error('Print job creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create print job: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified print job
     */
  /**
 * Display the specified print job
 */
public function show(int $id): Response
{
    \Log::info('PrintJob Show Method Called', [
        'print_job_id' => $id,
        'user_id' => auth()->id(),
    ]);

    $printJob = $this->printJobRepository->find($id);
    
    if (!$printJob) {
        abort(404, 'Print job not found.');
    }

    // Authorization checks...
    $user = auth()->user();
    $this->authorize('view production');

    // Check branch and company access...
    if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
        abort(403, 'You cannot view print jobs from other branches.');
    }

    // Load relationships explicitly
    $printJob->load([
        'invoice.customer',
        'invoice.items.product',
        'branch',
        'assignedTo',
        'productionStages' => function($query) {
            $query->orderBy('stage_order');
        }
    ]);

    // Get production staff
    $productionStaff = $this->getProductionStaff($printJob->branch_id);

    // **EXPLICIT DATA PREPARATION**
    $printJobData = [
        'id' => $printJob->id,
        'job_number' => $printJob->job_number,
        'job_type' => $printJob->job_type,
        'job_title' => $printJob->job_title,
        'job_description' => $printJob->job_description,
        'production_status' => $printJob->production_status,
        'priority' => $printJob->priority,
        'estimated_completion' => $printJob->estimated_completion,
        'actual_completion' => $printJob->actual_completion,
        'estimated_cost' => $printJob->estimated_cost,
        'actual_cost' => $printJob->actual_cost,
        'completion_percentage' => $printJob->completion_percentage,
        'production_notes' => $printJob->production_notes,
        'customer_instructions' => $printJob->customer_instructions,
        'special_instructions' => $printJob->special_instructions,
        'specifications' => $printJob->specifications,
        'created_at' => $printJob->created_at,
        'updated_at' => $printJob->updated_at,
        
        // Relationships
        'invoice' => $printJob->invoice ? [
            'id' => $printJob->invoice->id,
            'invoice_number' => $printJob->invoice->invoice_number,
            'customer' => $printJob->invoice->customer ? [
                'id' => $printJob->invoice->customer->id,
                'name' => $printJob->invoice->customer->name,
                'email' => $printJob->invoice->customer->email,
                'phone' => $printJob->invoice->customer->phone,
            ] : null,
            'items' => $printJob->invoice->items->map(function($item) {
                return [
                    'id' => $item->id,
                    'item_description' => $item->item_description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'line_total' => $item->line_total,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                    ] : null,
                ];
            })->toArray(),
        ] : null,
        
        'branch' => $printJob->branch ? [
            'id' => $printJob->branch->id,
            'name' => $printJob->branch->name,
            'code' => $printJob->branch->code,
        ] : null,
        
        'assignedTo' => $printJob->assignedTo ? [
            'id' => $printJob->assignedTo->id,
            'name' => $printJob->assignedTo->name,
            'email' => $printJob->assignedTo->email,
        ] : null,
        
        // **EXPLICIT PRODUCTION STAGES**
        'productionStages' => $printJob->productionStages->map(function($stage) {
            return [
                'id' => $stage->id,
                'stage_name' => $stage->stage_name,
                'stage_status' => $stage->stage_status,
                'started_at' => $stage->started_at,
                'completed_at' => $stage->completed_at,
                'estimated_duration' => $stage->estimated_duration,
                'actual_duration' => $stage->actual_duration,
                'stage_order' => $stage->stage_order,
                'notes' => $stage->notes,
                'requires_customer_approval' => $stage->requires_customer_approval,
            ];
        })->toArray(),
    ];

    \Log::info('Final data being sent to frontend', [
        'print_job_id' => $printJobData['id'],
        'production_stages_count' => count($printJobData['productionStages']),
        'production_stages' => $printJobData['productionStages'],
    ]);

    return Inertia::render('Production/PrintJobs/Show', [
        'printJob' => $printJobData,
        'productionStaff' => $productionStaff,
        'permissions' => [
            'edit' => $user->can('manage production'),
            'delete' => $user->can('manage production'),
            'manage_production' => $user->can('manage production'),
            'assign_staff' => $user->can('assign production jobs'),
            'update_priority' => $user->can('manage production'),
        ],
        'jobTypes' => [
            'business_cards' => 'Business Cards',
            'brochures' => 'Brochures',
            'flyers' => 'Flyers',
            'posters' => 'Posters',
            'banners' => 'Banners',
            'booklets' => 'Booklets',
            'stickers' => 'Stickers',
            'general_printing' => 'General Printing',
            'custom' => 'Custom Job',
        ]
    ]);
}

    /**
     * Show the form for editing the specified print job
     */
    public function edit(int $id): Response
    {
        $printJob = $this->printJobRepository->find($id);
        
        if (!$printJob) {
            abort(404, 'Print job not found.');
        }

        $this->authorize('edit print jobs');

        $user = auth()->user();
        
        // Check access permissions
        if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
            abort(403, 'You cannot edit print jobs from other branches.');
        }

        if (!$this->printJobRepository->canBeModified($id)) {
            return redirect()->route('production.print-jobs.show', $id)
                ->withErrors(['error' => 'Print job cannot be modified at this time.']);
        }

        // Load relationships
        $printJob->load(['invoice.customer', 'branch', 'assignedTo']);

        // Get production staff
        $productionStaff = $this->getProductionStaff($printJob->branch_id);

        // Get customers if this is a standalone job
        $customers = $this->customerRepository->getForDropdown($user->company_id);

        return Inertia::render('Production/PrintJobs/Edit', [
            'printJob' => $printJob,
            'productionStaff' => $productionStaff,
            'customers' => $customers,
            'jobTypes' => [
                'business_cards' => 'Business Cards',
                'brochures' => 'Brochures',
                'flyers' => 'Flyers',
                'posters' => 'Posters',
                'banners' => 'Banners',
                'booklets' => 'Booklets',
                'stickers' => 'Stickers',
                'general_printing' => 'General Printing',
                'custom' => 'Custom Job',
            ]
        ]);
    }

    /**
     * Update the specified print job
     */
    public function update(UpdatePrintJobRequest $request, int $id): RedirectResponse
    {
        try {
            $printJob = $this->printJobRepository->findOrFail($id);
            
            $this->authorize('edit print jobs');

            // Check access permissions
            $user = auth()->user();
            if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
                abort(403, 'You cannot edit print jobs from other branches.');
            }

            if (!$this->printJobRepository->canBeModified($id)) {
                return back()->withErrors(['error' => 'Print job cannot be modified at this time.']);
            }

            $this->printJobRepository->update($id, $request->validated());

            return redirect()
                ->route('production.print-jobs.show', $id)
                ->with('success', 'Print job updated successfully');

        } catch (\Exception $e) {
            \Log::error('Print job update failed', [
                'print_job_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Print job update failed. Please try again.']);
        }
    }

    /**
     * Remove the specified print job
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $printJob = $this->printJobRepository->findOrFail($id);
            
            $this->authorize('delete print jobs');

            // Check access permissions
            $user = auth()->user();
            if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
                abort(403, 'You cannot delete print jobs from other branches.');
            }

            if (!$this->printJobRepository->canBeDeleted($id)) {
                return back()->withErrors(['error' => 'Print job cannot be deleted at this time.']);
            }

            $this->printJobRepository->delete($id);

            return redirect()
                ->route('production.print-jobs.index')
                ->with('success', 'Print job deleted successfully');

        } catch (\Exception $e) {
            \Log::error('Print job deletion failed', [
                'print_job_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Print job deletion failed. Please try again.']);
        }
    }

    /**
     * Create standalone print job (without invoice)
     */
    public function createStandalone(Request $request): Response
    {
        $this->authorize('create standalone print jobs');

        $user = auth()->user();
        $companyId = $user->company_id;
        $branchId = $user->branch_id;

        // Get customers for selection
        $customers = $this->customerRepository->getForDropdown($companyId);
        
        // Get branches
        $branches = $user->can('view all branches') 
            ? $this->branchRepository->getForDropdown($companyId)
            : collect([['id' => $branchId, 'name' => $user->branch->branch_name]]);

        // Get production staff
        $productionStaff = $this->getProductionStaff($branchId);

        return Inertia::render('Production/PrintJobs/CreateStandalone', [
            'customers' => $customers,
            'branches' => $branches,
            'production_staff' => $productionStaff,
            'job_types' => [
                'business_cards' => 'Business Cards',
                'brochures' => 'Brochures',
                'flyers' => 'Flyers',
                'posters' => 'Posters',
                'banners' => 'Banners',
                'booklets' => 'Booklets',
                'stickers' => 'Stickers',
                'general_printing' => 'General Printing',
                'custom' => 'Custom Job',
            ]
        ]);
    }

    /**
     * Store standalone print job
     */
    public function storeStandalone(Request $request): RedirectResponse
    {
        $this->authorize('create standalone print jobs');

        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'job_type' => 'required|string|max:100',
            'job_title' => 'required|string|max:200',
            'description' => 'required|string|max:1000',
            'priority' => 'required|in:low,normal,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_completion' => 'nullable|date|after:now',
            'specifications' => 'nullable|array',
            'design_files' => 'nullable|array',
            'design_files.*' => 'file|mimes:pdf,jpg,jpeg,png,ai,psd|max:10240',
            'customer_instructions' => 'nullable|string|max:1000',
            'estimated_cost' => 'nullable|numeric|min:0',
        ]);

        try {
            $printJob = $this->printJobService->createStandaloneJob($request->all());
            
            return redirect()
                ->route('production.print-jobs.show', $printJob->id)
                ->with('success', 'Standalone print job created successfully');
                
        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Failed to create print job: ' . $e->getMessage()]);
        }
    }

    /**
     * Start production for a print job
     */
    /**
 * Start production for a print job
 */
public function startProduction(int $id): RedirectResponse
{
    try {
        $printJob = $this->printJobRepository->findOrFail($id);
        
        $this->authorize('manage production');

        // Check access permissions
        $user = auth()->user();
        if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
            abort(403, 'You cannot manage production for jobs from other branches.');
        }

        // Check if production can be started
        if ($printJob->production_status !== 'pending') {
            return back()->withErrors(['error' => 'Production has already been started or completed.']);
        }
       
        // Use PrintJobService to start production with stages workflow
        $success = $this->printJobService->startProduction($printJob);

        if ($success) {
            return redirect()
                ->route('production.print-jobs.show', $id)
                ->with('success', 'Production started successfully. First stage is now ready.');
        } else {
            return back()->withErrors(['error' => 'Failed to start production.']);
        }

    } catch (\Exception $e) {
        \Log::error('Start production failed', [
            'print_job_id' => $id,
            'user_id' => auth()->id(),
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return back()->withErrors(['error' => 'Failed to start production. Please try again.']);
    }
}


    /**
     * Assign staff to print job
     */
    public function assignStaff(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'assigned_to' => 'required|exists:users,id',
            'notes' => 'nullable|string|max:500'
        ]);

        try {
            $printJob = $this->printJobRepository->findOrFail($id);
            
            $this->authorize('manage production');

            // Check access permissions
            $user = auth()->user();
            if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
                abort(403, 'You cannot assign staff to jobs from other branches.');
            }

            $this->printJobService->assignToStaff($printJob, $request->assigned_to, $request->notes);

            return redirect()
                ->route('production.print-jobs.show', $id)
                ->with('success', 'Staff assigned successfully');

        } catch (\Exception $e) {
            \Log::error('Staff assignment failed', [
                'print_job_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to assign staff. Please try again.']);
        }
    }

    /**
     * Update priority of print job
     */
    public function updatePriority(Request $request, int $id): RedirectResponse
    {
        $request->validate([
            'priority' => 'required|in:low,normal,medium,high,urgent',
            'reason' => 'nullable|string|max:500'
        ]);

        try {
            $printJob = $this->printJobRepository->findOrFail($id);
            
            $this->authorize('manage production');

            // Check access permissions
            $user = auth()->user();
            if (!$user->can('view all branches') && $user->branch_id !== $printJob->branch_id) {
                abort(403, 'You cannot update priority for jobs from other branches.');
            }

            $this->printJobService->updatePriority($printJob, $request->priority, $request->reason);

            return redirect()
                ->route('production.print-jobs.show', $id)
                ->with('success', 'Priority updated successfully');

        } catch (\Exception $e) {
            \Log::error('Priority update failed', [
                'print_job_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['error' => 'Failed to update priority. Please try again.']);
        }
    }

    /**
     * Get production staff for dropdown
     */
    private function getProductionStaff(int $branchId)
    {
        return \App\Models\User::where('branch_id', $branchId)
            ->whereHas('roles', function ($query) {
                $query->where('name', 'Production Staff');
            })
            ->where('status', 'active')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
    }
}