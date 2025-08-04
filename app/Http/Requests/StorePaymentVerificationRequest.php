<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentVerificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create payment verifications');
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
            ],
            'customer_id' => [
                'required',
                'integer',
                'exists:customers,id',
            ],
            'payment_id' => [
                'nullable',
                'integer',
                'exists:payments,id',
            ],
            'verification_method' => [
                'required',
                'string',
                'in:manual,automatic,bank_slip,receipt',
            ],
            'bank_reference' => [
                'nullable',
                'string',
                'max:100',
                'required_if:verification_method,bank_slip',
            ],
            'bank_name' => [
                'nullable',
                'string',
                'max:100',
                'required_if:verification_method,bank_slip',
            ],
            'claimed_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:999999.99',
            ],
            'payment_claimed_date' => [
                'required',
                'date',
                'before_or_equal:today',
                'after:' . now()->subMonth(6)->format('Y-m-d'),
            ],
            'verification_notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'bank_slip_image' => [
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
            'invoice_id.required' => 'Invoice is required.',
            'invoice_id.exists' => 'Selected invoice does not exist.',
            'customer_id.required' => 'Customer is required.',
            'customer_id.exists' => 'Selected customer does not exist.',
            'verification_method.required' => 'Verification method is required.',
            'bank_reference.required_if' => 'Bank reference is required for bank slip verification.',
            'bank_name.required_if' => 'Bank name is required for bank slip verification.',
            'claimed_amount.required' => 'Claimed amount is required.',
            'claimed_amount.min' => 'Claimed amount must be at least Rs. 0.01',
            'payment_claimed_date.required' => 'Payment date is required.',
            'payment_claimed_date.before_or_equal' => 'Payment date cannot be in the future.',
            'payment_claimed_date.after' => 'Payment date cannot be more than 6 months ago.',
            'bank_slip_image.mimes' => 'Bank slip must be a JPEG, PNG, JPG, or PDF file.',
            'bank_slip_image.max' => 'Bank slip image size cannot exceed 5MB.',
        ];
    }
}