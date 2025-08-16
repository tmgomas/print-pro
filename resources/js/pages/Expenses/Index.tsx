// resources/js/pages/Expenses/Index.tsx

import { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
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
    Receipt, 
    Plus, 
    Search, 
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    Filter,
    Download,
    Upload,
    TrendingUp,
    DollarSign,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Calendar,
    Building2,
    User,
    Tag,
    CreditCard
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Expense {
    id: number;
    company_id: number;
    branch_id: number;
    category_id: number;
    created_by: number;
    approved_by?: number;
    expense_number: string;
    expense_date: string;
    amount: number;
    formatted_amount: string;
    description: string;
    vendor_name?: string;
    payment_method: string;
    payment_reference?: string;
    receipt_number?: string;
    receipt_attachments?: string[];
    status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'paid' | 'cancelled';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    is_recurring: boolean;
    notes?: string;
    approved_at?: string;
    paid_at?: string;
    created_at: string;
    updated_at: string;
    
    // Relations
    category?: {
        id: number;
        name: string;
        code: string;
        color?: string;
        icon?: string;
    };
    branch?: {
        id: number;
        branch_name: string;
        branch_code: string;
    };
    created_by_user?: {
        id: number;
        name: string;
        email: string;
    };
    approved_by_user?: {
        id: number;
        name: string;
        email: string;
    };
    
    // Computed
    can_approve?: boolean;
    can_edit?: boolean;
    can_delete?: boolean;
    is_overdue?: boolean;
}

interface ExpenseCategory {
    id: number;
    name: string;
    code: string;
    color?: string;
}

interface Branch {
    id: number;
    branch_name: string;
    branch_code: string;
}

interface ExpenseStats {
    this_month_total: number;
    pending_approval: {
        count: number;
        total: number;
    };
    by_status: Record<string, {
        count: number;
        total: number;
    }>;
}

interface Props {
    expenses: {
        data: Expense[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    categories: ExpenseCategory[];
    branches: Branch[];
    stats: ExpenseStats;
   filters?: {  // Make filters optional
        search?: string;
        status?: string;
        category_id?: number;
        priority?: string;
        payment_method?: string;
        date_from?: string;
        date_to?: string;
        amount_min?: number;
        amount_max?: number;
        created_by?: number;
        branch_id?: number;
        sort_by?: string;
        sort_order?: string;
    };
    statusOptions: Record<string, string>;
    priorityOptions: Record<string, string>;
    paymentMethodOptions: Record<string, string>;
    can: {
        create: boolean;
        approve: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Management', href: '/expenses' },
];

export default function ExpensesIndex({ 
    expenses, 
    categories, 
    branches, 
    stats, 
    filters, 
    statusOptions,
    priorityOptions,
    paymentMethodOptions,
    can 
}: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const [selectedExpenses, setSelectedExpenses] = useState<number[]>([]);
    const [showFilters, setShowFilters] = useState(false);
    const [localFilters, setLocalFilters] = useState(filters || {});

    useEffect(() => {
        const delayedSearch = setTimeout(() => {
            router.get('/expenses', {
                ...localFilters,
                search: search || undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);

        return () => clearTimeout(delayedSearch);
    }, [search, localFilters]);

    const handleFilterChange = (key: string, value: any) => {
        setLocalFilters(prev => ({
            ...prev,
            [key]: value || undefined
        }));
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'draft': return 'bg-gray-100 text-gray-800';
            case 'pending_approval': return 'bg-yellow-100 text-yellow-800';
            case 'approved': return 'bg-green-100 text-green-800';
            case 'rejected': return 'bg-red-100 text-red-800';
            case 'paid': return 'bg-blue-100 text-blue-800';
            case 'cancelled': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getPriorityColor = (priority: string) => {
        switch (priority) {
            case 'low': return 'bg-green-100 text-green-800';
            case 'medium': return 'bg-blue-100 text-blue-800';
            case 'high': return 'bg-orange-100 text-orange-800';
            case 'urgent': return 'bg-red-100 text-red-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'draft': return <Edit className="h-4 w-4" />;
            case 'pending_approval': return <Clock className="h-4 w-4" />;
            case 'approved': return <CheckCircle className="h-4 w-4" />;
            case 'rejected': return <XCircle className="h-4 w-4" />;
            case 'paid': return <DollarSign className="h-4 w-4" />;
            case 'cancelled': return <XCircle className="h-4 w-4" />;
            default: return <AlertCircle className="h-4 w-4" />;
        }
    };

    const handleSelectExpense = (expenseId: number, checked: boolean) => {
        if (checked) {
            setSelectedExpenses([...selectedExpenses, expenseId]);
        } else {
            setSelectedExpenses(selectedExpenses.filter(id => id !== expenseId));
        }
    };

    const handleSelectAll = (checked: boolean) => {
        if (checked) {
            setSelectedExpenses(expenses.data.map(expense => expense.id));
        } else {
            setSelectedExpenses([]);
        }
    };

    const handleBulkApprove = () => {
        if (selectedExpenses.length === 0) return;
        
        router.post('/expenses/bulk-approve', {
            expense_ids: selectedExpenses,
        }, {
            onSuccess: () => {
                setSelectedExpenses([]);
            }
        });
    };

    const clearFilters = () => {
        setSearch('');
        setLocalFilters({});
        router.get('/expenses');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expenses" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Expenses</h1>
                        <p className="text-muted-foreground">
                            Track and manage your business expenses
                        </p>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        {selectedExpenses.length > 0 && can.approve && (
                            <Button onClick={handleBulkApprove} variant="outline">
                                <CheckCircle className="h-4 w-4 mr-2" />
                                Approve Selected ({selectedExpenses.length})
                            </Button>
                        )}
                        
                        {can.create && (
                            <Button asChild>
                                <Link href="/expenses/create">
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Expense
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <TrendingUp className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">Rs. {stats.this_month_total.toLocaleString()}</p>
                                <p className="text-xs text-muted-foreground">This Month</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <Clock className="h-4 w-4 text-yellow-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.pending_approval.count}</p>
                                <p className="text-xs text-muted-foreground">
                                    Pending (Rs. {stats.pending_approval.total.toLocaleString()})
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <CheckCircle className="h-4 w-4 text-green-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">
                                    {stats.by_status?.approved?.count || 0}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Approved (Rs. {(stats.by_status?.approved?.total || 0).toLocaleString()})
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <DollarSign className="h-4 w-4 text-blue-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">
                                    {stats.by_status?.paid?.count || 0}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Paid (Rs. {(stats.by_status?.paid?.total || 0).toLocaleString()})
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <div className="flex items-center gap-4 flex-1">
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search expenses..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-9 w-80"
                                    />
                                </div>

                                <Select 
                                    value={localFilters.status || ''} 
                                    onValueChange={(value) => handleFilterChange('status', value)}
                                >
                                    <SelectTrigger className="w-40">
                                        <SelectValue placeholder="All Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Status</SelectItem>
                                        {Object.entries(statusOptions).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>{label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select 
                                    value={localFilters.category_id?.toString() || ''} 
                                    onValueChange={(value) => handleFilterChange('category_id', value ? parseInt(value) : null)}
                                >
                                    <SelectTrigger className="w-48">
                                        <SelectValue placeholder="All Categories" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Categories</SelectItem>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={category.id.toString()}>
                                                {category.name} ({category.code})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setShowFilters(!showFilters)}
                                >
                                    <Filter className="h-4 w-4 mr-2" />
                                    More Filters
                                </Button>
                                
                                {Object.keys(localFilters).length > 0 && (
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearFilters}
                                    >
                                        Clear All
                                    </Button>
                                )}
                            </div>
                        </div>

                        {/* Advanced Filters */}
                        {showFilters && (
                            <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4 pt-4 border-t">
                                <Select 
                                    value={localFilters.priority || ''} 
                                    onValueChange={(value) => handleFilterChange('priority', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Priority" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Priorities</SelectItem>
                                        {Object.entries(priorityOptions).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>{label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                <Select 
                                    value={localFilters.payment_method || ''} 
                                    onValueChange={(value) => handleFilterChange('payment_method', value)}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Payment Method" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All Methods</SelectItem>
                                        {Object.entries(paymentMethodOptions).map(([key, label]) => (
                                            <SelectItem key={key} value={key}>{label}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>

                                {branches.length > 0 && (
                                    <Select 
                                        value={localFilters.branch_id?.toString() || ''} 
                                        onValueChange={(value) => handleFilterChange('branch_id', value ? parseInt(value) : null)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Branch" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="">All Branches</SelectItem>
                                            {branches.map((branch) => (
                                                <SelectItem key={branch.id} value={branch.id.toString()}>
                                                    {branch.branch_name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}

                                <div className="flex gap-2">
                                    <Input
                                        type="date"
                                        value={localFilters.date_from || ''}
                                        onChange={(e) => handleFilterChange('date_from', e.target.value)}
                                        placeholder="From Date"
                                    />
                                    <Input
                                        type="date"
                                        value={localFilters.date_to || ''}
                                        onChange={(e) => handleFilterChange('date_to', e.target.value)}
                                        placeholder="To Date"
                                    />
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Expenses Table */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Expenses</CardTitle>
                                <CardDescription>
                                    Showing {expenses.from}-{expenses.to} of {expenses.total} expenses
                                </CardDescription>
                            </div>
                            
                            {can.approve && (
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        checked={selectedExpenses.length === expenses.data.length && expenses.data.length > 0}
                                        onCheckedChange={handleSelectAll}
                                    />
                                    <span className="text-sm text-muted-foreground">Select All</span>
                                </div>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent>
                        {expenses.data.length === 0 ? (
                            <div className="text-center py-12">
                                <Receipt className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-medium mb-2">No expenses found</h3>
                                <p className="text-muted-foreground mb-4">
                                    {search || Object.keys(localFilters).length > 0 ? 
                                        'No expenses match your search criteria.' : 
                                        'Get started by creating your first expense.'
                                    }
                                </p>
                                {can.create && (!search && Object.keys(localFilters).length === 0) && (
                                    <Button asChild>
                                        <Link href="/expenses/create">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Create Expense
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {expenses.data.map((expense) => (
                                    <div key={expense.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
                                        <div className="flex items-center gap-4 flex-1">
                                            {can.approve && expense.can_approve && (
                                                <Checkbox
                                                    checked={selectedExpenses.includes(expense.id)}
                                                    onCheckedChange={(checked) => handleSelectExpense(expense.id, checked as boolean)}
                                                />
                                            )}
                                            
                                            <div className="flex-1">
                                                <div className="flex items-center gap-3 mb-2">
                                                    <div 
                                                        className="w-8 h-8 rounded flex items-center justify-center text-white text-sm"
                                                        style={{ backgroundColor: expense.category?.color || '#6B7280' }}
                                                    >
                                                        {expense.category?.icon ? 
                                                            expense.category.icon.charAt(0).toUpperCase() : 
                                                            <Tag className="h-4 w-4" />
                                                        }
                                                    </div>
                                                    
                                                    <div>
                                                        <div className="flex items-center gap-2">
                                                            <Link 
                                                                href={`/expenses/${expense.id}`}
                                                                className="font-medium hover:text-blue-600"
                                                            >
                                                                {expense.expense_number}
                                                            </Link>
                                                            {expense.is_recurring && (
                                                                <Badge variant="outline" className="text-xs">
                                                                    Recurring
                                                                </Badge>
                                                            )}
                                                            {expense.is_overdue && (
                                                                <Badge variant="destructive" className="text-xs">
                                                                    Overdue
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-sm text-gray-600 truncate max-w-96">
                                                            {expense.description}
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <div className="flex items-center gap-6 text-xs text-gray-500">
                                                    <div className="flex items-center gap-1">
                                                        <Calendar className="h-3 w-3" />
                                                        {new Date(expense.expense_date).toLocaleDateString()}
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-1">
                                                        <Tag className="h-3 w-3" />
                                                        {expense.category?.name || 'Uncategorized'}
                                                    </div>
                                                    
                                                    {expense.branch && (
                                                        <div className="flex items-center gap-1">
                                                            <Building2 className="h-3 w-3" />
                                                            {expense.branch.branch_name}
                                                        </div>
                                                    )}
                                                    
                                                    <div className="flex items-center gap-1">
                                                        <User className="h-3 w-3" />
                                                        {expense.created_by_user?.name || 'Unknown'}
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-1">
                                                        <CreditCard className="h-3 w-3" />
                                                        {paymentMethodOptions[expense.payment_method] || expense.payment_method}
                                                    </div>
                                                    
                                                    {expense.vendor_name && (
                                                        <div className="flex items-center gap-1">
                                                            <Building2 className="h-3 w-3" />
                                                            {expense.vendor_name}
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <div className="text-right">
                                                <div className="text-lg font-semibold">
                                                    {expense.formatted_amount}
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    <Badge className={getStatusColor(expense.status)}>
                                                        <div className="flex items-center gap-1">
                                                            {getStatusIcon(expense.status)}
                                                            {statusOptions[expense.status]}
                                                        </div>
                                                    </Badge>
                                                    <Badge className={getPriorityColor(expense.priority)}>
                                                        {priorityOptions[expense.priority]}
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
                                                        <Link href={`/expenses/${expense.id}`}>
                                                            <Eye className="h-4 w-4 mr-2" />
                                                            View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    
                                                    {expense.can_edit && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/expenses/${expense.id}/edit`}>
                                                                <Edit className="h-4 w-4 mr-2" />
                                                                Edit Expense
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                    
                                                    <DropdownMenuSeparator />
                                                    
                                                    {expense.status === 'draft' && expense.can_edit && (
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                router.post(`/expenses/${expense.id}/submit-for-approval`);
                                                            }}
                                                        >
                                                            <Clock className="h-4 w-4 mr-2" />
                                                            Submit for Approval
                                                        </DropdownMenuItem>
                                                    )}
                                                    
                                                    {expense.can_approve && expense.status === 'pending_approval' && (
                                                        <>
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    router.post(`/expenses/${expense.id}/approve`);
                                                                }}
                                                            >
                                                                <CheckCircle className="h-4 w-4 mr-2" />
                                                                Approve
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    const reason = prompt('Please provide a reason for rejection:');
                                                                    if (reason) {
                                                                        router.post(`/expenses/${expense.id}/reject`, {
                                                                            rejection_reason: reason
                                                                        });
                                                                    }
                                                                }}
                                                            >
                                                                <XCircle className="h-4 w-4 mr-2" />
                                                                Reject
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                    
                                                    {expense.status === 'approved' && can.approve && (
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                router.post(`/expenses/${expense.id}/mark-as-paid`);
                                                            }}
                                                        >
                                                            <DollarSign className="h-4 w-4 mr-2" />
                                                            Mark as Paid
                                                        </DropdownMenuItem>
                                                    )}
                                                    
                                                    {expense.receipt_attachments && expense.receipt_attachments.length > 0 && (
                                                        <>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem
                                                                onClick={() => {
                                                                    // Handle receipt download
                                                                    window.open(`/expenses/${expense.id}/download-receipt/${expense.receipt_attachments![0]}`, '_blank');
                                                                }}
                                                            >
                                                                <Download className="h-4 w-4 mr-2" />
                                                                Download Receipt
                                                            </DropdownMenuItem>
                                                        </>
                                                    )}
                                                    
                                                    <DropdownMenuSeparator />
                                                    
                                                    {expense.can_delete && (
                                                        <DropdownMenuItem
                                                            className="text-red-600"
                                                            onClick={() => {
                                                                if (confirm('Are you sure you want to delete this expense?')) {
                                                                    router.delete(`/expenses/${expense.id}`);
                                                                }
                                                            }}
                                                        >
                                                            <Trash2 className="h-4 w-4 mr-2" />
                                                            Delete Expense
                                                        </DropdownMenuItem>
                                                    )}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        {/* Pagination */}
                        {expenses.last_page > 1 && (
                            <div className="flex items-center justify-between mt-6 pt-4 border-t">
                                <div className="text-sm text-muted-foreground">
                                    Showing {expenses.from} to {expenses.to} of {expenses.total} results
                                </div>
                                
                                <div className="flex items-center gap-2">
                                    {expenses.current_page > 1 && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get('/expenses', { 
                                                ...localFilters, 
                                                search,
                                                page: expenses.current_page - 1 
                                            })}
                                        >
                                            Previous
                                        </Button>
                                    )}
                                    
                                    <span className="text-sm">
                                        Page {expenses.current_page} of {expenses.last_page}
                                    </span>
                                    
                                    {expenses.current_page < expenses.last_page && (
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => router.get('/expenses', { 
                                                ...localFilters, 
                                                search,
                                                page: expenses.current_page + 1 
                                            })}
                                        >
                                            Next
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}