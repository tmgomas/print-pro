<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create payments');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'invoice_id' => [
                'required',
                'integer',
                'exists:invoices,id',
                function ($attribute, $value, $fail) {
                    $invoice = \App\Models\Invoice::find($value);
                    if ($invoice && $invoice->branch_id !== auth()->user()->branch_id) {
                        $fail('Selected invoice does not belong to your branch.');
                    }
                    if ($invoice && $invoice->payment_status === 'paid') {
                        $fail('Invoice is already fully paid.');
                    }
                },
            ],
            'customer_id' => [
                'nullable',
                'integer',
                'exists:customers,id',
            ],
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
                function ($attribute, $value, $fail) {
                    if ($this->invoice_id) {
                        $invoice = \App\Models\Invoice::find($this->invoice_id);
                        if ($invoice) {
                            $totalPaid = \App\Models\Payment::where('invoice_id', $invoice->id)
                                ->where('status', 'completed')
                                ->sum('amount');
                            $remainingAmount = $invoice->total_amount - $totalPaid;
                            
                            if ($value > $remainingAmount) {
                                $fail("Payment amount cannot exceed remaining balance of Rs. " . number_format($remainingAmount, 2));
                            }
                        }
                    }
                },
            ],
            'payment_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after:' . now()->subYear()->format('Y-m-d'),
            ],
            'payment_method' => [
                'required',
                'string',
                'in:cash,bank_transfer,online,card,cheque,mobile_payment',
            ],
            'bank_name' => [
                'nullable',
                'string',
                'max:100',
                'required_if:payment_method,bank_transfer,cheque',
            ],
            'transaction_id' => [
                'nullable',
                'string',
                'max:100',
                'unique:payments,transaction_id',
            ],
            'gateway_reference' => [
                'nullable',
                'string',
                'max:200',
                'required_if:payment_method,online',
            ],
            'cheque_number' => [
                'nullable',
                'string',
                'max:50',
                'required_if:payment_method,cheque',
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'receipt_image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,pdf',
                'max:5120', // 5MB
            ],
            'status' => [
                'nullable',
                'string',
                'in:pending,processing,completed,failed,cancelled',
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'pending',
            ]);
        }

        // Set customer_id from invoice if not provided
        if (!$this->has('customer_id') && $this->invoice_id) {
            $invoice = \App\Models\Invoice::find($this->invoice_id);
            if ($invoice) {
                $this->merge([
                    'customer_id' => $invoice->customer_id,
                ]);
            }
        }

        // Format amount to 2 decimal places
        if ($this->has('amount')) {
            $this->merge([
                'amount' => number_format((float)$this->amount, 2, '.', ''),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Please select an invoice.',
            'invoice_id.exists' => 'Selected invoice does not exist.',
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least Rs. 0.01',
            'amount.max' => 'Payment amount cannot exceed Rs. 999,999.99',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_date.after' => 'Payment date cannot be more than 1 year ago.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Invalid payment method selected.',
            'bank_name.required_if' => 'Bank name is required for bank transfers and cheques.',
            'gateway_reference.required_if' => 'Gateway reference is required for online payments.',
            'cheque_number.required_if' => 'Cheque number is required for cheque payments.',
            'transaction_id.unique' => 'This transaction ID has already been used.',
            'receipt_image.image' => 'Receipt must be an image file.',
            'receipt_image.mimes' => 'Receipt must be a JPEG, PNG, JPG, or PDF file.',
            'receipt_image.max' => 'Receipt image size cannot exceed 5MB.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'invoice_id' => 'invoice',
            'customer_id' => 'customer',
            'payment_date' => 'payment date',
            'payment_method' => 'payment method',
            'bank_name' => 'bank name',
            'transaction_id' => 'transaction ID',
            'gateway_reference' => 'gateway reference',
            'cheque_number' => 'cheque number',
            'receipt_image' => 'receipt image',
        ];
    }
}

