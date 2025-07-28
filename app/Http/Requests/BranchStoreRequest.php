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
                'max:10',
                'alpha_num',
                'uppercase',
                Rule::unique('branches', 'code')->where(function ($query) {
                    return $query->where('company_id', $this->company_id);
                })
            ],
            'address' => ['required', 'string', 'max:1000'],
            'phone' => ['required', 'string', 'max:20'],
            'manager_name' => ['nullable', 'string', 'max:255'],
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
            'address.required' => 'Branch address is required.',
            'phone.required' => 'Branch phone number is required.',
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
            'manager_name' => 'manager name',
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
    }
}