<?php
// app/Http/Controllers/CustomerController.php

namespace App\Http\Controllers;

use App\Http\Requests\CreateCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Repositories\CustomerRepository;
use App\Repositories\BranchRepository;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CustomerRepository $customerRepository,
        private BranchRepository $branchRepository,
        private CustomerService $customerService
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
            'province' => $request->get('province'),
            'has_credit_limit' => $request->boolean('has_credit_limit'),
            'has_outstanding_balance' => $request->boolean('has_outstanding_balance'),
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
                    ['value' => 'active', 'label' => 'Active / සක්‍රිය'],
                    ['value' => 'inactive', 'label' => 'Inactive / අක්‍රිය'],
                    ['value' => 'suspended', 'label' => 'Suspended / අත්හිටුවා ඇත'],
                ],
                'customerTypes' => [
                    ['value' => 'individual', 'label' => 'Individual / පුද්ගලික'],
                    ['value' => 'business', 'label' => 'Business / ව්‍යාපාරික'],
                ],
                'provinces' => [
                    ['value' => 'western', 'label' => 'Western Province / බස්නාහිර පළාත'],
                    ['value' => 'central', 'label' => 'Central Province / මධ්‍යම පළාත'],
                    ['value' => 'southern', 'label' => 'Southern Province / දකුණු පළාත'],
                    ['value' => 'northern', 'label' => 'Northern Province / උතුරු පළාත'],
                    ['value' => 'eastern', 'label' => 'Eastern Province / නැගෙනහිර පළාත'],
                    ['value' => 'north_western', 'label' => 'North Western Province / වයඹ පළාත'],
                    ['value' => 'north_central', 'label' => 'North Central Province / උතුරු මැද පළාත'],
                    ['value' => 'uva', 'label' => 'Uva Province / ඌව පළාත'],
                    ['value' => 'sabaragamuwa', 'label' => 'Sabaragamuwa Province / සබරගමුව පළාත'],
                ],
            ],
            'permissions' => [
                'canCreate' => $user->can('create customers'),
                'canEdit' => $user->can('edit customers'),
                'canDelete' => $user->can('delete customers'),
                'canViewAllBranches' => $user->can('view all branches'),
                'canBulkAction' => $user->can('bulk edit customers'),
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
            'formOptions' => $this->getFormOptions(),
        ]);
    }

    /**
     * Store a newly created customer
     */
    public function store(CreateCustomerRequest $request): RedirectResponse
    {
        try {
            $customer = $this->customerService->createCustomer(
                $request->validated(),
                auth()->user()->company_id
            );

            return redirect()->route('customers.show', $customer->id)
                ->with('success', 'Customer created successfully. / ගනුදෙනුකරු සාර්ථකව නිර්මාණය කරන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Customer creation failed', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'data' => $request->except(['password'])
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Customer creation failed. Please try again. / ගනුදෙනුකරු නිර්මාණය අසාර්ථක විය. කරුණාකර නැවත උත්සාහ කරන්න.']);
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

        $stats = $this->customerService->getCustomerStatistics($customer);

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
                'full_address' => $customer->full_address,
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
                'calculated_age' => $customer->calculated_age,
                'company_name' => $customer->company_name,
                'company_registration' => $customer->company_registration,
                'contact_person' => $customer->contact_person,
                'contact_person_phone' => $customer->contact_person_phone,
                'contact_person_email' => $customer->contact_person_email,
                'primary_contact' => $customer->primary_contact,
                'emergency_contact' => $customer->emergency_contact,
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
            'statistics' => $stats,
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
                'canUpdateBalance' => $user->can('update customer balance'),
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
                'company_registration' => $customer->company_registration,
                'contact_person' => $customer->contact_person,
                'contact_person_phone' => $customer->contact_person_phone,
                'contact_person_email' => $customer->contact_person_email,
                'emergency_contact_name' => $customer->emergency_contact_name,
                'emergency_contact_phone' => $customer->emergency_contact_phone,
                'emergency_contact_relationship' => $customer->emergency_contact_relationship,
                'notes' => $customer->notes,
                'preferences' => $customer->preferences,
                'branch_id' => $customer->branch_id,
            ],
            'branches' => $branches->map(fn($b) => ['value' => $b->id, 'label' => $b->name]),
            'formOptions' => $this->getFormOptions(),
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

            $updatedCustomer = $this->customerService->updateCustomer(
                $id,
                $request->validated(),
                $user->company_id
            );

            return redirect()->route('customers.show', $id)
                ->with('success', 'Customer updated successfully. / ගනුදෙනුකරු සාර්ථකව යාවත්කාලීන කරන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Customer update failed', [
                'customer_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->withInput()
                ->withErrors(['error' => 'Customer update failed. Please try again. / ගනුදෙනුකරු යාවත්කාලීන කිරීම අසාර්ථක විය.']);
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
                return back()->withErrors([
                    'error' => 'Cannot delete customer with existing invoices. / ප්‍රතිදාන සහිත ගනුදෙනුකරු මකා දැමිය නොහැක.'
                ]);
            }

            $this->customerService->deleteCustomer($id);

            return redirect()->route('customers.index')
                ->with('success', 'Customer deleted successfully. / ගනුදෙනුකරු සාර්ථකව මකා දමන ලදී.');

        } catch (\Exception $e) {
            \Log::error('Customer deletion failed', [
                'customer_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors([
                'error' => 'Customer deletion failed. Please try again. / ගනුදෙනුකරු මකා දැමීම අසාර්ථක විය.'
            ]);
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
            $updatedCustomer = $this->customerService->updateCustomerStatus($id, $newStatus);

            return response()->json([
                'success' => true,
                'status' => $updatedCustomer->status,
                'message' => 'Customer status updated successfully. / ගනුදෙනුකරුගේ තත්ත්වය සාර්ථකව යාවත්කාලීන කරන ලදී.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer status update failed', [
                'customer_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Status update failed. / තත්ත්ව යාවත්කාලීන කිරීම අසාර්ථක විය.'
            ], 500);
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
            $this->authorize('update customer balance');

            $request->validate([
                'amount' => 'required|numeric|min:0|max:999999999.99',
                'operation' => 'required|in:add,subtract,set',
                'reason' => 'required|string|max:255',
            ]);

            $customer = $this->customerRepository->findOrFail($id);

            $user = auth()->user();
            if ($customer->company_id !== $user->company_id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            $updatedCustomer = $this->customerService->updateBalance(
                $id,
                $request->amount,
                $request->operation,
                $request->reason,
                $user->id
            );

            return response()->json([
                'success' => true,
                'new_balance' => $updatedCustomer->current_balance,
                'formatted_balance' => $updatedCustomer->formatted_balance,
                'available_credit' => $updatedCustomer->available_credit,
                'formatted_available_credit' => $updatedCustomer->formatted_available_credit,
                'message' => 'Customer balance updated successfully. / ගනුදෙනුකරුගේ ශේෂය සාර්ථකව යාවත්කාලීන කරන ලදී.'
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer balance update failed', [
                'customer_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
            ]);

            return response()->json([
                'error' => 'Balance update failed. / ශේෂ යාවත්කාලීන කිරීම අසාර්ථක විය.'
            ], 500);
        }
    }

    /**
     * Bulk actions on customers
     */
    public function bulkAction(Request $request): JsonResponse
    {
        try {
            $this->authorize('bulk edit customers');

            $request->validate([
                'action' => 'required|in:activate,deactivate,suspend,delete,export',
                'customer_ids' => 'required|array|min:1|max:100',
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
                return response()->json([
                    'error' => 'Some customers not found or unauthorized. / සමහර ගනුදෙනුකරුවන් සොයා ගත නොහැක හෝ අවසර නැත.'
                ], 403);
            }

            $result = $this->customerService->bulkAction($customerIds, $action, $user->id);

            return response()->json([
                'success' => true,
                'updated' => $result['updated'],
                'message' => $result['message']
            ]);

        } catch (\Exception $e) {
            \Log::error('Customer bulk action failed', [
                'user_id' => auth()->id(),
                'action' => $request->action ?? 'unknown',
                'customer_ids' => $request->customer_ids ?? [],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Bulk action failed. / තොග ක්‍රියාව අසාර්ථක විය.'
            ], 500);
        }
    }

    /**
     * Export customers to CSV/Excel
     */
    public function export(Request $request): \Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $this->authorize('export customers');

        $user = auth()->user();
        $filters = $request->only(['status', 'customer_type', 'branch_id', 'city', 'province']);

        // Apply branch restriction for non-admin users
        if (!$user->can('view all branches') && $user->branch_id) {
            $filters['branch_id'] = $user->branch_id;
        }

        return $this->customerService->exportCustomers($user->company_id, $filters);
    }

    /**
     * Get form options for create/edit forms
     */
    private function getFormOptions(): array
    {
        return [
            'customerTypes' => [
                ['value' => 'individual', 'label' => 'Individual / පුද්ගලික'],
                ['value' => 'business', 'label' => 'Business / ව්‍යාපාරික'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Active / සක්‍රිය'],
                ['value' => 'inactive', 'label' => 'Inactive / අක්‍රිය'],
                ['value' => 'suspended', 'label' => 'Suspended / අත්හිටුවා ඇත'],
            ],
            'provinces' => [
                ['value' => 'western', 'label' => 'Western Province / බස්නාහිර පළාත'],
                ['value' => 'central', 'label' => 'Central Province / මධ්‍යම පළාත'],
                ['value' => 'southern', 'label' => 'Southern Province / දකුණු පළාත'],
                ['value' => 'northern', 'label' => 'Northern Province / උතුරු පළාත'],
                ['value' => 'eastern', 'label' => 'Eastern Province / නැගෙනහිර පළාත'],
                ['value' => 'north_western', 'label' => 'North Western Province / වයඹ පළාත'],
                ['value' => 'north_central', 'label' => 'North Central Province / උතුරු මැද පළාත'],
                ['value' => 'uva', 'label' => 'Uva Province / ඌව පළාත'],
                ['value' => 'sabaragamuwa', 'label' => 'Sabaragamuwa Province / සබරගමුව පළාත'],
            ],
            'emergencyContactRelationships' => [
                ['value' => 'spouse', 'label' => 'Spouse / කලත්‍රයා'],
                ['value' => 'parent', 'label' => 'Parent / මාපියන්'],
                ['value' => 'child', 'label' => 'Child / දරුවා'],
                ['value' => 'sibling', 'label' => 'Sibling / සහෝදර සහෝදරිය'],
                ['value' => 'friend', 'label' => 'Friend / මිතුරා'],
                ['value' => 'colleague', 'label' => 'Colleague / සගයා'],
                ['value' => 'other', 'label' => 'Other / වෙනත්'],
            ],
        ];
    }
}