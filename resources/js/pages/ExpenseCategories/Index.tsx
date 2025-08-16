// resources/js/pages/ExpenseCategories/Index.tsx

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
    FolderTree, 
    Plus, 
    Search, 
    MoreHorizontal,
    Eye,
    Edit,
    Trash2,
    Filter,
    Grid3X3,
    List,
    TrendingUp,
    Tags,
    ArrowUpDown,
    Circle,
    Folder,
    FolderOpen
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface ExpenseCategory {
    id: number;
    company_id: number;
    name: string;
    code: string;
    description?: string;
    icon?: string;
    color?: string;
    parent_id?: number;
    status: 'active' | 'inactive';
    sort_order: number;
    is_system_category: boolean;
    created_at: string;
    updated_at: string;
    
    // Relations
    parent?: {
        id: number;
        name: string;
        code: string;
    };
    children?: ExpenseCategory[];
    
    // Computed
    full_name?: string;
    level?: number;
    expenses_count?: number;
    total_expenses?: number;
    is_parent?: boolean;
}

interface CategoryStats {
    total: number;
    active: number;
    inactive: number;
    system_categories: number;
    custom_categories: number;
    with_expenses: number;
}

interface Props {
    categories: ExpenseCategory[];
    stats: CategoryStats;
    filters?: {  // Make filters optional
        search?: string;
        status?: string;
        parent_id?: number;
    };
    can: {
        create: boolean;
        update: boolean;
        delete: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Management', href: '/expenses' },
    { title: 'Categories', href: '/expense-categories' },
];

export default function ExpenseCategoriesIndex({ categories, stats, filters, can }: Props) {
    const [search, setSearch] = useState(filters?.search || '');
    const [statusFilter, setStatusFilter] = useState(filters?.status || '');
    const [viewMode, setViewMode] = useState<'tree' | 'list'>('tree');
    const [expandedCategories, setExpandedCategories] = useState<Set<number>>(new Set());

    useEffect(() => {
        const delayedSearch = setTimeout(() => {
            router.get('/expense-categories', {
                search: search || undefined,
                status: statusFilter || undefined,
            }, {
                preserveState: true,
                preserveScroll: true,
            });
        }, 300);

        return () => clearTimeout(delayedSearch);
    }, [search, statusFilter]);

    const toggleCategory = (categoryId: number) => {
        const newExpanded = new Set(expandedCategories);
        if (newExpanded.has(categoryId)) {
            newExpanded.delete(categoryId);
        } else {
            newExpanded.add(categoryId);
        }
        setExpandedCategories(newExpanded);
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': return 'bg-green-100 text-green-800';
            case 'inactive': return 'bg-gray-100 text-gray-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const getCategoryIcon = (category: ExpenseCategory) => {
        if (category.icon) {
            // You can implement icon mapping here
            return <Circle className="h-4 w-4" style={{ color: category.color || '#6B7280' }} />;
        }
        return category.is_parent ? 
            <FolderOpen className="h-4 w-4 text-blue-600" /> : 
            <Folder className="h-4 w-4 text-gray-600" />;
    };

    const renderTreeCategory = (category: ExpenseCategory, level: number = 0) => (
        <div key={category.id} className={`${level > 0 ? 'ml-6' : ''}`}>
            <div className="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">
                <div className="flex items-center gap-3 flex-1">
                    {category.children && category.children.length > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => toggleCategory(category.id)}
                            className="h-6 w-6 p-0"
                        >
                            <ArrowUpDown className={`h-3 w-3 transform transition-transform ${
                                expandedCategories.has(category.id) ? 'rotate-180' : ''
                            }`} />
                        </Button>
                    )}
                    
                    {getCategoryIcon(category)}
                    
                    <div className="flex-1">
                        <div className="flex items-center gap-2">
                            <h3 className="font-medium">{category.name}</h3>
                            <span className="text-sm text-gray-500">({category.code})</span>
                            {category.is_system_category && (
                                <Badge variant="outline" className="text-xs">System</Badge>
                            )}
                        </div>
                        {category.description && (
                            <p className="text-sm text-gray-600 mt-1">{category.description}</p>
                        )}
                        <div className="flex items-center gap-4 mt-1 text-xs text-gray-500">
                            <span>{category.expenses_count || 0} expenses</span>
                            {category.total_expenses && (
                                <span>Rs. {category.total_expenses.toLocaleString()}</span>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex items-center gap-2">
                    <Badge className={getStatusColor(category.status)}>
                        {category.status}
                    </Badge>

                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="sm">
                                <MoreHorizontal className="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuItem asChild>
                                <Link href={`/expense-categories/${category.id}`}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    View Details
                                </Link>
                            </DropdownMenuItem>
                            {can.update && (
                                <DropdownMenuItem asChild>
                                    <Link href={`/expense-categories/${category.id}/edit`}>
                                        <Edit className="h-4 w-4 mr-2" />
                                        Edit Category
                                    </Link>
                                </DropdownMenuItem>
                            )}
                            <DropdownMenuSeparator />
                            {can.delete && !category.is_system_category && !category.expenses_count && (
                                <DropdownMenuItem
                                    className="text-red-600"
                                    onClick={() => {
                                        if (confirm('Are you sure you want to delete this category?')) {
                                            router.delete(`/expense-categories/${category.id}`);
                                        }
                                    }}
                                >
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete Category
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>

            {/* Render children if expanded */}
            {expandedCategories.has(category.id) && category.children && (
                <div className="mt-2 space-y-2">
                    {category.children.map(child => renderTreeCategory(child, level + 1))}
                </div>
            )}
        </div>
    );

    const renderListCategory = (category: ExpenseCategory) => (
        <div key={category.id} className="flex items-center justify-between p-4 border rounded-lg hover:bg-gray-50">
            <div className="flex items-center gap-3 flex-1">
                {getCategoryIcon(category)}
                
                <div className="flex-1">
                    <div className="flex items-center gap-2">
                        <h3 className="font-medium">{category.full_name || category.name}</h3>
                        <span className="text-sm text-gray-500">({category.code})</span>
                        {category.is_system_category && (
                            <Badge variant="outline" className="text-xs">System</Badge>
                        )}
                    </div>
                    {category.description && (
                        <p className="text-sm text-gray-600 mt-1">{category.description}</p>
                    )}
                    <div className="flex items-center gap-4 mt-1 text-xs text-gray-500">
                        <span>{category.expenses_count || 0} expenses</span>
                        {category.total_expenses && (
                            <span>Rs. {category.total_expenses.toLocaleString()}</span>
                        )}
                    </div>
                </div>
            </div>

            <div className="flex items-center gap-2">
                <Badge className={getStatusColor(category.status)}>
                    {category.status}
                </Badge>

                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm">
                            <MoreHorizontal className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuItem asChild>
                            <Link href={`/expense-categories/${category.id}`}>
                                <Eye className="h-4 w-4 mr-2" />
                                View Details
                            </Link>
                        </DropdownMenuItem>
                        {can.update && (
                            <DropdownMenuItem asChild>
                                <Link href={`/expense-categories/${category.id}/edit`}>
                                    <Edit className="h-4 w-4 mr-2" />
                                    Edit Category
                                </Link>
                            </DropdownMenuItem>
                        )}
                        <DropdownMenuSeparator />
                        {can.delete && !category.is_system_category && !category.expenses_count && (
                            <DropdownMenuItem
                                className="text-red-600"
                                onClick={() => {
                                    if (confirm('Are you sure you want to delete this category?')) {
                                        router.delete(`/expense-categories/${category.id}`);
                                    }
                                }}
                            >
                                <Trash2 className="h-4 w-4 mr-2" />
                                Delete Category
                            </DropdownMenuItem>
                        )}
                    </DropdownMenuContent>
                </DropdownMenu>
            </div>
        </div>
    );

    const parentCategories = categories.filter(cat => !cat.parent_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Expense Categories" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Expense Categories</h1>
                        <p className="text-muted-foreground">
                            Organize and manage your expense categories
                        </p>
                    </div>
                    
                    {can.create && (
                        <Button asChild>
                            <Link href="/expense-categories/create">
                                <Plus className="h-4 w-4 mr-2" />
                                Add Category
                            </Link>
                        </Button>
                    )}
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <FolderTree className="h-4 w-4 text-muted-foreground" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.total}</p>
                                <p className="text-xs text-muted-foreground">Total Categories</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <Circle className="h-4 w-4 text-green-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.active}</p>
                                <p className="text-xs text-muted-foreground">Active</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <Circle className="h-4 w-4 text-gray-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.inactive}</p>
                                <p className="text-xs text-muted-foreground">Inactive</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <Tags className="h-4 w-4 text-blue-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.system_categories}</p>
                                <p className="text-xs text-muted-foreground">System</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <Folder className="h-4 w-4 text-purple-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.custom_categories}</p>
                                <p className="text-xs text-muted-foreground">Custom</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center">
                                <TrendingUp className="h-4 w-4 text-orange-600" />
                            </div>
                            <div className="mt-2">
                                <p className="text-2xl font-bold">{stats.with_expenses}</p>
                                <p className="text-xs text-muted-foreground">With Expenses</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters and View Toggle */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search categories..."
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        className="pl-9 w-80"
                                    />
                                </div>

                                <Select value={statusFilter} onValueChange={setStatusFilter}>
                                    <SelectTrigger className="w-40">
                                        <SelectValue placeholder="All Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-2">
                                <Button
                                    variant={viewMode === 'tree' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setViewMode('tree')}
                                >
                                    <FolderTree className="h-4 w-4 mr-2" />
                                    Tree View
                                </Button>
                                <Button
                                    variant={viewMode === 'list' ? 'default' : 'outline'}
                                    size="sm"
                                    onClick={() => setViewMode('list')}
                                >
                                    <List className="h-4 w-4 mr-2" />
                                    List View
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Categories List */}
                <Card>
                    <CardHeader>
                        <CardTitle>Categories</CardTitle>
                        <CardDescription>
                            {viewMode === 'tree' ? 
                                'Hierarchical view of expense categories' : 
                                'Flat list view of all categories'
                            }
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="p-6">
                        {categories.length === 0 ? (
                            <div className="text-center py-12">
                                <FolderTree className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                <h3 className="text-lg font-medium mb-2">No categories found</h3>
                                <p className="text-muted-foreground mb-4">
                                    {search ? 'No categories match your search criteria.' : 'Get started by creating your first expense category.'}
                                </p>
                                {can.create && !search && (
                                    <Button asChild>
                                        <Link href="/expense-categories/create">
                                            <Plus className="h-4 w-4 mr-2" />
                                            Create Category
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        ) : (
                            <div className="space-y-4">
                                {viewMode === 'tree' ? (
                                    parentCategories.map(category => renderTreeCategory(category))
                                ) : (
                                    categories.map(category => renderListCategory(category))
                                )}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}