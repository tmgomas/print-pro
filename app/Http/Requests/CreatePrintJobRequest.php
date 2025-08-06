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
            'invoice_id' => 'required|exists:invoices,id',
            'job_type' => 'required|string|max:100',
            'priority' => 'required|in:low,normal,medium,high,urgent',
            'assigned_to' => 'nullable|exists:users,id',
            'specifications' => 'nullable|array',
            'design_files' => 'nullable|array',
            'design_files.*' => 'file|mimes:pdf,jpg,jpeg,png,ai,psd|max:10240',
            'customer_instructions' => 'nullable|string|max:1000',
            'estimated_completion' => 'nullable|date|after:now',
        ];
    }

    public function messages(): array
    {
        return [
            'invoice_id.required' => 'Invoice is required',
            'invoice_id.exists' => 'Selected invoice does not exist',
            'job_type.required' => 'Job type is required',
            'priority.required' => 'Priority is required',
            'priority.in' => 'Priority must be one of: low, normal, medium, high, urgent',
            'assigned_to.exists' => 'Selected staff member does not exist',
            'design_files.*.mimes' => 'Design files must be: pdf, jpg, jpeg, png, ai, psd',
            'design_files.*.max' => 'Each design file must be less than 10MB',
            'estimated_completion.after' => 'Estimated completion must be in the future',
        ];
    }

    /**
     * Prepare data for validation
     */
    protected function prepareForValidation(): void
    {
        // Set default priority if not provided
        if (!$this->has('priority')) {
            $this->merge(['priority' => 'normal']);
        }

        // Set default job type based on invoice if not provided
        if (!$this->has('job_type') && $this->has('invoice_id')) {
            $invoice = \App\Models\Invoice::find($this->invoice_id);
            if ($invoice) {
                $this->merge(['job_type' => $this->determineJobType($invoice)]);
            }
        }
    }

    /**
     * Get validated data with additional processing
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();
        
        // Handle file uploads
        if ($this->hasFile('design_files')) {
            $designFiles = [];
            foreach ($this->file('design_files') as $file) {
                $path = $file->store('print-jobs/design-files', 'public');
                $designFiles[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'uploaded_at' => now()->toISOString()
                ];
            }
            $validated['design_files'] = $designFiles;
        }

        return $validated;
    }

    /**
     * Determine job type from invoice
     */
    private function determineJobType(\App\Models\Invoice $invoice): string
    {
        $products = $invoice->invoiceItems->pluck('product.name')->toArray();
        
        if (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'business card'))) {
            return 'business_cards';
        } elseif (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'brochure'))) {
            return 'brochures';
        } elseif (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'banner'))) {
            return 'banners';
        } elseif (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'flyer'))) {
            return 'flyers';
        } elseif (collect($products)->contains(fn($name) => str_contains(strtolower($name), 'poster'))) {
            return 'posters';
        }
        
        return 'general_printing';
    }
}