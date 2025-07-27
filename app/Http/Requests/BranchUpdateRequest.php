<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BranchUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $branch = $this->route('branch');
        return $this->user()->can('update', $branch);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $branchId = $this->route('branch');
        
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:10',
                'alpha_num',
                Rule::unique('branches')->where(function ($query) use ($branchId) {
                    return $query->where('company_id', $this->company_id)
                                 ->where('id', '!=', $branchId);
                })
            ],
            'address' => ['required', 'string'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => [
                'required',
                'email',
                Rule::unique('branches')->ignore($branchId)
            ],
            'is_main_branch' => ['boolean'],
            'status' => ['in:active,inactive'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'settings' => ['nullable', 'array'],
        ];
    }
}