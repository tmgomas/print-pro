import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import InputError from '@/components/input-error';
import { ArrowLeft, Building2, MapPin, Phone, Mail, User } from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Company {
    value: number;
    label: string;
}

interface Props {
    companies: Company[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Branches', href: '/branches' },
    { title: 'Create Branch', href: '/branches/create' },
];

// Corrected Create.tsx (resources/js/pages/Branches/Create.tsx)

export default function CreateBranch({ companies }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm({
        company_id: '',
        name: '',
        code: '',
        address: '',
        phone: '',
        email: '',
        // manager_name: '', // REMOVED: DB එකේ නෑ
        latitude: '',
        longitude: '',
        is_main_branch: false,
        status: 'active',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Debug logging
        console.log('Form submitted with data:', data);
        console.log('Required fields check:');
        console.log('- company_id:', data.company_id ? '✓' : '✗ MISSING');
        console.log('- name:', data.name ? '✓' : '✗ MISSING');
        console.log('- code:', data.code ? '✓' : '✗ MISSING');
        
        post('/branches', {
            onSuccess: () => {
                console.log('✓ Branch created successfully!');
            },
            onError: (errors) => {
                console.log('✗ Validation errors:', errors);
            }
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Branch" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Button variant="outline" asChild>
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
                                    <div className="grid gap-2">
                                        <Label htmlFor="company_id">Company *</Label>
                                        <select
                                            id="company_id"
                                            value={data.company_id}
                                            onChange={(e) => setData('company_id', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                            required
                                        >
                                            <option value="">Select a company</option>
                                            {companies.map((company) => (
                                                <option key={company.value} value={company.value}>
                                                    {company.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.company_id} />
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="name">Branch Name *</Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder="Enter branch name"
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="code">Branch Code *</Label>
                                            <Input
                                                id="code"
                                                type="text"
                                                value={data.code}
                                                onChange={(e) => setData('code', e.target.value)}
                                                placeholder="e.g., BR001"
                                                required
                                            />
                                            <InputError message={errors.code} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Contact Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Phone className="h-5 w-5" />
                                        Contact Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="address">Address</Label>
                                        <Textarea
                                            id="address"
                                            value={data.address}
                                            onChange={(e) => setData('address', e.target.value)}
                                            placeholder="Enter branch address"
                                            rows={3}
                                        />
                                        <InputError message={errors.address} />
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="phone">Phone</Label>
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
                                            <Label htmlFor="email">Email</Label>
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

                            {/* Location Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin className="h-5 w-5" />
                                        Location (Optional)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="latitude">Latitude</Label>
                                            <Input
                                                id="latitude"
                                                type="number"
                                                step="any"
                                                value={data.latitude}
                                                onChange={(e) => setData('latitude', e.target.value)}
                                                placeholder="e.g., 6.9271"
                                            />
                                            <InputError message={errors.latitude} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="longitude">Longitude</Label>
                                            <Input
                                                id="longitude"
                                                type="number"
                                                step="any"
                                                value={data.longitude}
                                                onChange={(e) => setData('longitude', e.target.value)}
                                                placeholder="e.g., 79.8612"
                                            />
                                            <InputError message={errors.longitude} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Settings</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label htmlFor="is_main_branch">Branch Type</Label>
                                        <select
                                            id="is_main_branch"
                                            value={data.is_main_branch ? 'true' : 'false'}
                                            onChange={(e) => setData('is_main_branch', e.target.value === 'true')}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm"
                                        >
                                            <option value="false">Regular Branch</option>
                                            <option value="true">Main Branch</option>
                                        </select>
                                        <InputError message={errors.is_main_branch} />
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
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Submit Actions */}
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

                            {/* Help Card */}
                            <Card className="bg-blue-50 border-blue-200">
                                <CardHeader>
                                    <CardTitle className="text-blue-900">Need Help?</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2 text-sm text-blue-800">
                                        <p>• Branch codes should be unique and follow format: ABC001</p>
                                        <p>• Only one main branch is allowed per company</p>
                                        <p>• GPS coordinates help with delivery services</p>
                                        <p>• Contact information is used for customer communications</p>
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