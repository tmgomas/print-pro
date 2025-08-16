// resources/js/pages/Expenses/Show.tsx

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { 
    ArrowLeft, 
    MoreHorizontal,
    Edit,
    Trash2,
    FileText,
    Download,
    Calendar,
    DollarSign,
    Building2,
    User,
    CreditCard,
    Clock,
    CheckCircle,
    XCircle,
    AlertCircle,
    Receipt,
    Eye,
    Send,
    Ban,
    Repeat,
    Tag,
    Mail,
    Phone,
    MapPin,
    Hash,
    Banknote,
    AlertTriangle
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Expense {
    id: number;
    expense_number: string;
    branch_id: number;
    category_id: number;
    expense_date: string;
    amount: number;
    formatted_amount: string;
    description: string;
    vendor_name?: string;
    vendor_address?: string;
    vendor_phone?: string;
    vendor_email?: string;
    payment_method: string;
    payment_reference?: string;
    receipt_number?: string;
    receipt_attachments?: string[];
    status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'paid' | 'cancelled';
    priority: 'low' | 'medium' | 'high' | 'urgent';
    is_recurring: boolean;
    recurring_period?: string;
    notes?: string;
    approval_notes?: string;
    rejection_reason?: string;
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
    created_by?: {
        id: number;
        name: string;
        email: string;
    };
    approved_by?: {
        id: number;
        name: string;
        email: string;
    };
}

interface Props {
    expense: Expense;
    can: {
        update: boolean;
        delete: boolean;
        approve: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Management', href: '/expenses' },
    { title: 'Expense Details', href: '#' },
];

const statusColors = {
    draft: 'bg-gray-100 text-gray-800',
    pending_approval: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    paid: 'bg-blue-100 text-blue-800',
    cancelled: 'bg-gray-100 text-gray-800',
};

const priorityColors = {
    low: 'bg-green-100 text-green-800',
    medium: 'bg-yellow-100 text-yellow-800',
    high: 'bg-orange-100 text-orange-800',
    urgent: 'bg-red-100 text-red-800',
};

const statusIcons = {
    draft: <FileText className="h-3 w-3" />,
    pending_approval: <Clock className="h-3 w-3" />,
    approved: <CheckCircle className="h-3 w-3" />,
    rejected: <XCircle className="h-3 w-3" />,
    paid: <Banknote className="h-3 w-3" />,
    cancelled: <Ban className="h-3 w-3" />,
};

export default function ExpenseShow({ expense, can }: Props) {
    const [showApprovalDialog, setShowApprovalDialog] = useState(false);
    const [showRejectionDialog, setShowRejectionDialog] = useState(false);
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [approvalNotes, setApprovalNotes] = useState('');
    const [rejectionReason, setRejectionReason] = useState('');
    const [processing, setProcessing] = useState(false);

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
    };

    const formatDateTime = (dateString: string) => {
        return new Date(dateString).toLocaleString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    };

    const handleApproval = () => {
        setProcessing(true);
        router.post(`/expenses/${expense.id}/approve`, {
            approval_notes: approvalNotes
        }, {
            onSuccess: () => {
                setShowApprovalDialog(false);
                setApprovalNotes('');
            },
            onFinish: () => setProcessing(false)
        });
    };

    const handleRejection = () => {
        setProcessing(true);
        router.post(`/expenses/${expense.id}/reject`, {
            rejection_reason: rejectionReason
        }, {
            onSuccess: () => {
                setShowRejectionDialog(false);
                setRejectionReason('');
            },
            onFinish: () => setProcessing(false)
        });
    };

    const handleMarkAsPaid = () => {
        setProcessing(true);
        router.post(`/expenses/${expense.id}/mark-as-paid`, {}, {
            onFinish: () => setProcessing(false)
        });
    };

    const handleDelete = () => {
        setProcessing(true);
        router.delete(`/expenses/${expense.id}`, {
            onSuccess: () => {
                router.visit('/expenses');
            },
            onFinish: () => setProcessing(false)
        });
    };

    const downloadReceipt = (attachment: string) => {
        window.open(`/expenses/${expense.id}/download-receipt/${attachment}`, '_blank');
    };

    return (
        <AppLayout 
            title="Expense Details"
            breadcrumbs={breadcrumbs}
        >
            <Head title={`Expense #${expense.expense_number}`} />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/expenses" className="flex items-center gap-2 text-gray-600 hover:text-gray-900">
                            <ArrowLeft className="h-4 w-4" />
                            Back to Expenses
                        </Link>
                        <Separator orientation="vertical" className="h-6" />
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                Expense #{expense.expense_number}
                            </h1>
                            <p className="text-gray-600">
                                Created on {formatDate(expense.created_at)}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {can.approve && expense.status === 'pending_approval' && (
                            <>
                                <Button
                                    onClick={() => setShowApprovalDialog(true)}
                                    className="bg-green-600 hover:bg-green-700"
                                    disabled={processing}
                                >
                                    <CheckCircle className="h-4 w-4 mr-2" />
                                    Approve
                                </Button>
                                <Button
                                    onClick={() => setShowRejectionDialog(true)}
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    <XCircle className="h-4 w-4 mr-2" />
                                    Reject
                                </Button>
                            </>
                        )}

                        {can.approve && expense.status === 'approved' && (
                            <Button
                                onClick={handleMarkAsPaid}
                                className="bg-blue-600 hover:bg-blue-700"
                                disabled={processing}
                            >
                                <Banknote className="h-4 w-4 mr-2" />
                                Mark as Paid
                            </Button>
                        )}

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <MoreHorizontal className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link href={`/expenses/${expense.id}`} className="flex items-center gap-2">
                                        <Eye className="h-4 w-4" />
                                        View Details
                                    </Link>
                                </DropdownMenuItem>
                                {can.update && (
                                    <DropdownMenuItem asChild>
                                        <Link href={`/expenses/${expense.id}/edit`} className="flex items-center gap-2">
                                            <Edit className="h-4 w-4" />
                                            Edit Expense
                                        </Link>
                                    </DropdownMenuItem>
                                )}
                                <DropdownMenuSeparator />
                                {can.delete && (
                                    <DropdownMenuItem 
                                        onClick={() => setShowDeleteDialog(true)}
                                        className="flex items-center gap-2 text-red-600"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        Delete Expense
                                    </DropdownMenuItem>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Expense Overview */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center gap-2">
                                        <Receipt className="h-5 w-5" />
                                        Expense Overview
                                    </CardTitle>
                                    <div className="flex items-center gap-2">
                                        <Badge className={statusColors[expense.status]}>
                                            <div className="flex items-center gap-1">
                                                {statusIcons[expense.status]}
                                                {expense.status.replace('_', ' ').toUpperCase()}
                                            </div>
                                        </Badge>
                                        <Badge className={priorityColors[expense.priority]}>
                                            {expense.priority.toUpperCase()}
                                        </Badge>
                                        {expense.is_recurring && (
                                            <Badge variant="outline">
                                                <Repeat className="h-3 w-3 mr-1" />
                                                Recurring
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-3">
                                            <DollarSign className="h-4 w-4 text-gray-500" />
                                            <div>
                                                <p className="text-sm text-gray-600">Amount</p>
                                                <p className="font-semibold text-lg">{expense.amount}</p>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center gap-3">
                                            <Calendar className="h-4 w-4 text-gray-500" />
                                            <div>
                                                <p className="text-sm text-gray-600">Expense Date</p>
                                                <p className="font-medium">{formatDate(expense.expense_date)}</p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <Tag className="h-4 w-4 text-gray-500" />
                                            <div>
                                                <p className="text-sm text-gray-600">Category</p>
                                                <p className="font-medium">{expense.category?.name || 'N/A'}</p>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-3">
                                        <div className="flex items-center gap-3">
                                            <Building2 className="h-4 w-4 text-gray-500" />
                                            <div>
                                                <p className="text-sm text-gray-600">Branch</p>
                                                <p className="font-medium">{expense.branch?.name || 'N/A'}</p>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3">
                                            <CreditCard className="h-4 w-4 text-gray-500" />
                                            <div>
                                                <p className="text-sm text-gray-600">Payment Method</p>
                                                <p className="font-medium capitalize">{expense.payment_method}</p>
                                            </div>
                                        </div>

                                        {expense.payment_reference && (
                                            <div className="flex items-center gap-3">
                                                <Hash className="h-4 w-4 text-gray-500" />
                                                <div>
                                                    <p className="text-sm text-gray-600">Payment Reference</p>
                                                    <p className="font-medium">{expense.payment_reference}</p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </div>

                                <Separator />

                                <div>
                                    <p className="text-sm text-gray-600 mb-2">Description</p>
                                    <p className="text-gray-900">{expense.description}</p>
                                </div>

                                {expense.notes && (
                                    <>
                                        <Separator />
                                        <div>
                                            <p className="text-sm text-gray-600 mb-2">Notes</p>
                                            <p className="text-gray-900">{expense.notes}</p>
                                        </div>
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Vendor Information */}
                        {expense.vendor_name && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        Vendor Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        <div>
                                            <p className="text-sm text-gray-600">Vendor Name</p>
                                            <p className="font-medium">{expense.vendor_name}</p>
                                        </div>
                                        
                                        {expense.vendor_email && (
                                            <div className="flex items-center gap-3">
                                                <Mail className="h-4 w-4 text-gray-500" />
                                                <div>
                                                    <p className="text-sm text-gray-600">Email</p>
                                                    <p className="font-medium">{expense.vendor_email}</p>
                                                </div>
                                            </div>
                                        )}

                                        {expense.vendor_phone && (
                                            <div className="flex items-center gap-3">
                                                <Phone className="h-4 w-4 text-gray-500" />
                                                <div>
                                                    <p className="text-sm text-gray-600">Phone</p>
                                                    <p className="font-medium">{expense.vendor_phone}</p>
                                                </div>
                                            </div>
                                        )}

                                        {expense.vendor_address && (
                                            <div className="flex items-center gap-3">
                                                <MapPin className="h-4 w-4 text-gray-500" />
                                                <div>
                                                    <p className="text-sm text-gray-600">Address</p>
                                                    <p className="font-medium">{expense.vendor_address}</p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Receipt Attachments */}
                        {expense.receipt_attachments && expense.receipt_attachments.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Receipt Attachments
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        {expense.receipt_attachments.map((attachment, index) => (
                                            <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                                                <div className="flex items-center gap-3">
                                                    <FileText className="h-5 w-5 text-gray-500" />
                                                    <span className="text-sm font-medium">Receipt {index + 1}</span>
                                                </div>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => downloadReceipt(attachment)}
                                                >
                                                    <Download className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Status Timeline */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Clock className="h-5 w-5" />
                                    Status Timeline
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3">
                                        <div className="w-2 h-2 bg-gray-400 rounded-full"></div>
                                        <div>
                                            <p className="text-sm font-medium">Created</p>
                                            <p className="text-xs text-gray-600">{formatDateTime(expense.created_at)}</p>
                                            {expense.created_by && (
                                                <p className="text-xs text-gray-500">by {expense.created_by.name}</p>
                                            )}
                                        </div>
                                    </div>

                                    {expense.approved_at && (
                                        <div className="flex items-center gap-3">
                                            <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                            <div>
                                                <p className="text-sm font-medium">Approved</p>
                                                <p className="text-xs text-gray-600">{formatDateTime(expense.approved_at)}</p>
                                                {expense.approved_by && (
                                                    <p className="text-xs text-gray-500">by {expense.approved_by.name}</p>
                                                )}
                                            </div>
                                        </div>
                                    )}

                                    {expense.paid_at && (
                                        <div className="flex items-center gap-3">
                                            <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                                            <div>
                                                <p className="text-sm font-medium">Paid</p>
                                                <p className="text-xs text-gray-600">{formatDateTime(expense.paid_at)}</p>
                                            </div>
                                        </div>
                                    )}

                                    {expense.status === 'rejected' && expense.rejection_reason && (
                                        <div className="flex items-center gap-3">
                                            <div className="w-2 h-2 bg-red-500 rounded-full"></div>
                                            <div>
                                                <p className="text-sm font-medium">Rejected</p>
                                                <p className="text-xs text-gray-600">{formatDateTime(expense.updated_at)}</p>
                                            </div>
                                        </div>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {/* Approval/Rejection Notes */}
                        {(expense.approval_notes || expense.rejection_reason) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        {expense.status === 'rejected' ? 'Rejection Reason' : 'Approval Notes'}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {expense.status === 'rejected' && expense.rejection_reason && (
                                        <Alert>
                                            <AlertTriangle className="h-4 w-4" />
                                            <AlertDescription>
                                                {expense.rejection_reason}
                                            </AlertDescription>
                                        </Alert>
                                    )}
                                    {expense.approval_notes && (
                                        <div className="bg-green-50 p-3 rounded-lg">
                                            <p className="text-sm text-green-800">{expense.approval_notes}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        )}

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-2">
                                {can.update && (
                                    <Button asChild variant="outline" className="w-full">
                                        <Link href={`/expenses/${expense.id}/edit`}>
                                            <Edit className="h-4 w-4 mr-2" />
                                            Edit Expense
                                        </Link>
                                    </Button>
                                )}
                                
                                <Button variant="outline" className="w-full">
                                    <Download className="h-4 w-4 mr-2" />
                                    Download PDF
                                </Button>

                                {expense.status === 'draft' && (
                                    <Button 
                                        className="w-full"
                                        onClick={() => router.post(`/expenses/${expense.id}/submit-for-approval`)}
                                    >
                                        <Send className="h-4 w-4 mr-2" />
                                        Submit for Approval
                                    </Button>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>

            {/* Approval Dialog */}
            <Dialog open={showApprovalDialog} onOpenChange={setShowApprovalDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Approve Expense</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to approve this expense? You can add approval notes below.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="approval-notes">Approval Notes (Optional)</Label>
                            <Textarea
                                id="approval-notes"
                                value={approvalNotes}
                                onChange={(e) => setApprovalNotes(e.target.value)}
                                placeholder="Add any notes about the approval..."
                                rows={3}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button 
                            variant="outline" 
                            onClick={() => setShowApprovalDialog(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button 
                            onClick={handleApproval}
                            disabled={processing}
                            className="bg-green-600 hover:bg-green-700"
                        >
                            <CheckCircle className="h-4 w-4 mr-2" />
                            Approve Expense
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Rejection Dialog */}
            <Dialog open={showRejectionDialog} onOpenChange={setShowRejectionDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Reject Expense</DialogTitle>
                        <DialogDescription>
                            Please provide a reason for rejecting this expense.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="rejection-reason">Rejection Reason *</Label>
                            <Textarea
                                id="rejection-reason"
                                value={rejectionReason}
                                onChange={(e) => setRejectionReason(e.target.value)}
                                placeholder="Explain why this expense is being rejected..."
                                rows={3}
                                required
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button 
                            variant="outline" 
                            onClick={() => setShowRejectionDialog(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button 
                            onClick={handleRejection}
                            disabled={processing || !rejectionReason.trim()}
                            variant="destructive"
                        >
                            <XCircle className="h-4 w-4 mr-2" />
                            Reject Expense
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            {/* Delete Dialog */}
            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Expense</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this expense? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button 
                            variant="outline" 
                            onClick={() => setShowDeleteDialog(false)}
                            disabled={processing}
                        >
                            Cancel
                        </Button>
                        <Button 
                            onClick={handleDelete}
                            disabled={processing}
                            variant="destructive"
                        >
                            <Trash2 className="h-4 w-4 mr-2" />
                            Delete Expense
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}