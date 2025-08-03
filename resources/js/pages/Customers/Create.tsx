// resources/js/pages/Customers/Create.tsx - Part 1

import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { 
    ArrowLeft, 
    User, 
    Building2, 
    MapPin, 
    Phone, 
    Mail, 
    CreditCard,
    FileText,
    Calendar,
    Settings,
    AlertCircle,
    Users,
    Shield,
    UserCheck
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

// Interfaces
interface Branch {
    value: number;
    label: string;
}

interface FormOption {
    value: string;
    label: string;
}

interface FormOptions {
    customerTypes: FormOption[];
    statuses: FormOption[];
    provinces: FormOption[];
    emergencyContactRelationships: FormOption[];
}

interface Props {
    branches: Branch[];
    defaultBranchId?: number;
    formOptions: FormOptions;
}

interface CustomerFormData {
    name: string;
    email: string;
    phone: string;
    customer_type: string;
    status: string;
    billing_address: string;
    shipping_address: string;
    city: string;
    postal_code: string;
    district: string;
    province: string;
    company_name: string;
    company_registration: string;
    contact_person: string;
    contact_person_phone: string;
    contact_person_email: string;
    tax_number: string;
    credit_limit: string;
    date_of_birth: string;
    emergency_contact_name: string;
    emergency_contact_phone: string;
    emergency_contact_relationship: string;
    branch_id: string;
    notes: string;
    preferences: Record<string, any>;
}

// Constants
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
    { title: 'Create Customer', href: '/customers/create' },
];

const districts = [
    'Colombo', 'Gampaha', 'Kalutara', 'Kandy', 'Matale', 'Nuwara Eliya',
    'Galle', 'Matara', 'Hambantota', 'Jaffna', 'Kilinochchi', 'Mannar',
    'Vavuniya', 'Mullaitivu', 'Batticaloa', 'Ampara', 'Trincomalee',
    'Kurunegala', 'Puttalam', 'Anuradhapura', 'Polonnaruwa', 'Badulla',
    'Moneragala', 'Ratnapura', 'Kegalle'
];

// Component Start
export default function CreateCustomer({ branches, defaultBranchId, formOptions }: Props) {
    // State
    const [showBusinessFields, setShowBusinessFields] = useState(false);
    const [shippingSameAsBilling, setShippingSameAsBilling] = useState(true);

    // Form State
    const { data, setData, post, processing, errors, reset } = useForm<CustomerFormData>({
        // Basic Information
        name: '',
        email: '',
        phone: '',
        customer_type: 'individual',
        status: 'active',
        
        // Address Information
        billing_address: '',
        shipping_address: '',
        city: '',
        postal_code: '',
        district: '',
        province: '',
        
        // Business Information
        company_name: '',
        company_registration: '',
        contact_person: '',
        contact_person_phone: '',
        contact_person_email: '',
        tax_number: '',
        
        // Financial Information
        credit_limit: '0',
        
        // Personal Information
        date_of_birth: '',
        
        // Emergency Contact
        emergency_contact_name: '',
        emergency_contact_phone: '',
        emergency_contact_relationship: '',
        
        // Organization
        branch_id: defaultBranchId ? defaultBranchId.toString() : '',
        
        // Additional Information
        notes: '',
        
        // Preferences
        preferences: {
            communication_method: 'email',
            language: 'en',
            payment_terms: '30',
            newsletter: true,
            sms_notifications: true,
            email_notifications: true,
        },
    });

    // Event Handlers
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        const submitData = { ...data };
       
    
        if (shippingSameAsBilling) {
            submitData.shipping_address = data.billing_address;
        }
        
        console.log('Form submitted with data:', submitData);
        
        post('/customers', {
            onSuccess: () => {
                console.log('✓ Customer created successfully!');
            },
            onError: (errors) => {
                console.log('✗ Validation errors:', errors);
            }
        });
    };

    const handleCustomerTypeChange = (type: string) => {
        setData('customer_type', type);
        setShowBusinessFields(type === 'business');
        
        if (type === 'individual') {
            setData(prev => ({
                ...prev,
                customer_type: type,
                company_name: '',
                company_registration: '',
                contact_person: '',
                contact_person_phone: '',
                contact_person_email: '',
                tax_number: '',
            }));
        } else {
            setData(prev => ({
                ...prev,
                customer_type: type,
                date_of_birth: '',
                emergency_contact_name: '',
                emergency_contact_phone: '',
                emergency_contact_relationship: '',
            }));
        }
    };

    const updatePreference = (key: string, value: any) => {
        setData('preferences', {
            ...data.preferences,
            [key]: value
        });
    };

    // JSX Return starts in Part 2...

    // Part 2: Main Form Content - continues from Part 1
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Customer" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Button variant="outline" asChild>
                        <Link href="/customers">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Customers
                        </Link>
                    </Button>
                    <div className="text-right">
                        <h1 className="text-3xl font-bold tracking-tight">Create New Customer</h1>
                        <p className="text-muted-foreground">Add a new customer to your database</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Form */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <User className="h-5 w-5" />
                                        Basic Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Customer Type */}
                                    <div className="grid gap-2">
                                        <Label>Customer Type *</Label>
                                        <div className="flex gap-4">
                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="radio"
                                                    name="customer_type"
                                                    value="individual"
                                                    checked={data.customer_type === 'individual'}
                                                    onChange={(e) => handleCustomerTypeChange(e.target.value)}
                                                    className="text-primary"
                                                />
                                                <span>Individual</span>
                                            </label>
                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="radio"
                                                    name="customer_type"
                                                    value="business"
                                                    checked={data.customer_type === 'business'}
                                                    onChange={(e) => handleCustomerTypeChange(e.target.value)}
                                                    className="text-primary"
                                                />
                                                <span>Business</span>
                                            </label>
                                        </div>
                                        <InputError message={errors.customer_type} />
                                    </div>

                                    {/* Name Field */}
                                   
                                    {/* Business Fields */}
                                    {showBusinessFields && (
                                        <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="contact_person">Contact Person Name *</Label>
                                            <Input
                                                id="contact_person"
                                                value={data.contact_person}
                                                onChange={(e) => setData('contact_person', e.target.value)}
                                                placeholder="Enter contact person name"
                                                required
                                            />
                                            <InputError message={errors.contact_person} />
                                        </div>
                                            <div className="grid gap-2">
            <Label htmlFor="company_name">Company Name *</Label>
            <Input
                id="company_name"
                type="text"
                value={data.company_name}
                onChange={(e) => {
                    setData('company_name', e.target.value);
                    setData('name', e.target.value); // Auto-sync to name field
                }}
                placeholder="Enter company name"
                required={showBusinessFields}
            />
            <InputError message={errors.company_name} />
        </div>

                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="company_registration">Company Registration No</Label>
                                                    <Input
                                                        id="company_registration"
                                                        type="text"
                                                        value={data.company_registration}
                                                        onChange={(e) => setData('company_registration', e.target.value)}
                                                        placeholder="Enter registration number"
                                                    />
                                                    <InputError message={errors.company_registration} />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="tax_number">Tax Number / VAT Number</Label>
                                                    <Input
                                                        id="tax_number"
                                                        type="text"
                                                        value={data.tax_number}
                                                        onChange={(e) => setData('tax_number', e.target.value)}
                                                        placeholder="Enter tax/VAT number"
                                                    />
                                                    <InputError message={errors.tax_number} />
                                                </div>
                                            </div>

                                            {/* Business Contact Information */}
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="contact_person_phone">Contact Person Phone</Label>
                                                    <Input
                                                        id="contact_person_phone"
                                                        type="tel"
                                                        value={data.contact_person_phone}
                                                        onChange={(e) => setData('contact_person_phone', e.target.value)}
                                                        placeholder="+94 77 123 4567"
                                                    />
                                                    <InputError message={errors.contact_person_phone} />
                                                </div>

                                                <div className="grid gap-2">
                                                    <Label htmlFor="contact_person_email">Contact Person Email</Label>
                                                    <Input
                                                        id="contact_person_email"
                                                        type="email"
                                                        value={data.contact_person_email}
                                                        onChange={(e) => setData('contact_person_email', e.target.value)}
                                                        placeholder="Enter contact person email"
                                                    />
                                                    <InputError message={errors.contact_person_email} />
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    {/* Personal Information for Individuals */}
                                    {!showBusinessFields && (

                                        <><div className="grid gap-2">
                                            <Label htmlFor="name">Full Name *</Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder="Enter full name"
                                                required />
                                            <InputError message={errors.name} />
                                        </div>
                                        <div className="grid gap-2">
                                                <Label htmlFor="date_of_birth">Date of Birth</Label>
                                                <Input
                                                    id="date_of_birth"
                                                    type="date"
                                                    value={data.date_of_birth}
                                                    onChange={(e) => setData('date_of_birth', e.target.value)} />
                                                <InputError message={errors.date_of_birth} />
                                            </div></>
                                        
                                    )}

                                    {/* Contact Information */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="email">Email Address</Label>
                                            <Input
                                                id="email"
                                                type="email"
                                                value={data.email}
                                                onChange={(e) => setData('email', e.target.value)}
                                                placeholder="Enter email address"
                                            />
                                            <InputError message={errors.email} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">Phone Number *</Label>
                                            <Input
                                                id="phone"
                                                type="tel"
                                                value={data.phone}
                                                onChange={(e) => setData('phone', e.target.value)}
                                                placeholder="+94 77 123 4567"
                                                required
                                            />
                                            <InputError message={errors.phone} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Emergency Contact (Individual Only) */}
                            {!showBusinessFields && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <UserCheck className="h-5 w-5" />
                                            Emergency Contact
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div className="grid gap-2">
                                                <Label htmlFor="emergency_contact_name">Emergency Contact Name</Label>
                                                <Input
                                                    id="emergency_contact_name"
                                                    type="text"
                                                    value={data.emergency_contact_name}
                                                    onChange={(e) => setData('emergency_contact_name', e.target.value)}
                                                    placeholder="Enter emergency contact name"
                                                />
                                                <InputError message={errors.emergency_contact_name} />
                                            </div>

                                            <div className="grid gap-2">
                                                <Label htmlFor="emergency_contact_phone">Emergency Contact Phone</Label>
                                                <Input
                                                    id="emergency_contact_phone"
                                                    type="tel"
                                                    value={data.emergency_contact_phone}
                                                    onChange={(e) => setData('emergency_contact_phone', e.target.value)}
                                                    placeholder="+94 77 123 4567"
                                                />
                                                <InputError message={errors.emergency_contact_phone} />
                                            </div>
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="emergency_contact_relationship">Relationship</Label>
                                            <select
                                                id="emergency_contact_relationship"
                                                value={data.emergency_contact_relationship}
                                                onChange={(e) => setData('emergency_contact_relationship', e.target.value)}
                                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                            >
                                                <option value="">Select Relationship</option>
                                                {formOptions.emergencyContactRelationships.map((relationship) => (
                                                    <option key={relationship.value} value={relationship.value}>
                                                        {relationship.label}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.emergency_contact_relationship} />
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Address Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin className="h-5 w-5" />
                                        Address Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Billing Address */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="billing_address">Billing Address *</Label>
                                        <Textarea
                                            id="billing_address"
                                            value={data.billing_address}
                                            onChange={(e) => setData('billing_address', e.target.value)}
                                            placeholder="Enter complete billing address"
                                            rows={3}
                                            required
                                        />
                                        <InputError message={errors.billing_address} />
                                    </div>

                                    {/* Shipping Address */}
                                    <div className="grid gap-2">
                                        <div className="flex items-center justify-between">
                                            <Label htmlFor="shipping_address">Shipping Address</Label>
                                            <label className="flex items-center space-x-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={shippingSameAsBilling}
                                                    onChange={(e) => setShippingSameAsBilling(e.target.checked)}
                                                    className="text-primary"
                                                />
                                                <span>Same as billing address</span>
                                            </label>
                                        </div>
                                        {!shippingSameAsBilling && (
                                            <Textarea
                                                id="shipping_address"
                                                value={data.shipping_address}
                                                onChange={(e) => setData('shipping_address', e.target.value)}
                                                placeholder="Enter shipping address"
                                                rows={3}
                                            />
                                        )}
                                        <InputError message={errors.shipping_address} />
                                    </div>

                                    {/* Location Details */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="city">City *</Label>
                                            <Input
                                                id="city"
                                                type="text"
                                                value={data.city}
                                                onChange={(e) => setData('city', e.target.value)}
                                                placeholder="Enter city"
                                                required
                                            />
                                            <InputError message={errors.city} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="district">District</Label>
                                            <select
                                                id="district"
                                                value={data.district}
                                                onChange={(e) => setData('district', e.target.value)}
                                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                            >
                                                <option value="">Select District</option>
                                                {districts.map((district) => (
                                                    <option key={district} value={district}>
                                                        {district}
                                                    </option>
                                                ))}
                                            </select>
                                            <InputError message={errors.district} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="postal_code">Postal Code</Label>
                                            <Input
                                                id="postal_code"
                                                type="text"
                                                value={data.postal_code}
                                                onChange={(e) => setData('postal_code', e.target.value)}
                                                placeholder="Enter postal code"
                                                maxLength={5}
                                            />
                                            <InputError message={errors.postal_code} />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="province">Province</Label>
                                        <select
                                            id="province"
                                            value={data.province}
                                            onChange={(e) => setData('province', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="">Select Province</option>
                                            {formOptions.provinces.map((province) => (
                                                <option key={province.value} value={province.value}>
                                                    {province.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.province} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Additional Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Additional Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">Notes & Comments</Label>
                                        <Textarea
                                            id="notes"
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                            placeholder="Add any additional notes about this customer..."
                                            rows={4}
                                        />
                                        <InputError message={errors.notes} />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Part 3 continues with Sidebar... */}

                        {/* Sidebar - Part 3 continues from Part 2 */}
                        <div className="space-y-6">
                            {/* Organization */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        Organization
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="branch_id">Branch</Label>
                                        <select
                                            id="branch_id"
                                            value={data.branch_id}
                                            onChange={(e) => setData('branch_id', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="">Select Branch</option>
                                            {branches.map((branch) => (
                                                <option key={branch.value} value={branch.value}>
                                                    {branch.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.branch_id} />
                                        <p className="text-xs text-muted-foreground">
                                            Assign customer to a specific branch
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <select
                                            id="status"
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            {formOptions.statuses.map((status) => (
                                                <option key={status.value} value={status.value}>
                                                    {status.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Financial Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="h-5 w-5" />
                                        Financial Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="credit_limit">Credit Limit (LKR)</Label>
                                        <Input
                                            id="credit_limit"
                                            type="number"
                                            value={data.credit_limit}
                                            onChange={(e) => setData('credit_limit', e.target.value)}
                                            placeholder="0.00"
                                            min="0"
                                            step="0.01"
                                        />
                                        <InputError message={errors.credit_limit} />
                                        <p className="text-xs text-muted-foreground">
                                            Maximum credit amount allowed for this customer
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Preferences */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings className="h-5 w-5" />
                                        Customer Preferences
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="communication_method">Preferred Communication</Label>
                                        <select
                                            id="communication_method"
                                            value={data.preferences.communication_method}
                                            onChange={(e) => updatePreference('communication_method', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="email">Email</option>
                                            <option value="sms">SMS</option>
                                            <option value="phone">Phone Call</option>
                                            <option value="whatsapp">WhatsApp</option>
                                        </select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="language">Language Preference</Label>
                                        <select
                                            id="language"
                                            value={data.preferences.language}
                                            onChange={(e) => updatePreference('language', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="en">English</option>
                                            <option value="si">Sinhala</option>
                                            <option value="ta">Tamil</option>
                                        </select>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="payment_terms">Payment Terms (Days)</Label>
                                        <select
                                            id="payment_terms"
                                            value={data.preferences.payment_terms}
                                            onChange={(e) => updatePreference('payment_terms', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="0">Cash Only</option>
                                            <option value="7">7 Days</option>
                                            <option value="15">15 Days</option>
                                            <option value="30">30 Days</option>
                                            <option value="45">45 Days</option>
                                            <option value="60">60 Days</option>
                                            <option value="90">90 Days</option>
                                        </select>
                                    </div>

                                    {/* Notification Preferences */}
                                    <div className="space-y-3">
                                        <Label>Notification Preferences</Label>
                                        
                                        <div className="space-y-2">
                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    checked={data.preferences.email_notifications}
                                                    onChange={(e) => updatePreference('email_notifications', e.target.checked)}
                                                    className="text-primary"
                                                />
                                                <span className="text-sm">Email Notifications</span>
                                            </label>

                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    checked={data.preferences.sms_notifications}
                                                    onChange={(e) => updatePreference('sms_notifications', e.target.checked)}
                                                    className="text-primary"
                                                />
                                                <span className="text-sm">SMS Notifications</span>
                                            </label>

                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="checkbox"
                                                    checked={data.preferences.newsletter}
                                                    onChange={(e) => updatePreference('newsletter', e.target.checked)}
                                                    className="text-primary"
                                                />
                                                <span className="text-sm">Newsletter Subscription</span>
                                            </label>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Action Buttons */}
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex flex-col gap-3">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            {processing ? 'Creating Customer...' : 'Create Customer'}
                                        </Button>
                                        
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => reset()}
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            Reset Form
                                        </Button>

                                        <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                            <AlertCircle className="h-3 w-3" />
                                            <span>Fields marked with * are required</span>
                                        </div>

                                        {/* Helpful Tips */}
                                        <div className="mt-4 p-3 bg-muted rounded-lg">
                                            <h4 className="text-sm font-medium mb-2">Quick Tips:</h4>
                                            <ul className="text-xs text-muted-foreground space-y-1">
                                                <li>• Phone numbers can be in local (+94) or international format</li>
                                                <li>• Emergency contact is required for individual customers only</li>
                                                <li>• Business customers require company name and contact person</li>
                                                <li>• Postal code should be 5 digits for Sri Lankan addresses</li>
                                                <li>• Credit limit can be set to 0 for cash-only customers</li>
                                                <li>• Preferences can be updated later from customer profile</li>
                                            </ul>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

// End of Component