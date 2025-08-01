<?php
// app/Http/Requests/UpdateProductRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('edit products');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $productId = $this->route('id') ?? $this->route('product');

        return [
            'category_id' => [
                'required',
                'integer',
                'exists:product_categories,id',
                function ($attribute, $value, $fail) {
                    // Check if category belongs to the same company
                    $category = \App\Models\ProductCategory::find($value);
                    if ($category && $category->company_id !== auth()->user()->company_id) {
                        $fail('Selected category does not belong to your company.');
                    }

                    // Check if category is active
                    if ($category && $category->status !== 'active') {
                        $fail('Selected category is not active.');
                    }
                },
            ],
            'product_code' => [
                'nullable',
                'string',
                'max:50',
                'min:3',
                Rule::unique('products', 'product_code')->ignore($productId),
                'regex:/^[A-Z0-9_-]+$/', // Only uppercase letters, numbers, underscore, hyphen
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'description' => [
                'nullable',
                'string',
                'max:2000',
            ],
            'base_price' => [
                'required',
                'numeric',
                'min:0.01',
                'max:9999999.99',
                'decimal:0,2', // Up to 2 decimal places
            ],
            'unit_type' => [
                'required',
                'string',
                'in:piece,sheet,roll,meter,square_meter,kilogram,gram,pound,liter,milliliter',
            ],
            'weight_per_unit' => [
                'required',
                'numeric',
                'min:0.001',
                'max:99999.999',
                'decimal:0,3', // Up to 3 decimal places
            ],
            'weight_unit' => [
                'required',
                'string',
                'in:kg,g,lb,oz',
            ],
            'tax_rate' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                'decimal:0,2',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:3072', // 3MB for product images
                'dimensions:min_width=200,min_height=200,max_width=3000,max_height=3000',
            ],
            'gallery' => [
                'nullable',
                'array',
                'max:5', // Maximum 5 gallery images
            ],
            'gallery.*' => [
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB for gallery images
                'dimensions:min_width=200,min_height=200,max_width=2000,max_height=2000',
            ],
            'specifications' => [
                'nullable',
                'array',
                'max:20', // Maximum 20 specifications
            ],
            'specifications.*.key' => [
                'required_with:specifications',
                'string',
                'max:100',
                'distinct', // No duplicate keys
            ],
            'specifications.*.value' => [
                'required_with:specifications',
                'string',
                'max:500',
            ],
            'pricing_tiers' => [
                'nullable',
                'array',
                'max:10', // Maximum 10 pricing tiers
            ],
            'pricing_tiers.*.min_quantity' => [
                'required_with:pricing_tiers',
                'integer',
                'min:1',
                'max:999999',
            ],
            'pricing_tiers.*.max_quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:999999',
                'gte:pricing_tiers.*.min_quantity',
            ],
            'pricing_tiers.*.unit_price' => [
                'required_with:pricing_tiers',
                'numeric',
                'min:0.01',
                'max:9999999.99',
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive,draft,out_of_stock',
                function ($attribute, $value, $fail) use ($productId) {
                    // If deactivating, check if there are pending orders
                    if ($value === 'inactive') {
                        $hasPendingOrders = \App\Models\InvoiceItem::whereHas('invoice', function ($query) {
                            $query->whereIn('status', ['pending', 'processing', 'confirmed']);
                        })->where('product_id', $productId)->exists();
                        
                        if ($hasPendingOrders) {
                            $fail('Cannot deactivate product with pending orders. Please complete or cancel pending orders first.');
                        }
                    }
                },
            ],
            'minimum_quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:999999',
            ],
            'maximum_quantity' => [
                'nullable',
                'integer',
                'min:1',
                'max:999999',
                'gte:minimum_quantity',
            ],
            'stock_quantity' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
            ],
            'reorder_level' => [
                'nullable',
                'integer',
                'min:0',
                'max:999999',
            ],
            'requires_customization' => [
                'nullable',
                'boolean',
            ],
            'customization_options' => [
                'nullable',
                'string',
                'max:1000',
                'required_if:requires_customization,true',
            ],
            'production_time_days' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
            'keywords' => [
                'nullable',
                'string',
                'max:500',
            ],
            'meta_title' => [
                'nullable',
                'string',
                'max:60',
            ],
            'meta_description' => [
                'nullable',
                'string',
                'max:160',
            ],
            'is_featured' => [
                'nullable',
                'boolean',
            ],
            'is_digital' => [
                'nullable',
                'boolean',
            ],
            // Additional fields for updates
            'remove_gallery_images' => [
                'nullable',
                'array',
            ],
            'remove_gallery_images.*' => [
                'string',
                'max:255',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Category validation messages
            'category_id.required' => 'නිෂ්පාදන කාණ්ඩය අවශ්‍යයි. / Product category is required.',
            'category_id.exists' => 'තෝරාගත් කාණ්ඩය නොපවතී. / Selected category does not exist.',

            // Product code validation messages
            'product_code.unique' => 'මෙම නිෂ්පාදන කේතය දැනටමත් භාවිතා වේ. / This product code is already taken.',
            'product_code.max' => 'නිෂ්පාදන කේතය අකුරු 50ට වඩා වැඩි විය නොහැක. / Product code cannot exceed 50 characters.',
            'product_code.min' => 'නිෂ්පාදන කේතය අවම අකුරු 3ක් විය යුතුයි. / Product code must be at least 3 characters.',
            'product_code.regex' => 'නිෂ්පාදන කේතය ලොකු අකුරු, අංක, _ සහ - පමණක් අඩංගු විය යුතුයි. / Product code can only contain uppercase letters, numbers, underscore, and hyphen.',

            // Name validation messages
            'name.required' => 'නිෂ්පාදන නාමය අවශ්‍යයි. / Product name is required.',
            'name.max' => 'නිෂ්පාදන නාමය අකුරු 255ට වඩා වැඩි විය නොහැක. / Product name cannot exceed 255 characters.',
            'name.min' => 'නිෂ්පාදන නාමය අවම අකුරු 2ක් විය යුතුයි. / Product name must be at least 2 characters.',

            // Description validation messages
            'description.max' => 'විස්තරය අකුරු 2000ට වඩා වැඩි විය නොහැක. / Description cannot exceed 2000 characters.',

            // Price validation messages
            'base_price.required' => 'මූලික මිල අවශ්‍යයි. / Base price is required.',
            'base_price.numeric' => 'මූලික මිල අංකයක් විය යුතුයි. / Base price must be a number.',
            'base_price.min' => 'මූලික මිල අවම රු. 0.01 විය යුතුයි. / Base price must be at least Rs. 0.01.',
            'base_price.max' => 'මූලික මිල උපරිම රු. 9,999,999.99 විය හැක. / Base price cannot exceed Rs. 9,999,999.99.',

            // Unit type validation messages
            'unit_type.required' => 'ඒකක වර්ගය අවශ්‍යයි. / Unit type is required.',
            'unit_type.in' => 'වලංගු ඒකක වර්ගයක් තෝරන්න. / Please select a valid unit type.',

            // Weight validation messages
            'weight_per_unit.required' => 'ඒකකයක බර අවශ්‍යයි. / Weight per unit is required.',
            'weight_per_unit.numeric' => 'ඒකකයක බර අංකයක් විය යුතුයි. / Weight per unit must be a number.',
            'weight_per_unit.min' => 'ඒකකයක බර අවම 0.001 විය යුතුයි. / Weight per unit must be at least 0.001.',
            'weight_per_unit.max' => 'ඒකකයක බර උපරිම 99,999.999 විය හැක. / Weight per unit cannot exceed 99,999.999.',

            // Weight unit validation messages
            'weight_unit.required' => 'බර ඒකකය අවශ්‍යයි. / Weight unit is required.',
            'weight_unit.in' => 'වලංගු බර ඒකකයක් තෝරන්න. / Please select a valid weight unit.',

            // Tax rate validation messages
            'tax_rate.numeric' => 'බදු අනුපාතය අංකයක් විය යුතුයි. / Tax rate must be a number.',
            'tax_rate.min' => 'බදු අනුපාතය අවම 0% විය යුතුයි. / Tax rate must be at least 0%.',
            'tax_rate.max' => 'බදු අනුපාතය උපරිම 100% විය හැක. / Tax rate cannot exceed 100%.',

            // Image validation messages
            'image.image' => 'උඩුගත කරන ගොනුව රූපයක් විය යුතුයි. / The uploaded file must be an image.',
            'image.mimes' => 'රූපය jpeg, png, jpg, gif, හෝ webp format එකේ විය යුතුයි. / Image must be jpeg, png, jpg, gif, or webp format.',
            'image.max' => 'රූප ප්‍රමාණය 3MB ට වඩා වැඩි විය නොහැක. / Image size cannot exceed 3MB.',
            'image.dimensions' => 'රූප ප්‍රමාණය අවම 200x200 සහ උපරිම 3000x3000 විය යුතුයි. / Image dimensions must be minimum 200x200 and maximum 3000x3000 pixels.',

            // Gallery validation messages
            'gallery.max' => 'උපරිම රූප 5ක් පමණක් උඩුගත කළ හැක. / Maximum 5 gallery images can be uploaded.',
            'gallery.*.image' => 'සියලු gallery ගොනු රූප විය යුතුයි. / All gallery files must be images.',
            'gallery.*.max' => 'Gallery රූප 2MB ට වඩා වැඩි විය නොහැක. / Gallery images cannot exceed 2MB.',

            // Specifications validation messages
            'specifications.max' => 'උපරිම specification 20ක් පමණක් එක් කළ හැක. / Maximum 20 specifications can be added.',
            'specifications.*.key.required_with' => 'Specification නාමය අවශ්‍යයි. / Specification name is required.',
            'specifications.*.key.distinct' => 'Specification නම් අනන්‍ය විය යුතුයි. / Specification names must be unique.',
            'specifications.*.key.max' => 'Specification නාමය අකුරු 100ට වඩා වැඩි විය නොහැක. / Specification name cannot exceed 100 characters.',
            'specifications.*.value.required_with' => 'Specification අගය අවශ්‍යයි. / Specification value is required.',
            'specifications.*.value.max' => 'Specification අගය අකුරු 500ට වඩා වැඩි විය නොහැක. / Specification value cannot exceed 500 characters.',

            // Pricing tiers validation messages
            'pricing_tiers.max' => 'උපරිම මිල කාණ්ඩ 10ක් පමණක් එක් කළ හැක. / Maximum 10 pricing tiers can be added.',
            'pricing_tiers.*.min_quantity.required_with' => 'අවම ප්‍රමාණය අවශ්‍යයි. / Minimum quantity is required.',
            'pricing_tiers.*.unit_price.required_with' => 'ඒකක මිල අවශ්‍යයි. / Unit price is required.',
            'pricing_tiers.*.max_quantity.gte' => 'උපරිම ප්‍රමාණය අවම ප්‍රමාණයට වඩා වැඩි විය යුතුයි. / Maximum quantity must be greater than minimum quantity.',

            // Status validation messages
            'status.required' => 'තත්ත්වය අවශ්‍යයි. / Status is required.',
            'status.in' => 'වලංගු තත්ත්වයක් තෝරන්න. / Please select a valid status.',

            // Quantity validation messages
            'maximum_quantity.gte' => 'උපරිම ප්‍රමාණය අවම ප්‍රමාණයට වඩා වැඩි විය යුතුයි. / Maximum quantity must be greater than minimum quantity.',

            // Customization validation messages
            'customization_options.required_if' => 'අභිරුචිකරණ අවශ්‍ය නම් එහි විකල්ප සඳහන් කරන්න. / If customization is required, please specify the options.',
            'customization_options.max' => 'අභිරුචිකරණ විකල්ප අකුරු 1000ට වඩා වැඩි විය නොහැක. / Customization options cannot exceed 1000 characters.',

            // Production time validation messages
            'production_time_days.max' => 'නිෂ්පාදන කාලය උපරිම දින 365ක් විය හැක. / Production time cannot exceed 365 days.',

            // SEO validation messages
            'keywords.max' => 'මූල පද අකුරු 500ට වඩා වැඩි විය නොහැක. / Keywords cannot exceed 500 characters.',
            'meta_title.max' => 'SEO title අකුරු 60ට වඩා වැඩි විය නොහැක. / SEO title cannot exceed 60 characters.',
            'meta_description.max' => 'SEO description අකුරු 160ට වඩා වැඩි විය නොහැක. / SEO description cannot exceed 160 characters.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'category_id' => 'Product Category / නිෂ්පාදන කාණ්ඩය',
            'product_code' => 'Product Code / නිෂ්පාදන කේතය',
            'name' => 'Product Name / නිෂ්පාදන නාමය',
            'description' => 'Description / විස්තරය',
            'base_price' => 'Base Price / මූලික මිල',
            'unit_type' => 'Unit Type / ඒකක වර්ගය',
            'weight_per_unit' => 'Weight Per Unit / ඒකකයක බර',
            'weight_unit' => 'Weight Unit / බර ඒකකය',
            'tax_rate' => 'Tax Rate / බදු අනුපාතය',
            'image' => 'Product Image / නිෂ්පාදන රූපය',
            'gallery' => 'Image Gallery / රූප ගැලරිය',
            'specifications' => 'Specifications / විශේෂාංග',
            'pricing_tiers' => 'Pricing Tiers / මිල කාණ්ඩ',
            'status' => 'Status / තත්ත්වය',
            'minimum_quantity' => 'Minimum Quantity / අවම ප්‍රමාණය',
            'maximum_quantity' => 'Maximum Quantity / උපරිම ප්‍රමාණය',
            'stock_quantity' => 'Stock Quantity / තොග ප්‍රමාණය',
            'reorder_level' => 'Reorder Level / නැවත ඇණවුම් මට්ටම',
            'requires_customization' => 'Requires Customization / අභිරුචිකරණය අවශ්‍යයි',
            'customization_options' => 'Customization Options / අභිරුචිකරණ විකල්ප',
            'production_time_days' => 'Production Time (Days) / නිෂ්පාදන කාලය (දින)',
            'keywords' => 'Keywords / මූල පද',
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
            'is_featured' => 'Featured Product / විශේෂාංගීකෘත නිෂ්පාදනය',
            'is_digital' => 'Digital Product / ඩිජිටල් නිෂ්පාදනය',
        ];
    }

    /**
     * Prepare the data for validation.
     */
  

    /**
     * Prepare specifications array
     */
    private function prepareSpecifications(): ?array
    {
        if (!$this->specifications || !is_array($this->specifications)) {
            return null;
        }

        $specifications = [];
        foreach ($this->specifications as $spec) {
            if (isset($spec['key']) && isset($spec['value']) && 
                !empty(trim($spec['key'])) && !empty(trim($spec['value']))) {
                $key = trim($spec['key']);
                $value = trim($spec['value']);
                
                // Avoid duplicates
                if (!isset($specifications[$key])) {
                    $specifications[$key] = $value;
                }
            }
        }

        return empty($specifications) ? null : $specifications;
    }

    /**
     * Prepare pricing tiers array
     */
    private function preparePricingTiers(): ?array
    {
        if (!$this->pricing_tiers || !is_array($this->pricing_tiers)) {
            return null;
        }

        $tiers = [];
        foreach ($this->pricing_tiers as $tier) {
            if (isset($tier['min_quantity']) && isset($tier['unit_price']) &&
                $tier['min_quantity'] > 0 && $tier['unit_price'] > 0) {
                
                $tiers[] = [
                    'min_quantity' => intval($tier['min_quantity']),
                    'max_quantity' => isset($tier['max_quantity']) && $tier['max_quantity'] > 0 
                        ? intval($tier['max_quantity']) : null,
                    'unit_price' => round(floatval($tier['unit_price']), 2),
                ];
            }
        }

        // Sort by min_quantity
        usort($tiers, function ($a, $b) {
            return $a['min_quantity'] <=> $b['min_quantity'];
        });

        return empty($tiers) ? null : $tiers;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        if ($this->expectsJson()) {
            $response = response()->json([
                'success' => false,
                'message' => 'Validation failed / වලංගුකරණය අසාර්ථකයි',
                'errors' => $validator->errors(),
                'error_count' => $validator->errors()->count(),
            ], 422);

            throw new \Illuminate\Validation\ValidationException($validator, $response);
        }

        parent::failedValidation($validator);
    }
    public function prepareForValidation(): void
{
    $this->merge([
        // Clean product name
        'name' => $this->name ? trim($this->name) : null,
        
        // Format product code
        'product_code' => $this->product_code ? 
            strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($this->product_code))) : null,
            
        // Clean description
        'description' => $this->description ? trim($this->description) : null,
        
        // Format prices
        'base_price' => $this->base_price ? round(floatval($this->base_price), 2) : null,
        'tax_rate' => $this->tax_rate ? round(floatval($this->tax_rate), 2) : 0,
        
        // Process specifications
        'specifications' => $this->prepareSpecifications(),
    ]);
}
}