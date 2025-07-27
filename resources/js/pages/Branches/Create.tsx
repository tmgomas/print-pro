import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { ArrowLeft, Building2 } from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Props {
    companies?: Array<{ value: string; label: string }>;
    defaultCompanyId?: string | number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Branches', href: '/branches' },
    { title: 'Create Branch', href: '/branches/create' },
];

export default function CreateBranch({ companies = [], defaultCompanyId }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        code: '',
        company_id: defaultCompanyId || '',
        address: '',
        city: '',
        postal_code: '',
        phone: '',
        email: '',
        is_main_branch: false,
        status: 'active',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/branches', {
            onSuccess: () => {
                // Will be redirected by controller
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Branch" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/branches">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Branches
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Create New Branch</h1>
                        <p className="text-muted-foreground">Add a new branch to your company</p>
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
                                        <Building2 className="h-5 w-5" />
                                        Basic Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="company_id">Company *</Label>
                                            <select
                                                id="company_id"
                                                value={data.company_id}
                                                onChange={(e) => setData('company_id', e.target.value)}
                                                className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            >
                                                <option value="">Select Company</option>
                                                {companies && companies.length > 0 ? companies.map((company) => (
                                                    <option key={company.value} value={company.value}>
                                                        {company.label}
                                                    </option>
                                                )) : (
                                                    <option value="" disabled>No companies available</option>
                                                )}
                                            </select>
                                            <InputError message={errors.company_id} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="code">Branch Code *</Label>
                                            <Input
                                                id="code"
                                                type="text"
                                                value={data.code}
                                                onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                                placeholder="e.g., COL001, KAN002"
                                                className="uppercase"
                                            />
                                            <InputError message={errors.code} />
                                            <p className="text-xs text-muted-foreground">
                                                Unique branch code for identification and invoice numbering
                                            </p>
                                        </div>

                                        <div className="grid gap-2 md:col-span-2">
                                            <Label htmlFor="name">Branch Name *</Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder="Enter branch name"
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Contact Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Contact Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="address">Address</Label>
                                        <Textarea
                                            id="address"
                                            value={data.address}
                                            onChange={(e) => setData('address', e.target.value)}
                                            placeholder="Enter full address"
                                            rows={3}
                                        />
                                        <InputError message={errors.address} />
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="city">City</Label>
                                            <Input
                                                id="city"
                                                type="text"
                                                value={data.city}
                                                onChange={(e) => setData('city', e.target.value)}
                                                placeholder="Enter city"
                                            />
                                            <InputError message={errors.city} />
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

                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">Phone Number</Label>
                                            <Input
                                                id="phone"
                                                type="tel"
                                                value={data.phone}
                                                onChange={(e) => setData('phone', e.target.value)}
                                                placeholder="Enter phone number"
                                            />
                                            <InputError message={errors.phone} />
                                        </div>

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
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Branch Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Branch Settings</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="is_main_branch">Main Branch</Label>
                                        <select
                                            id="is_main_branch"
                                            value={data.is_main_branch ? 'true' : 'false'}
                                            onChange={(e) => setData('is_main_branch', e.target.value === 'true')}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="false">Regular Branch</option>
                                            <option value="true">Main Branch</option>
                                        </select>
                                        <p className="text-sm text-muted-foreground">
                                            Set as the primary branch for this company
                                        </p>
                                        <InputError message={errors.is_main_branch} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="status">Status</Label>
                                        <select
                                            id="status"
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Form Actions */}
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="space-y-4">
                                        <Button 
                                            type="submit" 
                                            className="w-full"
                                            disabled={processing}
                                        >
                                            {processing ? 'Creating Branch...' : 'Create Branch'}
                                        </Button>
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            className="w-full"
                                            onClick={() => reset()}
                                            disabled={processing}
                                        >
                                            Reset Form
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Help Text */}
                            <Card className="bg-blue-50 border-blue-200">
                                <CardContent className="pt-6">
                                    <div className="space-y-2">
                                        <h4 className="font-medium text-blue-900">Branch Code Guidelines</h4>
                                        <ul className="text-sm text-blue-700 space-y-1">
                                            <li>• Use 3-letter city code + 3-digit number</li>
                                            <li>• Example: COL001, KAN002, GAL001</li>
                                            <li>• Must be unique across all branches</li>
                                            <li>• Used for invoice number generation</li>
                                        </ul>
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