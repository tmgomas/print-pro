<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Invoice;
use App\Models\PaymentVerification;
use App\Models\PaymentNotification;
use App\Repositories\PaymentRepository;
use App\Repositories\PaymentVerificationRepository;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Events\PaymentCreated;
use App\Events\PaymentVerified;
use App\Events\PaymentRejected;

class PaymentService extends BaseService
{
    protected PaymentVerificationRepository $verificationRepository;

    public function __construct(
        PaymentRepository $repository,
        PaymentVerificationRepository $verificationRepository
    ) {
        parent::__construct($repository);
        $this->verificationRepository = $verificationRepository;
    }

    /**
     * Create a new payment record
     */
    public function createPayment(array $data): Payment
    {
        try {
            return \DB::transaction(function () use ($data) {
                // Generate unique payment reference
                $data['payment_reference'] = $this->generatePaymentReference($data['branch_id']);
                
                // Set defaults
                $data['received_by'] = auth()->id();
                $data['branch_id'] = auth()->user()->branch_id;
                
                // Handle receipt image upload
                if (isset($data['receipt_image']) && $data['receipt_image'] instanceof UploadedFile) {
                    $data['receipt_image'] = $this->uploadReceiptImage(
                        $data['receipt_image'], 
                        $data['payment_reference']
                    );
                }

                // Create payment record
                $payment = $this->repository->create($data);

                // Update invoice payment status
                $this->updateInvoicePaymentStatus($payment->invoice_id);

                // Fire payment created event
                // event(new PaymentCreated($payment));

                return $payment;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'creating payment');
        }
    }

    /**
     * Update existing payment
     */
    public function updatePayment(int $paymentId, array $data): bool
    {
        try {
            return \DB::transaction(function () use ($paymentId, $data) {
                $payment = $this->repository->findOrFail($paymentId);
                
                // Only allow updating pending payments
                if ($payment->status !== 'pending') {
                    throw new \Exception('Cannot update processed payments');
                }

                // Handle receipt image upload
                if (isset($data['receipt_image']) && $data['receipt_image'] instanceof UploadedFile) {
                    // Delete old image if exists
                    if ($payment->receipt_image) {
                        Storage::disk('public')->delete($payment->receipt_image);
                    }
                    
                    $data['receipt_image'] = $this->uploadReceiptImage(
                        $data['receipt_image'], 
                        $payment->payment_reference
                    );
                }

                $result = $this->repository->update($paymentId, $data);

                if ($result) {
                    // Update invoice payment status
                    $this->updateInvoicePaymentStatus($payment->invoice_id);
                }

                return $result;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'updating payment');
        }
    }

    /**
     * Process manual payment verification
     */
    public function processManualPaymentVerification(array $data): PaymentVerification
    {
        try {
            return \DB::transaction(function () use ($data) {
                // Upload bank slip if provided
                if (isset($data['bank_slip_image']) && $data['bank_slip_image'] instanceof UploadedFile) {
                    $data['bank_slip_image'] = $this->uploadBankSlip(
                        $data['bank_slip_image'], 
                        $data['invoice_id']
                    );
                }

                // Set default verification status
                $data['verification_status'] = 'pending';

                // Create payment verification record
                $verification = $this->verificationRepository->create($data);

                // Create payment notification for staff
                $this->createPaymentNotification($verification);

                return $verification;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'processing manual payment verification');
        }
    }

    /**
     * Verify a payment
     */
    public function verifyPayment(int $paymentId, int $verifiedBy, string $notes = null): bool
    {
        try {
            return \DB::transaction(function () use ($paymentId, $verifiedBy, $notes) {
                $payment = $this->repository->findOrFail($paymentId);
                
                $result = $this->repository->updateVerificationStatus(
                    $paymentId, 
                    'verified', 
                    $verifiedBy, 
                    $notes
                );

                if ($result) {
                    // Update invoice payment status
                    $this->updateInvoicePaymentStatus($payment->invoice_id);
                    
                    // Fire payment verified event
                    event(new PaymentVerified($payment->fresh()));
                }

                return $result;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'verifying payment');
        }
    }

    /**
     * Reject a payment
     */
    public function rejectPayment(int $paymentId, int $rejectedBy, string $reason): bool
    {
        try {
            return \DB::transaction(function () use ($paymentId, $rejectedBy, $reason) {
                $payment = $this->repository->findOrFail($paymentId);
                
                $updateData = [
                    'status' => 'failed',
                    'verification_status' => 'rejected',
                    'verified_at' => now(),
                    'verified_by' => $rejectedBy,
                    'rejection_reason' => $reason,
                ];

                $result = $this->repository->update($paymentId, $updateData);

                if ($result) {
                    // Update invoice payment status
                    $this->updateInvoicePaymentStatus($payment->invoice_id);
                    
                    // Fire payment rejected event
                    event(new PaymentRejected($payment->fresh()));
                }

                return $result;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'rejecting payment');
        }
    }

    /**
     * Process online payment (Gateway integration)
     */
    public function processOnlinePayment(array $gatewayData): Payment
    {
        try {
            return \DB::transaction(function () use ($gatewayData) {
                $paymentData = [
                    'invoice_id' => $gatewayData['invoice_id'],
                    'branch_id' => $gatewayData['branch_id'],
                    'customer_id' => $gatewayData['customer_id'],
                    'received_by' => $gatewayData['received_by'],
                    'payment_reference' => $this->generatePaymentReference($gatewayData['branch_id']),
                    'amount' => $gatewayData['amount'],
                    'payment_date' => now(),
                    'payment_method' => 'online',
                    'gateway_reference' => $gatewayData['gateway_reference'],
                    'transaction_id' => $gatewayData['transaction_id'],
                    'status' => $gatewayData['status'] ?? 'completed',
                    'verification_status' => 'verified', // Online payments are auto-verified
                    'verified_at' => now(),
                    'payment_metadata' => $gatewayData['metadata'] ?? null,
                ];

                $payment = $this->repository->create($paymentData);

                // Update invoice payment status
                $this->updateInvoicePaymentStatus($payment->invoice_id);

                // Fire payment created event
                event(new PaymentCreated($payment));

                return $payment;
            });
        } catch (\Exception $e) {
            $this->handleException($e, 'processing online payment');
        }
    }

    /**
     * Get payment summary for an invoice
     */
    public function getInvoicePaymentSummary(int $invoiceId): array
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $payments = $this->repository->getByInvoiceId($invoiceId);

        $totalPaid = $this->repository->getTotalPaidForInvoice($invoiceId);
        $pendingAmount = $payments->where('status', 'pending')->sum('amount');
        $remainingBalance = $invoice->total_amount - $totalPaid;

        return [
            'invoice' => $invoice,
            'invoice_total' => $invoice->total_amount,
            'total_paid' => $totalPaid,
            'pending_amount' => $pendingAmount,
            'remaining_balance' => max(0, $remainingBalance),
            'payment_status' => $this->determinePaymentStatus($invoice->total_amount, $totalPaid),
            'payments' => $payments,
            'payment_history' => $payments->sortByDesc('payment_date'),
        ];
    }

    /**
     * Get filtered payments with pagination
     */
    public function getFilteredPayments(array $filters = [], int $perPage = 15)
    {
        // Add branch filter for current user
        if (!isset($filters['branch_id']) && auth()->user()->branch_id) {
            $filters['branch_id'] = auth()->user()->branch_id;
        }

        return $this->repository->getFilteredPayments($filters, $perPage);
    }

    /**
     * Get payments requiring verification
     */
    public function getPaymentsRequiringVerification(int $branchId = null)
    {
        $branchId = $branchId ?? auth()->user()->branch_id;
        return $this->repository->getPendingVerificationPayments($branchId);
    }

    /**
     * Get payment statistics for dashboard
     */
    public function getPaymentStatistics(int $branchId = null, Carbon $startDate = null, Carbon $endDate = null): array
    {
        $branchId = $branchId ?? auth()->user()->branch_id;
        return $this->repository->getPaymentStatistics($branchId, $startDate, $endDate);
    }

    /**
     * Get recent payments for dashboard
     */
    public function getRecentPayments(int $branchId = null, int $limit = 10)
    {
        $branchId = $branchId ?? auth()->user()->branch_id;
        return $this->repository->getRecentPayments($branchId, $limit);
    }

    /**
     * Get daily payment summary
     */
    public function getDailyPaymentSummary(Carbon $date, int $branchId = null): array
    {
        $branchId = $branchId ?? auth()->user()->branch_id;
        return $this->repository->getDailyPaymentSummary($date, $branchId);
    }

    /**
     * Generate unique payment reference
     */
    private function generatePaymentReference(int $branchId): string
    {
        $branch = \App\Models\Branch::find($branchId);
        $prefix = $branch ? strtoupper($branch->code) : 'PAY';
        $date = now()->format('ymd');
        $sequence = Payment::whereDate('created_at', today())->count() + 1;
        
        return sprintf('%s%s%04d', $prefix, $date, $sequence);
    }

    /**
     * Upload receipt image
     */
    private function uploadReceiptImage(UploadedFile $file, string $paymentReference): string
    {
        $filename = sprintf(
            'receipts/%s/%s_%s.%s',
            now()->format('Y/m'),
            $paymentReference,
            Str::random(8),
            $file->getClientOriginalExtension()
        );

        return $file->storeAs('public', $filename);
    }

    /**
     * Upload bank slip image
     */
    private function uploadBankSlip(UploadedFile $file, int $invoiceId): string
    {
        $filename = sprintf(
            'bank_slips/%s/invoice_%d_%s.%s',
            now()->format('Y/m'),
            $invoiceId,
            Str::random(8),
            $file->getClientOriginalExtension()
        );

        return $file->storeAs('public', $filename);
    }

    /**
     * Update invoice payment status based on payments
     */
    private function updateInvoicePaymentStatus(int $invoiceId): void
    {
        $invoice = Invoice::findOrFail($invoiceId);
        $totalPaid = $this->repository->getTotalPaidForInvoice($invoiceId);

        $paymentStatus = $this->determinePaymentStatus($invoice->total_amount, $totalPaid);
        
        $invoice->update(['payment_status' => $paymentStatus]);
    }

    /**
     * Determine payment status based on amounts
     */
    private function determinePaymentStatus(float $totalAmount, float $totalPaid): string
    {
        if ($totalPaid >= $totalAmount) {
            return 'paid';
        } elseif ($totalPaid > 0) {
            return 'partially_paid';
        } else {
            return 'pending';
        }
    }

    /**
     * Create payment notification for staff
     */
    private function createPaymentNotification(PaymentVerification $verification): void
    {
        PaymentNotification::create([
            'invoice_id' => $verification->invoice_id,
            'customer_id' => $verification->customer_id,
            'branch_id' => $verification->invoice->branch_id,
            'notification_type' => 'manual_payment_claim',
            'contact_method' => 'internal',
            'message_content' => sprintf(
                'Customer claims payment of Rs. %s for Invoice #%s via %s',
                number_format($verification->claimed_amount, 2),
                $verification->invoice->invoice_number,
                $verification->bank_name ?? 'Bank Transfer'
            ),
            'reference_number' => $verification->bank_reference,
            'amount_claimed' => $verification->claimed_amount,
            'bank_used' => $verification->bank_name,
            'status' => 'pending',
        ]);
    }
}