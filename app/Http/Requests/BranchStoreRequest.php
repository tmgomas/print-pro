<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create branches');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'company_id' => ['required', 'exists:companies,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:255',
                'alpha_num',
                'uppercase',
                Rule::unique('branches', 'code')->where(function ($query) {
                    return $query->where('company_id', $this->company_id);
                })
            ],
            'address' => ['nullable', 'string'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'is_main_branch' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'company_id.required' => 'Please select a company.',
            'company_id.exists' => 'Selected company does not exist.',
            'name.required' => 'Branch name is required.',
            'code.required' => 'Branch code is required.',
            'code.unique' => 'Branch code must be unique within the company.',
            'code.alpha_num' => 'Branch code can only contain letters and numbers.',
            'code.uppercase' => 'Branch code must be in uppercase.',
            'email.email' => 'Please enter a valid email address.',
        ];
    }

    /**
     * Get custom attribute names for error messages.
     */
    public function attributes(): array
    {
        return [
            'company_id' => 'company',
            'name' => 'branch name',
            'code' => 'branch code',
            'is_main_branch' => 'main branch',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert branch code to uppercase if provided
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->code),
            ]);
        }

        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge([
                'status' => 'active',
            ]);
        }

        // Handle is_main_branch conversion
        if ($this->has('is_main_branch')) {
            $this->merge([
                'is_main_branch' => filter_var($this->is_main_branch, FILTER_VALIDATE_BOOLEAN),
            ]);
        } else {
            $this->merge([
                'is_main_branch' => false,
            ]);
        }
    }
}