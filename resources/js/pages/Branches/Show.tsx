import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    ArrowLeft,
    Edit,
    Settings,
    Users,
    Phone,
    Mail,
    Star,
    MoreHorizontal,
    Eye,
    Plus,
    TrendingUp
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Branch {
    id: number;
    name: string;
    code: string;
    address?: string;
    city?: string;
    postal_code?: string;
    phone?: string;
    email?: string;
    status: 'active' | 'inactive';
    is_main_branch: boolean;
    created_at: string;
    updated_at: string;
    company: {
        id: number;
        name: string;
        logo_url?: string;
        status: string;
    };
    users: Array<{
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        avatar_url?: string;
        current_role?: string;
        status: string;
    }>;
    stats: {
        total_users: number;
        active_users: number;
        total_orders: number;
        total_invoices: number;
        monthly_revenue: number;
    };
}

interface Props {
    branch: Branch;
}

export default function ShowBranch({ branch }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Branches', href: '/branches' },
        { title: branch.name, href: `/branches/${branch.id}` },
    ];

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

    const handleStatusToggle = () => {
        router.patch(`/branches/${branch.id}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${branch.name} - Branch Details`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/branches">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Branches
                            </Link>
                        </Button>
                        <div className="flex items-center gap-3">
                            <Avatar className="h-12 w-12">
                                <AvatarImage src={branch.company.logo_url} />
                                <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                                    {branch.company.name.charAt(0)}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="flex items-center gap-2">
                                    <h1 className="text-3xl font-bold tracking-tight">{branch.name}</h1>
                                    {branch.is_main_branch && (
                                        <Star className="h-6 w-6 text-yellow-500 fill-current" />
                                    )}
                                </div>
                                <div className="flex items-center gap-2 text-muted-foreground">
                                    <Badge variant="outline">{branch.code}</Badge>
                                    <span>•</span>
                                    <span>{branch.company.name}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/branches/${branch.id}/edit`}>
                                <Edit className="h-4 w-4 mr-2" />
                                Edit Branch
                            </Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link href={`/branches/${branch.id}/settings`}>
                                        <Settings className="h-4 w-4 mr-2" />
                                        Settings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={`/branches/${branch.id}/users`}>
                                        <Users className="h-4 w-4 mr-2" />
                                        Manage Users
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleStatusToggle}>
                                    <Badge className="h-4 w-4 mr-2" />
                                    {branch.status === 'active' ? 'Deactivate' : 'Activate'} Branch
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Stats Cards */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                                    <Users className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{branch.stats.total_users}</div>
                                    <p className="text-xs text-muted-foreground">
                                        {branch.stats.active_users} active users
                                    </p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Total Orders</CardTitle>
                                    <TrendingUp className="h-4 w-4 text-muted-foreground" />
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">{branch.stats.total_orders}</div>
                                    <p className="text-xs text-muted-foreground">
                                        {branch.stats.total_invoices} invoices generated
                                    </p>
                                </CardContent>
                            </Card>
                            <Card className="md:col-span-2">
                                <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                    <CardTitle className="text-sm font-medium">Monthly Revenue</CardTitle>
                                    <Badge className="text-xs bg-green-100 text-green-800">Revenue</Badge>
                                </CardHeader>
                                <CardContent>
                                    <div className="text-2xl font-bold">
                                        LKR {branch.stats.monthly_revenue.toLocaleString()}
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        Current month performance
                                    </p>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Branch Users */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <div>
                                        <CardTitle>Branch Users</CardTitle>
                                        <CardDescription>Staff members assigned to this branch</CardDescription>
                                    </div>
                                    <Button size="sm" asChild>
                                        <Link href={`/users/create?branch_id=${branch.id}`}>
                                            <Plus className="h-4 w-4 mr-2" />
                                            Add User
                                        </Link>
                                    </Button>
                                </div>
                            </CardHeader>
                            <CardContent>
                                {branch.users && branch.users.length > 0 ? (
                                    <div className="space-y-4">
                                        {branch.users.slice(0, 5).map((user) => (
                                            <div key={user.id} className="flex items-center justify-between">
                                                <div className="flex items-center gap-3">
                                                    <Avatar className="h-10 w-10">
                                                        <AvatarImage src={user.avatar_url} />
                                                        <AvatarFallback>
                                                            {user.first_name.charAt(0)}{user.last_name.charAt(0)}
                                                        </AvatarFallback>
                                                    </Avatar>
                                                    <div>
                                                        <div className="font-medium">
                                                            {user.first_name} {user.last_name}
                                                        </div>
                                                        <div className="text-sm text-muted-foreground">
                                                            {user.email}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2">
                                                    {user.current_role && (
                                                        <Badge variant="outline" className="text-xs">
                                                            {user.current_role}
                                                        </Badge>
                                                    )}
                                                    {getStatusBadge(user.status)}
                                                    <Button variant="ghost" size="sm" asChild>
                                                        <Link href={`/users/${user.id}`}>
                                                            <Eye className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                        {branch.users.length > 5 && (
                                            <div className="text-center">
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/branches/${branch.id}/users`}>
                                                        View {branch.users.length - 5} more users
                                                    </Link>
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <Users className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                        <p className="text-muted-foreground">No users assigned to this branch</p>
                                        <Button size="sm" className="mt-2" asChild>
                                            <Link href={`/users/create?branch_id=${branch.id}`}>
                                                Add First User
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Branch Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Branch Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Status:</span>
                                        {getStatusBadge(branch.status)}
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Type:</span>
                                        <Badge variant={branch.is_main_branch ? "default" : "outline"}>
                                            {branch.is_main_branch ? 'Main Branch' : 'Regular Branch'}
                                        </Badge>
                                    </div>
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm font-medium">Code:</span>
                                        <Badge variant="outline">{branch.code}</Badge>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Contact Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Contact Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {branch.address && (
                                    <div className="flex items-start gap-2">
                                        <MapPin className="h-4 w-4 text-muted-foreground mt-0.5" />
                                        <div>
                                            <p className="text-sm">{branch.address}</p>
                                            {branch.city && (
                                                <p className="text-sm text-muted-foreground">
                                                    {branch.city} {branch.postal_code}
                                                </p>
                                            )}
                                        </div>
                                    </div>
                                )}
                                {branch.phone && (
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">{branch.phone}</span>
                                    </div>
                                )}
                                {branch.email && (
                                    <div className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm">{branch.email}</span>
                                    </div>
                                )}
                                {!branch.address && !branch.phone && !branch.email && (
                                    <p className="text-sm text-muted-foreground">
                                        No contact information available
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Company Information */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Company Information</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center gap-3">
                                    <Avatar className="h-10 w-10">
                                        <AvatarImage src={branch.company.logo_url} />
                                        <AvatarFallback className="bg-primary/10 text-primary font-semibold">
                                            {branch.company.name.charAt(0)}
                                        </AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div className="font-medium">{branch.company.name}</div>
                                        <div className="text-sm text-muted-foreground">
                                            Status: {branch.company.status}
                                        </div>
                                    </div>
                                </div>
                                <Button size="sm" variant="outline" className="w-full mt-4" asChild>
                                    <Link href={`/companies/${branch.company.id}`}>
                                        <Eye className="h-4 w-4 mr-2" />
                                        View Company
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                <Button size="sm" variant="outline" className="w-full" asChild>
                                    <Link href={`/orders/create?branch_id=${branch.id}`}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        Create Order
                                    </Link>
                                </Button>
                                <Button size="sm" variant="outline" className="w-full" asChild>
                                    <Link href={`/customers/create?branch_id=${branch.id}`}>
                                        <Plus className="h-4 w-4 mr-2" />
                                        Add Customer
                                    </Link>
                                </Button>
                                <Button size="sm" variant="outline" className="w-full" asChild>
                                    <Link href={`/reports?branch_id=${branch.id}`}>
                                        <TrendingUp className="h-4 w-4 mr-2" />
                                        View Reports
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}