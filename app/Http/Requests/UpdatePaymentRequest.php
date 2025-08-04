<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $payment = $this->route('payment');
        return $this->user() && 
               $this->user()->can('update payments') &&
               $payment->status === 'pending'; // Only allow updating pending payments
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $paymentId = $this->route('payment')->id;
        
        return [
            'amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
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
                'unique:payments,transaction_id,' . $paymentId,
            ],
            'gateway_reference' => [
                'nullable',
                'string',
                'max:200',
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
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Payment amount is required.',
            'amount.min' => 'Payment amount must be at least Rs. 0.01',
            'amount.max' => 'Payment amount cannot exceed Rs. 999,999.99',
            'payment_date.required' => 'Payment date is required.',
            'payment_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_method.required' => 'Please select a payment method.',
            'bank_name.required_if' => 'Bank name is required for bank transfers and cheques.',
            'cheque_number.required_if' => 'Cheque number is required for cheque payments.',
            'transaction_id.unique' => 'This transaction ID has already been used.',
            'receipt_image.mimes' => 'Receipt must be a JPEG, PNG, JPG, or PDF file.',
            'receipt_image.max' => 'Receipt image size cannot exceed 5MB.',
        ];
    }
}

