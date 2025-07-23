import { useState, useEffect } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { 
    Users, 
    Building2, 
    ShoppingCart, 
    DollarSign, 
    TrendingUp, 
    TrendingDown,
    Clock,
    CheckCircle,
    XCircle,
    Activity,
    Package,
    CreditCard,
    Eye,
    RefreshCw
} from 'lucide-react';

// Types
interface DashboardStats {
    total_users: number;
    total_companies: number;
    total_orders: number;
    total_revenue: number;
    monthly_growth: number;
    pending_orders: number;
    completed_orders: number;
    cancelled_orders: number;
}

interface ChartData {
    sales_chart: Array<{ month: string; sales: number; timestamp: number }>;
    orders_chart: Array<{ month: string; orders: number; timestamp: number }>;
    users_chart: Array<{ date: string; users: number; timestamp: number }>;
    revenue_chart: Array<{ date: string; revenue: number; timestamp: number }>;
}

interface RecentData {
    recent_orders: Array<{
        id: number;
        order_number: string;
        total_amount: number;
        status: string;
        created_at: string;
        customer_name: string;
        customer_email: string;
    }>;
    recent_users: Array<{
        id: number;
        name: string;
        email: string;
        created_at: string;
    }>;
    recent_payments: Array<{
        id: number;
        amount: number;
        status: string;
        payment_method: string;
        created_at: string;
        order_number: string;
        customer_name: string;
    }>;
    top_products: Array<{
        id: number;
        name: string;
        price: number;
        total_sold: number;
        total_revenue: number;
    }>;
}

interface Widgets {
    users: boolean;
    companies: boolean;
    orders: boolean;
    products: boolean;
    payments: boolean;
    reports: boolean;
}

interface User {
    id: number;
    name: string;
    email: string;
    company?: {
        id: number;
        name: string;
    };
    branch?: {
        id: number;
        name: string;
    };
}

interface DashboardProps {
    stats: DashboardStats;
    chartData: ChartData;
    recentData: RecentData;
    widgets: Widgets;
    user: User;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

// Utility Functions
const formatCurrency = (amount: number): string => {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
    }).format(amount);
};

const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getStatusColor = (status: string): string => {
    switch (status.toLowerCase()) {
        case 'completed':
        case 'success':
            return 'text-green-600 bg-green-100';
        case 'pending':
        case 'processing':
            return 'text-yellow-600 bg-yellow-100';
        case 'cancelled':
        case 'failed':
            return 'text-red-600 bg-red-100';
        default:
            return 'text-gray-600 bg-gray-100';
    }
};

// Components
const StatCard = ({ 
    title, 
    value, 
    icon: Icon, 
    trend, 
    trendValue, 
    color = 'blue' 
}: {
    title: string;
    value: string | number;
    icon: any;
    trend?: 'up' | 'down';
    trendValue?: number;
    color?: string;
}) => {
    const colorClasses = {
        blue: 'border-blue-500 text-blue-600',
        green: 'border-green-500 text-green-600',
        yellow: 'border-yellow-500 text-yellow-600',
        red: 'border-red-500 text-red-600',
        purple: 'border-purple-500 text-purple-600',
    };

    return (
        <div className="bg-white rounded-lg shadow border-l-4 border-sidebar-border/70 dark:border-sidebar-border p-6">
            <div className="flex items-center justify-between">
                <div>
                    <p className="text-sm font-medium text-gray-600 dark:text-gray-400">{title}</p>
                    <p className="text-2xl font-bold text-gray-900 dark:text-white">{value}</p>
                    {trend && trendValue !== undefined && (
                        <div className="flex items-center mt-1">
                            {trend === 'up' ? (
                                <TrendingUp className="w-4 h-4 text-green-500" />
                            ) : (
                                <TrendingDown className="w-4 h-4 text-red-500" />
                            )}
                            <span className={`text-sm ml-1 ${trend === 'up' ? 'text-green-600' : 'text-red-600'}`}>
                                {Math.abs(trendValue)}%
                            </span>
                        </div>
                    )}
                </div>
                <div className={`p-3 rounded-full ${colorClasses[color as keyof typeof colorClasses]} bg-opacity-10`}>
                    <Icon className="w-6 h-6" />
                </div>
            </div>
        </div>
    );
};

const SimpleChart = ({ 
    data, 
    title, 
    dataKey, 
    color = '#3B82F6' 
}: {
    data: any[];
    title: string;
    dataKey: string;
    color?: string;
}) => {
    if (!data || data.length === 0) {
        return (
            <div className="bg-white rounded-lg shadow p-6">
                <h3 className="text-lg font-semibold mb-4">{title}</h3>
                <div className="flex items-center justify-center h-48 text-gray-500">
                    No data available
                </div>
            </div>
        );
    }

    const maxValue = Math.max(...data.map(item => item[dataKey]));
    
    return (
        <div className="bg-white rounded-lg shadow p-6">
            <h3 className="text-lg font-semibold mb-4">{title}</h3>
            <div className="space-y-2">
                {data.slice(-6).map((item, index) => (
                    <div key={index} className="flex items-center justify-between">
                        <span className="text-sm text-gray-600">
                            {item.month || item.date}
                        </span>
                        <div className="flex items-center flex-1 ml-4">
                            <div className="flex-1 bg-gray-200 rounded-full h-2 mr-2">
                                <div
                                    className="h-2 rounded-full"
                                    style={{
                                        width: `${(item[dataKey] / maxValue) * 100}%`,
                                        backgroundColor: color,
                                    }}
                                />
                            </div>
                            <span className="text-sm font-medium min-w-0">
                                {typeof item[dataKey] === 'number' && dataKey.includes('sales') || dataKey.includes('revenue') 
                                    ? formatCurrency(item[dataKey])
                                    : item[dataKey]
                                }
                            </span>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
};

const RecentList = ({ 
    title, 
    items, 
    renderItem, 
    emptyMessage = "No recent items" 
}: {
    title: string;
    items: any[];
    renderItem: (item: any, index: number) => React.ReactNode;
    emptyMessage?: string;
}) => (
    <div className="bg-white rounded-lg shadow p-6">
        <h3 className="text-lg font-semibold mb-4">{title}</h3>
        <div className="space-y-3">
            {items && items.length > 0 ? (
                items.slice(0, 5).map(renderItem)
            ) : (
                <p className="text-gray-500 text-center py-4">{emptyMessage}</p>
            )}
        </div>
    </div>
);

export default function Dashboard({ stats, chartData, recentData, widgets, user }: DashboardProps) {
    const [isRefreshing, setIsRefreshing] = useState(false);

    const refreshDashboard = async () => {
        setIsRefreshing(true);
        try {
            router.reload();
        } finally {
            setTimeout(() => setIsRefreshing(false), 1000);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            
            <div className="flex h-full flex-1 flex-col gap-6 rounded-xl p-6 overflow-x-auto">
                {/* Header */}
                <div className="flex justify-between items-start">
                    <div>
                        <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Dashboard</h1>
                        <p className="text-gray-600 dark:text-gray-400 mt-1">
                            Welcome back, {user.name}! 
                            {user.company && ` • ${user.company.name}`}
                            {user.branch && ` • ${user.branch.name}`}
                        </p>
                    </div>
                    <button
                        onClick={refreshDashboard}
                        disabled={isRefreshing}
                        className="inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 text-white font-medium rounded-lg transition-colors"
                    >
                        <RefreshCw className={`w-4 h-4 mr-2 ${isRefreshing ? 'animate-spin' : ''}`} />
                        Refresh
                    </button>
                </div>

                {/* Stats Grid */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    {widgets.users && (
                        <StatCard
                            title="Total Users"
                            value={stats.total_users.toLocaleString()}
                            icon={Users}
                            color="blue"
                        />
                    )}
                    {widgets.companies && (
                        <StatCard
                            title="Total Companies"
                            value={stats.total_companies.toLocaleString()}
                            icon={Building2}
                            color="purple"
                        />
                    )}
                    {widgets.orders && (
                        <StatCard
                            title="Total Orders"
                            value={stats.total_orders.toLocaleString()}
                            icon={ShoppingCart}
                            color="green"
                        />
                    )}
                    <StatCard
                        title="Total Revenue"
                        value={formatCurrency(stats.total_revenue)}
                        icon={DollarSign}
                        trend={stats.monthly_growth >= 0 ? 'up' : 'down'}
                        trendValue={stats.monthly_growth}
                        color="yellow"
                    />
                </div>

                {/* Order Status Cards */}
                {widgets.orders && (
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <StatCard
                            title="Pending Orders"
                            value={stats.pending_orders.toLocaleString()}
                            icon={Clock}
                            color="yellow"
                        />
                        <StatCard
                            title="Completed Orders"
                            value={stats.completed_orders.toLocaleString()}
                            icon={CheckCircle}
                            color="green"
                        />
                        <StatCard
                            title="Cancelled Orders"
                            value={stats.cancelled_orders.toLocaleString()}
                            icon={XCircle}
                            color="red"
                        />
                    </div>
                )}

                {/* Charts Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <SimpleChart
                        data={chartData.sales_chart}
                        title="Monthly Sales"
                        dataKey="sales"
                        color="#10B981"
                    />
                    <SimpleChart
                        data={chartData.orders_chart}
                        title="Monthly Orders"
                        dataKey="orders"
                        color="#3B82F6"
                    />
                    <SimpleChart
                        data={chartData.users_chart}
                        title="User Registrations (Last 30 Days)"
                        dataKey="users"
                        color="#8B5CF6"
                    />
                    <SimpleChart
                        data={chartData.revenue_chart}
                        title="Daily Revenue (Last 7 Days)"
                        dataKey="revenue"
                        color="#F59E0B"
                    />
                </div>

                {/* Recent Data Grid */}
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {/* Recent Orders */}
                    {widgets.orders && (
                        <RecentList
                            title="Recent Orders"
                            items={recentData.recent_orders}
                            renderItem={(order, index) => (
                                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div className="flex-1">
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium text-gray-900 dark:text-white">
                                                {order.order_number}
                                            </span>
                                            <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(order.status)}`}>
                                                {order.status}
                                            </span>
                                        </div>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">
                                            {order.customer_name} • {formatCurrency(order.total_amount)}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {formatDate(order.created_at)}
                                        </p>
                                    </div>
                                </div>
                            )}
                            emptyMessage="No recent orders"
                        />
                    )}

                    {/* Recent Users */}
                    {widgets.users && (
                        <RecentList
                            title="Recent Users"
                            items={recentData.recent_users}
                            renderItem={(user, index) => (
                                <div key={index} className="flex items-center p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div className="w-10 h-10 bg-blue-500 rounded-full flex items-center justify-center text-white font-medium">
                                        {user.name.charAt(0).toUpperCase()}
                                    </div>
                                    <div className="ml-3 flex-1">
                                        <p className="font-medium text-gray-900 dark:text-white">{user.name}</p>
                                        <p className="text-sm text-gray-600 dark:text-gray-400">{user.email}</p>
                                        <p className="text-xs text-gray-500">{formatDate(user.created_at)}</p>
                                    </div>
                                </div>
                            )}
                            emptyMessage="No recent users"
                        />
                    )}

                    {/* Recent Payments */}
                    {widgets.payments && (
                        <RecentList
                            title="Recent Payments"
                            items={recentData.recent_payments}
                            renderItem={(payment, index) => (
                                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div className="flex items-center">
                                        <div className="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center">
                                            <CreditCard className="w-5 h-5 text-white" />
                                        </div>
                                        <div className="ml-3">
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                {formatCurrency(payment.amount)}
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {payment.order_number} • {payment.customer_name}
                                            </p>
                                            <p className="text-xs text-gray-500">
                                                {formatDate(payment.created_at)}
                                            </p>
                                        </div>
                                    </div>
                                    <span className={`px-2 py-1 rounded-full text-xs font-medium ${getStatusColor(payment.status)}`}>
                                        {payment.status}
                                    </span>
                                </div>
                            )}
                            emptyMessage="No recent payments"
                        />
                    )}

                    {/* Top Products */}
                    {widgets.products && (
                        <RecentList
                            title="Top Selling Products"
                            items={recentData.top_products}
                            renderItem={(product, index) => (
                                <div key={index} className="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                    <div className="flex items-center">
                                        <div className="w-10 h-10 bg-purple-500 rounded-full flex items-center justify-center">
                                            <Package className="w-5 h-5 text-white" />
                                        </div>
                                        <div className="ml-3">
                                            <p className="font-medium text-gray-900 dark:text-white">
                                                {product.name}
                                            </p>
                                            <p className="text-sm text-gray-600 dark:text-gray-400">
                                                {product.total_sold} sold • {formatCurrency(product.price)}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium text-gray-900 dark:text-white">
                                            {formatCurrency(product.total_revenue)}
                                        </p>
                                        <p className="text-xs text-gray-500">Revenue</p>
                                    </div>
                                </div>
                            )}
                            emptyMessage="No product data"
                        />
                    )}
                </div>

                {/* Quick Actions */}
                <div className="bg-white rounded-lg shadow p-6">
                    <h3 className="text-lg font-semibold mb-4">Quick Actions</h3>
                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
                        {widgets.users && (
                            <button
                                onClick={() => router.visit('/users')}
                                className="flex flex-col items-center p-4 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors"
                            >
                                <Users className="w-8 h-8 text-blue-600 mb-2" />
                                <span className="text-sm font-medium text-blue-900">Manage Users</span>
                            </button>
                        )}
                        {widgets.companies && (
                            <button
                                onClick={() => router.visit('/companies')}
                                className="flex flex-col items-center p-4 bg-purple-50 hover:bg-purple-100 rounded-lg transition-colors"
                            >
                                <Building2 className="w-8 h-8 text-purple-600 mb-2" />
                                <span className="text-sm font-medium text-purple-900">Companies</span>
                            </button>
                        )}
                        {widgets.orders && (
                            <button
                                onClick={() => router.visit('/orders')}
                                className="flex flex-col items-center p-4 bg-green-50 hover:bg-green-100 rounded-lg transition-colors"
                            >
                                <ShoppingCart className="w-8 h-8 text-green-600 mb-2" />
                                <span className="text-sm font-medium text-green-900">Orders</span>
                            </button>
                        )}
                        {widgets.products && (
                            <button
                                onClick={() => router.visit('/products')}
                                className="flex flex-col items-center p-4 bg-yellow-50 hover:bg-yellow-100 rounded-lg transition-colors"
                            >
                                <Package className="w-8 h-8 text-yellow-600 mb-2" />
                                <span className="text-sm font-medium text-yellow-900">Products</span>
                            </button>
                        )}
                        {widgets.payments && (
                            <button
                                onClick={() => router.visit('/payments')}
                                className="flex flex-col items-center p-4 bg-indigo-50 hover:bg-indigo-100 rounded-lg transition-colors"
                            >
                                <CreditCard className="w-8 h-8 text-indigo-600 mb-2" />
                                <span className="text-sm font-medium text-indigo-900">Payments</span>
                            </button>
                        )}
                        {widgets.reports && (
                            <button
                                onClick={() => router.visit('/reports')}
                                className="flex flex-col items-center p-4 bg-red-50 hover:bg-red-100 rounded-lg transition-colors"
                            >
                                <Activity className="w-8 h-8 text-red-600 mb-2" />
                                <span className="text-sm font-medium text-red-900">Reports</span>
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}