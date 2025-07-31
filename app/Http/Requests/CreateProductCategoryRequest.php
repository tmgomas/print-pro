<?php
// app/Http/Requests/CreateProductCategoryRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateProductCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create product categories');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'code' => [
                'nullable',
                'string',
                'max:50',
                'min:3',
                'unique:product_categories,code',
                'regex:/^[A-Z0-9_-]+$/', // Only uppercase letters, numbers, underscore, hyphen
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpeg,png,jpg,gif,webp',
                'max:2048', // 2MB
                'dimensions:min_width=100,min_height=100,max_width=2000,max_height=2000',
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:product_categories,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Check if parent category belongs to the same company
                        $parentCategory = \App\Models\ProductCategory::find($value);
                        if ($parentCategory && $parentCategory->company_id !== auth()->user()->company_id) {
                            $fail('Selected parent category does not belong to your company.');
                        }

                        // Check if parent is active
                        if ($parentCategory && $parentCategory->status !== 'active') {
                            $fail('Selected parent category is not active.');
                        }
                    }
                },
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive',
            ],
            'sort_order' => [
                'nullable',
                'integer',
                'min:0',
                'max:9999',
            ],
            'meta_title' => [
                'nullable',
                'string',
                'max:60', // SEO best practice
            ],
            'meta_description' => [
                'nullable',
                'string',
                'max:160', // SEO best practice
            ],
            'is_featured' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Name validation messages
            'name.required' => 'කැටගරි නාමය අවශ්‍යයි. / Category name is required.',
            'name.string' => 'කැටගරි නාමය වලංගු text එකක් විය යුතුයි. / Category name must be valid text.',
            'name.max' => 'කැටගරි නාමය අකුරු 255ට වඩා වැඩි විය නොහැක. / Category name cannot exceed 255 characters.',
            'name.min' => 'කැටගරි නාමය අවම වශයෙන් අකුරු 2ක් විය යුතුයි. / Category name must be at least 2 characters.',

            // Code validation messages
            'code.string' => 'කැටගරි කේතය වලංගු text එකක් විය යුතුයි. / Category code must be valid text.',
            'code.max' => 'කැටගරි කේතය අකුරු 50ට වඩා වැඩි විය නොහැක. / Category code cannot exceed 50 characters.',
            'code.min' => 'කැටගරි කේතය අවම වශයෙන් අකුරු 3ක් විය යුතුයි. / Category code must be at least 3 characters.',
            'code.unique' => 'මෙම කැටගරි කේතය දැනටමත් භාවිතා වේ. / This category code is already taken.',
            'code.regex' => 'කැටගරි කේතය ලොකු අකුරු, අංක, _ සහ - පමණක් අඩංගු විය යුතුයි. / Category code can only contain uppercase letters, numbers, underscore, and hyphen.',

            // Description validation messages
            'description.string' => 'විස්තරය වලංगු text එකක් විය යුතුයි. / Description must be valid text.',
            'description.max' => 'විස්තරය අකුරු 1000ට වඩා වැඩි විය නොහැක. / Description cannot exceed 1000 characters.',

            // Image validation messages
            'image.image' => 'උඩුගත කරන ගොනුව image එකක් විය යුතුයි. / The uploaded file must be an image.',
            'image.mimes' => 'Image එක jpeg, png, jpg, gif, හෝ webp format එකේ විය යුතුයි. / Image must be a file of type: jpeg, png, jpg, gif, webp.',
            'image.max' => 'Image size 2MB ට වඩා වැඩි විය නොහැක. / Image size should not exceed 2MB.',
            'image.dimensions' => 'Image size අවම වශයෙන් 100x100 සහ උපරිම 2000x2000 විය යුතුයි. / Image dimensions must be minimum 100x100 and maximum 2000x2000 pixels.',

            // Parent category validation messages
            'parent_id.integer' => 'වලංගු parent category එකක් තෝරන්න. / Please select a valid parent category.',
            'parent_id.exists' => 'තෝරාගත් parent category එක නොපවතී. / Selected parent category does not exist.',

            // Status validation messages
            'status.required' => 'Status එක අවශ්‍යයි. / Status is required.',
            'status.in' => 'Status එක active හෝ inactive විය යුතුයි. / Status must be either active or inactive.',

            // Sort order validation messages
            'sort_order.integer' => 'Sort order අංකයක් විය යුතුයි. / Sort order must be a number.',
            'sort_order.min' => 'Sort order අවම වශයෙන් 0 විය යුතුයි. / Sort order must be at least 0.',
            'sort_order.max' => 'Sort order උපරිම 9999 විය යුතුයි. / Sort order cannot exceed 9999.',

            // SEO validation messages
            'meta_title.max' => 'Meta title අකුරු 60ට වඩා වැඩි විය නොහැක. / Meta title cannot exceed 60 characters.',
            'meta_description.max' => 'Meta description අකුරු 160ට වඩා වැඩි විය නොහැක. / Meta description cannot exceed 160 characters.',

            // Featured validation messages
            'is_featured.boolean' => 'Featured status true හෝ false විය යුතුයි. / Featured status must be true or false.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'Category Name / කැටගරි නාමය',
            'code' => 'Category Code / කැටගරි කේතය',
            'description' => 'Description / විස්තරය',
            'image' => 'Category Image / කැටගරි රූපය',
            'parent_id' => 'Parent Category / මව් කැටගරිය',
            'status' => 'Status / තත්ත්වය',
            'sort_order' => 'Sort Order / අනුක්‍රමය',
            'meta_title' => 'SEO Title',
            'meta_description' => 'SEO Description',
            'is_featured' => 'Featured Status / විශේෂාංගීකෘත තත්ත්වය',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // Clean and format name
            'name' => $this->name ? trim($this->name) : null,

            // Auto-generate and format code
            'code' => $this->prepareCode(),

            // Clean description
            'description' => $this->description ? trim($this->description) : null,

            // Set default sort order
            'sort_order' => $this->sort_order ?? 0,

            // Set default featured status
            'is_featured' => $this->is_featured ?? false,

            // Clean SEO fields
            'meta_title' => $this->meta_title ? trim($this->meta_title) : null,
            'meta_description' => $this->meta_description ? trim($this->meta_description) : null,
        ]);
    }

    /**
     * Prepare category code
     */
    private function prepareCode(): ?string
    {
        if ($this->code) {
            // Clean and format provided code
            return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($this->code)));
        }

        if ($this->name) {
            // Auto-generate code from name
            $baseName = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->name));
            $baseCode = substr($baseName, 0, 6);
            
            // Ensure uniqueness
            $counter = 1;
            $code = $baseCode;
            
            while (\App\Models\ProductCategory::where('code', $code)->exists()) {
                $code = $baseCode . str_pad($counter, 2, '0', STR_PAD_LEFT);
                $counter++;
                
                if ($counter > 99) {
                    $code = $baseCode . uniqid();
                    break;
                }
            }
            
            return $code;
        }

        return null;
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
}
