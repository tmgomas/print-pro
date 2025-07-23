<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create companies');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email' => ['required', 'email', 'unique:companies,email'],
            'phone' => ['required', 'string', 'max:20'],
            'address' => ['required', 'string'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'tax_number' => ['nullable', 'string', 'max:50'],
            'bank_details' => ['nullable', 'string'],
            'branch_name' => ['required', 'string', 'max:255'],
            'branch_code' => ['required', 'string', 'max:10', 'unique:branches,code'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A company with this email already exists.',
            'branch_code.unique' => 'This branch code is already taken.',
        ];
    }
}