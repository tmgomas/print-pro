<?php
// app/Http/Requests/CreateCustomerRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateCustomerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('create customers');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'branch_id' => [
                'nullable',
                'integer',
                'exists:branches,id',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        // Check if branch belongs to the same company
                        $branch = \App\Models\Branch::find($value);
                        if ($branch && $branch->company_id !== auth()->user()->company_id) {
                            $fail('Selected branch does not belong to your company.');
                        }

                        // Check if branch is active
                        if ($branch && $branch->status !== 'active') {
                            $fail('Selected branch is not active.');
                        }
                    }
                },
            ],
            'customer_code' => [
                'nullable',
                'string',
                'max:50',
                'min:3',
                'unique:customers,customer_code',
                'regex:/^[A-Z0-9_-]+$/', // Only uppercase letters, numbers, underscore, hyphen
            ],
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-Z\s\u0D80-\u0DFF\.]+$/', // Allow English, Sinhala characters, spaces, dots
            ],
            'email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                'unique:customers,email',
                'lowercase',
            ],
            'phone' => [
                'required',
                'string',
                'max:20',
                'min:9',
                'regex:/^[\+]?[0-9\-\(\)\s]+$/', // Allow international format
                function ($attribute, $value, $fail) {
                    // Sri Lankan phone number validation
                    $cleanPhone = preg_replace('/[^\d\+]/', '', $value);
                    
                    // Check Sri Lankan mobile formats
                    if (preg_match('/^(?:\+94|94|0)?[1-9][0-9]{8}$/', $cleanPhone)) {
                        return; // Valid Sri Lankan number
                    }
                    
                    // Check international format
                    if (preg_match('/^\+[1-9]\d{6,14}$/', $cleanPhone)) {
                        return; // Valid international number
                    }
                    
                    $fail('Please enter a valid phone number.');
                },
            ],
            'billing_address' => [
                'required',
                'string',
                'max:500',
                'min:10',
            ],
            'shipping_address' => [
                'nullable',
                'string',
                'max:500',
                'min:10',
            ],
            'city' => [
                'required',
                'string',
                'max:100',
                'min:2',
                'regex:/^[a-zA-Z\s\u0D80-\u0DFF\-\']+$/', // Allow letters, spaces, hyphens, apostrophes
            ],
            'postal_code' => [
                'nullable',
                'string',
                'max:10',
                'regex:/^[0-9]{5}$/', // Sri Lankan postal code format
            ],
            'district' => [
                'nullable',
                'string',
                'max:100',
                'in:Colombo,Gampaha,Kalutara,Kandy,Matale,Nuwara Eliya,Galle,Matara,Hambantota,Jaffna,Kilinochchi,Mannar,Vavuniya,Mullaitivu,Batticaloa,Ampara,Trincomalee,Kurunegala,Puttalam,Anuradhapura,Polonnaruwa,Badulla,Moneragala,Ratnapura,Kegalle',
            ],
            'province' => [
                'nullable',
                'string',
                'max:100',
                'in:Western,Central,Southern,Northern,Eastern,North Western,North Central,Uva,Sabaragamuwa',
            ],
            'tax_number' => [
                'nullable',
                'string',
                'max:50',
                'unique:customers,tax_number',
                'regex:/^[A-Z0-9\-]+$/', // Tax number format
            ],
            'credit_limit' => [
                'nullable',
                'numeric',
                'min:0',
                'max:999999999.99',
                'decimal:0,2',
            ],
            'status' => [
                'required',
                'string',
                'in:active,inactive,suspended',
            ],
            'customer_type' => [
                'required',
                'string',
                'in:individual,business',
            ],
            'date_of_birth' => [
                'nullable',
                'date',
                'before:today',
                'after:1900-01-01',
                'required_if:customer_type,individual',
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $age = \Carbon\Carbon::parse($value)->age;
                        if ($age < 16) {
                            $fail('Customer must be at least 16 years old.');
                        }
                        if ($age > 120) {
                            $fail('Please enter a valid date of birth.');
                        }
                    }
                },
            ],
            'company_name' => [
                'required_if:customer_type,business',
                'nullable',
                'string',
                'max:255',
                'min:2',
            ],
            'company_registration' => [
                'nullable',
                'string',
                'max:50',
                'regex:/^[A-Z0-9\-\/]+$/', // Company registration format
                'required_if:customer_type,business',
            ],
            'contact_person' => [
                'required_if:customer_type,business',
                'nullable',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-Z\s\u0D80-\u0DFF\.]+$/',
            ],
            'contact_person_phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\-\(\)\s]+$/',
                'required_if:customer_type,business',
            ],
            'contact_person_email' => [
                'nullable',
                'email:rfc,dns',
                'max:255',
                'different:email', // Must be different from main email
            ],
            'notes' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'preferences' => [
                'nullable',
                'array',
            ],
            'preferences.communication_method' => [
                'nullable',
                'string',
                'in:email,sms,phone,whatsapp',
            ],
            'preferences.language' => [
                'nullable',
                'string',
                'in:en,si,ta',
            ],
            'preferences.currency' => [
                'nullable',
                'string',
                'in:LKR,USD,EUR',
            ],
            'preferences.payment_terms' => [
                'nullable',
                'integer',
                'min:0',
                'max:365',
            ],
            // Emergency contact information
            'emergency_contact_name' => [
                'nullable',
                'string',
                'max:255',
            ],
            'emergency_contact_phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\-\(\)\s]+$/',
            ],
            'emergency_contact_relationship' => [
                'nullable',
                'string',
                'max:100',
                'in:spouse,parent,sibling,child,friend,colleague,other',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            // Branch validation messages
            'branch_id.exists' => 'තෝරාගත් ශාඛාව නොපවතී. / Selected branch does not exist.',

            // Customer code validation messages
            'customer_code.unique' => 'මෙම පාරිභෝගික කේතය දැනටමත් භාවිතා වේ. / This customer code is already taken.',
            'customer_code.max' => 'පාරිභෝගික කේතය අකුරු 50ට වඩා වැඩි විය නොහැක. / Customer code cannot exceed 50 characters.',
            'customer_code.min' => 'පාරිභෝගික කේතය අවම අකුරු 3ක් විය යුතුයි. / Customer code must be at least 3 characters.',
            'customer_code.regex' => 'පාරිභෝගික කේතය ලොකු අකුරු, අංක, _ සහ - පමණක් අඩංගු විය යුතුයි. / Customer code can only contain uppercase letters, numbers, underscore, and hyphen.',

            // Name validation messages
            'name.required' => 'පාරිභෝගික නාමය අවශ්‍යයි. / Customer name is required.',
            'name.max' => 'පාරිභෝගික නාමය අකුරු 255ට වඩා වැඩි විය නොහැක. / Customer name cannot exceed 255 characters.',
            'name.min' => 'පාරිභෝගික නාමය අවම අකුරු 2ක් විය යුතුයි. / Customer name must be at least 2 characters.',
            'name.regex' => 'පාරිභෝගික නාමය වලංගු අකුරු පමණක් අඩංගු විය යුතුයි. / Customer name can only contain valid letters and spaces.',

            // Email validation messages
            'email.email' => 'වලංගු ඊමේල් ලිපිනයක් ඇතුලත් කරන්න. / Please enter a valid email address.',
            'email.unique' => 'මෙම ඊමේල් ලිපිනය දැනටමත් ලියාපදිංචි වී ඇත. / This email address is already registered.',
            'email.max' => 'ඊමේල් ලිපිනය අකුරු 255ට වඩා වැඩි විය නොහැක. / Email address cannot exceed 255 characters.',

            // Phone validation messages
            'phone.required' => 'දුරකථන අංකය අවශ්‍යයි. / Phone number is required.',
            'phone.max' => 'දුරකථන අංකය අකුරු 20ට වඩා වැඩි විය නොහැක. / Phone number cannot exceed 20 characters.',
            'phone.min' => 'දුරකථන අංකය අවම අකුරු 9ක් විය යුතුයි. / Phone number must be at least 9 characters.',
            'phone.regex' => 'වලංගු දුරකථන අංකයක් ඇතුලත් කරන්න. / Please enter a valid phone number.',

            // Address validation messages
            'billing_address.required' => 'බිල්පත් ලිපිනය අවශ්‍යයි. / Billing address is required.',
            'billing_address.max' => 'බිල්පත් ලිපිනය අකුරු 500ට වඩා වැඩි විය නොහැක. / Billing address cannot exceed 500 characters.',
            'billing_address.min' => 'බිල්පත් ලිපිනය අවම අකුරු 10ක් විය යුතුයි. / Billing address must be at least 10 characters.',
            'shipping_address.max' => 'බෙදාහැරීමේ ලිපිනය අකුරු 500ට වඩා වැඩි විය නොහැක. / Shipping address cannot exceed 500 characters.',
            'shipping_address.min' => 'බෙදාහැරීමේ ලිපිනය අවම අකුරු 10ක් විය යුතුයි. / Shipping address must be at least 10 characters.',

            // City validation messages
            'city.required' => 'නගරය අවශ්‍යයි. / City is required.',
            'city.max' => 'නගර නාමය අකුරු 100ට වඩා වැඩි විය නොහැක. / City name cannot exceed 100 characters.',
            'city.min' => 'නගර නාමය අවම අකුරු 2ක් විය යුතුයි. / City name must be at least 2 characters.',
            'city.regex' => 'නගර නාමය වලංගු අකුරු පමණක් අඩංගු විය යුතුයි. / City name can only contain valid letters.',

            // Postal code validation messages
            'postal_code.max' => 'තැපැල් කේතය අකුරු 10ට වඩා වැඩි විය නොහැක. / Postal code cannot exceed 10 characters.',
            'postal_code.regex' => 'වලංගු ශ්‍රී ලාංකික තැපැල් කේතයක් ඇතුලත් කරන්න (5 අංක). / Please enter a valid Sri Lankan postal code (5 digits).',

            // District validation messages
            'district.max' => 'දිස්ත්‍රික්ක නාමය අකුරු 100ට වඩා වැඩි විය නොහැක. / District name cannot exceed 100 characters.',
            'district.in' => 'වලංගු දිස්ත්‍රික්කයක් තෝරන්න. / Please select a valid district.',

            // Province validation messages
            'province.max' => 'පළාත නාමය අකුරු 100ට වඩා වැඩි විය නොහැක. / Province name cannot exceed 100 characters.',
            'province.in' => 'වලංගු පළාතක් තෝරන්න. / Please select a valid province.',

            // Tax number validation messages
            'tax_number.unique' => 'මෙම බදු අංකය දැනටමත් ලියාපදිංචි වී ඇත. / This tax number is already registered.',
            'tax_number.max' => 'බදු අංකය අකුරු 50ට වඩා වැඩි විය නොහැක. / Tax number cannot exceed 50 characters.',
            'tax_number.regex' => 'වලංගු බදු අංකයක් ඇතුලත් කරන්න. / Please enter a valid tax number.',

            // Credit limit validation messages
            'credit_limit.numeric' => 'ණය සීමාව අංකයක් විය යුතුයි. / Credit limit must be a number.',
            'credit_limit.min' => 'ණය සීමාව අවම රු. 0 විය යුතුයි. / Credit limit must be at least Rs. 0.',
            'credit_limit.max' => 'ණය සීමාව උපරිම රු. 999,999,999.99 විය හැක. / Credit limit cannot exceed Rs. 999,999,999.99.',

            // Status validation messages
            'status.required' => 'තත්ත්වය අවශ්‍යයි. / Status is required.',
            'status.in' => 'වලංගු තත්ත්වයක් තෝරන්න. / Please select a valid status.',

            // Customer type validation messages
            'customer_type.required' => 'පාරිභෝගික වර්ගය අවශ්‍යයි. / Customer type is required.',
            'customer_type.in' => 'පාරිභෝගික වර්ගය individual හෝ business විය යුතුයි. / Customer type must be either individual or business.',

            // Date of birth validation messages
            'date_of_birth.date' => 'වලංගු උපන් දිනයක් ඇතුලත් කරන්න. / Please enter a valid date of birth.',
            'date_of_birth.before' => 'උපන් දිනය අදට පෙර විය යුතුයි. / Date of birth must be before today.',
            'date_of_birth.after' => 'වලංගු උපන් දිනයක් ඇතුලත් කරන්න. / Please enter a valid date of birth.',
            'date_of_birth.required_if' => 'පුද්ගලික පාරිභෝගිකයින් සඳහා උපන් දිනය අවශ්‍යයි. / Date of birth is required for individual customers.',

            // Company validation messages
            'company_name.required_if' => 'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සමාගම් නාමය අවශ්‍යයි. / Company name is required for business customers.',
            'company_name.max' => 'සමාගම් නාමය අකුරු 255ට වඩා වැඩි විය නොහැක. / Company name cannot exceed 255 characters.',
            'company_name.min' => 'සමාගම් නාමය අවම අකුරු 2ක් විය යුතුයි. / Company name must be at least 2 characters.',

            'company_registration.required_if' => 'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සමාගම් ලියාපදිංචි අංකය අවශ්‍යයි. / Company registration number is required for business customers.',
            'company_registration.max' => 'සමාගම් ලියාපදිංචි අංකය අකුරු 50ට වඩා වැඩි විය නොහැක. / Company registration number cannot exceed 50 characters.',
            'company_registration.regex' => 'වලංගු සමාගම් ලියාපදිංචි අංකයක් ඇතුලත් කරන්න. / Please enter a valid company registration number.',

            // Contact person validation messages
            'contact_person.required_if' => 'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සම්බන්ධතා පුද්ගලයා අවශ්‍යයි. / Contact person is required for business customers.',
            'contact_person.max' => 'සම්බන්ධතා පුද්ගලයාගේ නාමය අකුරු 255ට වඩා වැඩි විය නොහැක. / Contact person name cannot exceed 255 characters.',
            'contact_person.min' => 'සම්බන්ධතා පුද්ගලයාගේ නාමය අවම අකුරු 2ක් විය යුතුයි. / Contact person name must be at least 2 characters.',
            'contact_person.regex' => 'සම්බන්ධතා පුද්ගලයාගේ නාමය වලංගු අකුරු පමණක් අඩංගු විය යුතුයි. / Contact person name can only contain valid letters.',

            'contact_person_phone.required_if' => 'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සම්බන්ධතා දුරකථන අංකය අවශ්‍යයි. / Contact person phone is required for business customers.',
            'contact_person_phone.max' => 'සම්බන්ධතා දුරකථන අංකය අකුරු 20ට වඩා වැඩි විය නොහැක. / Contact person phone cannot exceed 20 characters.',
            'contact_person_phone.regex' => 'වලංගු සම්බන්ධතා දුරකථන අංකයක් ඇතුලත් කරන්න. / Please enter a valid contact person phone number.',

            'contact_person_email.email' => 'වලංගු සම්බන්ධතා ඊමේල් ලිපිනයක් ඇතුලත් කරන්න. / Please enter a valid contact person email address.',
            'contact_person_email.max' => 'සම්බන්ධතා ඊමේල් ලිපිනය අකුරු 255ට වඩා වැඩි විය නොහැක. / Contact person email cannot exceed 255 characters.',
            'contact_person_email.different' => 'සම්බන්ධතා ඊමේල් ලිපිනය ප්‍රධාන ඊමේල් ලිපිනයට වඩා වෙනස් විය යුතුයි. / Contact person email must be different from main email.',

            // Notes validation messages
            'notes.max' => 'සටහන් අකුරු 1000ට වඩා වැඩි විය නොහැක. / Notes cannot exceed 1000 characters.',

            // Preferences validation messages
            'preferences.communication_method.in' => 'වලංගු සන්නිවේදන ක්‍රමයක් තෝරන්න. / Please select a valid communication method.',
            'preferences.language.in' => 'වලංගු භාෂාවක් තෝරන්න. / Please select a valid language.',
            'preferences.currency.in' => 'වලංගු මුදල් ඒකකයක් තෝරන්න. / Please select a valid currency.',
            'preferences.payment_terms.min' => 'ගෙවීම් කොන්දේසි අවම දින 0ක් විය යුතුයි. / Payment terms must be at least 0 days.',
            'preferences.payment_terms.max' => 'ගෙවීම් කොන්දේසි උපරිම දින 365ක් විය හැක. / Payment terms cannot exceed 365 days.',

            // Emergency contact validation messages
            'emergency_contact_name.max' => 'හදිසි සම්බන්ධතා නාමය අකුරු 255ට වඩා වැඩි විය නොහැක. / Emergency contact name cannot exceed 255 characters.',
            'emergency_contact_phone.max' => 'හදිසි සම්බන්ධතා දුරකථන අංකය අකුරු 20ට වඩා වැඩි විය නොහැක. / Emergency contact phone cannot exceed 20 characters.',
            'emergency_contact_phone.regex' => 'වලංගු හදිසි සම්බන්ධතා දුරකථන අංකයක් ඇතුලත් කරන්න. / Please enter a valid emergency contact phone number.',
            'emergency_contact_relationship.max' => 'සම්බන්ධතාව අකුරු 100ට වඩා වැඩි විය නොහැක. / Relationship cannot exceed 100 characters.',
            'emergency_contact_relationship.in' => 'වලංගු සම්බන්ධතාවක් තෝරන්න. / Please select a valid relationship.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'branch_id' => 'Branch / ශාඛාව',
            'customer_code' => 'Customer Code / පාරිභෝගික කේතය',
            'name' => 'Customer Name / පාරිභෝගික නාමය',
            'email' => 'Email Address / ඊමේල් ලිපිනය',
            'phone' => 'Phone Number / දුරකථන අංකය',
            'billing_address' => 'Billing Address / බිල්පත් ලිපිනය',
            'shipping_address' => 'Shipping Address / බෙදාහැරීමේ ලිපිනය',
            'city' => 'City / නගරය',
            'postal_code' => 'Postal Code / තැපැල් කේතය',
            'district' => 'District / දිස්ත්‍රික්කය',
            'province' => 'Province / පළාත',
            'tax_number' => 'Tax Number / බදු අංකය',
            'credit_limit' => 'Credit Limit / ණය සීමාව',
            'status' => 'Status / තත්ත්වය',
            'customer_type' => 'Customer Type / පාරිභෝගික වර්ගය',
            'date_of_birth' => 'Date of Birth / උපන් දිනය',
            'company_name' => 'Company Name / සමාගම් නාමය',
            'company_registration' => 'Company Registration / සමාගම් ලියාපදිංචිය',
            'contact_person' => 'Contact Person / සම්බන්ධතා පුද්ගලයා',
            'contact_person_phone' => 'Contact Person Phone / සම්බන්ධතා දුරකථනය',
            'contact_person_email' => 'Contact Person Email / සම්බන්ධතා ඊමේල්',
            'notes' => 'Notes / සටහන්',
            'emergency_contact_name' => 'Emergency Contact Name / හදිසි සම්බන්ධතා නාමය',
            'emergency_contact_phone' => 'Emergency Contact Phone / හදිසි සම්බන්ධතා දුරකථනය',
            'emergency_contact_relationship' => 'Emergency Contact Relationship / හදිසි සම්බන්ධතා ගැටුම',
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

            // Auto-generate and format customer code
            'customer_code' => $this->prepareCustomerCode(),

            // Clean and format email
            'email' => $this->email ? strtolower(trim($this->email)) : null,
            'contact_person_email' => $this->contact_person_email ? strtolower(trim($this->contact_person_email)) : null,

            // Clean and format phone numbers
            'phone' => $this->phone ? $this->formatPhoneNumber($this->phone) : null,
            'contact_person_phone' => $this->contact_person_phone ? $this->formatPhoneNumber($this->contact_person_phone) : null,
            'emergency_contact_phone' => $this->emergency_contact_phone ? $this->formatPhoneNumber($this->emergency_contact_phone) : null,

            // Clean addresses
            'billing_address' => $this->billing_address ? trim($this->billing_address) : null,
            'shipping_address' => $this->shipping_address ? trim($this->shipping_address) : $this->billing_address,

            // Clean location fields
            'city' => $this->city ? trim($this->city) : null,
            'postal_code' => $this->postal_code ? preg_replace('/[^0-9]/', '', $this->postal_code) : null,

            // Clean and format business fields
            'company_name' => $this->company_name ? trim($this->company_name) : null,
            'company_registration' => $this->company_registration ? strtoupper(trim($this->company_registration)) : null,
            'contact_person' => $this->contact_person ? trim($this->contact_person) : null,

            // Clean tax number
            'tax_number' => $this->tax_number ? strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', trim($this->tax_number))) : null,

            // Format credit limit
            'credit_limit' => $this->credit_limit ? round(floatval($this->credit_limit), 2) : 0,

            // Clean notes
            'notes' => $this->notes ? trim($this->notes) : null,

            // Clean emergency contact fields
            'emergency_contact_name' => $this->emergency_contact_name ? trim($this->emergency_contact_name) : null,

            // Set default preferences
            'preferences' => $this->preparePreferences(),
        ]);
    }

    /**
     * Prepare customer code
     */
    private function prepareCustomerCode(): ?string
    {
        if ($this->customer_code) {
            // Clean and format provided code
            return strtoupper(preg_replace('/[^A-Za-z0-9_-]/', '', trim($this->customer_code)));
        }

        // Auto-generate customer code
        $prefix = 'CUS';
        $year = date('y');
        $month = date('m');
        
        // Get the last customer code for this month
        $lastCustomer = \App\Models\Customer::where('customer_code', 'like', "{$prefix}{$year}{$month}%")
            ->orderBy('customer_code', 'desc')
            ->first();
            
        $counter = 1;
        if ($lastCustomer) {
            $lastNumber = (int) substr($lastCustomer->customer_code, -3);
            $counter = $lastNumber + 1;
        }
        
        return $prefix . $year . $month . str_pad($counter, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Format phone number
     */
    private function formatPhoneNumber(string $phone): string
    {
        // Remove all non-digit characters except +
        $cleanPhone = preg_replace('/[^\d\+]/', '', $phone);
        
        // Sri Lankan number formatting
        if (preg_match('/^(?:94|0)?([1-9][0-9]{8})$/', $cleanPhone, $matches)) {
            return '+94' . $matches[1];
        }
        
        // International number - keep as is if starts with +
        if (strpos($cleanPhone, '+') === 0) {
            return $cleanPhone;
        }
        
        // Default formatting
        return $cleanPhone;
    }

    /**
     * Prepare preferences array
     */
    private function preparePreferences(): array
    {
        $preferences = [
            'communication_method' => $this->input('preferences.communication_method', 'email'),
            'language' => $this->input('preferences.language', 'en'),
            'currency' => $this->input('preferences.currency', 'LKR'),
            'payment_terms' => $this->input('preferences.payment_terms', 30),
        ];

        // Add custom preferences
        if ($this->preferences && is_array($this->preferences)) {
            foreach ($this->preferences as $key => $value) {
                if (!in_array($key, ['communication_method', 'language', 'currency', 'payment_terms'])) {
                    $preferences[$key] = $value;
                }
            }
        }

        return $preferences;
    }

    /**
     * Get additional validation rules based on customer type
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Additional business logic validations
            $this->validateCustomerType($validator);
            $this->validateCreditLimit($validator);
            $this->validatePhoneUniqueness($validator);
        });
    }

    /**
     * Validate customer type specific requirements
     */
    private function validateCustomerType($validator): void
    {
        $customerType = $this->get('customer_type');
        
        if ($customerType === 'business') {
            // For business customers, ensure all business fields are provided
            if (empty($this->get('company_name'))) {
                $validator->errors()->add('company_name', 
                    'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සමාගම් නාමය අවශ්‍යයි. / Company name is required for business customers.'
                );
            }
            
            if (empty($this->get('contact_person'))) {
                $validator->errors()->add('contact_person', 
                    'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සම්බන්ධතා පුද්ගලයා අවශ්‍යයි. / Contact person is required for business customers.'
                );
            }
            
            if (empty($this->get('contact_person_phone'))) {
                $validator->errors()->add('contact_person_phone', 
                    'ව්‍යාපාරික පාරිභෝගිකයින් සඳහා සම්බන්ධතා දුරකථන අංකය අවශ්‍යයි. / Contact person phone is required for business customers.'
                );
            }
        }
        
        if ($customerType === 'individual') {
            // For individual customers, date of birth should be provided
            if (empty($this->get('date_of_birth'))) {
                $validator->errors()->add('date_of_birth', 
                    'පුද්ගලික පාරිභෝගිකයින් සඳහා උපන් දිනය අවශ්‍යයි. / Date of birth is required for individual customers.'
                );
            }
        }
    }

    /**
     * Validate credit limit based on customer type and company policies
     */
    private function validateCreditLimit($validator): void
    {
        $creditLimit = floatval($this->get('credit_limit', 0));
        $customerType = $this->get('customer_type');
        
        // Set maximum credit limits based on customer type
        $maxCreditLimits = [
            'individual' => 500000.00, // Rs. 500,000 for individuals
            'business' => 5000000.00,  // Rs. 5,000,000 for businesses
        ];
        
        if (isset($maxCreditLimits[$customerType]) && $creditLimit > $maxCreditLimits[$customerType]) {
            $formattedLimit = number_format($maxCreditLimits[$customerType], 2);
            $validator->errors()->add('credit_limit', 
                "{$customerType} පාරිභෝගිකයින් සඳහා උපරිම ණය සීමාව රු. {$formattedLimit} වේ. / Maximum credit limit for {$customerType} customers is Rs. {$formattedLimit}."
            );
        }
        
        // Warn if credit limit is high for new customers
        if ($creditLimit > 100000.00) {
            $validator->errors()->add('credit_limit', 
                'නව පාරිභෝගිකයෙකු සඳහා ඉහළ ණය සීමාවක්. කරුණාකර තහවුරු කරන්න. / High credit limit for a new customer. Please confirm.'
            );
        }
    }

    /**
     * Validate phone number uniqueness (soft validation)
     */
    private function validatePhoneUniqueness($validator): void
    {
        $phone = $this->get('phone');
        
        if ($phone) {
            $existingCustomer = \App\Models\Customer::where('phone', $phone)
                ->where('company_id', auth()->user()->company_id)
                ->first();
                
            if ($existingCustomer) {
                $validator->errors()->add('phone', 
                    'මෙම දුරකථන අංකය දැනටමත් වෙනත් පාරිභෝගිකයෙකු භාවිතා කරයි. / This phone number is already used by another customer.'
                );
            }
        }
    }

    /**
     * Get the error messages for the defined validation rules.
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        // Log validation failures for debugging
        \Log::info('Customer creation validation failed', [
            'errors' => $validator->errors()->toArray(),
            'input' => $this->except(['password', 'password_confirmation']),
            'user_id' => auth()->id(),
            'company_id' => auth()->user()->company_id ?? null,
        ]);

        if ($this->expectsJson()) {
            $response = response()->json([
                'success' => false,
                'message' => 'Customer validation failed / පාරිභෝගික වලංගුකරණය අසාර්ථකයි',
                'errors' => $validator->errors(),
                'error_count' => $validator->errors()->count(),
                'suggestions' => $this->getValidationSuggestions($validator->errors()),
            ], 422);

            throw new \Illuminate\Validation\ValidationException($validator, $response);
        }

        parent::failedValidation($validator);
    }

    /**
     * Get validation suggestions based on common errors
     */
    private function getValidationSuggestions($errors): array
    {
        $suggestions = [];
        
        if ($errors->has('phone')) {
            $suggestions[] = 'Sri Lankan mobile numbers: 0771234567 or +94771234567';
            $suggestions[] = 'International format: +1234567890';
        }
        
        if ($errors->has('email')) {
            $suggestions[] = 'Valid email format: example@domain.com';
        }
        
        if ($errors->has('postal_code')) {
            $suggestions[] = 'Sri Lankan postal codes are 5 digits: 10400, 80000';
        }
        
        if ($errors->has('customer_code')) {
            $suggestions[] = 'Customer codes are auto-generated or use format: CUS2407001';
        }
        
        if ($errors->has('date_of_birth')) {
            $suggestions[] = 'Date format: YYYY-MM-DD (e.g., 1990-05-15)';
            $suggestions[] = 'Customer must be at least 16 years old';
        }
        
        return $suggestions;
    }

    /**
     * Handle successful validation
     */
    public function passedValidation(): void
    {
        // Log successful validation for audit trail
        \Log::info('Customer creation validation passed', [
            'customer_code' => $this->get('customer_code'),
            'customer_type' => $this->get('customer_type'),
            'user_id' => auth()->id(),
            'company_id' => auth()->user()->company_id ?? null,
        ]);
    }
}