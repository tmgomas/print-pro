import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
// Switch component not available, using checkbox instead
import InputError from '@/components/input-error';
import { ArrowLeft, Building2, AlertTriangle, MapPin, Phone, Mail } from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Branch {
    id: number;
    name: string;
    code: string;
    company_id: number;
    address?: string;
    city?: string;
    postal_code?: string;
    phone?: string;
    email?: string;
    latitude?: string;
    longitude?: string;
    is_main_branch: boolean;
    status: string;
    company: {
        id: number;
        name: string;
        logo_url?: string;
    };
}

interface Props {
    branch: Branch;
    companies?: Array<{ value: string; label: string }>;
}

export default function EditBranch({ branch, companies = [] }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Branches', href: '/branches' },
        { title: branch.name, href: `/branches/${branch.id}` },
        { title: 'Edit', href: `/branches/${branch.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: branch.name,
        code: branch.code,
        company_id: branch.company_id.toString(),
        address: branch.address || '',
        city: branch.city || '',
        postal_code: branch.postal_code || '',
        phone: branch.phone || '',
        email: branch.email || '',
        latitude: branch.latitude || '',
        longitude: branch.longitude || '',
        is_main_branch: branch.is_main_branch,
        status: branch.status,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Convert company_id back to number for submission
        const submitData = {
            ...data,
            company_id: parseInt(data.company_id),
        };

        put(`/branches/${branch.id}`, {
            data: submitData,
            onSuccess: () => {
                // Will be redirected by controller
            },
            onError: (errors) => {
                console.error('Update failed:', errors);
            }
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${branch.name}`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/branches/${branch.id}`}>
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Branch
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Edit Branch</h1>
                        <p className="text-muted-foreground">Update branch information and settings</p>
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
                                                required
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
                                                required
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
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Contact Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin className="h-5 w-5" />
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
                                            <Label htmlFor="phone">
                                                <Phone className="h-4 w-4 inline mr-1" />
                                                Phone Number
                                            </Label>
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
                                            <Label htmlFor="email">
                                                <Mail className="h-4 w-4 inline mr-1" />
                                                Email Address
                                            </Label>
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

                            {/* Location Coordinates */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin className="h-5 w-5" />
                                        Location Coordinates
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="latitude">Latitude</Label>
                                            <Input
                                                id="latitude"
                                                type="text"
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
                                                type="text"
                                                value={data.longitude}
                                                onChange={(e) => setData('longitude', e.target.value)}
                                                placeholder="e.g., 79.8612"
                                            />
                                            <InputError message={errors.longitude} />
                                        </div>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Optional: GPS coordinates for delivery and location services
                                    </p>
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

                                    {data.is_main_branch && (
                                        <div className="bg-yellow-50 border border-yellow-200 rounded-md p-3">
                                            <div className="flex">
                                                <AlertTriangle className="h-5 w-5 text-yellow-400" />
                                                <div className="ml-2">
                                                    <h3 className="text-sm font-medium text-yellow-800">
                                                        Main Branch
                                                    </h3>
                                                    <p className="text-sm text-yellow-700">
                                                        This will become the primary branch for the company. Only one main branch is allowed per company.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    )}

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

                            {/* Current Branch Info */}
                            <Card className="bg-blue-50 border-blue-200">
                                <CardHeader>
                                    <CardTitle className="text-blue-900">Current Branch</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between">
                                            <span className="text-blue-700">Company:</span>
                                            <span className="text-blue-900 font-medium">{branch.company.name}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-700">Current Code:</span>
                                            <span className="text-blue-900 font-medium">{branch.code}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-700">Type:</span>
                                            <span className="text-blue-900 font-medium">
                                                {branch.is_main_branch ? 'Main Branch' : 'Regular Branch'}
                                            </span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-blue-700">Status:</span>
                                            <span className="text-blue-900 font-medium capitalize">{branch.status}</span>
                                        </div>
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
                                            {processing ? 'Updating Branch...' : 'Update Branch'}
                                        </Button>
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            className="w-full"
                                            asChild
                                        >
                                            <Link href={`/branches/${branch.id}`}>
                                                Cancel Changes
                                            </Link>
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Help Text */}
                            <Card className="bg-gray-50 border-gray-200">
                                <CardContent className="pt-6">
                                    <div className="space-y-2">
                                        <h4 className="font-medium text-gray-900">Important Notes</h4>
                                        <ul className="text-sm text-gray-700 space-y-1">
                                            <li>• Changing the branch code may affect invoice numbering</li>
                                            <li>• Only one main branch is allowed per company</li>
                                            <li>• Inactive branches cannot process new orders</li>
                                            <li>• Users will retain access to historical data</li>
                                            <li>• GPS coordinates help with delivery routing</li>
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