<?php
// app/Services/

namespace App\Services;

use App\Repositories\CustomerRepository;
use App\Models\Customer;
use Illuminate\Support\Collection;

class CustomerService
{
    public function __construct(
        private CustomerRepository $customerRepository
    ) {}

    /**
     * Create customer with validation
     */
    public function createCustomer(array $data, int $companyId): Customer
    {
        $data['company_id'] = $companyId;
        
        // Generate unique customer code
        if (empty($data['customer_code'])) {
            $data['customer_code'] = $this->customerRepository->generateUniqueCode($companyId);
        }
        
        // Set shipping address same as billing if not provided
        if (empty($data['shipping_address'])) {
            $data['shipping_address'] = $data['billing_address'];
        }
        
        // Set default credit limit
        if (!isset($data['credit_limit'])) {
            $data['credit_limit'] = 0;
        }
        
        return $this->customerRepository->create($data);
    }

    /**
     * Update customer with validation
     */
    public function updateCustomer(int $customerId, array $data, int $companyId): bool
    {
        $customer = $this->customerRepository->findOrFail($customerId);
        
        if ($customer->company_id !== $companyId) {
            throw new \Exception('Customer not found in company');
        }
        
        // Set shipping address same as billing if not provided
        if (empty($data['shipping_address'])) {
            $data['shipping_address'] = $data['billing_address'];
        }
        
        return $this->customerRepository->update($customerId, $data);
    }

    /**
     * Get customer with complete information
     */
    public function getCustomerDetails(int $customerId, int $companyId): array
    {
        $customer = $this->customerRepository->findOrFail($customerId);
        
        if ($customer->company_id !== $companyId) {
            throw new \Exception('Customer not found in company');
        }
        
        // Load relationships
        $customer->load(['branch', 'invoices.payments']);
        
        // Calculate statistics
        $totalInvoices = $customer->invoices->count();
        $totalInvoiceAmount = $customer->invoices->sum('total_amount');
        $totalPaidAmount = $customer->invoices->sum(function ($invoice) {
            return $invoice->payments->where('status', 'completed')->sum('amount');
        });
        $outstandingBalance = $totalInvoiceAmount - $totalPaidAmount;
        
        // Recent activity
        $recentInvoices = $customer->invoices()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        $recentPayments = $customer->payments()
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
        
        return [
            'customer' => $customer,
            'statistics' => [
                'total_invoices' => $totalInvoices,
                'total_invoice_amount' => $totalInvoiceAmount,
                'total_paid_amount' => $totalPaidAmount,
                'outstanding_balance' => $outstandingBalance,
                'available_credit' => $customer->credit_limit - $customer->current_balance,
                'credit_utilization' => $customer->credit_limit > 0 
                    ? ($customer->current_balance / $customer->credit_limit) * 100 
                    : 0,
            ],
            'recent_activity' => [
                'invoices' => $recentInvoices,
                'payments' => $recentPayments,
            ],
        ];
    }

    /**
     * Search customers with advanced filters
     */
    public function searchCustomers(array $filters, int $companyId): Collection
    {
        $customers = $this->customerRepository->searchAndPaginate($companyId, $filters, 100);
        
        return collect($customers->items())->map(function ($customer) {
            return [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->display_name,
                'phone' => $customer->phone,
                'email' => $customer->email,
                'city' => $customer->city,
                'customer_type' => $customer->customer_type,
                'status' => $customer->status,
                'credit_limit' => $customer->credit_limit,
                'current_balance' => $customer->current_balance,
                'available_credit' => $customer->available_credit,
                'branch_name' => $customer->branch?->name,
            ];
        });
    }

    /**
     * Update customer balance with logging
     */
    public function updateCustomerBalance(int $customerId, float $amount, string $operation, string $reason, int $userId): bool
    {
        $customer = $this->customerRepository->findOrFail($customerId);
        $oldBalance = $customer->current_balance;
        
        $success = $this->customerRepository->updateBalance($customerId, $amount, $operation);
        
        if ($success) {
            // Log the activity
            activity()
                ->performedOn($customer)
                ->causedBy(\App\Models\User::find($userId))
                ->withProperties([
                    'amount' => $amount,
                    'operation' => $operation,
                    'reason' => $reason,
                    'old_balance' => $oldBalance,
                    'new_balance' => $customer->fresh()->current_balance,
                ])
                ->log('balance_updated');
        }
        
        return $success;
    }

    /**
     * Get customers with outstanding balances
     */
    public function getCustomersWithOutstandingBalance(int $companyId): Collection
    {
        return $this->customerRepository->getCustomersWithOutstandingBalance($companyId)
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'customer_code' => $customer->customer_code,
                    'name' => $customer->display_name,
                    'phone' => $customer->phone,
                    'current_balance' => $customer->current_balance,
                    'credit_limit' => $customer->credit_limit,
                    'days_overdue' => $this->calculateDaysOverdue($customer),
                    'risk_level' => $this->calculateRiskLevel($customer),
                ];
            });
    }

    /**
     * Calculate customer risk level
     */
    private function calculateRiskLevel(Customer $customer): string
    {
        $creditUtilization = $customer->credit_limit > 0 
            ? ($customer->current_balance / $customer->credit_limit) * 100 
            : 0;
            
        $daysOverdue = $this->calculateDaysOverdue($customer);
        
        if ($creditUtilization > 90 || $daysOverdue > 30) {
            return 'high';
        } elseif ($creditUtilization > 70 || $daysOverdue > 15) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate days overdue for customer
     */
    private function calculateDaysOverdue(Customer $customer): int
    {
        $oldestUnpaidInvoice = $customer->invoices()
            ->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now())
            ->orderBy('due_date')
            ->first();
            
        if ($oldestUnpaidInvoice) {
            return now()->diffInDays($oldestUnpaidInvoice->due_date);
        }
        
        return 0;
    }

    /**
     * Export customers to CSV
     */
    public function exportCustomersToCsv(int $companyId, array $filters = []): string
    {
        $customers = $this->customerRepository->searchAndPaginate($companyId, $filters, 10000);
        
        $fileName = 'customers_export_' . date('Y-m-d_H-i-s') . '.csv';
        $filePath = storage_path('app/exports/' . $fileName);
        
        // Ensure directory exists
        if (!is_dir(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }
        
        $file = fopen($filePath, 'w');
        
        // Write headers
        fputcsv($file, [
            'Customer Code',
            'Name',
            'Email',
            'Phone',
            'Customer Type',
            'Company Name',
            'City',
            'Province',
            'Credit Limit',
            'Current Balance',
            'Status',
            'Created At',
        ]);
        
        // Write data
        foreach ($customers as $customer) {
            fputcsv($file, [
                $customer->customer_code,
                $customer->name,
                $customer->email,
                $customer->phone,
                $customer->customer_type,
                $customer->company_name,
                $customer->city,
                $customer->province,
                $customer->credit_limit,
                $customer->current_balance,
                $customer->status,
                $customer->created_at->format('Y-m-d H:i:s'),
            ]);
        }
        
        fclose($file);
        
        return $filePath;
    }

    /**
     * Import customers from CSV
     */
    public function importCustomersFromCsv(string $filePath, int $companyId): array
    {
        $imported = 0;
        $errors = [];
        
        if (!file_exists($filePath)) {
            throw new \Exception('Import file not found');
        }

        $csvData = array_map('str_getcsv', file($filePath));
        $headers = array_shift($csvData);
        
        foreach ($csvData as $index => $row) {
            try {
                $data = array_combine($headers, $row);
                $data['company_id'] = $companyId;
                
                // Generate customer code if not provided
                if (empty($data['customer_code'])) {
                    $data['customer_code'] = $this->customerRepository->generateUniqueCode($companyId);
                }
                
                // Set defaults
                if (empty($data['shipping_address'])) {
                    $data['shipping_address'] = $data['billing_address'];
                }
                
                if (!isset($data['credit_limit'])) {
                    $data['credit_limit'] = 0;
                }
                
                $this->customerRepository->create($data);
                $imported++;
                
            } catch (\Exception $e) {
                $errors[] = "Row " . ($index + 2) . ": " . $e->getMessage();
            }
        }
        
        return [
            'imported' => $imported,
            'errors' => $errors,
            'total_rows' => count($csvData),
        ];
    }

    /**
     * Get customer analytics
     */
    public function getCustomerAnalytics(int $companyId): array
    {
        $stats = $this->customerRepository->getStats($companyId);
        $cities = $this->customerRepository->getCustomersByCity($companyId);
        $outstanding = $this->getCustomersWithOutstandingBalance($companyId);
        
        return [
            'overview' => $stats,
            'geographic_distribution' => $cities,
            'outstanding_balances' => [
                'total_customers' => $outstanding->count(),
                'total_amount' => $outstanding->sum('current_balance'),
                'high_risk' => $outstanding->where('risk_level', 'high')->count(),
                'medium_risk' => $outstanding->where('risk_level', 'medium')->count(),
                'low_risk' => $outstanding->where('risk_level', 'low')->count(),
            ],
            'customer_types' => [
                'individual' => $stats['individual'],
                'business' => $stats['business'],
                'individual_percentage' => $stats['total'] > 0 ? ($stats['individual'] / $stats['total']) * 100 : 0,
                'business_percentage' => $stats['total'] > 0 ? ($stats['business'] / $stats['total']) * 100 : 0,
            ],
        ];
    }
}
