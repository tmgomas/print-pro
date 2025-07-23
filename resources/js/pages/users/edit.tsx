import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { ArrowLeft } from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone?: string;
    status: string;
    company_id: number;
    branch_id?: number;
    current_role?: string;
    avatar_url?: string;
}

interface Props {
    user: User;
    companies: Array<{ value: string; label: string }>;
    roles: Array<{ value: string; label: string }>;
    branches: Array<{ value: string; label: string }>;
}

export default function EditUser({ user, companies, roles, branches }: Props) {
    const [avatarPreview, setAvatarPreview] = useState<string | null>(user.avatar_url || null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.first_name + ' ' + user.last_name, href: `/users/${user.id}` },
        { title: 'Edit', href: `/users/${user.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm({
        first_name: user.first_name,
        last_name: user.last_name,
        email: user.email,
        phone: user.phone || '',
        company_id: user.company_id,
        branch_id: user.branch_id || '',
        role: user.current_role || '',
        status: user.status,
        avatar: null as File | null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/users/${user.id}`, {
            forceFormData: true,
        });
    };

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('avatar', file);
            const reader = new FileReader();
            reader.onload = () => setAvatarPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const availableBranches = branches.filter(branch => 
        !data.company_id || branch.company_id == data.company_id
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${user.first_name} ${user.last_name}`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={`/users/${user.id}`}>
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Edit User</h1>
                        <p className="text-muted-foreground">Update user information</p>
                    </div>
                </div>

                {/* Form */}
                <Card className="max-w-4xl">
                    <CardHeader>
                        <CardTitle>User Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleSubmit} className="space-y-6">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                {/* Personal Information */}
                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium">Personal Information</h3>
                                    
                                    <div className="grid gap-2">
                                        <Label htmlFor="first_name">First Name *</Label>
                                        <Input
                                            id="first_name"
                                            type="text"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.first_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="last_name">Last Name *</Label>
                                        <Input
                                            id="last_name"
                                            type="text"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.last_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email Address *</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            required
                                        />
                                        <InputError message={errors.email} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="phone">Phone Number</Label>
                                        <Input
                                            id="phone"
                                            type="tel"
                                            value={data.phone}
                                            onChange={(e) => setData('phone', e.target.value)}
                                        />
                                        <InputError message={errors.phone} />
                                    </div>
                                </div>

                                {/* Organization Information */}
                                <div className="space-y-4">
                                    <h3 className="text-lg font-medium">Organization</h3>
                                    
                                    <div className="grid gap-2">
                                        <Label htmlFor="company_id">Company *</Label>
                                        <select
                                            id="company_id"
                                            value={data.company_id}
                                            onChange={(e) => {
                                                setData('company_id', e.target.value);
                                                setData('branch_id', '');
                                            }}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            required
                                        >
                                            <option value="">Select Company</option>
                                            {companies.map((company) => (
                                                <option key={company.value} value={company.value}>
                                                    {company.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.company_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="branch_id">Branch</Label>
                                        <select
                                            id="branch_id"
                                            value={data.branch_id}
                                            onChange={(e) => setData('branch_id', e.target.value)}
                                            disabled={!availableBranches.length}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                        >
                                            <option value="">Select Branch</option>
                                            {availableBranches.map((branch) => (
                                                <option key={branch.value} value={branch.value}>
                                                    {branch.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.branch_id} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="role">Role *</Label>
                                        <select
                                            id="role"
                                            value={data.role}
                                            onChange={(e) => setData('role', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            required
                                        >
                                            <option value="">Select Role</option>
                                            {roles.map((role) => (
                                                <option key={role.value} value={role.value}>
                                                    {role.label}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError message={errors.role} />
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
                                            <option value="suspended">Suspended</option>
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>
                            </div>

                            {/* Avatar Section */}
                            <div className="border-t pt-6">
                                <h3 className="text-lg font-medium mb-4">Profile Picture</h3>
                                <div className="flex items-center gap-6">
                                    <div className="w-20 h-20 rounded-full bg-muted flex items-center justify-center overflow-hidden">
                                        {avatarPreview ? (
                                            <img src={avatarPreview} alt="Avatar preview" className="w-full h-full object-cover" />
                                        ) : (
                                            <span className="text-2xl text-muted-foreground">
                                                {data.first_name.charAt(0).toUpperCase()}
                                            </span>
                                        )}
                                    </div>
                                    <div>
                                        <input
                                            type="file"
                                            id="avatar"
                                            accept="image/*"
                                            onChange={handleAvatarChange}
                                            className="hidden"
                                        />
                                        <Button 
                                            type="button" 
                                            variant="outline"
                                            onClick={() => document.getElementById('avatar')?.click()}
                                        >
                                            Change Picture
                                        </Button>
                                        <p className="text-sm text-muted-foreground mt-1">
                                            JPG, PNG up to 2MB
                                        </p>
                                    </div>
                                </div>
                                <InputError message={errors.avatar} />
                            </div>

                            {/* Actions */}
                            <div className="flex justify-end gap-4 pt-6 border-t">
                                <Button type="button" variant="outline" asChild>
                                    <Link href={`/users/${user.id}`}>Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Updating...' : 'Update User'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}