<?php

namespace App\Http\Controllers;

use App\Models\PaymentVerification;
use App\Models\Invoice;
use App\Services\PaymentService;
use App\Repositories\PaymentVerificationRepository;
use App\Http\Requests\StorePaymentVerificationRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;

class PaymentVerificationController extends Controller
{
    protected PaymentService $paymentService;
    protected PaymentVerificationRepository $verificationRepository;

    public function __construct(
        PaymentService $paymentService,
        PaymentVerificationRepository $verificationRepository
    ) {
        $this->paymentService = $paymentService;
        $this->verificationRepository = $verificationRepository;
        $this->middleware('auth');
        $this->middleware('can:view payment verifications')->only(['index', 'show']);
        $this->middleware('can:create payment verifications')->only(['create', 'store']);
        $this->middleware('can:verify payments')->only(['verify', 'reject']);
    }

    /**
     * Display payment verifications listing
     */
    public function index(Request $request): View
    {
        try {
            // Get pending verifications with pagination
            $verifications = $this->verificationRepository->getPendingVerifications(
                auth()->user()->branch_id, 
                15
            );
            
            // Get verification statistics
            $statistics = $this->verificationRepository->getVerificationStatistics(
                auth()->user()->branch_id
            );

            return view('payment-verifications.index', compact('verifications', 'statistics'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load payment verifications: ' . $e->getMessage()]);
        }
    }

    /**
     * Show verification creation form
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

            $verificationMethods = [
                'manual' => 'Manual Verification',
                'bank_slip' => 'Bank Slip Upload',
                'receipt' => 'Receipt Upload',
                'automatic' => 'Automatic Verification',
            ];

            return view('payment-verifications.create', compact(
                'invoice', 
                'paymentSummary', 
                'verificationMethods'
            ));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load verification form: ' . $e->getMessage()]);
        }
    }

    /**
     * Store new payment verification
     */
    public function store(StorePaymentVerificationRequest $request): RedirectResponse
    {
        try {
            $verification = $this->paymentService->processManualPaymentVerification(
                $request->validated()
            );

            return redirect()->route('payment-verifications.show', $verification)
                ->with('success', 'Payment verification submitted successfully. Staff will review and process it.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to submit verification: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Display verification details
     */
    public function show(PaymentVerification $paymentVerification): View
    {
        try {
            // Check if user can view this verification (same branch)
            if ($paymentVerification->invoice->branch_id !== auth()->user()->branch_id) {
                abort(403, 'You can only view verifications from your branch.');
            }

            $paymentVerification->load([
                'invoice.customer', 
                'customer', 
                'payment',
                'verifiedBy'
            ]);

            return view('payment-verifications.show', compact('paymentVerification'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load verification details: ' . $e->getMessage()]);
        }
    }

    /**
     * Verify a payment verification
     */
    public function verify(Request $request, PaymentVerification $paymentVerification): JsonResponse
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            // Check permissions
            if ($paymentVerification->invoice->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only verify payments from your branch.',
                ], 403);
            }

            $result = $this->verificationRepository->updateVerificationStatus(
                $paymentVerification->id,
                'verified',
                auth()->id(),
                $request->notes
            );

            if ($result) {
                // If verification has associated payment, verify that payment too
                if ($paymentVerification->payment_id) {
                    $this->paymentService->verifyPayment(
                        $paymentVerification->payment_id,
                        auth()->id(),
                        $request->notes
                    );
                } else {
                    // Create new payment record based on verification
                    $paymentData = [
                        'invoice_id' => $paymentVerification->invoice_id,
                        'customer_id' => $paymentVerification->customer_id,
                        'amount' => $paymentVerification->claimed_amount,
                        'payment_date' => $paymentVerification->payment_claimed_date,
                        'payment_method' => 'bank_transfer',
                        'bank_name' => $paymentVerification->bank_name,
                        'transaction_id' => $paymentVerification->bank_reference,
                        'status' => 'completed',
                        'verification_status' => 'verified',
                        'notes' => $request->notes,
                        'verified_at' => now(),
                        'verified_by' => auth()->id(),
                    ];

                    $payment = $this->paymentService->createPayment($paymentData);
                    
                    // Link verification to the created payment
                    $paymentVerification->update(['payment_id' => $payment->id]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verification approved successfully',
                    'verification' => $paymentVerification->fresh(['invoice', 'customer', 'payment']),
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
     * Reject a payment verification
     */
    public function reject(Request $request, PaymentVerification $paymentVerification): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        try {
            // Check permissions
            if ($paymentVerification->invoice->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only reject verifications from your branch.',
                ], 403);
            }

            $result = $this->verificationRepository->updateVerificationStatus(
                $paymentVerification->id,
                'rejected',
                auth()->id(),
                $request->reason
            );

            if ($result) {
                // If verification has associated payment, reject that payment too
                if ($paymentVerification->payment_id) {
                    $this->paymentService->rejectPayment(
                        $paymentVerification->payment_id,
                        auth()->id(),
                        $request->reason
                    );
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Payment verification rejected successfully',
                    'verification' => $paymentVerification->fresh(['invoice', 'customer', 'payment']),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to reject verification',
            ], 400);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error rejecting verification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get verification data for AJAX
     */
    public function getVerificationData(PaymentVerification $paymentVerification): JsonResponse
    {
        try {
            // Check permissions
            if ($paymentVerification->invoice->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'error' => 'You can only view verifications from your branch.'
                ], 403);
            }

            $paymentVerification->load(['invoice', 'customer', 'payment', 'verifiedBy']);
            
            return response()->json([
                'success' => true,
                'verification' => $paymentVerification,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading verification data: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get verifications by invoice (AJAX)
     */
    public function getInvoiceVerifications(Invoice $invoice): JsonResponse
    {
        try {
            // Check permissions
            if ($invoice->branch_id !== auth()->user()->branch_id) {
                return response()->json([
                    'error' => 'You can only view invoices from your branch.'
                ], 403);
            }

            $verifications = $this->verificationRepository->getByInvoiceId($invoice->id);
            
            return response()->json([
                'success' => true,
                'verifications' => $verifications,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading invoice verifications: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Customer payment claim form (for customer portal)
     */
    public function customerClaim(Request $request): View
    {
        try {
            $invoice = null;
            
            if ($request->filled('invoice_number')) {
                $invoice = Invoice::with(['customer', 'branch'])
                    ->where('invoice_number', $request->invoice_number)
                    ->first();

                if (!$invoice) {
                    return back()->withErrors(['error' => 'Invoice not found.']);
                }

                // Check if customer has access to this invoice
                if (auth()->guard('customer')->check()) {
                    $customer = auth()->guard('customer')->user();
                    if ($invoice->customer_id !== $customer->id) {
                        abort(403, 'You can only claim payments for your own invoices.');
                    }
                }
            }

            return view('customer.payment-claim', compact('invoice'));

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to load payment claim form: ' . $e->getMessage()]);
        }
    }

    /**
     * Process customer payment claim
     */
    public function processCustomerClaim(StorePaymentVerificationRequest $request): RedirectResponse
    {
        try {
            $data = $request->validated();
            
            // If customer is authenticated, use their ID
            if (auth()->guard('customer')->check()) {
                $data['customer_id'] = auth()->guard('customer')->id();
            }

            $verification = $this->paymentService->processManualPaymentVerification($data);

            return redirect()->back()
                ->with('success', 'Payment claim submitted successfully. We will verify and process your payment within 24 hours.');

        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to submit payment claim: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Dashboard widget for pending verifications count
     */
    public function pendingCount(): JsonResponse
    {
        try {
            $statistics = $this->verificationRepository->getVerificationStatistics(
                auth()->user()->branch_id
            );
            
            return response()->json([
                'success' => true,
                'pending_count' => $statistics['pending_count'],
                'statistics' => $statistics,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading verification statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Bulk verify verifications
     */
    public function bulkVerify(Request $request): JsonResponse
    {
        $request->validate([
            'verification_ids' => 'required|array',
            'verification_ids.*' => 'integer|exists:payment_verifications,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        try {
            $successCount = 0;
            $errors = [];

            foreach ($request->verification_ids as $verificationId) {
                try {
                    $verification = PaymentVerification::findOrFail($verificationId);
                    
                    // Check permissions
                    if ($verification->invoice->branch_id !== auth()->user()->branch_id) {
                        $errors[] = "Verification #{$verificationId}: Access denied";
                        continue;
                    }

                    $result = $this->verificationRepository->updateVerificationStatus(
                        $verificationId,
                        'verified',
                        auth()->id(),
                        $request->notes
                    );

                    if ($result) {
                        $successCount++;
                        
                        // Create payment if needed
                        if (!$verification->payment_id) {
                            $paymentData = [
                                'invoice_id' => $verification->invoice_id,
                                'customer_id' => $verification->customer_id,
                                'amount' => $verification->claimed_amount,
                                'payment_date' => $verification->payment_claimed_date,
                                'payment_method' => 'bank_transfer',
                                'bank_name' => $verification->bank_name,
                                'status' => 'completed',
                                'verification_status' => 'verified',
                            ];

                            $payment = $this->paymentService->createPayment($paymentData);
                            $verification->update(['payment_id' => $payment->id]);
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = "Verification #{$verificationId}: " . $e->getMessage();
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully verified {$successCount} payments",
                'errors' => $errors,
                'success_count' => $successCount,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error processing bulk verification: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export verifications to Excel/CSV
     */
    public function export(Request $request)
    {
        try {
            // This would typically use a Job or Export class
            // For now, returning JSON with data that can be processed
            $verifications = $this->verificationRepository->getPendingVerifications(
                auth()->user()->branch_id,
                1000 // Large number to get all
            );

            return response()->json([
                'success' => true,
                'data' => $verifications->map(function ($verification) {
                    return [
                        'id' => $verification->id,
                        'invoice_number' => $verification->invoice->invoice_number,
                        'customer_name' => $verification->customer->name,
                        'claimed_amount' => $verification->claimed_amount,
                        'bank_name' => $verification->bank_name,
                        'bank_reference' => $verification->bank_reference,
                        'payment_date' => $verification->payment_claimed_date->format('Y-m-d'),
                        'status' => $verification->verification_status,
                        'created_at' => $verification->created_at->format('Y-m-d H:i:s'),
                    ];
                }),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error exporting verifications: ' . $e->getMessage(),
            ], 500);
        }
    }
}