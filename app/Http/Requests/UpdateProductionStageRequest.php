<?php
// app/Http/Requests/UpdateProductionStageRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update production stages');
    }

    public function rules(): array
    {
        return [
            'stage_status' => 'required|in:pending,in_progress,completed,on_hold,requires_approval,rejected,skipped',
            'notes' => 'nullable|string|max:1000',
            'stage_data' => 'nullable|array',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|mimes:jpg,jpeg,png,pdf|max:5120',
            'actual_duration' => 'nullable|integer|min:1',
            'rejection_reason' => 'required_if:stage_status,rejected|string|max:500',
            'hold_reason' => 'required_if:stage_status,on_hold|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'stage_status.required' => 'Stage status is required',
            'stage_status.in' => 'Invalid stage status',
            'notes.max' => 'Notes cannot exceed 1000 characters',
            'attachments.*.mimes' => 'Attachments must be: jpg, jpeg, png, pdf',
            'attachments.*.max' => 'Each attachment must be less than 5MB',
            'actual_duration.integer' => 'Duration must be a number in minutes',
            'actual_duration.min' => 'Duration must be at least 1 minute',
            'rejection_reason.required_if' => 'Rejection reason is required when rejecting a stage',
            'hold_reason.required_if' => 'Hold reason is required when putting a stage on hold',
        ];
    }

    /**
     * Get validated data with file processing
     */
    public function getValidatedData(): array
    {
        $validated = $this->validated();
        
        // Handle file uploads
        if ($this->hasFile('attachments')) {
            $attachments = [];
            foreach ($this->file('attachments') as $file) {
                $path = $file->store('production-stages/attachments', 'public');
                $attachments[] = [
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'mime_type' => $file->getClientMimeType(),
                    'uploaded_at' => now()->toISOString(),
                    'uploaded_by' => $this->user()->id
                ];
            }
            $validated['attachments'] = $attachments;
        }

        return $validated;
    }

    /**
     * Custom validation for business rules
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional business rule validations
            $stage = $this->route('stage') ?? $this->route('id');
            
            if ($stage) {
                $productionStage = \App\Models\ProductionStage::find($stage);
                
                if ($productionStage) {
                    $this->validateStatusTransition($validator, $productionStage);
                }
            }
        });
    }

    /**
     * Validate status transitions
     */
    private function validateStatusTransition($validator, $stage): void
    {
        $currentStatus = $stage->stage_status;
        $newStatus = $this->input('stage_status');

        $allowedTransitions = [
            'pending' => ['in_progress', 'on_hold', 'skipped'],
            'in_progress' => ['completed', 'on_hold', 'requires_approval', 'rejected'],
            'on_hold' => ['pending', 'in_progress'],
            'requires_approval' => ['completed', 'rejected'],
            'rejected' => ['pending', 'in_progress'],
            'completed' => [], // Completed stages cannot be changed
            'skipped' => [], // Skipped stages cannot be changed
        ];

        if (!in_array($newStatus, $allowedTransitions[$currentStatus] ?? [])) {
            $validator->errors()->add(
                'stage_status', 
                "Cannot change status from {$currentStatus} to {$newStatus}"
            );
        }

        // Validate completion requirements
        if ($newStatus === 'completed' && $currentStatus === 'pending') {
            $validator->errors()->add(
                'stage_status', 
                'Stage must be started before it can be completed'
            );
        }

        // Validate customer approval stages
        if ($stage->requires_customer_approval && $newStatus === 'completed') {
            if ($currentStatus !== 'requires_approval') {
                $validator->errors()->add(
                    'stage_status', 
                    'Customer approval stages must go through approval process'
                );
            }
        }
    }
}