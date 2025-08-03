<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit invoices');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'due_date' => 'sometimes|date|after_or_equal:invoice_date',
            'discount_amount' => 'sometimes|numeric|min:0',
            'notes' => 'sometimes|string|max:1000',
            'terms_conditions' => 'sometimes|string|max:2000',
            'status' => 'sometimes|in:draft,pending,processing,completed,cancelled',
            
            // Invoice items validation (optional for updates)
            'items' => 'sometimes|array|min:1',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.item_description' => 'sometimes|string|max:500',
            'items.*.quantity' => 'required_with:items|numeric|min:0.01',
            'items.*.unit_price' => 'sometimes|numeric|min:0',
            'items.*.unit_weight' => 'sometimes|numeric|min:0',
            'items.*.specifications' => 'sometimes|array',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function messages(): array
    {
        return [
            'due_date.date' => 'Due date must be a valid date. / ගෙවීමේ දිනය වලංගු දිනයක් විය යුතුය.',
            'due_date.after_or_equal' => 'Due date must be after or equal to invoice date. / ගෙවීමේ දිනය ප්‍රතිදාන දිනයට වඩා පසුව හෝ සමාන විය යුතුය.',
            'discount_amount.numeric' => 'Discount amount must be a number. / වට්ටම් මුදල සංඛ්‍යාවක් විය යුතුය.',
            'discount_amount.min' => 'Discount amount cannot be negative. / වට්ටම් මුදල සෘණ විය නොහැක.',
            'items.array' => 'Items must be an array. / අයිතම සමූහයක් විය යුතුය.',
            'items.min' => 'At least one item is required. / අවම වශයෙන් එක් අයිතමයක් අවශ්‍යයි.',
            'items.*.product_id.required_with' => 'Product is required for each item. / සෑම අයිතමයක් සඳහාම නිෂ්පාදනය අවශ්‍යයි.',
            'items.*.product_id.exists' => 'Selected product is invalid. / තෝරාගත් නිෂ්පාදනය වලංගු නොවේ.',
            'items.*.quantity.required_with' => 'Quantity is required for each item. / සෑම අයිතමයක් සඳහාම ප්‍රමාණය අවශ්‍යයි.',
            'items.*.quantity.numeric' => 'Quantity must be a number. / ප්‍රමාණය සංඛ්‍යාවක් විය යුතුය.',
            'items.*.quantity.min' => 'Quantity must be greater than zero. / ප්‍රමාණය ශුන්‍යයට වඩා වැඩි විය යුතුය.',
            'items.*.unit_price.numeric' => 'Unit price must be a number. / ඒකක මිල සංඛ්‍යාවක් විය යුතුය.',
            'items.*.unit_price.min' => 'Unit price cannot be negative. / ඒකක මිල සෘණ විය නොහැක.',
            'items.*.unit_weight.numeric' => 'Unit weight must be a number. / ඒකක බර සංඛ්‍යාවක් විය යුතුය.',
            'items.*.unit_weight.min' => 'Unit weight cannot be negative. / ඒකක බර සෘණ විය නොහැක.',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Get the invoice being updated
            $invoice = \App\Models\Invoice::find($this->route('invoice'));
            
            if (!$invoice) {
                $validator->errors()->add('invoice', 'Invoice not found.');
                return;
            }

            // Check if invoice belongs to user's company
            if ($invoice->company_id !== auth()->user()->company_id) {
                $validator->errors()->add('invoice', 'Invoice does not belong to your company.');
                return;
            }

            // Check if invoice can be modified
            $invoiceRepository = app(\App\Repositories\InvoiceRepository::class);
            if (!$invoiceRepository->canBeModified($invoice->id)) {
                $validator->errors()->add('invoice', 'Invoice cannot be modified at this time.');
                return;
            }

            // Check if products belong to user's company (if items are being updated)
            if ($this->has('items')) {
                foreach ($this->items as $index => $item) {
                    if (isset($item['product_id'])) {
                        $product = \App\Models\Product::find($item['product_id']);
                        if ($product && $product->company_id !== auth()->user()->company_id) {
                            $validator->errors()->add("items.{$index}.product_id", 'Product does not belong to your company.');
                        }
                    }
                }
            }

            // Validate due date against invoice date
            if ($this->due_date && $invoice->invoice_date) {
                $invoiceDate = \Carbon\Carbon::parse($invoice->invoice_date);
                $dueDate = \Carbon\Carbon::parse($this->due_date);
                
                if ($dueDate->lt($invoiceDate)) {
                    $validator->errors()->add('due_date', 'Due date must be after or equal to invoice date.');
                }
            }

            // Validate total discount amount (if items are being updated)
            if ($this->discount_amount && $this->has('items')) {
                $subtotal = 0;
                foreach ($this->items as $item) {
                    if (isset($item['quantity'], $item['unit_price'])) {
                        $subtotal += $item['quantity'] * $item['unit_price'];
                    }
                }
                
                if ($this->discount_amount > $subtotal) {
                    $validator->errors()->add('discount_amount', 'Discount amount cannot exceed subtotal.');
                }
            }
        });
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'due_date' => 'due date',
            'discount_amount' => 'discount amount',
            'items.*.product_id' => 'product',
            'items.*.quantity' => 'quantity',
            'items.*.unit_price' => 'unit price',
            'items.*.unit_weight' => 'unit weight',
        ];
    }
}