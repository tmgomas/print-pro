<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Repositories\InvoiceRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\ProductRepository;
use App\Repositories\BranchRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class InvoiceService extends BaseService
{
    protected CustomerRepository $customerRepository;
    protected ProductRepository $productRepository;
    protected BranchRepository $branchRepository;

    public function __construct(
        InvoiceRepository $repository,
        CustomerRepository $customerRepository,
        ProductRepository $productRepository,
        BranchRepository $branchRepository
    ) {
        parent::__construct($repository);
        $this->customerRepository = $customerRepository;
        $this->productRepository = $productRepository;
        $this->branchRepository = $branchRepository;
    }

    /**
     * Create invoice with items
     */
    public function createInvoice(array $data, int $companyId, int $userId): Invoice
    {
        try {
            return DB::transaction(function () use ($data, $companyId, $userId) {
                // Validate customer exists in company
                $customer = $this->customerRepository->findOrFail($data['customer_id']);
                if ($customer->company_id !== $companyId) {
                    throw new \Exception('Customer not found in company.');
                }

                // Validate branch exists in company
                $branch = $this->branchRepository->findOrFail($data['branch_id']);
                if ($branch->company_id !== $companyId) {
                    throw new \Exception('Branch not found in company.');
                }

                // Prepare invoice data
                $invoiceData = [
                    'company_id' => $companyId,
                    'branch_id' => $data['branch_id'],
                    'customer_id' => $data['customer_id'],
                    'created_by' => $userId,
                    'invoice_date' => $data['invoice_date'] ?? now()->toDateString(),
                    'due_date' => $data['due_date'] ?? now()->addDays(30)->toDateString(),
                    'notes' => $data['notes'] ?? null,
                    'terms_conditions' => $data['terms_conditions'] ?? null,
                    'discount_amount' => $data['discount_amount'] ?? 0,
                    'status' => $data['status'] ?? 'draft',
                ];

                // Generate invoice number if not provided
                if (empty($data['invoice_number'])) {
                    $invoiceData['invoice_number'] = $this->repository->generateInvoiceNumber($data['branch_id']);
                } else {
                    $invoiceData['invoice_number'] = $data['invoice_number'];
                }

                // Create invoice
                $invoice = $this->repository->create($invoiceData);

                // Add invoice items if provided
                if (!empty($data['items'])) {
                    $this->addInvoiceItems($invoice, $data['items']);
                }

                // Calculate and update totals
                $this->calculateInvoiceTotals($invoice);

                return $invoice->load(['customer', 'branch', 'items.product', 'creator']);
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'invoice creation');
            throw $e;
        }
    }

    /**
     * Update invoice
     */
    public function updateInvoice(int $invoiceId, array $data, int $companyId): Invoice
    {
        try {
            return DB::transaction(function () use ($invoiceId, $data, $companyId) {
                $invoice = $this->repository->findOrFail($invoiceId);
                
                // Verify invoice belongs to company
                if ($invoice->company_id !== $companyId) {
                    throw new \Exception('Invoice not found in company.');
                }

                // Check if invoice can be modified
                if (!$this->repository->canBeModified($invoiceId)) {
                    throw new \Exception('Invoice cannot be modified at this time.');
                }

                // Update invoice data
                $updateData = array_intersect_key($data, array_flip([
                    'due_date', 'notes', 'terms_conditions', 'discount_amount', 'status'
                ]));

                $this->repository->update($invoiceId, $updateData);

                // Update items if provided
                if (isset($data['items'])) {
                    $this->updateInvoiceItems($invoice, $data['items']);
                }

                // Recalculate totals
                $invoice->refresh();
                $this->calculateInvoiceTotals($invoice);

                return $invoice->load(['customer', 'branch', 'items.product', 'creator']);
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'invoice update');
            throw $e;
        }
    }

    /**
     * Add items to invoice
     */
    protected function addInvoiceItems(Invoice $invoice, array $items): void
    {
        foreach ($items as $itemData) {
            // Validate product exists
            $product = $this->productRepository->findOrFail($itemData['product_id']);
            if ($product->company_id !== $invoice->company_id) {
                throw new \Exception("Product {$product->name} not found in company.");
            }

            // Calculate item totals
            $quantity = $itemData['quantity'];
            $unitPrice = $itemData['unit_price'] ?? $product->base_price;
            $unitWeight = $itemData['unit_weight'] ?? $product->weight_per_unit;
            
            $lineTotal = $quantity * $unitPrice;
            $lineWeight = $quantity * $unitWeight;
            $taxAmount = $lineTotal * ($product->tax_rate / 100);

            // Create invoice item
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'product_id' => $product->id,
                'item_description' => $itemData['item_description'] ?? $product->name,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'unit_weight' => $unitWeight,
                'line_total' => $lineTotal,
                'line_weight' => $lineWeight,
                'tax_amount' => $taxAmount,
                'specifications' => $itemData['specifications'] ?? null,
            ]);
        }
    }

    /**
     * Update invoice items
     */
    protected function updateInvoiceItems(Invoice $invoice, array $items): void
    {
        // Delete existing items
        $invoice->items()->delete();

        // Add new items
        $this->addInvoiceItems($invoice, $items);
    }

    /**
     * Calculate invoice totals
     */
    protected function calculateInvoiceTotals(Invoice $invoice): void
    {
        $invoice->load('items');
        
        $subtotal = $invoice->items->sum('line_total');
        $totalWeight = $invoice->items->sum('line_weight');
        
        // Calculate weight-based delivery charge
        $weightCharge = $this->calculateWeightCharge($totalWeight, $invoice->company_id);
        
        // Calculate tax on subtotal + weight charge - discount
        $taxableAmount = $subtotal + $weightCharge - $invoice->discount_amount;
        $taxAmount = $taxableAmount * ($invoice->company->tax_rate ?? 0.12);
        
        // Calculate final total
        $totalAmount = $subtotal + $weightCharge + $taxAmount - $invoice->discount_amount;

        // Update invoice totals
        $invoice->update([
            'subtotal' => $subtotal,
            'total_weight' => $totalWeight,
            'weight_charge' => $weightCharge,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
        ]);
    }

    /**
     * Calculate weight-based delivery charge
     */
    protected function calculateWeightCharge(float $weight, int $companyId): float
    {
        // Check if company has custom weight pricing tiers
        $weightTier = \App\Models\WeightPricingTier::where('company_id', $companyId)
            ->where('status', 'active')
            ->where('min_weight', '<=', $weight)
            ->where(function($query) use ($weight) {
                $query->where('max_weight', '>=', $weight)
                      ->orWhereNull('max_weight');
            })
            ->orderBy('min_weight', 'desc')
            ->first();

        if ($weightTier) {
            $basePrice = $weightTier->base_price;
            $extraWeight = max(0, $weight - $weightTier->min_weight);
            return $basePrice + ($extraWeight * $weightTier->price_per_kg);
        }

        // Default weight-based pricing
        if ($weight <= 1) {
            return 200; // Light (0-1kg): Rs. 200 flat rate
        } elseif ($weight <= 3) {
            return 300; // Medium (1-3kg): Rs. 300 flat rate
        } elseif ($weight <= 5) {
            return 400; // Heavy (3-5kg): Rs. 400 flat rate
        } elseif ($weight <= 10) {
            return 500 + (($weight - 5) * 50); // Extra Heavy (5-10kg)
        } else {
            return 750 + (($weight - 10) * 75); // Bulk (10kg+)
        }
    }

    /**
     * Delete invoice
     */
    public function deleteInvoice(int $invoiceId, int $companyId): bool
    {
        try {
            return DB::transaction(function () use ($invoiceId, $companyId) {
                $invoice = $this->repository->findOrFail($invoiceId);
                
                // Verify invoice belongs to company
                if ($invoice->company_id !== $companyId) {
                    throw new \Exception('Invoice not found in company.');
                }

                // Check if invoice can be deleted
                if (!$this->repository->canBeDeleted($invoiceId)) {
                    throw new \Exception('Invoice cannot be deleted. It has payments or is not in draft status.');
                }

                // Delete invoice items first
                $invoice->items()->delete();

                // Delete invoice
                return $this->repository->delete($invoiceId);
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'invoice deletion');
            throw $e;
        }
    }

    /**
     * Mark invoice as paid
     */
    public function markAsPaid(int $invoiceId, int $companyId): Invoice
    {
        $invoice = $this->repository->findOrFail($invoiceId);
        
        if ($invoice->company_id !== $companyId) {
            throw new \Exception('Invoice not found in company.');
        }

        $invoice->markAsPaid();
        
        return $invoice;
    }

    /**
     * Update payment status based on payments
     */
    public function updatePaymentStatus(int $invoiceId): void
    {
        $invoice = $this->repository->findOrFail($invoiceId);
        $invoice->updatePaymentStatus();
    }

    /**
     * Generate invoice PDF
     */
    public function generatePDF(int $invoiceId, int $companyId): string
    {
        $invoice = $this->repository->findWithDetails($invoiceId);
        
        if (!$invoice || $invoice->company_id !== $companyId) {
            throw new \Exception('Invoice not found.');
        }

        // Here you would implement PDF generation logic
        // Using something like Laravel DomPDF or similar
        
        return "PDF generation logic here";
    }

    /**
     * Get invoice statistics - delegated to repository
     */
    public function getInvoiceStats(int $companyId, ?int $branchId = null): array
    {
        return $this->repository->getStats($companyId, $branchId);
    }

    /**
     * Get daily income report - delegated to repository
     */
    public function getDailyIncomeReport(int $companyId, Carbon $startDate, Carbon $endDate, ?int $branchId = null): array
    {
        return $this->repository->getDailyIncomeReport($companyId, $startDate, $endDate, $branchId);
    }

    /**
     * Get weekly income summary - delegated to repository
     */
    public function getWeeklyIncomeSummary(int $companyId, ?int $branchId = null): array
    {
        return $this->repository->getWeeklyIncomeSummary($companyId, $branchId);
    }

    /**
     * Get overdue invoices - delegated to repository
     */
    public function getOverdueInvoices(int $companyId, ?int $branchId = null): \Illuminate\Database\Eloquent\Collection
    {
        return $this->repository->getOverdueInvoices($companyId, $branchId);
    }

    /**
     * Duplicate invoice
     */
    public function duplicateInvoice(int $invoiceId, int $companyId, int $userId): Invoice
    {
        try {
            return DB::transaction(function () use ($invoiceId, $companyId, $userId) {
                $originalInvoice = $this->repository->findWithDetails($invoiceId);
                
                if (!$originalInvoice || $originalInvoice->company_id !== $companyId) {
                    throw new \Exception('Invoice not found.');
                }

                // Prepare duplicate data
                $duplicateData = [
                    'customer_id' => $originalInvoice->customer_id,
                    'branch_id' => $originalInvoice->branch_id,
                    'notes' => $originalInvoice->notes,
                    'terms_conditions' => $originalInvoice->terms_conditions,
                    'discount_amount' => $originalInvoice->discount_amount,
                    'items' => $originalInvoice->items->map(function ($item) {
                        return [
                            'product_id' => $item->product_id,
                            'item_description' => $item->item_description,
                            'quantity' => $item->quantity,
                            'unit_price' => $item->unit_price,
                            'unit_weight' => $item->unit_weight,
                            'specifications' => $item->specifications,
                        ];
                    })->toArray(),
                ];

                return $this->createInvoice($duplicateData, $companyId, $userId);
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'invoice duplication');
            throw $e;
        }
    }

    /**
     * Business rule: Validate invoice creation data
     */
    public function validateInvoiceCreation(array $data, int $companyId): array
    {
        $errors = [];

        // Check customer credit limit
        if (!empty($data['customer_id'])) {
            $customer = $this->customerRepository->find($data['customer_id']);
            if ($customer && isset($data['total_amount'])) {
                $availableCredit = $customer->credit_limit - $customer->current_balance;
                if ($data['total_amount'] > $availableCredit) {
                    $errors[] = "Invoice amount exceeds customer's available credit limit.";
                }
            }
        }

        // Check branch operational status
        if (!empty($data['branch_id'])) {
            $branch = $this->branchRepository->find($data['branch_id']);
            if ($branch && $branch->status !== 'active') {
                $errors[] = "Cannot create invoice for inactive branch.";
            }
        }

        // Validate items stock availability (if product has inventory tracking)
        if (!empty($data['items'])) {
            foreach ($data['items'] as $item) {
                $product = $this->productRepository->find($item['product_id']);
                if ($product && isset($product->stock_quantity)) {
                    if ($item['quantity'] > $product->stock_quantity) {
                        $errors[] = "Insufficient stock for product: {$product->name}";
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Business rule: Calculate invoice analytics
     */
    public function calculateInvoiceAnalytics(int $invoiceId): array
    {
        $invoice = $this->repository->findWithDetails($invoiceId);
        
        if (!$invoice) {
            throw new \Exception('Invoice not found.');
        }

        // Calculate profitability metrics
        $totalCost = $invoice->items->sum(function ($item) {
            return $item->quantity * ($item->product->cost_price ?? 0);
        });

        $grossProfit = $invoice->subtotal - $totalCost;
        $profitMargin = $invoice->subtotal > 0 ? ($grossProfit / $invoice->subtotal) * 100 : 0;

        // Calculate delivery efficiency
        $deliveryDays = $invoice->deliveries->count() > 0 
            ? $invoice->created_at->diffInDays($invoice->deliveries->first()->delivered_at)
            : null;

        return [
            'financial' => [
                'total_cost' => $totalCost,
                'gross_profit' => $grossProfit,
                'profit_margin' => round($profitMargin, 2),
                'weight_to_value_ratio' => $invoice->total_amount > 0 
                    ? $invoice->total_weight / $invoice->total_amount 
                    : 0,
            ],
            'operational' => [
                'items_count' => $invoice->items->count(),
                'average_item_value' => $invoice->items->count() > 0 
                    ? $invoice->subtotal / $invoice->items->count() 
                    : 0,
                'delivery_days' => $deliveryDays,
                'payment_terms_days' => $invoice->created_at->diffInDays($invoice->due_date),
            ],
            'customer' => [
                'customer_value_score' => $this->calculateCustomerValueScore($invoice->customer_id),
                'repeat_customer' => $this->repository->getByCustomer($invoice->customer_id, 2)->count() > 1,
            ],
        ];
    }

    /**
     * Helper: Calculate customer value score
     */
    private function calculateCustomerValueScore(int $customerId): float
    {
        $customerInvoices = $this->repository->getByCustomer($customerId, 12); // Last 12 invoices
        
        if ($customerInvoices->isEmpty()) {
            return 0;
        }

        $totalValue = $customerInvoices->sum('total_amount');
        $averageValue = $totalValue / $customerInvoices->count();
        $frequency = $customerInvoices->count();
        $recency = $customerInvoices->first()->created_at->diffInDays(now());

        // Simple scoring algorithm (can be enhanced)
        $valueScore = min($averageValue / 10000, 10); // Max 10 points for high-value orders
        $frequencyScore = min($frequency / 2, 10); // Max 10 points for frequent orders
        $recencyScore = max(10 - ($recency / 30), 0); // Lose points for old last order

        return round(($valueScore + $frequencyScore + $recencyScore) / 3, 2);
    }

    /**
     * Business rule: Auto-generate recommendations
     */
    public function generateInvoiceRecommendations(int $customerId, array $orderData = []): array
    {
        $recommendations = [];

        // Get customer's order history
        $customerInvoices = $this->repository->getByCustomer($customerId, 10);
        
        if ($customerInvoices->isNotEmpty()) {
            // Recommend frequently ordered products
            $frequentProducts = $customerInvoices->flatMap->items
                ->groupBy('product_id')
                ->map->count()
                ->sortDesc()
                ->take(5);

            $recommendations['frequent_products'] = $frequentProducts->keys()->map(function ($productId) {
                return $this->productRepository->find($productId);
            })->filter();

            // Recommend optimal quantities based on history
            $recommendations['optimal_quantities'] = $frequentProducts->map(function ($count, $productId) use ($customerInvoices) {
                $averageQuantity = $customerInvoices->flatMap->items
                    ->where('product_id', $productId)
                    ->avg('quantity');
                
                return [
                    'product_id' => $productId,
                    'recommended_quantity' => ceil($averageQuantity),
                ];
            });

            // Recommend best delivery schedule
            $averageDeliveryDays = $customerInvoices->avg(function ($invoice) {
                return $invoice->created_at->diffInDays($invoice->due_date);
            });

            $recommendations['suggested_due_date'] = now()->addDays(ceil($averageDeliveryDays));
        }

        return $recommendations;
    }
}