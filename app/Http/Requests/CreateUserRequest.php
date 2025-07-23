<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CreateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create users');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $user = auth()->user();
        
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string', 'min:8'],
            'company_id' => [
                'required',
                'integer',
                'exists:companies,id',
                function ($attribute, $value, $fail) use ($user) {
                    // Super admin can create users in any company
                    if ($user->isSuperAdmin()) {
                        return;
                    }
                    
                    // Company admin can only create users in their company
                    if (!$user->belongsToCompany(\App\Models\Company::find($value))) {
                        $fail('You can only create users within your company.');
                    }
                },
            ],
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    if ($value && $this->input('company_id')) {
                        $branch = \App\Models\Branch::find($value);
                        if ($branch && $branch->company_id != $this->input('company_id')) {
                            $fail('Selected branch does not belong to the selected company.');
                        }
                    }
                },
            ],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name'),
                function ($attribute, $value, $fail) use ($user) {
                    // Super admin can assign any role
                    if ($user->isSuperAdmin()) {
                        return;
                    }
                    
                    // Company admin cannot create super admins
                    if ($value === 'Super Admin') {
                        $fail('You cannot assign Super Admin role.');
                    }
                },
            ],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive', 'suspended'])],
        ];
    }

    /**
     * Get custom attribute names
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'password_confirmation' => 'password confirmation',
            'company_id' => 'company',
            'branch_id' => 'branch',
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists.',
            'company_id.exists' => 'Selected company does not exist.',
            'branch_id.exists' => 'Selected branch does not exist.',
            'role.exists' => 'Selected role does not exist.',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower($this->input('email')),
        ]);
    }

    /**
     * Get available roles for the current user
     */
    public function getAvailableRoles(): array
    {
        $user = auth()->user();
        
        if ($user->isSuperAdmin()) {
            return Role::pluck('name', 'name')->toArray();
        }
        
        // Company admin cannot assign Super Admin role
        return Role::where('name', '!=', 'Super Admin')
            ->pluck('name', 'name')
            ->toArray();
    }

    /**
     * Get available companies for the current user
     */
    public function getAvailableCompanies(): array
    {
        $user = auth()->user();
        
        if ($user->isSuperAdmin()) {
            return \App\Models\Company::active()
                ->pluck('name', 'id')
                ->toArray();
        }
        
        // Company admin can only see their company
        return [$user->company_id => $user->company->name];
    }

    /**
     * Get available branches for selected company
     */
    public function getAvailableBranches(): array
    {
        $companyId = $this->input('company_id');
        
        if (!$companyId) {
            return [];
        }
        
        $user = auth()->user();
        
        // Check if user can access this company
        if (!$user->isSuperAdmin() && $user->company_id != $companyId) {
            return [];
        }
        
        return \App\Models\Branch::where('company_id', $companyId)
            ->active()
            ->pluck('name', 'id')
            ->toArray();
    }
}