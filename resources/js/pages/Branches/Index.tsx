import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { 
    Building2, 
    MapPin,
    Plus, 
    Search, 
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    Users,
    Phone,
    Mail,
    Settings,
    Filter,
    Star
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Branch {
    id: number;
    name: string;
    code: string;
    address?: string;
    city?: string;
    phone?: string;
    email?: string;
    status: 'active' | 'inactive';
    is_main_branch: boolean;
    users_count: number;
    company: {
        id: number;
        name: string;
        logo_url?: string;
    };
    created_at: string;
}

interface Props {
    branches?: {
        data: Branch[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    stats?: {
        total: number;
        active: number;
        inactive: number;
        main_branches: number;
    };
    filters?: {
        search?: string;
        status?: string;
        company_id?: string;
    };
    companies?: Array<{ value: string; label: string }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Branches', href: '/branches' },
];

export default function BranchesIndex({ branches, stats, filters, companies }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [companyFilter, setCompanyFilter] = useState(filters?.company_id || '');

    // Default stats if undefined
    const safeStats = stats || {
        total: 0,
        active: 0,
        inactive: 0,
        main_branches: 0
    };

    useEffect(() => {
        const delayedSearch = setTimeout(() => {
            router.get('/branches', { 
                search: searchTerm || undefined,
                status: statusFilter || undefined,
                company_id: companyFilter || undefined 
            }, {
                preserveState: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(delayedSearch);
    }, [searchTerm, statusFilter, companyFilter]);

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'bg-green-100 text-green-800 border-green-200',
            inactive: 'bg-red-100 text-red-800 border-red-200',
        };
        
        return (
            <Badge 
                variant="outline" 
                className={variants[status as keyof typeof variants]}
            >
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    const handleStatusToggle = (branchId: number) => {
        router.patch(`/branches/${branchId}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (branchId: number) => {
        if (confirm('Are you sure you want to delete this branch?')) {
            router.delete(`/branches/${branchId}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Branches" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Branches</h1>
                        <p className="text-muted-foreground">Manage branches across all companies</p>
                    </div>
                    <Button asChild>
                        <Link href="/branches/create">
                            <Plus className="h-4 w-4 mr-2" />
                            Add Branch
                        </Link>
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Branches</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{safeStats.total}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Branches</CardTitle>
                            <Badge className="text-xs bg-green-100 text-green-800">Active</Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{safeStats.active}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Inactive Branches</CardTitle>
                            <Badge className="text-xs bg-red-100 text-red-800">Inactive</Badge>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{safeStats.inactive}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Main Branches</CardTitle>
                            <Star className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{safeStats.main_branches}</div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Filter className="h-5 w-5" />
                            Filters
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-col md:flex-row gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground h-4 w-4" />
                                    <Input
                                        placeholder="Search branches..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <div className="w-full md:w-48">
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                            <div className="w-full md:w-48">
                                <select
                                    value={companyFilter}
                                    onChange={(e) => setCompanyFilter(e.target.value)}
                                    className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm transition-colors file:border-0 file:bg-transparent file:text-sm file:font-medium placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    <option value="">All Companies</option>
                                    {companies?.map((company) => (
                                        <option key={company.value} value={company.value}>
                                            {company.label}
                                        </option>
                                    )) || (
                        <div className="col-span-full text-center py-8">
                            <p className="text-muted-foreground">No branches found.</p>
                        </div>
                    )}
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Branches Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {branches?.data?.map((branch) => (
                        <Card key={branch.id} className="hover:shadow-lg transition-shadow">
                            <CardHeader className="pb-3">
                                <div className="flex items-start justify-between">
                                    <div className="flex items-center gap-3">
                                        <Avatar className="h-12 w-12">
                                            <AvatarImage src={branch.company.logo_url} />
                                            <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                                                {branch.company.name.charAt(0)}
                                            </AvatarFallback>
                                        </Avatar>
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <CardTitle className="text-lg">{branch.name}</CardTitle>
                                                {branch.is_main_branch && (
                                                    <Star className="h-4 w-4 text-yellow-500 fill-current" />
                                                )}
                                            </div>
                                            <Badge variant="outline" className="text-xs mt-1">
                                                {branch.code}
                                            </Badge>
                                        </div>
                                    </div>
                                    <DropdownMenu>
                                        <DropdownMenuTrigger asChild>
                                            <Button variant="ghost" size="sm">
                                                <MoreHorizontal className="h-4 w-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem asChild>
                                                <Link href={`/branches/${branch.id}`}>
                                                    <Eye className="h-4 w-4 mr-2" />
                                                    View Details
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={`/branches/${branch.id}/edit`}>
                                                    <Edit className="h-4 w-4 mr-2" />
                                                    Edit Branch
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={`/branches/${branch.id}/settings`}>
                                                    <Settings className="h-4 w-4 mr-2" />
                                                    Settings
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem asChild>
                                                <Link href={`/branches/${branch.id}/users`}>
                                                    <Users className="h-4 w-4 mr-2" />
                                                    Users ({branch.users_count})
                                                </Link>
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() => handleStatusToggle(branch.id)}
                                            >
                                                <Badge className="h-4 w-4 mr-2" />
                                                {branch.status === 'active' ? 'Deactivate' : 'Activate'}
                                            </DropdownMenuItem>
                                            <DropdownMenuItem
                                                onClick={() => handleDelete(branch.id)}
                                                className="text-red-600"
                                            >
                                                <Trash2 className="h-4 w-4 mr-2" />
                                                Delete
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Company:</span>
                                        <span className="text-sm text-muted-foreground">{branch.company.name}</span>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Status:</span>
                                        {getStatusBadge(branch.status)}
                                    </div>
                                    {branch.address && (
                                        <div className="flex items-start gap-2">
                                            <MapPin className="h-4 w-4 text-muted-foreground mt-0.5" />
                                            <span className="text-sm text-muted-foreground">{branch.address}</span>
                                        </div>
                                    )}
                                    {branch.phone && (
                                        <div className="flex items-center gap-2">
                                            <Phone className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-sm text-muted-foreground">{branch.phone}</span>
                                        </div>
                                    )}
                                    {branch.email && (
                                        <div className="flex items-center gap-2">
                                            <Mail className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-sm text-muted-foreground">{branch.email}</span>
                                        </div>
                                    )}
                                    <div className="flex items-center gap-2">
                                        <Users className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {branch.users_count} {branch.users_count === 1 ? 'user' : 'users'}
                                        </span>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                {/* Pagination */}
                {branches?.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-muted-foreground">
                            Showing {branches.from} to {branches.to} of {branches.total} branches
                        </div>
                        <div className="flex items-center gap-2">
                            {branches.current_page > 1 && (
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    onClick={() => router.get(`/branches?page=${branches.current_page - 1}`)}
                                >
                                    Previous
                                </Button>
                            )}
                            <span className="text-sm">
                                Page {branches.current_page} of {branches.last_page}
                            </span>
                            {branches.current_page < branches.last_page && (
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    onClick={() => router.get(`/branches?page=${branches.current_page + 1}`)}
                                >
                                    Next
                                </Button>
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}