// resources/js/pages/Customers/Show.tsx

import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    ArrowLeft, 
    Edit, 
    Mail, 
    Phone, 
    MapPin, 
    Building2, 
    CreditCard, 
    Calendar, 
    User, 
    FileText, 
    TrendingUp,
    Receipt,
    Shield,
    Clock,
    AlertCircle,
    CheckCircle,
    XCircle,
    DollarSign,
    Users,
    Settings,
    Download,
    Send,
    MoreHorizontal,
    Eye,
    Trash2
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

// Interfaces
interface Customer {
    id: number;
    customer_code: string;
    name: string;
    display_name: string;
    email: string | null;
    phone: string;
    billing_address: string;
    shipping_address: string | null;
    full_address: string;
    city: string;
    postal_code: string | null;
    district: string | null;
    province: string | null;
    tax_number: string | null;
    credit_limit: number;
    formatted_credit_limit: string;
    current_balance: number;
    formatted_balance: string;
    available_credit: number;
    formatted_available_credit: string;
    status: 'active' | 'inactive' | 'suspended';
    customer_type: 'individual' | 'business';
    date_of_birth: string | null;
    age: number | null;
    calculated_age: number | null;
    company_name: string | null;
    company_registration: string | null;
    contact_person: string | null;
    contact_person_phone: string | null;
    contact_person_email: string | null;
    primary_contact: string;
    emergency_contact: any;
    notes: string | null;
    preferences: any;
    created_at: string;
    updated_at: string;
    branch: {
        id: number;
        name: string;
        code: string;
    } | null;
}

interface Invoice {
    id: number;
    invoice_number: string;
    total_amount: number;
    paid_amount: number;
    status: string;
    created_at: string;
}

interface Statistics {
    total_orders: number;
    total_spent: number;
    average_order_value: number;
    last_order_date: string | null;
    outstanding_amount: number;
}

interface Props {
    customer: Customer;
    recentInvoices: Invoice[];
    statistics: Statistics;
}

// Component
export default function ShowCustomer({ customer, recentInvoices, statistics }: Props) {
    const [activeTab, setActiveTab] = useState('overview');

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Customers', href: '/customers' },
        { title: customer.display_name || customer.name, href: `/customers/${customer.id}` },
    ];

    const getStatusBadge = (status: string) => {
        const variants = {
            active: { variant: 'default' as const, icon: CheckCircle, text: 'Active' },
            inactive: { variant: 'secondary' as const, icon: Clock, text: 'Inactive' },
            suspended: { variant: 'destructive' as const, icon: XCircle, text: 'Suspended' },
        };
        
        const config = variants[status as keyof typeof variants];
        const Icon = config.icon;
        
        return (
            <Badge variant={config.variant} className="flex items-center gap-1">
                <Icon className="h-3 w-3" />
                {config.text}
            </Badge>
        );
    };

    const handleStatusToggle = () => {
        router.patch(`/customers/${customer.id}/toggle-status`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                console.log('Status updated successfully');
            }
        });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
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
            <Head title={`Customer - ${customer.display_name || customer.name}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Button variant="outline" asChild>
                            <Link href="/customers">
                                <ArrowLeft className="h-4 w-4 mr-2" />
                                Back to Customers
                            </Link>
                        </Button>
                        <div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-3xl font-bold tracking-tight">
                                    {customer.display_name || customer.name}
                                </h1>
                                {getStatusBadge(customer.status)}
                                {customer.customer_type === 'business' && (
                                    <Badge variant="outline" className="flex items-center gap-1">
                                        <Building2 className="h-3 w-3" />
                                        Business
                                    </Badge>
                                )}
                            </div>
                            <p className="text-muted-foreground">
                                Customer ID: {customer.customer_code}
                                {customer.branch && ` • Branch: ${customer.branch.name}`}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        <Button asChild>
                            <Link href={`/customers/${customer.id}/edit`}>
                                <Edit className="h-4 w-4 mr-2" />
                                Edit Customer
                            </Link>
                        </Button>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="icon">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link href={`/customers/${customer.id}/orders`}>
                                        <TrendingUp className="h-4 w-4 mr-2" />
                                        View Orders
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={`/invoices?customer=${customer.id}`}>
                                        <Receipt className="h-4 w-4 mr-2" />
                                        View Invoices
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem onClick={handleStatusToggle}>
                                    {customer.status === 'active' ? (
                                        <>
                                            <XCircle className="h-4 w-4 mr-2" />
                                            Deactivate
                                        </>
                                    ) : (
                                        <>
                                            <CheckCircle className="h-4 w-4 mr-2" />
                                            Activate
                                        </>
                                    )}
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                                <DropdownMenuItem className="text-destructive">
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete Customer
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                {/* Quick Stats Cards */}
                <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Orders</p>
                                    <p className="text-2xl font-bold">{statistics.total_orders}</p>
                                </div>
                                <TrendingUp className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Total Spent</p>
                                    <p className="text-2xl font-bold">{formatCurrency(statistics.total_spent)}</p>
                                </div>
                                <DollarSign className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Current Balance</p>
                                    <p className={`text-2xl font-bold ${customer.current_balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                        {customer.formatted_balance}
                                    </p>
                                </div>
                                <CreditCard className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardContent className="p-4">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm text-muted-foreground">Available Credit</p>
                                    <p className="text-2xl font-bold text-green-600">
                                        {customer.formatted_available_credit}
                                    </p>
                                </div>
                                <Shield className="h-8 w-8 text-muted-foreground" />
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content */}
                <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
                    <TabsList className="grid w-full grid-cols-4">
                        <TabsTrigger value="overview">Overview</TabsTrigger>
                        <TabsTrigger value="contact">Contact & Address</TabsTrigger>
                        <TabsTrigger value="financial">Financial Info</TabsTrigger>
                        <TabsTrigger value="activity">Recent Activity</TabsTrigger>
                    </TabsList>

                    {/* Overview Tab */}
                    <TabsContent value="overview" className="space-y-6">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <User className="h-5 w-5" />
                                        Basic Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <p className="text-sm text-muted-foreground">Customer Type</p>
                                            <p className="font-semibold capitalize">{customer.customer_type}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Status</p>
                                            <div className="mt-1">{getStatusBadge(customer.status)}</div>
                                        </div>
                                    </div>

                                    {customer.customer_type === 'business' ? (
                                        <>
                                            <Separator />
                                            <div>
                                                <p className="text-sm text-muted-foreground">Company Name</p>
                                                <p className="font-semibold">{customer.company_name}</p>
                                            </div>
                                            {customer.contact_person && (
                                                <div>
                                                    <p className="text-sm text-muted-foreground">Contact Person</p>
                                                    <p className="font-semibold">{customer.contact_person}</p>
                                                </div>
                                            )}
                                            {customer.company_registration && (
                                                <div>
                                                    <p className="text-sm text-muted-foreground">Registration Number</p>
                                                    <p className="font-mono text-sm">{customer.company_registration}</p>
                                                </div>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            {customer.date_of_birth && (
                                                <>
                                                    <Separator />
                                                    <div className="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <p className="text-sm text-muted-foreground">Date of Birth</p>
                                                            <p className="font-semibold">{formatDate(customer.date_of_birth)}</p>
                                                        </div>
                                                        {customer.calculated_age && (
                                                            <div>
                                                                <p className="text-sm text-muted-foreground">Age</p>
                                                                <p className="font-semibold">{customer.calculated_age} years</p>
                                                            </div>
                                                        )}
                                                    </div>
                                                </>
                                            )}
                                        </>
                                    )}

                                    <Separator />
                                    <div className="grid grid-cols-2 gap-4 text-xs text-muted-foreground">
                                        <div>
                                            <p>Created: {formatDate(customer.created_at)}</p>
                                        </div>
                                        <div>
                                            <p>Updated: {formatDate(customer.updated_at)}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Recent Invoices */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Receipt className="h-5 w-5" />
                                            Recent Invoices
                                        </div>
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={`/invoices?customer=${customer.id}`}>
                                                View All
                                            </Link>
                                        </Button>
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {recentInvoices && recentInvoices.length > 0 ? (
                                        <div className="space-y-3">
                                            {recentInvoices.map((invoice) => (
                                                <div key={invoice.id} className="flex items-center justify-between p-3 border rounded-lg">
                                                    <div>
                                                        <p className="font-semibold">{invoice.invoice_number}</p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {formatDate(invoice.created_at)}
                                                        </p>
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="font-semibold">{formatCurrency(invoice.total_amount)}</p>
                                                        <Badge variant={invoice.status === 'paid' ? 'default' : 'secondary'} className="text-xs">
                                                            {invoice.status}
                                                        </Badge>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <Receipt className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                            <p className="text-muted-foreground">No invoices found</p>
                                            <Button className="mt-4" asChild>
                                                <Link href={`/invoices/create?customer=${customer.id}`}>
                                                    Create Invoice
                                                </Link>
                                            </Button>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>

                        {/* Notes */}
                        {customer.notes && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Notes
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm leading-relaxed">{customer.notes}</p>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Contact & Address Tab */}
                    <TabsContent value="contact" className="space-y-6">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Contact Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Phone className="h-5 w-5" />
                                        Contact Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center gap-3">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <div>
                                            <p className="text-sm text-muted-foreground">Primary Phone</p>
                                            <p className="font-semibold">{customer.phone}</p>
                                        </div>
                                    </div>

                                    {customer.email && (
                                        <div className="flex items-center gap-3">
                                            <Mail className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm text-muted-foreground">Email Address</p>
                                                <p className="font-semibold">{customer.email}</p>
                                            </div>
                                        </div>
                                    )}

                                    {customer.customer_type === 'business' && (
                                        <>
                                            {customer.contact_person_phone && (
                                                <div className="flex items-center gap-3">
                                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                                    <div>
                                                        <p className="text-sm text-muted-foreground">Contact Person Phone</p>
                                                        <p className="font-semibold">{customer.contact_person_phone}</p>
                                                    </div>
                                                </div>
                                            )}

                                            {customer.contact_person_email && (
                                                <div className="flex items-center gap-3">
                                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                                    <div>
                                                        <p className="text-sm text-muted-foreground">Contact Person Email</p>
                                                        <p className="font-semibold">{customer.contact_person_email}</p>
                                                    </div>
                                                </div>
                                            )}
                                        </>
                                    )}

                                    {customer.emergency_contact && (
                                        <>
                                            <Separator />
                                            <div>
                                                <p className="text-sm text-muted-foreground mb-2">Emergency Contact</p>
                                                <div className="space-y-2 text-sm">
                                                    <p><span className="font-medium">Name:</span> {customer.emergency_contact.name}</p>
                                                    <p><span className="font-medium">Phone:</span> {customer.emergency_contact.phone}</p>
                                                    <p><span className="font-medium">Relationship:</span> {customer.emergency_contact.relationship}</p>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Address Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <MapPin className="h-5 w-5" />
                                        Address Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <p className="text-sm text-muted-foreground">Billing Address</p>
                                        <div className="mt-1 p-3 bg-muted rounded-lg">
                                            <p className="text-sm leading-relaxed">{customer.billing_address}</p>
                                            <div className="mt-2 text-xs text-muted-foreground">
                                                {customer.city}
                                                {customer.postal_code && `, ${customer.postal_code}`}
                                                {customer.district && `, ${customer.district}`}
                                                {customer.province && `, ${customer.province}`}
                                            </div>
                                        </div>
                                    </div>

                                    {customer.shipping_address && customer.shipping_address !== customer.billing_address && (
                                        <div>
                                            <p className="text-sm text-muted-foreground">Shipping Address</p>
                                            <div className="mt-1 p-3 bg-muted rounded-lg">
                                                <p className="text-sm leading-relaxed">{customer.shipping_address}</p>
                                            </div>
                                        </div>
                                    )}

                                    {customer.tax_number && (
                                        <>
                                            <Separator />
                                            <div>
                                                <p className="text-sm text-muted-foreground">Tax Number</p>
                                                <p className="font-mono text-sm font-semibold">{customer.tax_number}</p>
                                            </div>
                                        </>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Financial Info Tab */}
                    <TabsContent value="financial" className="space-y-6">
                        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            {/* Credit Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="h-5 w-5" />
                                        Credit Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="space-y-4">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Credit Limit</span>
                                            <span className="font-semibold">{customer.formatted_credit_limit}</span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Current Balance</span>
                                            <span className={`font-semibold ${customer.current_balance > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                {customer.formatted_balance}
                                            </span>
                                        </div>
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Available Credit</span>
                                            <span className="font-semibold text-green-600">{customer.formatted_available_credit}</span>
                                        </div>
                                    </div>

                                    <Separator />

                                    {/* Credit Utilization */}
                                    <div>
                                        <div className="flex justify-between items-center mb-2">
                                            <span className="text-sm text-muted-foreground">Credit Utilization</span>
                                            <span className="text-sm font-medium">
                                                {customer.credit_limit > 0 
                                                    ? `${Math.round((customer.current_balance / customer.credit_limit) * 100)}%`
                                                    : '0%'
                                                }
                                            </span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2">
                                            <div 
                                                className={`h-2 rounded-full ${
                                                    customer.current_balance / customer.credit_limit > 0.8 
                                                        ? 'bg-red-600' 
                                                        : customer.current_balance / customer.credit_limit > 0.6 
                                                        ? 'bg-yellow-600' 
                                                        : 'bg-green-600'
                                                }`}
                                                style={{ 
                                                    width: `${customer.credit_limit > 0 
                                                        ? Math.min((customer.current_balance / customer.credit_limit) * 100, 100)
                                                        : 0
                                                    }%` 
                                                }}
                                            ></div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Payment Statistics */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <TrendingUp className="h-5 w-5" />
                                        Payment Statistics
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="text-center p-4 border rounded-lg">
                                            <p className="text-2xl font-bold text-blue-600">{statistics.total_orders}</p>
                                            <p className="text-sm text-muted-foreground">Total Orders</p>
                                        </div>
                                        <div className="text-center p-4 border rounded-lg">
                                            <p className="text-2xl font-bold text-green-600">{formatCurrency(statistics.total_spent)}</p>
                                            <p className="text-sm text-muted-foreground">Total Spent</p>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-muted-foreground">Average Order Value</span>
                                            <span className="font-semibold">{formatCurrency(statistics.average_order_value)}</span>
                                        </div>
                                        {statistics.last_order_date && (
                                            <div className="flex justify-between">
                                                <span className="text-sm text-muted-foreground">Last Order</span>
                                                <span className="font-semibold">{formatDate(statistics.last_order_date)}</span>
                                            </div>
                                        )}
                                        <div className="flex justify-between">
                                            <span className="text-sm text-muted-foreground">Outstanding Amount</span>
                                            <span className={`font-semibold ${statistics.outstanding_amount > 0 ? 'text-red-600' : 'text-green-600'}`}>
                                                {formatCurrency(statistics.outstanding_amount)}
                                            </span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Payment Preferences */}
                        {customer.preferences && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Settings className="h-5 w-5" />
                                        Payment Preferences
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <p className="text-sm text-muted-foreground">Payment Terms</p>
                                            <p className="font-semibold">
                                                {customer.preferences.payment_terms === '0' ? 'Cash Only' : `${customer.preferences.payment_terms} Days`}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Preferred Communication</p>
                                            <p className="font-semibold capitalize">{customer.preferences.communication_method}</p>
                                        </div>
                                        <div>
                                            <p className="text-sm text-muted-foreground">Language Preference</p>
                                            <p className="font-semibold">
                                                {customer.preferences.language === 'en' ? 'English' : 
                                                 customer.preferences.language === 'si' ? 'Sinhala' : 'Tamil'}
                                            </p>
                                        </div>
                                    </div>

                                    <Separator className="my-4" />

                                    <div className="space-y-2">
                                        <p className="text-sm text-muted-foreground">Notification Preferences</p>
                                        <div className="flex flex-wrap gap-2">
                                            {customer.preferences.email_notifications && (
                                                <Badge variant="outline">Email Notifications</Badge>
                                            )}
                                            {customer.preferences.sms_notifications && (
                                                <Badge variant="outline">SMS Notifications</Badge>
                                            )}
                                            {customer.preferences.newsletter && (
                                                <Badge variant="outline">Newsletter</Badge>
                                            )}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </TabsContent>

                    {/* Recent Activity Tab */}
                    <TabsContent value="activity" className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5" />
                                    Recent Activity
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                {recentInvoices && recentInvoices.length > 0 ? (
                                    <div className="space-y-4">
                                        {recentInvoices.map((invoice, index) => (
                                            <div key={invoice.id} className="flex items-start gap-4 pb-4 border-b last:border-b-0">
                                                <div className="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                                    <Receipt className="h-4 w-4 text-blue-600" />
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="flex items-center justify-between">
                                                        <p className="font-semibold">Invoice {invoice.invoice_number}</p>
                                                        <Badge variant={invoice.status === 'paid' ? 'default' : 'secondary'}>
                                                            {invoice.status}
                                                        </Badge>
                                                    </div>
                                                    <p className="text-sm text-muted-foreground">
                                                        Amount: {formatCurrency(invoice.total_amount)}
                                                        {invoice.paid_amount > 0 && (
                                                            <span> • Paid: {formatCurrency(invoice.paid_amount)}</span>
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground mt-1">
                                                        {formatDate(invoice.created_at)}
                                                    </p>
                                                </div>
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link href={`/invoices/${invoice.id}`}>
                                                        <Eye className="h-4 w-4 mr-1" />
                                                        View
                                                    </Link>
                                                </Button>
                                            </div>
                                        ))}
                                        
                                        <div className="text-center pt-4">
                                            <Button variant="outline" asChild>
                                                <Link href={`/invoices?customer=${customer.id}`}>
                                                    View All Invoices
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="text-center py-12">
                                        <Clock className="h-12 w-12 text-muted-foreground mx-auto mb-4" />
                                        <h3 className="text-lg font-semibold mb-2">No Recent Activity</h3>
                                        <p className="text-muted-foreground mb-4">
                                            This customer hasn't made any orders yet.
                                        </p>
                                        <Button asChild>
                                            <Link href={`/invoices/create?customer=${customer.id}`}>
                                                Create First Invoice
                                            </Link>
                                        </Button>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </AppLayout>
    );
}