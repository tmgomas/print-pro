<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CompanyRegistrationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Company Information
            'company_name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100', 'unique:companies,registration_number'],
            'address' => ['required', 'string', 'max:500'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['required', 'email', 'max:255', 'unique:companies,email'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'bank_details' => ['nullable', 'string', 'max:1000'],
            
            // Branch Information
            'branch_name' => ['nullable', 'string', 'max:255'],
            
            // Admin User Information
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'admin_phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
            
            // Terms and Conditions
            'terms_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'company_name' => 'company name',
            'registration_number' => 'registration number',
            'tax_rate' => 'tax rate',
            'tax_number' => 'tax number',
            'bank_details' => 'bank details',
            'branch_name' => 'branch name',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'admin_email' => 'admin email',
            'admin_phone' => 'admin phone',
            'password_confirmation' => 'password confirmation',
            'terms_accepted' => 'terms and conditions',
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'email.unique' => 'A company with this email already exists.',
            'registration_number.unique' => 'This registration number is already taken.',
            'admin_email.unique' => 'A user with this email already exists.',
            'terms_accepted.accepted' => 'You must accept the terms and conditions.',
            'logo.image' => 'Logo must be an image file.',
            'logo.mimes' => 'Logo must be a JPEG, PNG, JPG, or GIF file.',
            'logo.max' => 'Logo size cannot exceed 2MB.',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->input('email')),
            'admin_email' => strtolower($this->input('admin_email')),
        ]);
    }

    /**
     * Get validated data formatted for service
     */
    public function getCompanyData(): array
    {
        return [
            'company_name' => $this->validated('company_name'),
            'registration_number' => $this->validated('registration_number'),
            'address' => $this->validated('address'),
            'phone' => $this->validated('phone'),
            'email' => $this->validated('email'),
            'tax_rate' => $this->validated('tax_rate', 0.00),
            'tax_number' => $this->validated('tax_number'),
            'bank_details' => $this->validated('bank_details'),
            'branch_name' => $this->validated('branch_name', 'Main Branch'),
        ];
    }

    /**
     * Get admin user data
     */
    public function getAdminData(): array
    {
        return [
            'first_name' => $this->validated('first_name'),
            'last_name' => $this->validated('last_name'),
            'email' => $this->validated('admin_email'),
            'phone' => $this->validated('admin_phone'),
            'password' => $this->validated('password'),
        ];
    }
}