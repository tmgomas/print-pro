<?php
// 1. CORRECTED Branch Model (app/Models/Branch.php)

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'address',
        'phone',
        'email',
        // 'manager_name', // REMOVED: DB එකේ නෑ
        'is_main_branch',
        'status',
        'settings',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'is_main_branch' => 'boolean',
        'settings' => 'array',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
    ];

    protected $attributes = [
        'is_main_branch' => false,
        'status' => 'active',
    ];

    // ... rest of the methods remain same
}

// ===================================================

// 2. CORRECTED Validation Rules (app/Http/Requests/BranchStoreRequest.php)

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create branches');
    }

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
            // 'manager_name' => ['nullable', 'string', 'max:255'], // REMOVED
            'is_main_branch' => ['boolean'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'status' => ['nullable', 'in:active,inactive'],
        ];
    }

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

    public function attributes(): array
    {
        return [
            'company_id' => 'company',
            'name' => 'branch name',
            'code' => 'branch code',
            'is_main_branch' => 'main branch',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->code),
            ]);
        }

        if (!$this->has('status')) {
            $this->merge([
                'status' => 'active',
            ]);
        }

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