<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('edit print jobs');
    }

    public function rules(): array
    {
        return [
            'job_type' => 'sometimes|required|string|max:100',
            'job_title' => 'nullable|string|max:200',
            'job_description' => 'nullable|string|max:1000',
            'priority' => 'sometimes|required|in:low,normal,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'specifications' => 'nullable|array',
            'customer_instructions' => 'nullable|string|max:1000',
            'estimated_completion' => 'nullable|date|after:now',
            'estimated_cost' => 'nullable|numeric|min:0',
            'production_notes' => 'nullable|string|max:2000',
            'special_instructions' => 'nullable|string|max:1000',
            'material_requirements' => 'nullable|array',
            
            // For standalone jobs
            'customer_id' => 'nullable|exists:customers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'job_type.required' => 'Job type is required',
            'priority.required' => 'Priority is required',
            'priority.in' => 'Priority must be one of: low, normal, medium, high, urgent',
            'assigned_to.exists' => 'Selected staff member does not exist',
            'estimated_completion.after' => 'Estimated completion must be in the future',
            'estimated_cost.min' => 'Estimated cost must be positive',
            'customer_id.exists' => 'Selected customer does not exist',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Get the print job being updated
            $printJob = \App\Models\PrintJob::find($this->route('printJob'));
            
            if (!$printJob) {
                $validator->errors()->add('print_job', 'Print job not found.');
                return;
            }

            // Check if print job belongs to user's company
            if ($printJob->company_id !== auth()->user()->company_id) {
                $validator->errors()->add('print_job', 'Print job does not belong to your company.');
                return;
            }

            // Check if print job can be modified
            $printJobRepository = app(\App\Repositories\PrintJobRepository::class);
            if (!$printJobRepository->canBeModified($printJob->id)) {
                $validator->errors()->add('print_job', 'Print job cannot be modified at this time.');
                return;
            }

            // Validate that assigned staff belongs to same branch (if provided)
            if ($this->assigned_to) {
                $staff = \App\Models\User::find($this->assigned_to);
                if ($staff && $staff->branch_id !== $printJob->branch_id) {
                    $validator->errors()->add('assigned_to', 'Staff member must be from the same branch as the print job.');
                }
            }

            // For standalone jobs, validate customer belongs to company
            if ($this->customer_id && !$printJob->invoice_id) {
                $customer = \App\Models\Customer::find($this->customer_id);
                if ($customer && $customer->company_id !== auth()->user()->company_id) {
                    $validator->errors()->add('customer_id', 'Customer does not belong to your company.');
                }
            }
        });
    }
}