import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { 
    ArrowLeft, 
    Edit, 
    MoreHorizontal,
    Building2,
    Users,
    MapPin,
    Phone,
    Mail,
    Calendar,
    Hash,
    CreditCard,
    Settings,
    TrendingUp,
    Trash2,
    Plus,
    Eye
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
    tax_rate: number;
    tax_number?: string;
    bank_details?: {
        bank_name?: string;
        account_number?: string;
        account_holder?: string;
        swift_code?: string;
    };
    created_at: string;
    updated_at: string;
    branches: Array<{
        id: number;
        name: string;
        code: string;
        address?: string;
        phone?: string;
        status: string;
        is_main_branch: boolean;
        users_count: number;
    }>;
    users: Array<{
        id: number;
        first_name: string;
        last_name: string;
        email: string;
        status: string;
        roles: Array<{ name: string }>;
    }>;
}

interface Props {
    company: Company;
    stats: {
        total_branches: number;
        active_branches: number;
        total_users: number;
        active_users: number;
    };
}

export default function ShowCompany({ company, stats }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Companies', href: '/companies' },
        { title: company.name, href: `/companies/${company.id}` },
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
        router.patch(`/companies/${company.id}/toggle-status`, {}, {
            preserveScroll: true,
        });
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this company? This action cannot be undone.')) {
            router.delete(`/companies/${company.id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${company.name} - Company Details`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/companies">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="flex items-center gap-4">
                            <Avatar className="h-16 w-16">
                                <AvatarImage src={company.logo_url} alt={company.name} />
                                <AvatarFallback className="text-lg">
                                    {company.name.slice(0, 2).toUpperCase()}
                                </AvatarFallback>
                            </Avatar>
                            <div>
                                <div className="flex items-center gap-3">
                                    <h1 className="text-3xl font-bold tracking-tight">{company.name}</h1>
                                    {getStatusBadge(company.status)}
                                </div>
                                <p className="text-muted-foreground">Company Details</p>
                            </div>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-2">
                        <Button variant="outline" asChild>
                            <Link href={`/companies/${company.id}/edit`}>
                                <Edit className="h-4 w-4 mr-2" />
                                Edit
                            </Link>
                        </Button>
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link href={`/companies/${company.id}/settings`}>
                                        <Settings className="h-4 w-4 mr-2" />
                                        Settings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem onClick={handleStatusToggle}>
                                    <TrendingUp className="h-4 w-4 mr-2" />
                                    {company.status === 'active' ? 'Deactivate' : 'Activate'}
                                </DropdownMenuItem>
                                <DropdownMenuItem 
                                    onClick={handleDelete}
                                    className="text-destructive"
                                >
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Branches</CardTitle>
                            <Building2 className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_branches}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.active_branches} active
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Users</CardTitle>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_users}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.active_users} active
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Tax Rate</CardTitle>
                            <CreditCard className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{company.tax_rate}%</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Member Since</CardTitle>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-lg font-bold">
                                {new Date(company.created_at).toLocaleDateString()}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Company Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Company Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {company.registration_number && (
                                <div className="flex items-center gap-3">
                                    <Hash className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{company.registration_number}</p>
                                        <p className="text-sm text-muted-foreground">Registration Number</p>
                                    </div>
                                </div>
                            )}
                            {company.email && (
                                <div className="flex items-center gap-3">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{company.email}</p>
                                        <p className="text-sm text-muted-foreground">Email</p>
                                    </div>
                                </div>
                            )}
                            {company.phone && (
                                <div className="flex items-center gap-3">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{company.phone}</p>
                                        <p className="text-sm text-muted-foreground">Phone</p>
                                    </div>
                                </div>
                            )}
                            {company.address && (
                                <div className="flex items-center gap-3">
                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{company.address}</p>
                                        <p className="text-sm text-muted-foreground">Address</p>
                                    </div>
                                </div>
                            )}
                            {company.tax_number && (
                                <div className="flex items-center gap-3">
                                    <CreditCard className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{company.tax_number}</p>
                                        <p className="text-sm text-muted-foreground">Tax Number</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Bank Information */}
                    {company.bank_details && (
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <CreditCard className="h-5 w-5" />
                                    Bank Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                {company.bank_details.bank_name && (
                                    <div>
                                        <p className="font-medium">{company.bank_details.bank_name}</p>
                                        <p className="text-sm text-muted-foreground">Bank Name</p>
                                    </div>
                                )}
                                {company.bank_details.account_number && (
                                    <div>
                                        <p className="font-medium">{company.bank_details.account_number}</p>
                                        <p className="text-sm text-muted-foreground">Account Number</p>
                                    </div>
                                )}
                                {company.bank_details.account_holder && (
                                    <div>
                                        <p className="font-medium">{company.bank_details.account_holder}</p>
                                        <p className="text-sm text-muted-foreground">Account Holder</p>
                                    </div>
                                )}
                                {company.bank_details.swift_code && (
                                    <div>
                                        <p className="font-medium">{company.bank_details.swift_code}</p>
                                        <p className="text-sm text-muted-foreground">SWIFT Code</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}
                </div>

                {/* Branches */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Branches ({company.branches.length})
                            </CardTitle>
                            <Button size="sm" asChild>
                                <Link href={`/companies/${company.id}/branches`}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    View All
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {company.branches.length > 0 ? (
                            <div className="space-y-3">
                                {company.branches.slice(0, 5).map((branch) => (
                                    <div
                                        key={branch.id}
                                        className="flex items-center justify-between p-3 border rounded-lg"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className={`h-3 w-3 rounded-full ${
                                                branch.status === 'active' ? 'bg-green-500' : 'bg-red-500'
                                            }`} />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">{branch.name}</span>
                                                    <Badge variant="outline" className="text-xs">
                                                        {branch.code}
                                                    </Badge>
                                                    {branch.is_main_branch && (
                                                        <Badge variant="default" className="text-xs">
                                                            Main
                                                        </Badge>
                                                    )}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {branch.users_count} users
                                                    {branch.address && ` • ${branch.address}`}
                                                </div>
                                            </div>
                                        </div>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={`/branches/${branch.id}`}>
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                                {company.branches.length > 5 && (
                                    <div className="text-center">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/companies/${company.id}/branches`}>
                                                View {company.branches.length - 5} more branches
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <Building2 className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                <p className="text-muted-foreground">No branches found</p>
                            </div>
                        )}
                    </CardContent>
                </Card>

                {/* Recent Users */}
                <Card>
                    <CardHeader>
                        <div className="flex items-center justify-between">
                            <CardTitle className="flex items-center gap-2">
                                <Users className="h-5 w-5" />
                                Recent Users ({company.users.length})
                            </CardTitle>
                            <Button size="sm" asChild>
                                <Link href={`/companies/${company.id}/users`}>
                                    <Eye className="h-4 w-4 mr-2" />
                                    View All
                                </Link>
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        {company.users.length > 0 ? (
                            <div className="space-y-3">
                                {company.users.slice(0, 5).map((user) => (
                                    <div
                                        key={user.id}
                                        className="flex items-center justify-between p-3 border rounded-lg"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className={`h-3 w-3 rounded-full ${
                                                user.status === 'active' ? 'bg-green-500' : 'bg-red-500'
                                            }`} />
                                            <div>
                                                <span className="font-medium">
                                                    {user.first_name} {user.last_name}
                                                </span>
                                                <div className="text-sm text-muted-foreground">
                                                    {user.email} • {user.roles.map(r => r.name).join(', ')}
                                                </div>
                                            </div>
                                        </div>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={`/users/${user.id}`}>
                                                <Eye className="h-4 w-4" />
                                            </Link>
                                        </Button>
                                    </div>
                                ))}
                                {company.users.length > 5 && (
                                    <div className="text-center">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/companies/${company.id}/users`}>
                                                View {company.users.length - 5} more users
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </div>
                        ) : (
                            <div className="text-center py-8">
                                <Users className="h-8 w-8 text-muted-foreground mx-auto mb-2" />
                                <p className="text-muted-foreground">No users found</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}