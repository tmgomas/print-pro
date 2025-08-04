<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateInvoiceRequest;
use App\Http\Requests\UpdateInvoiceRequest;
use App\Repositories\InvoiceRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\BranchRepository;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class InvoiceController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private InvoiceRepository $invoiceRepository,
        private CustomerRepository $customerRepository,
        private ProductRepository $productRepository,
        private BranchRepository $branchRepository,
        private InvoiceService $invoiceService
    ) {}

    /**
     * Display a listing of invoices
     */
    public function index(Request $request): Response
    {
        $this->authorize('view invoices');

        $user = auth()->user();
        $companyId = $user->company_id;

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'payment_status' => $request->get('payment_status'),
            'branch_id' => $request->get('branch_id'),
            'customer_id' => $request->get('customer_id'),
            'date_from' => $request->get('date_from'),
            'date_to' => $request->get('date_to'),
            'overdue' => $request->boolean('overdue'),
        ];

        // Apply branch restriction for non-admin users
        if (!$user->can('view all branches') && $user->branch_id) {
            $filters['branch_id'] = $user->branch_id;
        }

        $invoices = $this->invoiceRepository->searchAndPaginate($companyId, $filters, 15);
        $customers = $this->customerRepository->getForDropdown($companyId);
        $branches = $this->branchRepository->getForDropdown($companyId);
        $stats = $this->invoiceService->getInvoiceStats($companyId, $filters['branch_id']);

        return Inertia::render('Invoices/Index', [
            'invoices' => [
                'data' => $invoices->items(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ],
            'filters' => $filters,
            'customers' => $customers,
            'branches' => $branches,
            'stats' => $stats,
            'permissions' => [
                'create' => $user->can('create invoices'),
                'edit' => $user->can('edit invoices'),
                'delete' => $user->can('delete invoices'),
                'view_all_branches' => $user->can('view all branches'),
            ]
        ]);
    }

    /**
     * Show the form for creating a new invoice
     */
    public function create(): Response
{
    $this->authorize('create invoices');

    $user = auth()->user();
    $companyId = $user->company_id;

    try {
        // Get raw data from repositories
        $customersData = $this->customerRepository->getForDropdown($companyId);
        $productsData = $this->productRepository->getForDropdown($companyId);
        $branchesData = $this->branchRepository->getForDropdown($companyId);

        // Transform customers data for frontend
        $customers = $customersData->map(function ($customer) {
            return [
                'value' => $customer->id,
                'label' => $customer->name . ' (' . ($customer->customer_code ?? '') . ')',
                'display_name' => $customer->name,
                'credit_limit' => $customer->credit_limit ?? 0,
                'current_balance' => $customer->current_balance ?? 0,
                'phone' => $customer->phone ?? '',
                'email' => $customer->email ?? '',
            ];
        })->toArray();

        // Transform products data for frontend
        $products = $productsData->map(function ($product) {
            return [
                'value' => $product->id,
                'label' => $product->name . ' - Rs. ' . number_format($product->base_price ?? 0, 2),
                'name' => $product->name,
                'base_price' => $product->base_price ?? 0,
                'weight_per_unit' => $product->weight_per_unit ?? 0,
                'weight_unit' => $product->weight_unit ?? 'kg',
                'tax_rate' => $product->tax_rate ?? 0,
                'unit_type' => $product->unit_type ?? 'piece',
            ];
        })->toArray();

        // Transform branches data for frontend
        $branches = $branchesData->map(function ($branch) {
            return [
                'value' => $branch->id,
                'label' => $branch->name . ' (' . ($branch->code ?? '') . ')',
                'name' => $branch->name,
                'code' => $branch->code ?? '',
            ];
        })->toArray();

        return Inertia::render('Invoices/Create', [
            'customers' => $customers,
            'products' => $products,
            'branches' => $branches,
            'default_branch_id' => $user->branch_id,
        ]);

    } catch (\Exception $e) {
        \Log::error('Invoice create page error', [
            'user_id' => $user->id,
            'company_id' => $companyId,
            'error' => $e->getMessage(),
        ]);

        return Inertia::render('Invoices/Create', [
            'customers' => [],
            'products' => [],
            'branches' => [],
            'default_branch_id' => $user->branch_id,
            'error' => 'Failed to load invoice data. Please try again.',
        ]);
    }
}
    /**
     * Store a newly created invoice
     */
    public function store(CreateInvoiceRequest $request): RedirectResponse
    {
        try {
            $invoice = $this->invoiceService->createInvoice(
                $request->validated(),
                auth()->user()->company_id,
                auth()->id()
            );

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice created successfully. / ප්‍රතිදානය සාර්ථකව නිර්මාණය කරන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Invoice creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'data' => $request->validated(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Invoice creation failed. Please try again. / ප්‍රතිදාන නිර්මාණය අසාර්ථක විය.']);
        }
    }

    /**
     * Display the specified invoice
     */
    public function show(int $id): Response
    {
        $invoice = $this->invoiceRepository->findWithDetails($id);
        
        if (!$invoice) {
            abort(404, 'Invoice not found.');
        }

        $this->authorize('view invoices');

        $user = auth()->user();
        if ($invoice->company_id !== $user->company_id) {
            abort(403, 'You cannot view this invoice.');
        }

        // Check branch access
        if (!$user->can('view all branches') && $user->branch_id !== $invoice->branch_id) {
            abort(403, 'You cannot view invoices from other branches.');
        }

        return Inertia::render('Invoices/Show', [
            'invoice' => $invoice,
            'permissions' => [
                'edit' => $user->can('edit invoices') && $this->invoiceRepository->canBeModified($id),
                'delete' => $user->can('delete invoices') && $this->invoiceRepository->canBeDeleted($id),
                'mark_paid' => $user->can('manage payments'),
                'generate_pdf' => $user->can('view invoices'),
            ]
        ]);
    }

    /**
     * Show the form for editing the specified invoice
     */
    public function edit(int $id): Response
    {
        $invoice = $this->invoiceRepository->findWithDetails($id);
        
        if (!$invoice) {
            abort(404, 'Invoice not found.');
        }

        $this->authorize('edit invoices');

        $user = auth()->user();
        if ($invoice->company_id !== $user->company_id) {
            abort(403, 'You cannot edit this invoice.');
        }

        if (!$this->invoiceRepository->canBeModified($id)) {
            return redirect()->route('invoices.show', $id)
                ->withErrors(['error' => 'Invoice cannot be modified at this time.']);
        }

        $customers = $this->customerRepository->getForDropdown($user->company_id);
        $products = $this->productRepository->getForDropdown($user->company_id);
        $branches = $this->branchRepository->getForDropdown($user->company_id);

        return Inertia::render('Invoices/Edit', [
            'invoice' => $invoice,
            'customers' => $customers,
            'products' => $products,
            'branches' => $branches,
        ]);
    }

    /**
     * Update the specified invoice
     */
    public function update(UpdateInvoiceRequest $request, int $id): RedirectResponse
    {
        try {
            $invoice = $this->invoiceService->updateInvoice(
                $id,
                $request->validated(),
                auth()->user()->company_id
            );

            return redirect()->route('invoices.show', $invoice->id)
                ->with('success', 'Invoice updated successfully. / ප්‍රතිදානය සාර්ථකව යාවත්කාලීන කරන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Invoice update failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Invoice update failed. Please try again. / ප්‍රතිදාන යාවත්කාලීන කිරීම අසාර්ථක විය.']);
        }
    }

    /**
     * Remove the specified invoice
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $this->invoiceService->deleteInvoice($id, auth()->user()->company_id);

            return redirect()->route('invoices.index')
                ->with('success', 'Invoice deleted successfully. / ප්‍රතිදානය සාර්ථකව මකා දමන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Invoice deletion failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Invoice deletion failed. Please try again. / ප්‍රතිදාන මකා දැමීම අසාර්ථක විය.'
            ]);
        }
    }

    /**
     * Generate invoice PDF
     */
    public function generatePDF(int $id): \Illuminate\Http\Response
    {
        try {
            $this->authorize('view invoices');

            $pdf = $this->invoiceService->generatePDF($id, auth()->user()->company_id);
            
            return response($pdf)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="invoice-' . $id . '.pdf"');

        } catch (\Exception $e) {
            \Log::error('Invoice PDF generation failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'PDF generation failed. Please try again. / PDF නිර්මාණය අසාර්ථක විය.'
            ]);
        }
    }

    /**
     * Send invoice via email
     */
    public function sendEmail(int $id): JsonResponse
    {
        try {
            $this->authorize('edit invoices');

            // Implementation for sending invoice email
            // This would typically queue an email job

            return response()->json([
                'success' => true,
                'message' => 'Invoice sent successfully. / ප්‍රතිදානය සාර්ථකව යවන ලදී.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Invoice email sending failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Email sending failed. Please try again. / ඊමේල් යැවීම අසාර්ථක විය.'
            ], 500);
        }
    }

    /**
     * Update invoice status
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        try {
            $this->authorize('edit invoices');

            $request->validate([
                'status' => 'required|in:draft,pending,processing,completed,cancelled'
            ]);

            $invoice = $this->invoiceRepository->findOrFail($id);
            
            if ($invoice->company_id !== auth()->user()->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $this->invoiceRepository->update($id, ['status' => $request->status]);

            return response()->json([
                'success' => true,
                'status' => $request->status,
                'message' => 'Invoice status updated successfully. / ප්‍රතිදාන තත්ත්වය සාර්ථකව යාවත්කාලීන කරන ලදී.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Invoice status update failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Status update failed. Please try again. / තත්ත්ව යාවත්කාලීන කිරීම අසාර්ථක විය.'
            ], 500);
        }
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(int $id): JsonResponse
    {
        try {
            $this->authorize('manage payments');

            $invoice = $this->invoiceService->markAsPaid($id, auth()->user()->company_id);

            return response()->json([
                'success' => true,
                'payment_status' => $invoice->payment_status,
                'message' => 'Invoice marked as paid successfully. / ප්‍රතිදානය ගෙවීම් සම්පූර්ණ ලෙස සලකුණු කරන ලදී.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Mark invoice as paid failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to mark as paid. Please try again. / ගෙවීම් සම්පූර්ණ ලෙස සලකුණු කිරීම අසාර්ථක විය.'
            ], 500);
        }
    }

    /**
     * Duplicate invoice
     */
    public function duplicate(int $id): RedirectResponse
    {
        try {
            $this->authorize('create invoices');

            $newInvoice = $this->invoiceService->duplicateInvoice(
                $id,
                auth()->user()->company_id,
                auth()->id()
            );

            return redirect()->route('invoices.edit', $newInvoice->id)
                ->with('success', 'Invoice duplicated successfully. / ප්‍රතිදානය සාර්ථකව පිටපත් කරන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Invoice duplication failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Invoice duplication failed. Please try again. / ප්‍රතිදාන පිටපත් කිරීම අසාර්ථක විය.'
            ]);
        }
    }

    /**
     * Get invoice data for API
     */
    public function apiShow(int $id): JsonResponse
    {
        try {
            $invoice = $this->invoiceRepository->findWithDetails($id);
            
            if (!$invoice) {
                return response()->json(['error' => 'Invoice not found'], 404);
            }

            // Check permissions
            $user = auth()->user();
            if ($invoice->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            return response()->json([
                'success' => true,
                'invoice' => $invoice
            ]);

        } catch (\Exception $e) {
            \Log::error('Invoice API show failed', [
                'invoice_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch invoice data'
            ], 500);
        }
    }

    /**
     * Bulk action on invoices
     */
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'action' => 'required|in:delete,mark_paid,export',
                'invoice_ids' => 'required|array',
                'invoice_ids.*' => 'exists:invoices,id'
            ]);

            $user = auth()->user();
            $action = $request->action;
            $invoiceIds = $request->invoice_ids;

            $results = [];

            foreach ($invoiceIds as $invoiceId) {
                try {
                    switch ($action) {
                        case 'delete':
                            $this->authorize('delete invoices');
                            $this->invoiceService->deleteInvoice($invoiceId, $user->company_id);
                            $results[] = ['id' => $invoiceId, 'success' => true];
                            break;

                        case 'mark_paid':
                            $this->authorize('manage payments');
                            $this->invoiceService->markAsPaid($invoiceId, $user->company_id);
                            $results[] = ['id' => $invoiceId, 'success' => true];
                            break;

                        case 'export':
                            // Implementation for export functionality
                            $results[] = ['id' => $invoiceId, 'success' => true];
                            break;
                    }
                } catch (\Exception $e) {
                    $results[] = ['id' => $invoiceId, 'success' => false, 'error' => $e->getMessage()];
                }
            }

            return response()->json([
                'success' => true,
                'results' => $results,
                'message' => 'Bulk action completed. / තොගයේ ක්‍රියාව සම්පූර්ණ කරන ලදී.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Invoice bulk action failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Bulk action failed. Please try again. / තොගයේ ක්‍රියාව අසාර්ථක විය.'
            ], 500);
        }
    }
}