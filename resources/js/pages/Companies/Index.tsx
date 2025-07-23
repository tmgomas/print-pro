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
    Plus, 
    Search, 
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    Users,
    MapPin,
    Phone,
    Mail,
    TrendingUp,
    Filter
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Company {
    id: number;
    name: string;
    registration_number?: string;
    email?: string;
    phone?: string;
    address?: string;
    logo_url?: string;
    status: 'active' | 'inactive';
    created_at: string;
    branches_count: number;
    users_count: number;
    active_branches_count: number;
}

interface Props {
    companies: {
        data: Company[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    stats: {
        total: number;
        active: number;
        inactive: number;
    };
    filters: {
        search?: string;
        status?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Companies', href: '/companies' },
];

export default function CompaniesIndex({ companies, stats, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    useEffect(() => {
        const delayedSearch = setTimeout(() => {
            router.get('/companies', { 
                search: searchTerm || undefined,
                status: statusFilter || undefined 
            }, {
                preserveState: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(delayedSearch);
    }, [searchTerm, statusFilter]);

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

    const handleStatusToggle = (companyId: number) => {
        router.patch(`/companies/${companyId}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (companyId: number) => {
        if (confirm('Are you sure you want to delete this company?')) {
            router.delete(`/companies/${companyId}`, {
                preserveScroll: true,
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Companies" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Companies</h1>
                        <p className="text-muted-foreground">Manage companies in your system</p>
                    </div>
                    <Button asChild>
                        <Link href="/companies/create">
                            <Plus className="h-4 w-4 mr-2" />
                            Add Company
                        </Link>
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Companies</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Companies</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Inactive Companies</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-red-600">{stats.inactive}</div>
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
                        <div className="flex gap-4">
                            <div className="flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search companies..."
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                        className="pl-10"
                                    />
                                </div>
                            </div>
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="px-3 py-2 border border-input bg-background rounded-md text-sm"
                            >
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                {/* Companies List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Companies ({companies.total})</CardTitle>
                        <CardDescription>
                            Showing {companies.from} to {companies.to} of {companies.total} companies
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {companies.data.length > 0 ? (
                            <div className="space-y-4">
                                {companies.data.map((company) => (
                                    <div
                                        key={company.id}
                                        className="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                                    >
                                        <div className="flex items-center gap-4">
                                            <Avatar className="h-12 w-12">
                                                <AvatarImage src={company.logo_url} alt={company.name} />
                                                <AvatarFallback>
                                                    {company.name.slice(0, 2).toUpperCase()}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h3 className="font-semibold">{company.name}</h3>
                                                    {getStatusBadge(company.status)}
                                                </div>
                                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                    {company.email && (
                                                        <div className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" />
                                                            {company.email}
                                                        </div>
                                                    )}
                                                    {company.phone && (
                                                        <div className="flex items-center gap-1">
                                                            <Phone className="h-3 w-3" />
                                                            {company.phone}
                                                        </div>
                                                    )}
                                                    {company.address && (
                                                        <div className="flex items-center gap-1">
                                                            <MapPin className="h-3 w-3" />
                                                            {company.address}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-4 text-sm">
                                                    <span>{company.branches_count} Branches</span>
                                                    <span>{company.users_count} Users</span>
                                                    <span className="text-muted-foreground">
                                                        Created {new Date(company.created_at).toLocaleDateString()}
                                                    </span>
                                                </div>
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
                                                    <Link href={`/companies/${company.id}`}>
                                                        <Eye className="h-4 w-4 mr-2" />
                                                        View
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem asChild>
                                                    <Link href={`/companies/${company.id}/edit`}>
                                                        <Edit className="h-4 w-4 mr-2" />
                                                        Edit
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    onClick={() => handleStatusToggle(company.id)}
                                                >
                                                    <TrendingUp className="h-4 w-4 mr-2" />
                                                    {company.status === 'active' ? 'Deactivate' : 'Activate'}
                                                </DropdownMenuItem>
                                                <DropdownMenuItem 
                                                    onClick={() => handleDelete(company.id)}
                                                    className="text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Delete
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-12">
                                <Building2 className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-semibold mb-2">No companies found</h3>
                                <p className="text-muted-foreground mb-4">
                                    {searchTerm || statusFilter 
                                        ? 'Try adjusting your search filters'
                                        : 'Get started by creating your first company'
                                    }
                                </p>
                                {!searchTerm && !statusFilter && (
                                    <Button asChild>
                                        <Link href="/companies/create">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Company
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {companies.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-muted-foreground">
                            Showing {companies.from} to {companies.to} of {companies.total} results
                        </div>
                        <div className="flex gap-2">
                            {companies.current_page > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.get(`/companies?page=${companies.current_page - 1}`)}
                                >
                                    Previous
                                </Button>
                            )}
                            {companies.current_page < companies.last_page && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.get(`/companies?page=${companies.current_page + 1}`)}
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