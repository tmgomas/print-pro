<?php
// app/Http/Controllers/CustomerController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Repositories\CustomerRepository;
use App\Repositories\BranchRepository;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CustomerRepository $customerRepository,
        private BranchRepository $branchRepository
    ) {}

    /**
     * Display a listing of customers
     */
    public function index(Request $request): Response
    {
        $this->authorize('view customers');

        $user = auth()->user();
        $companyId = $user->company_id;

        $filters = [
            'search' => $request->get('search'),
            'status' => $request->get('status'),
            'customer_type' => $request->get('customer_type'),
            'branch_id' => $request->get('branch_id'),
            'city' => $request->get('city'),
        ];

        // Apply branch restriction for non-admin users
        if (!$user->can('view all branches') && $user->branch_id) {
            $filters['branch_id'] = $user->branch_id;
        }

        $customers = $this->customerRepository->searchAndPaginate($companyId, $filters, 15);
        $stats = $this->customerRepository->getStats($companyId);
        $branches = $this->branchRepository->getForCompany($companyId);

        return Inertia::render('Customers/Index', [
            'customers' => [
                'data' => $customers->items(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'from' => $customers->firstItem(),
                'to' => $customers->lastItem(),
            ],
            'filters' => $filters,
            'stats' => $stats,
            'filterOptions' => [
                'branches' => $branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name]),
                'statuses' => [
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                    ['value' => 'suspended', 'label' => 'Suspended'],
                ],
                'customerTypes' => [
                    ['value' => 'individual', 'label' => 'Individual'],
                    ['value' => 'business', 'label' => 'Business'],
                ],
            ],
            'permissions' => [
                'canCreate' => $user->can('create customers'),
                'canEdit' => $user->can('edit customers'),
                'canDelete' => $user->can('delete customers'),
                'canViewAllBranches' => $user->can('view all branches'),
            ],
        ]);
    }

    /**
     * Show the form for creating a new customer
     */
    public function create(): Response
    {
        $this->authorize('create customers');

        $user = auth()->user();
        $branches = $this->branchRepository->getForCompany($user->company_id);

        return Inertia::render('Customers/Create', [
            'branches' => $branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name]),
            'defaultBranchId' => $user->branch_id,
            'customerTypes' => [
                ['value' => 'individual', 'label' => 'Individual'],
                ['value' => 'business', 'label' => 'Business'],
            ],
            'provinces' => [
                ['value' => 'western', 'label' => 'Western Province'],
                ['value' => 'central', 'label' => 'Central Province'],
                ['value' => 'southern', 'label' => 'Southern Province'],
                ['value' => 'northern', 'label' => 'Northern Province'],
                ['value' => 'eastern', 'label' => 'Eastern Province'],
                ['value' => 'north_western', 'label' => 'North Western Province'],
                ['value' => 'north_central', 'label' => 'North Central Province'],
                ['value' => 'uva', 'label' => 'Uva Province'],
                ['value' => 'sabaragamuwa', 'label' => 'Sabaragamuwa Province'],
            ],
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(CreateCustomerRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            $data['company_id'] = auth()->user()->company_id;

            // Generate unique customer code if not provided
            if (empty($data['customer_code'])) {
                $data['customer_code'] = $this->customerRepository->generateUniqueCode($data['company_id']);
            }

            // Set shipping address same as billing if not provided
            if (empty($data['shipping_address'])) {
                $data['shipping_address'] = $data['billing_address'];
            }

            $customer = $this->customerRepository->create($data);

            return redirect()->route('customers.show', $customer->id)
                ->with('success', 'Customer created successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Customer creation failed. Please try again.']);
        }
    }

    /**
     * Display the specified customer
     */
    public function show(int $id): Response
    {
        $customer = $this->customerRepository->findOrFail($id);
        $this->authorize('view customers');

        $user = auth()->user();
        if ($customer->company_id !== $user->company_id) {
            abort(403, 'You cannot view this customer.');
        }

        // Check branch access
        if (!$user->can('view all branches') && $user->branch_id !== $customer->branch_id) {
            abort(403, 'You cannot view customers from other branches.');
        }

        $customer->load(['branch']);

        // Get customer statistics
        $recentInvoices = $customer->invoices()->with(['payments'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $totalInvoices = $customer->invoices()->count();
        $totalPaid = $customer->payments()->where('status', 'completed')->sum('amount');
        $outstandingBalance = $customer->getOutstandingBalance();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'display_name' => $customer->display_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'city' => $customer->city,
                'postal_code' => $customer->postal_code,
                'district' => $customer->district,
                'province' => $customer->province,
                'tax_number' => $customer->tax_number,
                'credit_limit' => $customer->credit_limit,
                'formatted_credit_limit' => $customer->formatted_credit_limit,
                'current_balance' => $customer->current_balance,
                'formatted_balance' => $customer->formatted_balance,
                'available_credit' => $customer->available_credit,
                'formatted_available_credit' => $customer->formatted_available_credit,
                'status' => $customer->status,
                'customer_type' => $customer->customer_type,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'age' => $customer->age,
                'company_name' => $customer->company_name,
                'contact_person' => $customer->contact_person,
                'notes' => $customer->notes,
                'preferences' => $customer->preferences,
                'created_at' => $customer->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $customer->updated_at->format('Y-m-d H:i:s'),
                'branch' => $customer->branch ? [
                    'id' => $customer->branch->id,
                    'name' => $customer->branch->name,
                    'code' => $customer->branch->code,
                ] : null,
            ],
            'statistics' => [
                'total_invoices' => $totalInvoices,
                'total_amount_invoiced' => $customer->getTotalInvoiceAmount(),
                'total_paid' => $totalPaid,
                'outstanding_balance' => $outstandingBalance,
            ],
            'recentInvoices' => $recentInvoices->map(fn($invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'total_amount' => $invoice->total_amount,
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'created_at' => $invoice->created_at->format('Y-m-d'),
            ]),
            'permissions' => [
                'canEdit' => $user->can('edit customers'),
                'canDelete' => $user->can('delete customers'),
                'canViewInvoices' => $user->can('view invoices'),
                'canCreateInvoice' => $user->can('create invoices'),
            ],
        ]);
    }

    /**
     * Show the form for editing the specified customer
     */
    public function edit(int $id): Response
    {
        $customer = $this->customerRepository->findOrFail($id);
        $this->authorize('edit customers');

        $user = auth()->user();
        if ($customer->company_id !== $user->company_id) {
            abort(403, 'You cannot edit this customer.');
        }

        // Check branch access
        if (!$user->can('view all branches') && $user->branch_id !== $customer->branch_id) {
            abort(403, 'You cannot edit customers from other branches.');
        }

        $branches = $this->branchRepository->getForCompany($user->company_id);

        return Inertia::render('Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'billing_address' => $customer->billing_address,
                'shipping_address' => $customer->shipping_address,
                'city' => $customer->city,
                'postal_code' => $customer->postal_code,
                'district' => $customer->district,
                'province' => $customer->province,
                'tax_number' => $customer->tax_number,
                'credit_limit' => $customer->credit_limit,
                'status' => $customer->status,
                'customer_type' => $customer->customer_type,
                'date_of_birth' => $customer->date_of_birth?->format('Y-m-d'),
                'company_name' => $customer->company_name,
                'contact_person' => $customer->contact_person,
                'notes' => $customer->notes,
                'branch_id' => $customer->branch_id,
            ],
            'branches' => $branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name]),
            'customerTypes' => [
                ['value' => 'individual', 'label' => 'Individual'],
                ['value' => 'business', 'label' => 'Business'],
            ],
            'provinces' => [
                ['value' => 'western', 'label' => 'Western Province'],
                ['value' => 'central', 'label' => 'Central Province'],
                ['value' => 'southern', 'label' => 'Southern Province'],
                ['value' => 'northern', 'label' => 'Northern Province'],
                ['value' => 'eastern', 'label' => 'Eastern Province'],
                ['value' => 'north_western', 'label' => 'North Western Province'],
                ['value' => 'north_central', 'label' => 'North Central Province'],
                ['value' => 'uva', 'label' => 'Uva Province'],
                ['value' => 'sabaragamuwa', 'label' => 'Sabaragamuwa Province'],
            ],
        ]);
    }

    /**
     * Update the specified customer
     */
    public function update(UpdateCustomerRequest $request, int $id): RedirectResponse
    {
        try {
            $customer = $this->customerRepository->findOrFail($id);

            $user = auth()->user();
            if ($customer->company_id !== $user->company_id) {
                abort(403, 'You cannot edit this customer.');
            }

            $data = $request->validated();

            // Set shipping address same as billing if not provided
            if (empty($data['shipping_address'])) {
                $data['shipping_address'] = $data['billing_address'];
            }

            $this->customerRepository->update($id, $data);

            return redirect()->route('customers.show', $id)
                ->with('success', 'Customer updated successfully.');

        } catch (\Exception $e) {
            return back()
                ->withInput()
                ->withErrors(['error' => 'Customer update failed. Please try again.']);
        }
    }

    /**
     * Remove the specified customer
     */
    public function destroy(int $id): RedirectResponse
    {
        try {
            $customer = $this->customerRepository->findOrFail($id);
            $this->authorize('delete customers');

            $user = auth()->user();
            if ($customer->company_id !== $user->company_id) {
                abort(403, 'You cannot delete this customer.');
            }

            // Check if customer has invoices
            if ($customer->invoices()->count() > 0) {
                return back()->withErrors(['error' => 'Cannot delete customer with existing invoices.']);
            }

            $this->customerRepository->delete($id);

            return redirect()->route('customers.index')
                ->with('success', 'Customer deleted successfully.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Customer deletion failed. Please try again.']);
        }
    }

    /**
     * Toggle customer status
     */
    public function toggleStatus(int $id): JsonResponse
    {
        try {
            $customer = $this->customerRepository->findOrFail($id);
            $this->authorize('edit customers');

            $user = auth()->user();
            if ($customer->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $newStatus = $customer->status === 'active' ? 'inactive' : 'active';
            $this->customerRepository->update($id, ['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'status' => $newStatus,
                'message' => 'Customer status updated successfully.'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Status update failed'], 500);
        }
    }

    /**
     * Get customer orders
     */
    public function orders(int $id): Response
    {
        $customer = $this->customerRepository->findOrFail($id);
        $this->authorize('view customers');

        $user = auth()->user();
        if ($customer->company_id !== $user->company_id) {
            abort(403, 'You cannot view this customer.');
        }

        $invoices = $customer->invoices()->with(['invoiceItems.product', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Customers/Orders', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->display_name,
                'customer_code' => $customer->customer_code,
            ],
            'invoices' => [
                'data' => $invoices->items(),
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
                'from' => $invoices->firstItem(),
                'to' => $invoices->lastItem(),
            ],
        ]);
    }

    /**
     * Get customer invoices
     */
    public function invoices(int $id): Response
    {
        return $this->orders($id); // Same as orders for now
    }

    /**
     * Get customer payments
     */
    public function payments(int $id): Response
    {
        $customer = $this->customerRepository->findOrFail($id);
        $this->authorize('view customers');

        $user = auth()->user();
        if ($customer->company_id !== $user->company_id) {
            abort(403, 'You cannot view this customer.');
        }

        $payments = $customer->payments()->with(['invoice'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return Inertia::render('Customers/Payments', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->display_name,
                'customer_code' => $customer->customer_code,
            ],
            'payments' => [
                'data' => $payments->items(),
                'current_page' => $payments->currentPage(),
                'last_page' => $payments->lastPage(),
                'per_page' => $payments->perPage(),
                'total' => $payments->total(),
                'from' => $payments->firstItem(),
                'to' => $payments->lastItem(),
            ],
        ]);
    }

    /**
     * Update customer balance
     */
    public function updateBalance(Request $request, int $id): JsonResponse
    {
        try {
            $this->authorize('edit customers');

            $request->validate([
                'amount' => 'required|numeric|min:0',
                'operation' => 'required|in:add,subtract',
                'reason' => 'required|string|max:255',
            ]);

            $customer = $this->customerRepository->findOrFail($id);

            $user = auth()->user();
            if ($customer->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $success = $this->customerRepository->updateBalance(
                $id, 
                $request->amount, 
                $request->operation
            );

            if ($success) {
                // Log the balance update
                activity()
                    ->performedOn($customer)
                    ->causedBy($user)
                    ->withProperties([
                        'amount' => $request->amount,
                        'operation' => $request->operation,
                        'reason' => $request->reason,
                        'old_balance' => $customer->current_balance,
                    ])
                    ->log('balance_updated');

                $customer->refresh();

                return response()->json([
                    'success' => true,
                    'new_balance' => $customer->current_balance,
                    'formatted_balance' => $customer->formatted_balance,
                    'message' => 'Customer balance updated successfully.'
                ]);
            }

            return response()->json(['error' => 'Balance update failed'], 500);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Balance update failed'], 500);
        }
    }

    /**
     * Bulk actions on customers
     */
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            $this->authorize('edit customers');

            $request->validate([
                'action' => 'required|in:activate,deactivate,suspend,delete',
                'customer_ids' => 'required|array|min:1',
                'customer_ids.*' => 'exists:customers,id',
            ]);

            $customerIds = $request->customer_ids;
            $action = $request->action;

            $user = auth()->user();

            // Verify all customers belong to user's company
            $customers = $this->customerRepository->getModel()
                ->whereIn('id', $customerIds)
                ->where('company_id', $user->company_id)
                ->get();

            if ($customers->count() !== count($customerIds)) {
                return response()->json(['error' => 'Some customers not found or unauthorized'], 403);
            }

            $updated = 0;

            switch ($action) {
                case 'activate':
                    $updated = $this->customerRepository->bulkUpdateStatus($customerIds, 'active');
                    break;
                case 'deactivate':
                    $updated = $this->customerRepository->bulkUpdateStatus($customerIds, 'inactive');
                    break;
                case 'suspend':
                    $updated = $this->customerRepository->bulkUpdateStatus($customerIds, 'suspended');
                    break;
                case 'delete':
                    // Check if any customer has invoices
                    $customersWithInvoices = $customers->filter(fn($c) => $c->invoices()->count() > 0);
                    if ($customersWithInvoices->count() > 0) {
                        return response()->json([
                            'error' => 'Cannot delete customers with existing invoices'
                        ], 400);
                    }
                    
                    foreach ($customerIds as $customerId) {
                        $this->customerRepository->delete($customerId);
                        $updated++;
                    }
                    break;
            }

            return response()->json([
                'success' => true,
                'updated' => $updated,
                'message' => "Successfully {$action}d {$updated} customers."
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Bulk action failed'], 500);
        }
    }
}