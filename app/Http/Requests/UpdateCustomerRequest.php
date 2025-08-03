<?php
// app/Http/Requests/UpdateCustomerRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('update customers');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $customerId = $this->route('customer')->id ?? $this->route('id');
        
        $baseRules = [
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $branch = \App\Models\Branch::find($value);
                        if ($branch && $branch->company_id !== auth()->user()->company_id) {
                            $fail('Selected branch does not belong to your company.');
                        }
                        if ($branch && $branch->status !== 'active') {
                            $fail('Selected branch is not active.');
                        }
                    }
                },
            ],
            'customer_code' => [
                'required',
                'string',
                'max:50',
                'min:3',
                Rule::unique('customers', 'customer_code')->ignore($customerId),
                'regex:/^[A-Z0-9_\-]+$/', // Fixed: escaped the dash
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                Rule::unique('customers', 'email')->ignore($customerId),
                'lowercase',
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'min:9',
                function ($attribute, $value, $fail) {
                    $cleanPhone = preg_replace('/[^\d\+]/', '', $value);
                    
                    if (preg_match('/^(?:\+94|94|0)?[1-9][0-9]{8}$/', $cleanPhone)) {
                        return;
                    }
                    
                    if (preg_match('/^\+[1-9]\d{6,14}$/', $cleanPhone)) {
                        return;
                    }
                    
                    $fail('Please enter a valid phone number.');
                },
            ],
            'billing_address' => [
                'required',
                'string',
                'max:500',
                'min:10',
            ],
            'shipping_address' => [
                'nullable',
                'string',
                'max:500',
                'min:10',
            ],
            'city' => [
                'required',
                'string',
                'max:100',
                'min:2',
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[0-9]{5}$/',
            ],
            'district' => [
                'nullable',
                'string',
                'max:100',
            ],
            'province' => [
                'nullable',
                'string',
                'max:100',
            ],
            'tax_number' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('customers', 'tax_number')->ignore($customerId),
                'regex:/^[A-Z0-9\-]+$/', // Fixed: escaped the dash
            ],
            'credit_limit' => [
                'required',
                'numeric',
                'min:0',
                'max:999999999.99',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive', 'suspended']),
            ],
            'customer_type' => [
                'required',
                Rule::in(['individual', 'business']),
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'preferences' => [
                'nullable',
                'array',
            ],
        ];

        // Individual customer specific rules
        if ($this->input('customer_type') === 'individual') {
            $baseRules = array_merge($baseRules, [
                'date_of_birth' => [
                    'nullable',
                    'date',
                    'before:today',
                    'after:1900-01-01',
                ],
                'emergency_contact_name' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'emergency_contact_phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            $cleanPhone = preg_replace('/[^\d\+]/', '', $value);
                            
                            if (preg_match('/^(?:\+94|94|0)?[1-9][0-9]{8}$/', $cleanPhone)) {
                                return;
                            }
                            
                            if (preg_match('/^\+[1-9]\d{6,14}$/', $cleanPhone)) {
                                return;
                            }
                            
                            $fail('Please enter a valid emergency contact phone number.');
                        }
                    },
                ],
                'emergency_contact_relationship' => [
                    'nullable',
                    'string',
                    'max:100',
                ],
            ]);
        }

        // Business customer specific rules
        if ($this->input('customer_type') === 'business') {
            $baseRules = array_merge($baseRules, [
                'company_name' => [
                    'required',
                    'string',
                    'max:255',
                    'min:2',
                ],
                'company_registration' => [
                    'nullable',
                    'string',
                    'max:50',
                    'regex:/^[A-Z0-9\/\-]+$/', // Fixed: escaped both slash and dash
                ],
                'contact_person' => [
                    'required',
                    'string',
                    'max:255',
                    'min:2',
                ],
                'contact_person_phone' => [
                    'nullable',
                    'string',
                    'max:20',
                    function ($attribute, $value, $fail) {
                        if ($value) {
                            $cleanPhone = preg_replace('/[^\d\+]/', '', $value);
                            
                            if (preg_match('/^(?:\+94|94|0)?[1-9][0-9]{8}$/', $cleanPhone)) {
                                return;
                            }
                            
                            if (preg_match('/^\+[1-9]\d{6,14}$/', $cleanPhone)) {
                                return;
                            }
                            
                            $fail('Please enter a valid contact person phone number.');
                        }
                    },
                ],
                'contact_person_email' => [
                    'nullable',
                    'email:rfc,dns',
                    'max:255',
                    'lowercase',
                ],
            ]);
        }

        return $baseRules;
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'customer_code.unique' => 'මෙම ගනුදෙනුකරු කේතය දැනටමත් භාවිතා වේ / This customer code is already taken.',
            'customer_code.regex' => 'ගනුදෙනුකරු කේතයේ විශේෂ අකුරු හෝ රික්ත ස්ථාන අඩංගු විය නොහැක / Customer code can only contain uppercase letters, numbers, underscores, and hyphens.',
            'email.unique' => 'මෙම විද්‍යුත් තැපැල් ලිපිනය දැනටමත් භාවිතා වේ / This email address is already taken.',
            'phone.required' => 'දුරකථන අංකය අවශ්‍ය වේ / Phone number is required.',
            'billing_address.required' => 'බිල්පත් ලිපිනය අවශ්‍ය වේ / Billing address is required.',
            'city.required' => 'නගරය අවශ්‍ය වේ / City is required.',
            'credit_limit.required' => 'ණය සීමාව අවශ්‍ය වේ / Credit limit is required.',
            'customer_type.required' => 'ගනුදෙනුකරු වර්ගය අවශ්‍ය වේ / Customer type is required.',
            'company_name.required' => 'ව්‍යාපාරික ගනුදෙනුකරුවන් සඳහා සමාගම් නම අවශ්‍ය වේ / Company name is required for business customers.',
            'contact_person.required' => 'ව්‍යාපාරික ගනුදෙනුකරුවන් සඳහා සම්බන්ධතා පුද්ගලයා අවශ්‍ය වේ / Contact person is required for business customers.',
            'date_of_birth.before' => 'උපන් දිනය අදට පෙර විය යුතුය / Date of birth must be before today.',
            'postal_code.regex' => 'තැපැල් කේතය 5 ඉලක්කම් විය යුතුය / Postal code must be 5 digits.',
            'company_registration.regex' => 'සමාගම් ලියාපදිංචි අංකයේ අවලංගු අකුරු ඇත / Invalid characters in company registration number.',
            'tax_number.regex' => 'බදු අංකයේ අවලංගු අකුරු ඇත / Invalid characters in tax number.',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Clean and format phone numbers
        if ($this->phone) {
            $this->merge([
                'phone' => $this->cleanPhoneNumber($this->phone),
            ]);
        }

        if ($this->contact_person_phone) {
            $this->merge([
                'contact_person_phone' => $this->cleanPhoneNumber($this->contact_person_phone),
            ]);
        }

        if ($this->emergency_contact_phone) {
            $this->merge([
                'emergency_contact_phone' => $this->cleanPhoneNumber($this->emergency_contact_phone),
            ]);
        }

        // Clean and format text fields
        if ($this->company_registration) {
            $this->merge([
                'company_registration' => strtoupper(trim($this->company_registration)),
            ]);
        }

        if ($this->tax_number) {
            $this->merge([
                'tax_number' => strtoupper(trim($this->tax_number)),
            ]);
        }

        // Handle preferences
        if ($this->preferences && is_string($this->preferences)) {
            $this->merge([
                'preferences' => json_decode($this->preferences, true) ?: [],
            ]);
        }
    }

    /**
     * Clean phone number
     */
    private function cleanPhoneNumber(string $phone): string
    {
        // Remove all non-digit and non-plus characters
        $cleaned = preg_replace('/[^\d\+]/', '', $phone);
        
        // Handle Sri Lankan numbers
        if (preg_match('/^0[1-9][0-9]{8}$/', $cleaned)) {
            // Convert 0771234567 to +94771234567
            $cleaned = '+94' . substr($cleaned, 1);
        } elseif (preg_match('/^94[1-9][0-9]{8}$/', $cleaned)) {
            // Convert 94771234567 to +94771234567
            $cleaned = '+' . $cleaned;
        } elseif (preg_match('/^\+94[1-9][0-9]{8}$/', $cleaned)) {
            // Already in correct format
        }
        
        return $cleaned;
    }
}