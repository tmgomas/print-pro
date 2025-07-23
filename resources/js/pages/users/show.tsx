
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
    ArrowLeft, 
    Edit, 
    Mail, 
    Phone, 
    Building, 
    MapPin, 
    Calendar,
    Shield,
    Clock,
    Activity
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface User {
    id: number;
    first_name: string;
    last_name: string;
    full_name: string;
    email: string;
    phone?: string;
    status: string;
    avatar_url?: string;
    last_login_at?: string;
    last_login_ip?: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
    company?: {
        id: number;
        name: string;
        email: string;
        status: string;
    };
    branch?: {
        id: number;
        name: string;
        code: string;
        status: string;
    };
    roles: Array<{
        id: number;
        name: string;
    }>;
    permissions: Array<{
        id: number;
        name: string;
    }>;
}

interface Props {
    user: User;
    permissions: {
        canEdit: boolean;
        canDelete: boolean;
        canActivate: boolean;
    };
}

export default function ShowUser({ user, permissions }: Props) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Users', href: '/users' },
        { title: user.full_name, href: `/users/${user.id}` },
    ];

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active': return 'bg-green-100 text-green-800';
            case 'inactive': return 'bg-red-100 text-red-800';
            case 'suspended': return 'bg-yellow-100 text-yellow-800';
            default: return 'bg-gray-100 text-gray-800';
        }
    };

    const handleStatusToggle = () => {
        const action = user.status === 'active' ? 'deactivate' : 'activate';
        if (confirm(`Are you sure you want to ${action} this user?`)) {
            router.post(`/users/${user.id}/${action}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${user.full_name} - User Profile`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="ghost" size="sm" asChild>
                            <Link href="/users">
                                <ArrowLeft className="h-4 w-4" />
                            </Link>
                        </Button>
                        <div className="flex items-center gap-4">
                            <div className="w-16 h-16 rounded-full bg-muted flex items-center justify-center overflow-hidden">
                                {user.avatar_url ? (
                                    <img src={user.avatar_url} alt={user.full_name} className="w-full h-full object-cover" />
                                ) : (
                                    <span className="text-2xl font-medium text-muted-foreground">
                                        {user.first_name.charAt(0).toUpperCase()}
                                    </span>
                                )}
                            </div>
                            <div>
                                <h1 className="text-3xl font-bold tracking-tight">{user.full_name}</h1>
                                <p className="text-muted-foreground">{user.email}</p>
                                <div className="flex items-center gap-2 mt-2">
                                    <span className={`inline-flex items-center px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(user.status)}`}>
                                        {user.status}
                                    </span>
                                    {user.roles.map((role) => (
                                        <Badge key={role.id} variant="secondary">
                                            {role.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {permissions.canActivate && (
                            <Button 
                                variant="outline" 
                                onClick={handleStatusToggle}
                            >
                                {user.status === 'active' ? 'Deactivate' : 'Activate'}
                            </Button>
                        )}
                        {permissions.canEdit && (
                            <Button asChild>
                                <Link href={`/users/${user.id}/edit`}>
                                    <Edit className="h-4 w-4 mr-2" />
                                    Edit User
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {/* User Details */}
                <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                    {/* Contact Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Mail className="h-5 w-5" />
                                Contact Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Mail className="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">{user.email}</p>
                                    <p className="text-sm text-muted-foreground">
                                        {user.email_verified_at ? 'Verified' : 'Not verified'}
                                    </p>
                                </div>
                            </div>
                            {user.phone && (
                                <div className="flex items-center gap-3">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{user.phone}</p>
                                        <p className="text-sm text-muted-foreground">Phone number</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Organization */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building className="h-5 w-5" />
                                Organization
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {user.company && (
                                <div className="flex items-center gap-3">
                                    <Building className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{user.company.name}</p>
                                        <p className="text-sm text-muted-foreground">Company</p>
                                    </div>
                                </div>
                            )}
                            {user.branch && (
                                <div className="flex items-center gap-3">
                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{user.branch.name}</p>
                                        <p className="text-sm text-muted-foreground">Branch ({user.branch.code})</p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Account Information */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Clock className="h-5 w-5" />
                                Account Information
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="flex items-center gap-3">
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                <div>
                                    <p className="font-medium">
                                        {new Date(user.created_at).toLocaleDateString()}
                                    </p>
                                    <p className="text-sm text-muted-foreground">Member since</p>
                                </div>
                            </div>
                            {user.last_login_at && (
                                <div className="flex items-center gap-3">
                                    <Activity className="h-4 w-4 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">
                                            {new Date(user.last_login_at).toLocaleDateString()}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            Last login {user.last_login_ip && `from ${user.last_login_ip}`}
                                        </p>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Permissions */}
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Shield className="h-5 w-5" />
                            Permissions & Roles
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <div>
                                <h4 className="font-medium mb-2">Roles</h4>
                                <div className="flex flex-wrap gap-2">
                                    {user.roles.map((role) => (
                                        <Badge key={role.id} variant="outline">
                                            {role.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                            <div>
                                <h4 className="font-medium mb-2">Permissions</h4>
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-2">
                                    {user.permissions.map((permission) => (
                                        <Badge key={permission.id} variant="secondary" className="text-xs">
                                            {permission.name}
                                        </Badge>
                                    ))}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}