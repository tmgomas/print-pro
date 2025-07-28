import { useState, useEffect } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';
import { ArrowLeft } from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Props {
    companies: Array<{ value: string; label: string }>;
    roles: Array<{ value: string; label: string }>;
    branches?: Array<{ value: string; label: string; company_id?: string | number }>; // Make optional and add company_id
    defaultCompanyId?: string | number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Users', href: '/users' },
    { title: 'Create User', href: '/users/create' },
];

export default function CreateUser({ companies, roles, branches = [], defaultCompanyId }: Props) {
    const [avatarPreview, setAvatarPreview] = useState<string | null>(null);
    const [availableBranches, setAvailableBranches] = useState(branches || []);
    const [loadingBranches, setLoadingBranches] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        password: '',
        password_confirmation: '',
        company_id: defaultCompanyId || '',
        branch_id: '',
        role: '',
        status: 'active',
        avatar: null as File | null,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/users', {
            forceFormData: true,
            onSuccess: () => {
                // Will be redirected by controller
            },
        });
    };

    // Load branches when company changes
    useEffect(() => {
        if (data.company_id) {
            setLoadingBranches(true);
            fetch(`/users/get-branches?company_id=${data.company_id}`)
                .then(response => response.json())
                .then(branches => {
                    setAvailableBranches(branches);
                    setLoadingBranches(false);
                })
                .catch(() => {
                    setAvailableBranches([]);
                    setLoadingBranches(false);
                });
        } else {
            setAvailableBranches([]);
        }
    }, [data.company_id]);

    const handleAvatarChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('avatar', file);
            const reader = new FileReader();
            reader.onload = () => setAvatarPreview(reader.result as string);
            reader.readAsDataURL(file);
        }
    };

    const handleCompanyChange = (companyId: string) => {
        setData('company_id', companyId);
        setData('branch_id', ''); // Reset branch when company changes
    };

    // Fix: Remove the original filter logic since we're using state
    // const availableBranches = branches?.filter(branch => 
    //     !data.company_id || branch.company_id == data.company_id
    // ) || [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create User" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href="/users">
                            <ArrowLeft className="h-4 w-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Create New User</h1>
                        <p className="text-muted-foreground">Add a new user to the system</p>
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
                                            placeholder="Enter first name"
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
                                            placeholder="Enter last name"
                                            required
                                        />
                                        <InputError message={errors.last_name} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="email">Email *</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="Enter email address"
                                            required
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
                                            placeholder="Enter phone number"
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
                                            onChange={(e) => handleCompanyChange(e.target.value)}
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
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            disabled={!data.company_id || loadingBranches}
                                        >
                                            <option value="">
                                                {loadingBranches ? 'Loading branches...' : 'Select Branch'}
                                            </option>
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
                                        <Label htmlFor="status">Status *</Label>
                                        <select
                                            id="status"
                                            value={data.status}
                                            onChange={(e) => setData('status', e.target.value)}
                                            className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                            required
                                        >
                                            <option value="active">Active</option>
                                            <option value="inactive">Inactive</option>
                                        </select>
                                        <InputError message={errors.status} />
                                    </div>
                                </div>
                            </div>

                            {/* Security Information */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-medium">Security</h3>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div className="grid gap-2">
                                        <Label htmlFor="password">Password *</Label>
                                        <Input
                                            id="password"
                                            type="password"
                                            value={data.password}
                                            onChange={(e) => setData('password', e.target.value)}
                                            placeholder="Enter password"
                                            required
                                        />
                                        <InputError message={errors.password} />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="password_confirmation">Confirm Password *</Label>
                                        <Input
                                            id="password_confirmation"
                                            type="password"
                                            value={data.password_confirmation}
                                            onChange={(e) => setData('password_confirmation', e.target.value)}
                                            placeholder="Confirm password"
                                            required
                                        />
                                        <InputError message={errors.password_confirmation} />
                                    </div>
                                </div>
                            </div>

                            {/* Avatar Upload */}
                            <div className="space-y-4">
                                <h3 className="text-lg font-medium">Profile Picture</h3>
                                <div className="flex items-center gap-6">
                                    {avatarPreview && (
                                        <img
                                            src={avatarPreview}
                                            alt="Avatar preview"
                                            className="h-20 w-20 rounded-full object-cover"
                                        />
                                    )}
                                    <div className="grid gap-2">
                                        <Label htmlFor="avatar">Avatar</Label>
                                        <Input
                                            id="avatar"
                                            type="file"
                                            accept="image/*"
                                            onChange={handleAvatarChange}
                                        />
                                        <InputError message={errors.avatar} />
                                    </div>
                                </div>
                            </div>

                            {/* Submit Button */}
                            <div className="flex justify-end gap-4">
                                <Button variant="outline" asChild>
                                    <Link href="/users">Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Creating...' : 'Create User'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}