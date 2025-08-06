<?php
// app/Http/Requests/CreatePrintJobRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create print jobs');
    }

    public function rules(): array
    {
        return [
            'invoice_id' => 'nullable|exists:invoices,id', // Make it nullable for standalone jobs
            'job_type' => 'required|string|max:100',
            'priority' => 'required|in:low,normal,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'specifications' => 'nullable|array',
            'design_files' => 'nullable|array',
            'design_files.*' => 'file|mimes:pdf,jpg,jpeg,png,ai,psd|max:10240',
            'customer_instructions' => 'nullable|string|max:1000',
            'estimated_completion' => 'nullable|date|after:now',
            
            // Additional fields for standalone jobs
            'customer_id' => 'nullable|exists:customers,id',
            'job_title' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:1000',
            'estimated_cost' => 'nullable|numeric|min:0',
            'branch_id' => 'nullable|exists:branches,id',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.exists' => 'Selected invoice does not exist',
            'job_type.required' => 'Job type is required',
            'priority.required' => 'Priority is required',
            'priority.in' => 'Priority must be one of: low, normal, medium, high, urgent',
            'assigned_to.exists' => 'Selected staff member does not exist',
            'design_files.*.mimes' => 'Design files must be: pdf, jpg, jpeg, png, ai, psd',
            'design_files.*.max' => 'Design files must not exceed 10MB',
            'customer_instructions.max' => 'Customer instructions must not exceed 1000 characters',
            'estimated_completion.after' => 'Estimated completion must be in the future',
            'customer_id.exists' => 'Selected customer does not exist',
            'estimated_cost.min' => 'Estimated cost must be positive',
            'branch_id.exists' => 'Selected branch does not exist',
        ];
    }

    // Remove getValidatedData() method - use validated() instead
}