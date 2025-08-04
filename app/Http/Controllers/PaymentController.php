<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Http\Requests\StorePaymentVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Carbon\Carbon;

class PaymentController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
        $this->middleware('auth');
        $this->middleware('can:view payments')->only(['index', 'show']);
        $this->middleware('can:create payments')->only(['create', 'store']);
        $this->middleware('can:update payments')->only(['edit', 'update']);
        $this->middleware('can:verify payments')->only(['verify', 'reject']);
    }

    /**
     * Display payments listing
     */
    public function index(Request $request): View
    {
        try {
            // Prepare filters from request
            $filters = $request->only([
                'status', 
                'verification_status', 
                'payment_method', 
                'date_from', 
                'date_to', 
                'search'
            ]);

            // Get filtered payments
            $payments = $this->paymentService->getFilteredPayments($filters, 15);
            
            // Get statistics for dashboard
            $statistics = $this->paymentService->getPaymentStatistics(
                auth()->user()->branch_id,
                $request->date_from ? Carbon::parse($request->date_from) : null,
                $request->date_to ? Carbon::parse($request->date_to) : null
            );

            // Payment methods for filter dropdown
            $paymentMethods = [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'online' => 'Online Payment',
                'card' => 'Card Payment',
                'cheque' => 'Cheque',
                'mobile_payment' => 'Mobile Payment',
            ];

            // Status options for filter dropdown
            $statusOptions = [
                'pending' => 'Pending',
                'processing' => 'Processing',
                'completed' => 'Completed',
                'failed' => 'Failed',
                'cancelled' => 'Cancelled',
                'refunded' => 'Refunded',
            ];

            $verificationStatusOptions = [
                'pending' => 'Pending Verification',
                'verified' => 'Verified',
                'rejected' => 'Rejected',
            ];

            return view('payments.index', compact(
                'payments', 
                'statistics', 
                'paymentMethods', 
                'statusOptions', 
                'verificationStatusOptions',
                'filters'
            ));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load payments: ' . $e->getMessage()]);
        }
    }

    /**
     * Show payment creation form
     */
    public function create(Request $request): View
    {
        try {
            $invoice = null;
            $paymentSummary = null;
            
            if ($request->filled('invoice_id')) {
                $invoice = Invoice::with(['customer', 'branch'])
                    ->where('branch_id', auth()->user()->branch_id)
                    ->findOrFail($request->invoice_id);
                    
                $paymentSummary = $this->paymentService->getInvoicePaymentSummary($invoice->id);
            }

            $paymentMethods = [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'online' => 'Online Payment',
                'card' => 'Card Payment',
                'cheque' => 'Cheque',
                'mobile_payment' => 'Mobile Payment',
            ];

            return view('payments.create', compact('invoice', 'paymentMethods', 'paymentSummary'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load payment form: ' . $e->getMessage()]);
        }
    }

    /**
     *   new payment
     */
    public function store(StorePaymentRequest $request): RedirectResponse
    {
        try {
            $payment = $this->paymentService->createPayment($request->validated());

            return redirect()->route('payments.show', $payment)
                ->with('success', 'Payment recorded successfully. Reference: ' . $payment->payment_reference);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to record payment: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display payment details
     */
    public function show(Payment $payment): View
    {
        try {
            // Check if user can view this payment (same branch)
            if ($payment->branch_id !== auth()->user()->branch_id) {
                abort(403, 'You can only view payments from your branch.');
            }

            $payment->load([
                'invoice.customer', 
                'customer', 
                'branch', 
                'receivedBy', 
                'verifiedBy', 
                'paymentVerifications'
            ]);
            
            $paymentSummary = $this->paymentService->getInvoicePaymentSummary($payment->invoice_id);

            return view('payments.show', compact('payment', 'paymentSummary'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load payment details: ' . $e->getMessage()]);
        }
    }

    /**
     * Show payment edit form
     */
    public function edit(Payment $payment): View
    {
        try {
            // Check permissions
            if ($payment->branch_id !== auth()->user()->branch_id) {
                abort(403, 'You can only edit payments from your branch.');
            }

            // Only allow editing pending payments
            if ($payment->status !== 'pending') {
                return back()->withErrors(['error' => 'Cannot edit processed payments']);
            }

            $paymentMethods = [
                'cash' => 'Cash',
                'bank_transfer' => 'Bank Transfer',
                'online' => 'Online Payment',
                'card' => 'Card Payment',
                'cheque' => 'Cheque',
                'mobile_payment' => 'Mobile Payment',
            ];

            return view('payments.edit', compact('payment', 'paymentMethods'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load payment edit form: ' . $e->getMessage()]);
        }
    }

    /**
     * Update payment
     */
    public function update(UpdatePaymentRequest $request, Payment $payment): RedirectResponse
    {
        try {
            // Check permissions
            if ($payment->branch_id !== auth()->user()->branch_id) {
                abort(403, 'You can only update payments from your branch.');
            }

            $result = $this->paymentService->updatePayment($payment->id, $request->validated());

            if ($result) {
                return redirect()->route('payments.show', $payment)
                    ->with('success', 'Payment updated successfully.');
            }

            return back()->withErrors(['error' => 'Failed to update payment'])
                ->withInput();

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to update payment: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Verify payment
     */
    public function verify(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Check permissions
            if ($payment->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only verify payments from your branch.',
                ], 403);
            }

            $result = $this->paymentService->verifyPayment(
                $payment->id,
                auth()->id(),
                $request->notes
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment verified successfully',
                    'payment' => $payment->fresh(['invoice', 'customer']),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify payment',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error verifying payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reject payment
     */
    public function reject(Request $request, Payment $payment): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            // Check permissions
            if ($payment->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reject payments from your branch.',
                ], 403);
            }

            $result = $this->paymentService->rejectPayment(
                $payment->id,
                auth()->id(),
                $request->reason
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Payment rejected successfully',
                    'payment' => $payment->fresh(['invoice', 'customer']),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject payment',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete payment (soft delete)
     */
    public function destroy(Payment $payment): RedirectResponse
    {
        try {
            // Check permissions
            if ($payment->branch_id !== auth()->user()->branch_id) {
                abort(403, 'You can only delete payments from your branch.');
            }

            // Only allow deleting pending payments
            if ($payment->status !== 'pending') {
                return back()->withErrors(['error' => 'Cannot delete processed payments']);
            }

            $result = $this->paymentService->delete($payment->id);

            if ($result) {
                return redirect()->route('payments.index')
                    ->with('success', 'Payment deleted successfully.');
            }

            return back()->withErrors(['error' => 'Failed to delete payment']);

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete payment: ' . $e->getMessage()]);
        }
    }

    /**
     * Get payment data for AJAX
     */
    public function getPaymentData(Payment $payment): JsonResponse
    {
        try {
            // Check permissions
            if ($payment->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'error' => 'You can only view payments from your branch.'
                ], 403);
            }

            $payment->load(['invoice', 'customer', 'receivedBy', 'verifiedBy']);
            
            return response()->json([
                'success' => true,
                'payment' => $payment,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading payment data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get payments for invoice (AJAX)
     */
    public function getInvoicePayments(Invoice $invoice): JsonResponse
    {
        try {
            // Check permissions
            if ($invoice->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'error' => 'You can only view invoices from your branch.'
                ], 403);
            }

            $paymentSummary = $this->paymentService->getInvoicePaymentSummary($invoice->id);
            
            return response()->json([
                'success' => true,
                'summary' => $paymentSummary,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading invoice payments: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dashboard widget for pending verifications
     */
    public function pendingVerifications(): JsonResponse
    {
        try {
            $pendingPayments = $this->paymentService->getPaymentsRequiringVerification();
            
            return response()->json([
                'success' => true,
                'pending_count' => $pendingPayments->count(),
                'payments' => $pendingPayments->take(5), // Show only 5 recent ones
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading pending verifications: ' . $e->getMessage(),
            ], 500);
        }
    }
}