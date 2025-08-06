<?php
// app/Http/Controllers/PrintJobController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreatePrintJobRequest;
use App\Http\Requests\UpdatePrintJobRequest;
use App\Repositories\PrintJobRepository;
use App\Repositories\BranchRepository;
use App\Repositories\InvoiceRepository;
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
        private PrintJobService $printJobService
    ) {}

    /**
     * Display production queue
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
        $validatedData = $request->getValidatedData();
        
        // If creating from invoice
        if ($validatedData['invoice_id']) {
            $invoice = $this->invoiceRepository->find($validatedData['invoice_id']);
            
            if (!$invoice) {
                return back()->withErrors(['invoice_id' => 'Invoice not found']);
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

            $printJob = $this->printJobService->createFromInvoice($invoice);
        } 
        // Manual job creation without invoice
        else {
            $printJob = $this->printJobService->createManualJob($validatedData);
        }
        
        return redirect()
            ->route('production.print-jobs.show', $printJob->id)
            ->with('success', 'Print job created successfully');
            
    } catch (\Exception $e) {
        return back()
            ->withInput()
            ->withErrors(['error' => 'Failed to create print job: ' . $e->getMessage()]);
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
}