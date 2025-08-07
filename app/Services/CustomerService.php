<?php

namespace App\Services;

use App\Repositories\CustomerRepository;
use App\Models\Customer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomerService extends BaseService
{
    public function __construct(
        private CustomerRepository $customerRepository
    ) {
        parent::__construct($customerRepository);
    }

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
     * Update customer status
     */
    public function updateCustomerStatus(int $customerId, string $status): Customer
    {
        $customer = $this->customerRepository->findOrFail($customerId);
        $customer->update(['status' => $status]);
        return $customer->fresh();
    }

    /**
     * Delete customer
     */
    public function deleteCustomer(int $customerId): bool
    {
        $customer = $this->customerRepository->findOrFail($customerId);
        
        // Check if customer has invoices
        if ($customer->invoices()->count() > 0) {
            throw new \Exception('Cannot delete customer with existing invoices');
        }
        
        return $this->customerRepository->delete($customerId);
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
     * Get customer statistics - MISSING METHOD ADDED
     */
    public function getCustomerStatistics(Customer $customer): array
    {
        // Load relationships if not already loaded
        $customer->loadMissing(['invoices.payments', 'payments']);
        
        // Calculate invoice statistics
        $totalInvoices = $customer->invoices->count();
        $totalInvoiceAmount = $customer->invoices->sum('total_amount');
        $paidInvoices = $customer->invoices->where('payment_status', 'paid')->count();
        $pendingInvoices = $customer->invoices->where('payment_status', 'pending')->count();
        $overdueInvoices = $customer->invoices->where('payment_status', '!=', 'paid')
            ->where('due_date', '<', now())->count();
        
        // Calculate payment statistics
        $totalPaidAmount = $customer->invoices->sum(function ($invoice) {
            return $invoice->payments->where('status', 'completed')->sum('amount');
        });
        
        $totalPayments = $customer->payments()->where('status', 'completed')->count();
        $averagePaymentAmount = $totalPayments > 0 ? $totalPaidAmount / $totalPayments : 0;
        
        // Calculate outstanding balance
        $outstandingBalance = $totalInvoiceAmount - $totalPaidAmount;
        
        // Calculate credit utilization
        $creditUtilization = $customer->credit_limit > 0 
            ? ($customer->current_balance / $customer->credit_limit) * 100 
            : 0;
        
        // Calculate available credit
        $availableCredit = max(0, $customer->credit_limit - $customer->current_balance);
        
        // Calculate average invoice amount
        $averageInvoiceAmount = $totalInvoices > 0 ? $totalInvoiceAmount / $totalInvoices : 0;
        
        // Calculate customer lifetime value
        $lifetimeValue = $totalPaidAmount;
        
        // Get recent activity counts
        $recentInvoices = $customer->invoices()
            ->where('created_at', '>=', now()->subMonths(3))
            ->count();
        
        $recentPayments = $customer->payments()
            ->where('created_at', '>=', now()->subMonths(3))
            ->where('status', 'completed')
            ->count();
        
        // Calculate payment behavior score (0-100)
        $paymentScore = $this->calculatePaymentScore($customer);
        
        return [
            'invoice_statistics' => [
                'total_invoices' => $totalInvoices,
                'total_amount' => $totalInvoiceAmount,
                'formatted_total_amount' => 'Rs. ' . number_format($totalInvoiceAmount, 2),
                'paid_invoices' => $paidInvoices,
                'pending_invoices' => $pendingInvoices,
                'overdue_invoices' => $overdueInvoices,
                'average_invoice_amount' => $averageInvoiceAmount,
                'formatted_average_invoice' => 'Rs. ' . number_format($averageInvoiceAmount, 2),
            ],
            'payment_statistics' => [
                'total_payments' => $totalPayments,
                'total_paid_amount' => $totalPaidAmount,
                'formatted_total_paid' => 'Rs. ' . number_format($totalPaidAmount, 2),
                'average_payment_amount' => $averagePaymentAmount,
                'formatted_average_payment' => 'Rs. ' . number_format($averagePaymentAmount, 2),
                'payment_score' => $paymentScore,
            ],
            'balance_statistics' => [
                'outstanding_balance' => $outstandingBalance,
                'formatted_outstanding' => 'Rs. ' . number_format($outstandingBalance, 2),
                'credit_limit' => $customer->credit_limit,
                'formatted_credit_limit' => 'Rs. ' . number_format($customer->credit_limit, 2),
                'available_credit' => $availableCredit,
                'formatted_available_credit' => 'Rs. ' . number_format($availableCredit, 2),
                'credit_utilization' => round($creditUtilization, 2),
                'current_balance' => $customer->current_balance,
                'formatted_current_balance' => 'Rs. ' . number_format($customer->current_balance, 2),
            ],
            'activity_statistics' => [
                'lifetime_value' => $lifetimeValue,
                'formatted_lifetime_value' => 'Rs. ' . number_format($lifetimeValue, 2),
                'recent_invoices_3m' => $recentInvoices,
                'recent_payments_3m' => $recentPayments,
                'customer_since' => $customer->created_at->format('Y-m-d'),
                'days_as_customer' => $customer->created_at->diffInDays(now()),
                'last_invoice_date' => $customer->invoices->max('created_at')?->format('Y-m-d'),
                'last_payment_date' => $customer->payments()
    ->where('status', 'completed')
    ->latest('created_at')
    ->first()
    ?->created_at
    ?->format('Y-m-d'),
            ],
            'risk_assessment' => [
                'risk_level' => $this->calculateRiskLevel($customer, $outstandingBalance, $creditUtilization, $overdueInvoices),
                'risk_factors' => $this->getRiskFactors($customer, $outstandingBalance, $creditUtilization, $overdueInvoices),
            ]
        ];
    }

    /**
     * Calculate payment behavior score (0-100)
     */
    private function calculatePaymentScore(Customer $customer): int
    {
        $totalInvoices = $customer->invoices->count();
        
        if ($totalInvoices === 0) {
            return 100; // New customer gets perfect score
        }
        
        $paidOnTime = $customer->invoices->where('payment_status', 'paid')
            ->filter(function ($invoice) {
                $lastPayment = $invoice->payments->where('status', 'completed')->last();
                return $lastPayment && $lastPayment->payment_date <= $invoice->due_date;
            })->count();
        
        $paidLate = $customer->invoices->where('payment_status', 'paid')
            ->filter(function ($invoice) {
                $lastPayment = $invoice->payments->where('status', 'completed')->last();
                return $lastPayment && $lastPayment->payment_date > $invoice->due_date;
            })->count();
        
        $unpaid = $customer->invoices->where('payment_status', '!=', 'paid')->count();
        
        // Calculate score based on payment behavior
        $score = 100;
        $score -= ($paidLate * 10); // -10 points for each late payment
        $score -= ($unpaid * 20); // -20 points for each unpaid invoice
        
        return max(0, min(100, $score));
    }

    /**
     * Calculate customer risk level
     */
    private function calculateRiskLevel(Customer $customer, float $outstandingBalance, float $creditUtilization, int $overdueInvoices): string
    {
        $riskScore = 0;
        
        // Outstanding balance risk
        if ($outstandingBalance > 100000) $riskScore += 3;
        elseif ($outstandingBalance > 50000) $riskScore += 2;
        elseif ($outstandingBalance > 25000) $riskScore += 1;
        
        // Credit utilization risk
        if ($creditUtilization > 90) $riskScore += 3;
        elseif ($creditUtilization > 75) $riskScore += 2;
        elseif ($creditUtilization > 50) $riskScore += 1;
        
        // Overdue invoices risk
        if ($overdueInvoices > 5) $riskScore += 3;
        elseif ($overdueInvoices > 2) $riskScore += 2;
        elseif ($overdueInvoices > 0) $riskScore += 1;
        
        // Payment score risk
        $paymentScore = $this->calculatePaymentScore($customer);
        if ($paymentScore < 50) $riskScore += 3;
        elseif ($paymentScore < 75) $riskScore += 2;
        elseif ($paymentScore < 90) $riskScore += 1;
        
        // Determine risk level
        if ($riskScore >= 8) return 'high';
        if ($riskScore >= 4) return 'medium';
        return 'low';
    }

    /**
     * Get risk factors for customer
     */
    private function getRiskFactors(Customer $customer, float $outstandingBalance, float $creditUtilization, int $overdueInvoices): array
    {
        $factors = [];
        
        if ($outstandingBalance > 50000) {
            $factors[] = 'High outstanding balance';
        }
        
        if ($creditUtilization > 75) {
            $factors[] = 'High credit utilization';
        }
        
        if ($overdueInvoices > 0) {
            $factors[] = $overdueInvoices . ' overdue invoice(s)';
        }
        
        $paymentScore = $this->calculatePaymentScore($customer);
        if ($paymentScore < 75) {
            $factors[] = 'Poor payment history';
        }
        
        if ($customer->status === 'suspended') {
            $factors[] = 'Account suspended';
        }
        
        return $factors;
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
            ];
        });
    }

    /**
     * Get customers with outstanding balances
     */
    public function getCustomersWithOutstandingBalance(int $companyId): Collection
    {
        return $this->customerRepository->getCustomersWithOutstandingBalance($companyId);
    }

    /**
     * Import customers from CSV
     */
    public function importCustomersFromCsv(array $csvData, int $companyId): array
    {
        $imported = 0;
        $errors = [];
        
        foreach ($csvData as $index => $row) {
            try {
                $this->createCustomer($row, $companyId);
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