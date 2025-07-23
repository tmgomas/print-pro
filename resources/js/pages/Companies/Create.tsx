import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { ArrowLeft, Upload, X } from 'lucide-react';
import { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Companies', href: '/companies' },
    { title: 'Create Company', href: '/companies/create' },
];

export default function CreateCompany() {
    const [logoPreview, setLogoPreview] = useState<string | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        registration_number: '',
        email: '',
        phone: '',
        address: '',
        tax_number: '',
        tax_rate: 0,
        logo: null as File | null,
        // Main branch details
        branch_name: '',
        branch_code: '',
        // Bank details
        bank_name: '',
        account_number: '',
        account_holder: '',
        swift_code: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/companies', {
            forceFormData: true,
            onSuccess: () => {
                // Will be redirected by controller
            },
        });
    };

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('logo', file);
            const reader = new FileReader();
            reader.onload = () => setLogoPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const removeLogo = () => {
        setData('logo', null);
        setLogoPreview(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Company" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/companies">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Create New Company</h1>
                        <p className="text-muted-foreground">Add a new company to the system</p>
                    </div>
                </div>

                {/* Form */}
                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Company Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Company Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {/* Logo Upload */}
                                <div className="space-y-2">
                                    <Label>Company Logo</Label>
                                    <div className="flex items-center gap-4">
                                        {logoPreview ? (
                                            <div className="relative">
                                                <img
                                                    src={logoPreview}
                                                    alt="Logo preview"
                                                    className="h-16 w-16 object-cover rounded-lg border"
                                                />
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="sm"
                                                    className="absolute -top-2 -right-2 h-6 w-6 rounded-full p-0"
                                                    onClick={removeLogo}
                                                >
                                                    <X className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        ) : (
                                            <div className="h-16 w-16 border-2 border-dashed border-muted-foreground/25 rounded-lg flex items-center justify-center">
                                                <Upload className="h-6 w-6 text-muted-foreground" />
                                            </div>
                                        )}
                                        <div>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={handleLogoChange}
                                                className="hidden"
                                                id="logo-upload"
                                            />
                                            <Label htmlFor="logo-upload" className="cursor-pointer">
                                                <Button type="button" variant="outline" size="sm" asChild>
                                                    <span>Choose Logo</span>
                                                </Button>
                                            </Label>
                                            <p className="text-xs text-muted-foreground mt-1">
                                                PNG, JPG up to 2MB
                                            </p>
                                        </div>
                                    </div>
                                    <InputError message={errors.logo} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Company Name *</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="registration_number">Registration Number</Label>
                                    <Input
                                        id="registration_number"
                                        type="text"
                                        value={data.registration_number}
                                        onChange={(e) => setData('registration_number', e.target.value)}
                                    />
                                    <InputError message={errors.registration_number} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    <InputError message={errors.email} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        type="tel"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="address">Address</Label>
                                    <Textarea
                                        id="address"
                                        value={data.address}
                                        onChange={(e: { target: { value: string; }; }) => setData('address', e.target.value)}
                                        rows={3}
                                    />
                                    <InputError message={errors.address} />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Tax & Main Branch Information */}
                        <div className="space-y-6">
                            {/* Tax Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Tax Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="tax_number">Tax Number</Label>
                                        <Input
                                            id="tax_number"
                                            type="text"
                                            value={data.tax_number}
                                            onChange={(e) => setData('tax_number', e.target.value)}
                                        />
                                        <InputError message={errors.tax_number} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="tax_rate">Tax Rate (%)</Label>
                                        <Input
                                            id="tax_rate"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            value={data.tax_rate}
                                            onChange={(e) => setData('tax_rate', parseFloat(e.target.value) || 0)}
                                        />
                                        <InputError message={errors.tax_rate} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Main Branch Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Main Branch Details</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="branch_name">Branch Name</Label>
                                        <Input
                                            id="branch_name"
                                            type="text"
                                            value={data.branch_name}
                                            onChange={(e) => setData('branch_name', e.target.value)}
                                            placeholder="Main Branch"
                                        />
                                        <InputError message={errors.branch_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="branch_code">Branch Code</Label>
                                        <Input
                                            id="branch_code"
                                            type="text"
                                            value={data.branch_code}
                                            onChange={(e) => setData('branch_code', e.target.value)}
                                            placeholder="MAIN"
                                        />
                                        <InputError message={errors.branch_code} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Bank Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Bank Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="bank_name">Bank Name</Label>
                                        <Input
                                            id="bank_name"
                                            type="text"
                                            value={data.bank_name}
                                            onChange={(e) => setData('bank_name', e.target.value)}
                                        />
                                        <InputError message={errors.bank_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="account_number">Account Number</Label>
                                        <Input
                                            id="account_number"
                                            type="text"
                                            value={data.account_number}
                                            onChange={(e) => setData('account_number', e.target.value)}
                                        />
                                        <InputError message={errors.account_number} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="account_holder">Account Holder Name</Label>
                                        <Input
                                            id="account_holder"
                                            type="text"
                                            value={data.account_holder}
                                            onChange={(e) => setData('account_holder', e.target.value)}
                                        />
                                        <InputError message={errors.account_holder} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="swift_code">SWIFT Code</Label>
                                        <Input
                                            id="swift_code"
                                            type="text"
                                            value={data.swift_code}
                                            onChange={(e) => setData('swift_code', e.target.value)}
                                        />
                                        <InputError message={errors.swift_code} />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Form Actions */}
                    <div className="flex items-center justify-end gap-4">
                        <Button type="button" variant="outline" asChild>
                            <Link href="/companies">Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating...' : 'Create Company'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}