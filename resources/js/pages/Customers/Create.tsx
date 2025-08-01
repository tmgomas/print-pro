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
    AlertCircle
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Branch {
    value: number;
    label: string;
}

interface Props {
    branches: Branch[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
    { title: 'Create Customer', href: '/customers/create' },
];

export default function CreateCustomer({ branches }: Props) {
    const [showBusinessFields, setShowBusinessFields] = useState(false);
    const [shippingSameAsBilling, setShippingSameAsBilling] = useState(true);

    const { data, setData, post, processing, errors, reset } = useForm({
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
        
        // Business Information (conditional)
        company_name: '',
        contact_person: '',
        tax_number: '',
        
        // Financial Information
        credit_limit: '',
        
        // Personal Information (for individuals)
        date_of_birth: '',
        
        // Organization
        branch_id: '',
        
        // Additional Information
        notes: '',
        
        // Preferences
        communication_method: 'email',
        language: 'en',
        payment_terms: '30',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Copy billing address to shipping if same
        if (shippingSameAsBilling) {
            setData('shipping_address', data.billing_address);
        }
        
        console.log('Form submitted with data:', data);
        
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
        
        // Clear business fields if switching to individual
        if (type === 'individual') {
            setData({
                ...data,
                customer_type: type,
                company_name: '',
                contact_person: '',
                tax_number: '',
            });
        }
    };

    // Sri Lankan provinces
    const provinces = [
        { value: 'western', label: 'Western Province' },
        { value: 'central', label: 'Central Province' },
        { value: 'southern', label: 'Southern Province' },
        { value: 'northern', label: 'Northern Province' },
        { value: 'eastern', label: 'Eastern Province' },
        { value: 'north_western', label: 'North Western Province' },
        { value: 'north_central', label: 'North Central Province' },
        { value: 'uva', label: 'Uva Province' },
        { value: 'sabaragamuwa', label: 'Sabaragamuwa Province' },
    ];

    // Sri Lankan districts (common ones)
    const districts = [
        'Colombo', 'Gampaha', 'Kalutara', 'Kandy', 'Matale', 'Nuwara Eliya',
        'Galle', 'Matara', 'Hambantota', 'Jaffna', 'Kilinochchi', 'Mannar',
        'Vavuniya', 'Mullaitivu', 'Batticaloa', 'Ampara', 'Trincomalee',
        'Kurunegala', 'Puttalam', 'Anuradhapura', 'Polonnaruwa', 'Badulla',
        'Moneragala', 'Ratnapura', 'Kegalle'
    ];

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
                    <div>
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">
                                            {data.customer_type === 'business' ? 'Contact Person Name' : 'Full Name'} *
                                        </Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            placeholder={data.customer_type === 'business' ? 'Enter contact person name' : 'Enter full name'}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    {/* Business Fields */}
                                    {showBusinessFields && (
                                        <>
                                            <div className="grid gap-2">
                                                <Label htmlFor="company_name">Company Name *</Label>
                                                <Input
                                                    id="company_name"
                                                    type="text"
                                                    value={data.company_name}
                                                    onChange={(e) => setData('company_name', e.target.value)}
                                                    placeholder="Enter company name"
                                                    required={showBusinessFields}
                                                />
                                                <InputError message={errors.company_name} />
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
                                        </>
                                    )}

                                    {/* Personal Information for Individuals */}
                                    {!showBusinessFields && (
                                        <div className="grid gap-2">
                                            <Label htmlFor="date_of_birth">Date of Birth</Label>
                                            <Input
                                                id="date_of_birth"
                                                type="date"
                                                value={data.date_of_birth}
                                                onChange={(e) => setData('date_of_birth', e.target.value)}
                                            />
                                            <InputError message={errors.date_of_birth} />
                                        </div>
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
                                            {provinces.map((province) => (
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

                        {/* Sidebar */}
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
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                            <option value="suspended">Suspended</option>
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

                                    <div className="grid gap-2">
                                        <Label htmlFor="payment_terms">Payment Terms (Days)</Label>
                                        <select
                                            id="payment_terms"
                                            value={data.payment_terms}
                                            onChange={(e) => setData('payment_terms', e.target.value)}
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
                                        <InputError message={errors.payment_terms} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Preferences */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings className="h-5 w-5" />
                                        Preferences
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="communication_method">Preferred Communication</Label>
                                        <select
                                            id="communication_method"
                                            value={data.communication_method}
                                            onChange={(e) => setData('communication_method', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="email">Email</option>
                                            <option value="sms">SMS</option>
                                            <option value="phone">Phone Call</option>
                                            <option value="whatsapp">WhatsApp</option>
                                        </select>
                                        <InputError message={errors.communication_method} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="language">Language Preference</Label>
                                        <select
                                            id="language"
                                            value={data.language}
                                            onChange={(e) => setData('language', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="en">English</option>
                                            <option value="si">Sinhala</option>
                                            <option value="ta">Tamil</option>
                                        </select>
                                        <InputError message={errors.language} />
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