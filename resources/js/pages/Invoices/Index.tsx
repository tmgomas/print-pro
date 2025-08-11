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
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { 
    FileText, 
    Plus, 
    Search, 
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    Download,
    Upload,
    Filter,
    Calendar,
    DollarSign,
    User,
    Building2,
    CreditCard,
    Clock,
    AlertCircle,
    CheckCircle,
    XCircle,
    Printer,
    TrendingUp,
    RefreshCw
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

// TypeScript Interfaces
interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    due_date: string;
    status: 'draft' | 'pending' | 'processing' | 'completed' | 'cancelled';
    payment_status: 'pending' | 'partially_paid' | 'paid' | 'refunded';
    total_amount: number;
    total_invoice: number;
    subtotal: number;
    weight_charge: number;
    tax_amount: number;
    discount_amount: number;
    total_weight: number;
    formatted_total: string;
    formatted_subtotal: string;
    formatted_weight_charge: string;
    formatted_tax_amount: string;
    formatted_discount_amount: string;
    created_at: string;
    updated_at: string;
    customer: {
        id: number;
        name: string;
        email?: string;
        phone: string;
        customer_code: string;
    };
    branch: {
        id: number;
        name: string;
        code: string;
    };
    creator: {
        id: number;
        name: string;
    };
    items_count: number;
    payments_count: number;
    is_overdue: boolean;
    days_overdue?: number;
}

interface Customer {
    id: number;
    name: string;
    customer_code: string;
    display_name: string;
}

interface Branch {
    id: number;
    name: string;
    code: string;
}

interface InvoiceStats {
    total?: number;
    draft?: number;
    pending?: number;
    processing?: number;
    completed?: number;
    cancelled?: number;
    total_amount?: number;
    total_invoice?: number;
    paid_amount?: number;
    outstanding_amount?: number;
    overdue_count?: number;
    overdue_amount?: number;
    // Backend actually returns these fields
    paid_count?: number;
    pending_count?: number;
    // Daily stats
    today_income?: number;
    today_invoices?: number;
    yesterday_income?: number;
    weekly_income?: number;
    monthly_income?: number;
}

interface Props {
    invoices: {
        data: Invoice[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    customers: Customer[];
    branches: Branch[];
    stats?: InvoiceStats;
    filters: {
        search?: string;
        status?: string;
        payment_status?: string;
        branch_id?: string;
        customer_id?: string;
        date_from?: string;
        date_to?: string;
        overdue?: boolean;
        per_page?: string;
        page?: number;
    };
    permissions?: {
        create?: boolean;
        edit?: boolean;
        delete?: boolean;
        view_all_branches?: boolean;
        create_payment?: boolean;
        manage_payments?: boolean;
    };
}

// Status color mappings
const getStatusColor = (status: string) => {
    const colors = {
        draft: 'bg-gray-100 text-gray-800',
        pending: 'bg-yellow-100 text-yellow-800',
        processing: 'bg-blue-100 text-blue-800',
        completed: 'bg-green-100 text-green-800',
        cancelled: 'bg-red-100 text-red-800',
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
};

// Generate page numbers for pagination
const getPageNumbers = (currentPage: number, lastPage: number) => {
    const pages: (number | string)[] = [];
    const delta = 2; // Number of pages to show around current page

    // Always show first page
    pages.push(1);

    // Calculate start and end for middle pages
    const start = Math.max(2, currentPage - delta);
    const end = Math.min(lastPage - 1, currentPage + delta);

    // Add ellipsis after first page if needed
    if (start > 2) {
        pages.push('...');
    }

    // Add middle pages
    for (let i = start; i <= end; i++) {
        pages.push(i);
    }

    // Add ellipsis before last page if needed
    if (end < lastPage - 1) {
        pages.push('...');
    }

    // Always show last page (if more than 1 page)
    if (lastPage > 1) {
        pages.push(lastPage);
    }

    return pages;
};

const getPaymentStatusColor = (status: string) => {
    const colors = {
        pending: 'bg-orange-100 text-orange-800',
        partially_paid: 'bg-yellow-100 text-yellow-800',
        paid: 'bg-green-100 text-green-800',
        refunded: 'bg-purple-100 text-purple-800',
    };
    return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
};

export default function InvoicesIndex({ 
    invoices, 
    customers = [], 
    branches = [], 
    stats = {}, 
    filters, 
    permissions = {} 
}: Props) {
    const [currentFilters, setCurrentFilters] = useState(filters);
    const [showFilters, setShowFilters] = useState(false);

    // Breadcrumb items
    const breadcrumbItems: BreadcrumbItem[] = [
        { label: 'Dashboard', href: '/dashboard' },
        { label: 'Invoices', href: '/invoices', current: true },
    ];

    // Handle filter changes with proper URL params
    const updateFilters = (newFilters: Partial<typeof filters>) => {
        const updatedFilters = { ...currentFilters, ...newFilters };
        setCurrentFilters(updatedFilters);
        
        // Remove empty filters and ensure proper types
        const cleanFilters = Object.fromEntries(
            Object.entries(updatedFilters).filter(([_, value]) => 
                value !== '' && value !== null && value !== undefined
            )
        );
        
        // Navigate with filters
        router.get('/invoices', cleanFilters, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Clear all filters
    const clearFilters = () => {
        setCurrentFilters({});
        router.get('/invoices', {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Handle search
    const handleSearch = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        const search = formData.get('search') as string;
        updateFilters({ search });
    };

    // Delete invoice
    const handleDelete = (invoiceId: number) => {
        if (confirm('Are you sure you want to delete this invoice?')) {
            router.delete(`/invoices/${invoiceId}`, {
                preserveScroll: true,
            });
        }
    };

    // Format date for display
    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    return (
        <AppLayout>
            <Head title="Invoices" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Invoices</h1>
                        <p className="text-muted-foreground">
                            Manage customer invoices and payments
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" onClick={() => window.print()}>
                            <Printer className="mr-2 h-4 w-4" />
                            Print
                        </Button>
                        {permissions.create && (
                            <Button asChild>
                                <Link href="/invoices/create">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Create Invoice
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Today's Income</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-blue-600">
                                Rs. {(stats.today_income || 0).toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                {stats.today_invoices || 0} invoices today
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Invoices</CardTitle>
                            <FileText className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold">{stats.total_invoices || 0}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.paid_count || 0} paid • {stats.pending_count || 0} pending
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Amount</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold">Rs. {(stats.total_amount || 0).toLocaleString()}</div>
                            <p className="text-xs text-muted-foreground">
                                All invoices value
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Outstanding</CardTitle>
                            <AlertCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-orange-600">
                                Rs. {(stats.outstanding_amount || 0).toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Pending payments
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Overdue</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-red-600">{stats.overdue_count || 0}</div>
                            <p className="text-xs text-muted-foreground">
                                Rs. {(stats.overdue_amount || 0).toLocaleString()}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Paid Amount</CardTitle>
                            <CheckCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-green-600">
                                Rs. {(stats.paid_amount || 0).toLocaleString()}
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Collected payments
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters and Search */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle>Filter Invoices</CardTitle>
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => setShowFilters(!showFilters)}
                            >
                                <Filter className="mr-2 h-4 w-4" />
                                {showFilters ? 'Hide' : 'Show'} Filters
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {/* Search Bar */}
                        <form onSubmit={handleSearch} className="mb-4">
                            <div className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 h-4 w-4" />
                                    <Input
                                        name="search"
                                        placeholder="Search by invoice number, customer name..."
                                        defaultValue={currentFilters.search || ''}
                                        className="pl-10"
                                    />
                                </div>
                                <Button type="submit">Search</Button>
                                {Object.keys(currentFilters).length > 0 && (
                                    <Button type="button" variant="outline" onClick={clearFilters}>
                                        Clear
                                    </Button>
                                )}
                            </div>
                        </form>

                        {/* Advanced Filters */}
                        {showFilters && (
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label className="text-sm font-medium">Status</label>
                                    <Select 
                                        value={currentFilters.status || ''} 
                                        onValueChange={(value) => updateFilters({ status: value || undefined })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All statuses</SelectItem>
                                            <SelectItem value="draft">Draft</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="processing">Processing</SelectItem>
                                            <SelectItem value="completed">Completed</SelectItem>
                                            <SelectItem value="cancelled">Cancelled</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <label className="text-sm font-medium">Payment Status</label>
                                    <Select 
                                        value={currentFilters.payment_status || ''} 
                                        onValueChange={(value) => updateFilters({ payment_status: value || undefined })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All payment statuses" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All payment statuses</SelectItem>
                                            <SelectItem value="pending">Pending</SelectItem>
                                            <SelectItem value="partially_paid">Partially Paid</SelectItem>
                                            <SelectItem value="paid">Paid</SelectItem>
                                            <SelectItem value="refunded">Refunded</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <label className="text-sm font-medium">Customer</label>
                                    <Select 
                                        value={currentFilters.customer_id || ''} 
                                        onValueChange={(value) => updateFilters({ customer_id: value || undefined })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="All customers" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All customers</SelectItem>
                                            {customers.map((customer) => (
                                                <SelectItem key={customer.id} value={customer.id.toString()}>
                                                    {customer.display_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                {permissions.view_all_branches && (
                                    <div>
                                        <label className="text-sm font-medium">Branch</label>
                                        <Select 
                                            value={currentFilters.branch_id || ''} 
                                            onValueChange={(value) => updateFilters({ branch_id: value || undefined })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="All branches" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="">All branches</SelectItem>
                                                {branches.map((branch) => (
                                                    <SelectItem key={branch.id} value={branch.id.toString()}>
                                                        {branch.name} ({branch.code})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Invoices Table */}
                <Card>
                    <CardHeader>
                        <CardTitle>Invoice List</CardTitle>
                        <CardDescription>
                            Showing {invoices.from} to {invoices.to} of {invoices.total} invoices
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead>
                                    <tr className="border-b">
                                        <th className="text-left py-3 px-2">Invoice #</th>
                                        <th className="text-left py-3 px-2">Customer</th>
                                        <th className="text-left py-3 px-2">Date</th>
                                        <th className="text-left py-3 px-2">Due Date</th>
                                        <th className="text-left py-3 px-2">Status</th>
                                        <th className="text-left py-3 px-2">Payment</th>
                                        <th className="text-right py-3 px-2">Amount</th>
                                        <th className="text-right py-3 px-2">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {invoices.data.length > 0 ? (
                                        invoices.data.map((invoice) => (
                                            <tr key={invoice.id} className="border-b hover:bg-gray-50">
                                                <td className="py-3 px-2">
                                                    <div className="font-medium">
                                                        {invoice.invoice_number}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {invoice.branch.code}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">
                                                    <div className="font-medium">
                                                        {invoice.customer.name}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {invoice.customer.customer_code}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">
                                                    <div className="text-sm">
                                                        {formatDate(invoice.invoice_date)}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">
                                                    <div className={`text-sm ${invoice.is_overdue ? 'text-red-600 font-medium' : ''}`}>
                                                        {formatDate(invoice.due_date)}
                                                        {invoice.is_overdue && (
                                                            <div className="text-xs text-red-500">
                                                                {invoice.days_overdue} days overdue
                                                            </div>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">
                                                    <Badge className={getStatusColor(invoice.status)}>
                                                        {invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-2">
                                                    <Badge className={getPaymentStatusColor(invoice.payment_status)}>
                                                        {invoice.payment_status.replace('_', ' ').charAt(0).toUpperCase() + 
                                                         invoice.payment_status.replace('_', ' ').slice(1)}
                                                    </Badge>
                                                </td>
                                                <td className="py-3 px-2 text-right">
                                                    <div className="font-medium">
                                                        {invoice.formatted_total}
                                                    </div>
                                                    <div className="text-sm text-gray-500">
                                                        {invoice.items_count} items
                                                    </div>
                                                </td>
                                                <td className="py-3 px-2">
                                                    <div className="flex justify-end">
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="sm">
                                                                    <MoreHorizontal className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem asChild>
                                                                    <Link href={`/invoices/${invoice.id}`}>
                                                                        <Eye className="mr-2 h-4 w-4" />
                                                                        View Details
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                                {permissions.edit && (
                                                                    <DropdownMenuItem asChild>
                                                                        <Link href={`/invoices/${invoice.id}/edit`}>
                                                                            <Edit className="mr-2 h-4 w-4" />
                                                                            Edit
                                                                        </Link>
                                                                    </DropdownMenuItem>
                                                                )}
                                                                <DropdownMenuItem asChild>
                                                                    <Link href={`/invoices/${invoice.id}/pdf`} target="_blank">
                                                                        <Download className="mr-2 h-4 w-4" />
                                                                        Download PDF
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator />
                                                                {permissions.delete && (
                                                                    <DropdownMenuItem 
                                                                        className="text-red-600"
                                                                        onClick={() => handleDelete(invoice.id)}
                                                                    >
                                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                                        Delete
                                                                    </DropdownMenuItem>
                                                                )}
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={8} className="py-8 text-center text-gray-500">
                                                <FileText className="mx-auto h-12 w-12 text-gray-400 mb-4" />
                                                <p className="text-lg font-medium">No invoices found</p>
                                                <p className="text-sm">
                                                    {Object.keys(currentFilters).length > 0 
                                                        ? 'Try adjusting your filters' 
                                                        : 'Create your first invoice to get started'
                                                    }
                                                </p>
                                                {permissions.create && Object.keys(currentFilters).length === 0 && (
                                                    <Button asChild className="mt-4">
                                                        <Link href="/invoices/create">
                                                            <Plus className="mr-2 h-4 w-4" />
                                                            Create Invoice
                                                        </Link>
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {invoices.last_page > 1 && (
                            <div className="mt-6 space-y-4">
                                {/* Pagination Info */}
                                <div className="flex items-center justify-between text-sm text-gray-500">
                                    <div>
                                        Showing <span className="font-medium">{invoices.from || 0}</span> to{' '}
                                        <span className="font-medium">{invoices.to || 0}</span> of{' '}
                                        <span className="font-medium">{invoices.total || 0}</span> results
                                    </div>
                                    <div>
                                        Page <span className="font-medium">{invoices.current_page}</span> of{' '}
                                        <span className="font-medium">{invoices.last_page}</span>
                                    </div>
                                </div>

                                {/* Pagination Controls */}
                                <div className="flex items-center justify-center space-x-2">
                                    {/* First Page */}
                                    {invoices.current_page > 1 && (
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            onClick={() => router.get('/invoices', { ...currentFilters, page: 1 })}
                                        >
                                            First
                                        </Button>
                                    )}

                                    {/* Previous Page */}
                                    <Button 
                                        variant="outline" 
                                        size="sm"
                                        onClick={() => router.get('/invoices', { ...currentFilters, page: invoices.current_page - 1 })}
                                        disabled={invoices.current_page === 1}
                                    >
                                        ← Previous
                                    </Button>

                                    {/* Page Numbers */}
                                    {getPageNumbers(invoices.current_page, invoices.last_page).map((page, index) => (
                                        page === '...' ? (
                                            <span key={`ellipsis-${index}`} className="px-3 py-1 text-gray-400">
                                                ...
                                            </span>
                                        ) : (
                                            <Button
                                                key={page}
                                                variant={page === invoices.current_page ? "default" : "outline"}
                                                size="sm"
                                                onClick={() => router.get('/invoices', { ...currentFilters, page })}
                                                className={page === invoices.current_page ? "bg-blue-600 text-white" : ""}
                                            >
                                                {page}
                                            </Button>
                                        )
                                    ))}

                                    {/* Next Page */}
                                    <Button 
                                        variant="outline" 
                                        size="sm"
                                        onClick={() => router.get('/invoices', { ...currentFilters, page: invoices.current_page + 1 })}
                                        disabled={invoices.current_page === invoices.last_page}
                                    >
                                        Next →
                                    </Button>

                                    {/* Last Page */}
                                    {invoices.current_page < invoices.last_page && (
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            onClick={() => router.get('/invoices', { ...currentFilters, page: invoices.last_page })}
                                        >
                                            Last
                                        </Button>
                                    )}
                                </div>

                                {/* Items per page selector */}
                                <div className="flex items-center justify-center">
                                    <div className="flex items-center space-x-2 text-sm">
                                        <span>Show:</span>
                                        <Select 
                                            value={currentFilters.per_page?.toString() || '15'} 
                                            onValueChange={(value) => updateFilters({ per_page: value, page: 1 })}
                                        >
                                            <SelectTrigger className="w-20">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="10">10</SelectItem>
                                                <SelectItem value="15">15</SelectItem>
                                                <SelectItem value="25">25</SelectItem>
                                                <SelectItem value="50">50</SelectItem>
                                                <SelectItem value="100">100</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <span>per page</span>
                                    </div>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}