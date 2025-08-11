import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { 
    Users, 
    UserPlus, 
    Search, 
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    UserCheck,
    UserX,
    Download,
    Upload,
    Filter,
    Building2,
    MapPin,
    Phone,
    Mail,
    CreditCard,
    Calendar,
    TrendingUp,
    AlertCircle,
    CheckCircle,
    XCircle,
    Plus
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Customer {
    id: number;
    company_id: number;
    branch_id: number;
    customer_code: string;
    name: string;
    email: string;
    phone: string;
    billing_address: string;
    shipping_address: string;
    city: string;
    postal_code: string;
    district: string;
    province: string;
    tax_number?: string;
    credit_limit: number;
    current_balance: number;
    status: 'active' | 'inactive' | 'suspended';
    customer_type: 'individual' | 'business';
    date_of_birth?: string;
    company_name?: string;
    contact_person?: string;
    notes?: string;
    preferences?: Record<string, any>;
    created_at: string;
    updated_at: string;
    
    // Relations
    branch?: {
        id: number;
        name: string;
        code: string;
    };
    
    // Computed attributes
    display_name?: string;
    formatted_credit_limit?: string;
    formatted_balance?: string;
    available_credit?: number;
    formatted_available_credit?: string;
    age?: number;
    
    // Statistics
    total_invoices?: number;
    total_orders?: number;
    total_paid?: number;
    outstanding_balance?: number;
}

interface Branch {
    id: number;
    name: string;
    code: string;
    customers_count?: number;
}

interface CustomerStats {
    total: number;
    active: number;
    inactive: number;
    suspended: number;
    business: number;
    individual: number;
    total_credit_limit: number;
    total_outstanding: number;
    average_credit_limit: number;
}

interface Props {
    customers: {
        data: Customer[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    branches?: Branch[];
    stats: CustomerStats;
    filters: {
        search?: string;
        status?: string;
        customer_type?: string;
        branch_id?: string;
        city?: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/customers' },
];

export default function CustomersIndex({ customers, branches = [], stats, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [customerTypeFilter, setCustomerTypeFilter] = useState(filters.customer_type || '');
    const [branchFilter, setBranchFilter] = useState(filters.branch_id || '');
    const [cityFilter, setCityFilter] = useState(filters.city || '');

    useEffect(() => {
        const delayedSearch = setTimeout(() => {
            router.get('/customers', { 
                search: searchTerm || undefined,
                status: statusFilter || undefined,
                customer_type: customerTypeFilter || undefined,
                branch_id: branchFilter || undefined,
                city: cityFilter || undefined
            }, {
                preserveState: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(delayedSearch);
    }, [searchTerm, statusFilter, customerTypeFilter, branchFilter, cityFilter]);

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'bg-green-100 text-green-800 border-green-200',
            inactive: 'bg-red-100 text-red-800 border-red-200',
            suspended: 'bg-yellow-100 text-yellow-800 border-yellow-200',
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

    const handleStatusToggle = (customerId: number) => {
        router.patch(`/customers/${customerId}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = (customerId: number) => {
        if (confirm('Are you sure you want to delete this customer?')) {
            router.delete(`/customers/${customerId}`, {
                preserveScroll: true,
            });
        }
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-LK', {
            style: 'currency',
            currency: 'LKR',
            minimumFractionDigits: 2,
        }).format(amount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Customers" />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold">Customer Management</h1>
                        <p className="text-muted-foreground">
                            Manage your customer database and and  relationships
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/customers/create">
                            <UserPlus className="h-4 w-4 mr-2" />
                            Add Customer
                    
                        </Link>
                    </Button>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Customers</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.business} business, {stats.individual} individual
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Active Customers</CardTitle>
                            <CheckCircle className="h-4 w-4 text-green-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.active}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.inactive} inactive, {stats.suspended} suspended
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Credit Limit</CardTitle>
                            <CreditCard className="h-4 w-4 text-purple-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(stats.total_credit_limit)}</div>
                            <p className="text-xs text-muted-foreground">
                                Avg: {formatCurrency(stats.average_credit_limit)}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Outstanding Balance</CardTitle>
                            <TrendingUp className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">
                                {formatCurrency(stats.total_outstanding)}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Across all customers
                            </p>
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
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4">
                            {/* Search */}
                            <div className="relative lg:col-span-2">
                                <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search customers, phone, email..."
                                    value={searchTerm}
                                    onChange={(e) => setSearchTerm(e.target.value)}
                                    className="pl-10"
                                />
                            </div>

                            {/* Status Filter */}
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="px-3 py-2 border border-input bg-background rounded-md text-sm"
                            >
                                <option value="">All Status</option>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                                <option value="suspended">Suspended</option>
                            </select>

                            {/* Customer Type Filter */}
                            <select
                                value={customerTypeFilter}
                                onChange={(e) => setCustomerTypeFilter(e.target.value)}
                                className="px-3 py-2 border border-input bg-background rounded-md text-sm"
                            >
                                <option value="">All Types</option>
                                <option value="individual">Individual</option>
                                <option value="business">Business</option>
                            </select>

                            {/* Branch Filter */}
                            {branches.length > 0 && (
                                <select
                                    value={branchFilter}
                                    onChange={(e) => setBranchFilter(e.target.value)}
                                    className="px-3 py-2 border border-input bg-background rounded-md text-sm"
                                >
                                    <option value="">All Branches</option>
                                    {branches.map((branch) => (
                                        <option key={branch.id} value={branch.id.toString()}>
                                            {branch.name}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>

                        {/* City Filter */}
                        <div className="mt-4">
                            <Input
                                placeholder="Filter by city..."
                                value={cityFilter}
                                onChange={(e) => setCityFilter(e.target.value)}
                                className="max-w-sm"
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* Customers List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Customers ({customers.total})</CardTitle>
                        <CardDescription>
                            Showing {customers.from} to {customers.to} of {customers.total} customers
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {customers.data.length > 0 ? (
                            <div className="space-y-4">
                                {customers.data.map((customer) => (
                                    <div
                                        key={customer.id}
                                        className="flex items-center justify-between p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                                    >
                                        <div className="flex items-center gap-4">
                                            <div className="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                                                <span className="text-blue-600 font-semibold">
                                                    {customer.name.charAt(0).toUpperCase()}
                                                </span>
                                            </div>
                                            <div className="space-y-1">
                                                <div className="flex items-center gap-2">
                                                    <h3 className="font-semibold">
                                                        {customer.display_name || customer.name}
                                                    </h3>
                                                    {getStatusBadge(customer.status)}
                                                    {customer.customer_type === 'business' && (
                                                        <Building2 className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                </div>
                                                <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                    <span className="font-mono text-xs bg-gray-100 px-2 py-1 rounded">
                                                        {customer.customer_code}
                                                    </span>
                                                    {customer.email && (
                                                        <div className="flex items-center gap-1">
                                                            <Mail className="h-3 w-3" />
                                                            {customer.email}
                                                        </div>
                                                    )}
                                                    {customer.phone && (
                                                        <div className="flex items-center gap-1">
                                                            <Phone className="h-3 w-3" />
                                                            {customer.phone}
                                                        </div>
                                                    )}
                                                    {customer.city && (
                                                        <div className="flex items-center gap-1">
                                                            <MapPin className="h-3 w-3" />
                                                            {customer.city}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center gap-4">
                                            {/* Credit & Balance Info */}
                                            <div className="text-right text-sm">
                                                <div className="font-medium">
                                                    Credit: {customer.formatted_credit_limit || formatCurrency(customer.credit_limit)}
                                                </div>
                                                <div className={`text-sm ${customer.current_balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                    Balance: {customer.formatted_balance || formatCurrency(customer.current_balance)}
                                                </div>
                                            </div>

                                            {/* Actions */}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="outline" size="sm">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/customers/${customer.id}`}>
                                                            <Eye className="h-4 w-4 mr-2" />
                                                            View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/customers/${customer.id}/edit`}>
                                                            <Edit className="h-4 w-4 mr-2" />
                                                            Edit Customer
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/customers/${customer.id}/orders`}>
                                                            <TrendingUp className="h-4 w-4 mr-2" />
                                                            View Orders
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/customers/${customer.id}/invoices`}>
                                                            <Calendar className="h-4 w-4 mr-2" />
                                                            View Invoices
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleStatusToggle(customer.id)}
                                                    >
                                                        {customer.status === 'active' ? (
                                                            <>
                                                                <UserX className="h-4 w-4 mr-2" />
                                                                Deactivate
                                                            </>
                                                        ) : (
                                                            <>
                                                                <UserCheck className="h-4 w-4 mr-2" />
                                                                Activate
                                                            </>
                                                        )}
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem
                                                        onClick={() => handleDelete(customer.id)}
                                                        className="text-red-600"
                                                    >
                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                        Delete Customer
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-12">
                                <Users className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-medium mb-2">No customers found</h3>
                                <p className="text-muted-foreground mb-4">
                                    {searchTerm || statusFilter || customerTypeFilter || branchFilter || cityFilter
                                        ? 'Try adjusting your search filters'
                                        : 'Get started by creating your first customer'
                                    }
                                </p>
                                {!searchTerm && !statusFilter && !customerTypeFilter && !branchFilter && !cityFilter && (
                                    <Button asChild>
                                        <Link href="/customers/create">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add Customer
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Pagination */}
                {customers.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-muted-foreground">
                            Showing {customers.from} to {customers.to} of {customers.total} results
                        </div>
                        <div className="flex gap-2">
                            {customers.current_page > 1 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.get(`/customers?page=${customers.current_page - 1}`)}
                                >
                                    Previous
                                </Button>
                            )}
                            {customers.current_page < customers.last_page && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => router.get(`/customers?page=${customers.current_page + 1}`)}
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