<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->can('create users');
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'company_id' => ['required', 'exists:companies,id'],
            'branch_id' => ['nullable', 'exists:branches,id'],
            'role' => ['required', 'string', Rule::in($this->getAvailableRoles())],
            'status' => ['nullable', 'string', Rule::in(['active', 'inactive'])],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'A user with this email already exists.',
            'company_id.exists' => 'Selected company does not exist.',
            'branch_id.exists' => 'Selected branch does not exist.',
        ];
    }

    private function getAvailableRoles(): array
    {
        $user = auth()->user();
        
        if ($user->hasRole('Super Admin')) {
            return Role::pluck('name')->toArray();
        }
        
        return Role::where('name', '!=', 'Super Admin')->pluck('name')->toArray();
    }
}
