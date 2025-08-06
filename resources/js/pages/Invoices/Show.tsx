import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Progress } from '@/components/ui/progress';

import { 
    FileText, 
    User, 
    Calendar, 
    Clock, 
    CreditCard, 
    Plus, 
    Eye, 
    Check, 
    X, 
    Download,
    Edit,
    History,
    AlertCircle,
    CheckCircle,
    DollarSign,
    Loader2,
    Printer,
    Factory,
    PlayCircle,
    Settings,
    Upload
} from 'lucide-react';

interface Invoice {
    id: number;
    invoice_number: string;
    invoice_date: string;
    due_date: string;
    status: string;
    payment_status: string;
    total_amount: number;
    subtotal: number;
    weight_charge: number;
    tax_amount: number;
    discount_amount: number;
    total_weight: number | string;
    formatted_total: string;
    formatted_subtotal: string;
    formatted_weight_charge: string;
    formatted_tax_amount: string;
    formatted_discount_amount: string;
    notes?: string;
    customer: {
        id: number;
        name: string;
        email?: string;
        phone: string;
        customer_code: string;
        address?: string;
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
    items: InvoiceItem[];
}

interface InvoiceItem {
    id: number;
    item_description: string;
    quantity: number;
    unit_price: number;
    unit_weight: number;
    line_total: number;
    line_weight: string | number;
    tax_amount: number;
    specifications?: Record<string, any>;
    product?: {
        id: number;
        name: string;
    };
}

interface Payment {
    id: number;
    payment_reference: string;
    amount: number;
    payment_date: string;
    payment_method: string;
    payment_method_label: string;
    status: string;
    status_label: string;
    status_color: string;
    verification_status: string;
    verification_status_label: string;
    transaction_id?: string;
    notes?: string;
    bank_name?: string;
}

interface PaymentSummary {
    invoice_total: number;
    total_paid: number;
    pending_amount: number;
    remaining_balance: number;
    payment_status: string;
    payments: Payment[];
    payment_history: Payment[];
}

interface PrintJob {
    id: number;
    job_number: string;
    job_type: string;
    production_status: string;
    priority: string;
    estimated_completion: string;
    actual_completion?: string;
    assigned_to?: {
        id: number;
        name: string;
    };
    current_stage?: string;
    progress_percentage: number;
    can_start_production: boolean;
}

interface Props {
    invoice: Invoice;
    paymentSummary: PaymentSummary;
    recentPayments: Payment[];
    printJob?: PrintJob | null;
    permissions: {
        edit: boolean;
        delete: boolean;
        create_payment: boolean;
        verify_payment: boolean;
        generate_pdf: boolean;
        create_print_job: boolean;
        manage_production: boolean;
    };
    paymentMethods: Record<string, string>;
    jobTypes: Record<string, string>;
    productionStaff: Array<{id: number; name: string; email: string}>;
}

export default function InvoiceShow({ 
    invoice, 
    paymentSummary, 
    recentPayments, 
    printJob,
    permissions, 
    paymentMethods,
    jobTypes,
    productionStaff
}: Props) {
    const [isPaymentDialogOpen, setIsPaymentDialogOpen] = useState(false);
    const [isPrintJobDialogOpen, setIsPrintJobDialogOpen] = useState(false);
    const [paymentData, setPaymentData] = useState(paymentSummary);
    
    // Initialize payment form
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: paymentSummary.remaining_balance > 0 ? paymentSummary.remaining_balance.toString() : '0',
        payment_method: 'cash',
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
        bank_name: '',
        transaction_id: '',
        gateway_reference: '',
        cheque_number: '',
    });

    // Initialize print job form
    const { 
        data: printJobData, 
        setData: setPrintJobData, 
        post: postPrintJob, 
        processing: printJobProcessing, 
        errors: printJobErrors, 
        reset: resetPrintJob 
    } = useForm({
        invoice_id: invoice.id,
        job_type: 'general_printing',
        priority: 'normal',
        assigned_to: '',
        estimated_completion: '',
        customer_instructions: invoice.notes || '',
        design_files: null,
        specifications: {}
    });

    const statusColors: Record<string, string> = {
        draft: 'bg-gray-500',
        pending: 'bg-yellow-500',
        processing: 'bg-blue-500',
        completed: 'bg-green-500',
        cancelled: 'bg-red-500'
    };

    const paymentStatusColors: Record<string, string> = {
        pending: 'bg-red-500',
        partially_paid: 'bg-yellow-500',
        paid: 'bg-green-500',
        refunded: 'bg-purple-500'
    };

    const productionStatusColors: Record<string, string> = {
        pending: 'bg-gray-500',
        in_progress: 'bg-blue-500',
        quality_check: 'bg-yellow-500',
        completed: 'bg-green-500',
        on_hold: 'bg-orange-500'
    };

    // Enhanced payment form handler
    const handleQuickPayment = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Frontend validation
        if (!data.amount || parseFloat(data.amount) <= 0) {
            alert('Please enter a valid payment amount');
            return;
        }
        
        if (!data.payment_method) {
            alert('Please select a payment method');
            return;
        }
        
        // Bank transfer/cheque validation
        if ((data.payment_method === 'bank_transfer' || data.payment_method === 'cheque') && !data.bank_name) {
            alert('Bank name is required for bank transfers and cheques');
            return;
        }
        
        // Online payment validation
        if (data.payment_method === 'online' && !data.gateway_reference) {
            alert('Gateway reference is required for online payments');
            return;
        }
        
        // Cheque validation
        if (data.payment_method === 'cheque' && !data.cheque_number) {
            alert('Cheque number is required for cheque payments');
            return;
        }
        
        console.log('=== PAYMENT FORM SUBMIT ===');
        console.log('Form data:', data);
        console.log('Invoice ID:', invoice.id);
        
        // Use Inertia's post method for form submission
        post(route('invoices.record-payment', invoice.id), {
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page: any) => {
                console.log('Payment success:', page);
                
                // Check if response has payment summary data
                if (page.props && page.props.paymentSummary) {
                    setPaymentData(page.props.paymentSummary);
                }
                
                // Handle different response formats
                if (page.props && page.props.flash && page.props.flash.success) {
                    alert(page.props.flash.success);
                }
                
                setIsPaymentDialogOpen(false);
                reset();
                
                // Refresh the page to show updated payment data
                window.location.reload();
            },
            onError: (errors) => {
                console.error('Payment failed:', errors);
                
                // Display specific validation errors
                Object.keys(errors).forEach(field => {
                    if (errors[field]) {
                        console.error(`${field} error:`, errors[field]);
                    }
                });
                
                // Show first error message
                const firstError = Object.values(errors)[0];
                if (firstError) {
                    alert(`Payment Error: ${firstError}`);
                }
            },
            onFinish: () => {
                console.log('Payment request finished');
            }
        });
    };

    // Print job creation handler
    const handleCreatePrintJob = (e: React.FormEvent) => {
        e.preventDefault();
        
        console.log('=== PRINT JOB FORM SUBMIT ===');
        console.log('Print job data:', printJobData);
        
        postPrintJob(route('production.print-jobs.store-manual'), {
            forceFormData: true, // For file uploads
            onSuccess: (page: any) => {
                console.log('Print job created successfully:', page);
                setIsPrintJobDialogOpen(false);
                resetPrintJob();
                
                // Show success message
                if (page.props && page.props.flash && page.props.flash.success) {
                    alert(page.props.flash.success);
                } else {
                    alert('Print job created successfully!');
                }
                
                // Refresh to show the new print job
                window.location.reload();
            },
            onError: (errors) => {
                console.error('Print job creation failed:', errors);
                
                // Show first error message
                const firstError = Object.values(errors)[0];
                if (firstError) {
                    alert(`Print Job Error: ${firstError}`);
                }
            }
        });
    };

    // Start production handler
    const handleStartProduction = () => {
        if (!printJob) return;
        
        router.post(route('production.print-jobs.start-production', printJob.id), {}, {
            onSuccess: () => {
                alert('Production started successfully!');
                window.location.reload();
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                alert(`Error: ${firstError}`);
            }
        });
    };

    // Payment method change handler
    const handlePaymentMethodChange = (value: string) => {
        setData('payment_method', value);
        
        // Clear conditional fields when method changes
        if (value !== 'bank_transfer' && value !== 'cheque') {
            setData('bank_name', '');
        }
        if (value !== 'online') {
            setData('gateway_reference', '');
        }
        if (value !== 'cheque') {
            setData('cheque_number', '');
        }
    };

    const handleVerifyPayment = (paymentId: number) => {
        router.post(route('payments.verify', paymentId), {}, {
            onSuccess: () => {
                // Refresh payment data
                router.reload({ only: ['paymentSummary'] });
            }
        });
    };

    const handleRejectPayment = (paymentId: number) => {
        const reason = prompt('Enter rejection reason:');
        if (reason) {
            router.post(route('payments.reject', paymentId), { reason }, {
                onSuccess: () => {
                    router.reload({ only: ['paymentSummary'] });
                }
            });
        }
    };

    const handlePrint = () => {
        window.print();
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-LK', {
            style: 'currency',
            currency: 'LKR',
            minimumFractionDigits: 2
        }).format(amount).replace('LKR', 'Rs.');
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
    };

    const isOverdue = new Date(invoice.due_date) < new Date() && invoice.payment_status !== 'paid';
    const paymentProgress = (paymentData.total_paid / invoice.total_amount) * 100;

    // Helper function to get the correct status color class
    const getStatusColorClass = (status: string) => {
        return statusColors[status] || 'bg-gray-500';
    };

    const getPaymentStatusColorClass = (status: string) => {
        return paymentStatusColors[status] || 'bg-gray-500';
    };

    const getProductionStatusColorClass = (status: string) => {
        return productionStatusColors[status] || 'bg-gray-500';
    };

    // Helper function to convert line_weight to number
    const getLineWeight = (weight: string | number): number => {
        if (typeof weight === 'string') {
            return parseFloat(weight) || 0;
        }
        return weight || 0;
    };

    // Check if invoice is ready for print job creation
    const canCreatePrintJob = () => {
        console.log('=== PRINT JOB CREATION CHECK ===');
        console.log('Payment Status:', invoice.payment_status);
        console.log('Remaining Balance:', paymentData.remaining_balance);
        console.log('Existing Print Job:', printJob);
        console.log('Create Permission:', permissions.create_print_job);
        
        // Invoice must be fully paid (no remaining balance) and no existing print job
        const isPaid = invoice.payment_status === 'paid' || paymentData.remaining_balance <= 0;
        const noExistingJob = !printJob;
        const hasPermission = permissions.create_print_job;
        
        console.log('Can Create:', isPaid && noExistingJob && hasPermission);
        
        return isPaid && noExistingJob && hasPermission;
    };

    return (
        <AppLayout>
            <Head title={`Invoice ${invoice.invoice_number}`} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                Invoice {invoice.invoice_number}
                            </h1>
                            <nav className="flex space-x-2 text-sm text-gray-500">
                                <Link href={route('dashboard')} className="hover:text-gray-700">
                                    Dashboard
                                </Link>
                                <span>/</span>
                                <Link href={route('invoices.index')} className="hover:text-gray-700">
                                    Invoices
                                </Link>
                                <span>/</span>
                                <span>{invoice.invoice_number}</span>
                            </nav>
                        </div>

                        <div className="flex space-x-2">
                            {permissions.edit && (
                                <Button asChild variant="outline">
                                    <Link href={route('invoices.edit', invoice.id)}>
                                        <Edit className="h-4 w-4 mr-1" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            
                            <Button variant="outline" onClick={handlePrint}>
                                <Printer className="h-4 w-4 mr-1" />
                                Print
                            </Button>
                            
                            {permissions.generate_pdf && (
                                <Button asChild variant="outline">
                                    <Link href={route('invoices.pdf', invoice.id)} target="_blank">
                                        <Download className="h-4 w-4 mr-1" />
                                        PDF
                                    </Link>
                                </Button>
                            )}

                            {permissions.create_payment && paymentData.remaining_balance > 0 && (
                                <Dialog open={isPaymentDialogOpen} onOpenChange={setIsPaymentDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button>
                                            <Plus className="h-4 w-4 mr-1" />
                                            Record Payment
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-md">
                                        <DialogHeader>
                                            <DialogTitle>Record Payment for Invoice {invoice.invoice_number}</DialogTitle>
                                        </DialogHeader>
                                        
                                        {/* Show remaining balance info */}
                                        <Alert className="mb-4">
                                            <AlertCircle className="h-4 w-4" />
                                            <AlertDescription>
                                                Remaining Balance: <strong>{formatCurrency(paymentData.remaining_balance)}</strong>
                                            </AlertDescription>
                                        </Alert>

                                        <form onSubmit={handleQuickPayment} className="space-y-4">
                                            <div>
                                                <Label htmlFor="amount">Payment Amount *</Label>
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    max={paymentData.remaining_balance}
                                                    value={data.amount}
                                                    onChange={(e) => setData('amount', e.target.value)}
                                                    placeholder={`Max: ${formatCurrency(paymentData.remaining_balance)}`}
                                                    className={errors.amount ? 'border-red-500' : ''}
                                                    required
                                                />
                                                {errors.amount && (
                                                    <p className="text-sm text-red-600 mt-1">{errors.amount}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="payment_method">Payment Method *</Label>
                                                <Select
                                                    value={data.payment_method}
                                                    onValueChange={handlePaymentMethodChange}
                                                >
                                                    <SelectTrigger className={errors.payment_method ? 'border-red-500' : ''}>
                                                        <SelectValue placeholder="Select payment method" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(paymentMethods).map(([key, label]) => (
                                                            <SelectItem key={key} value={key}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {errors.payment_method && (
                                                    <p className="text-sm text-red-600 mt-1">{errors.payment_method}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="payment_date">Payment Date *</Label>
                                                <Input
                                                    id="payment_date"
                                                    type="date"
                                                    value={data.payment_date}
                                                    onChange={(e) => setData('payment_date', e.target.value)}
                                                    max={new Date().toISOString().split('T')[0]}
                                                    className={errors.payment_date ? 'border-red-500' : ''}
                                                    required
                                                />
                                                {errors.payment_date && (
                                                    <p className="text-sm text-red-600 mt-1">{errors.payment_date}</p>
                                                )}
                                            </div>

                                            {/* Conditional fields based on payment method */}
                                            {(data.payment_method === 'bank_transfer' || data.payment_method === 'cheque') && (
                                                <div>
                                                    <Label htmlFor="bank_name">Bank Name *</Label>
                                                    <Input
                                                        id="bank_name"
                                                        value={data.bank_name}
                                                        onChange={(e) => setData('bank_name', e.target.value)}
                                                        placeholder="Enter bank name"
                                                        className={errors.bank_name ? 'border-red-500' : ''}
                                                        required
                                                    />
                                                    {errors.bank_name && (
                                                        <p className="text-sm text-red-600 mt-1">{errors.bank_name}</p>
                                                    )}
                                                </div>
                                            )}

                                            {data.payment_method === 'online' && (
                                                <div>
                                                    <Label htmlFor="gateway_reference">Gateway Reference *</Label>
                                                    <Input
                                                        id="gateway_reference"
                                                        value={data.gateway_reference}
                                                        onChange={(e) => setData('gateway_reference', e.target.value)}
                                                        placeholder="Enter gateway reference"
                                                        className={errors.gateway_reference ? 'border-red-500' : ''}
                                                        required
                                                    />
                                                    {errors.gateway_reference && (
                                                        <p className="text-sm text-red-600 mt-1">{errors.gateway_reference}</p>
                                                    )}
                                                </div>
                                            )}

                                            {data.payment_method === 'cheque' && (
                                                <div>
                                                    <Label htmlFor="cheque_number">Cheque Number *</Label>
                                                    <Input
                                                        id="cheque_number"
                                                        value={data.cheque_number}
                                                        onChange={(e) => setData('cheque_number', e.target.value)}
                                                        placeholder="Enter cheque number"
                                                        className={errors.cheque_number ? 'border-red-500' : ''}
                                                        required
                                                    />
                                                    {errors.cheque_number && (
                                                        <p className="text-sm text-red-600 mt-1">{errors.cheque_number}</p>
                                                    )}
                                                </div>
                                            )}

                                            <div>
                                                <Label htmlFor="transaction_id">Transaction ID (Optional)</Label>
                                                <Input
                                                    id="transaction_id"
                                                    value={data.transaction_id}
                                                    onChange={(e) => setData('transaction_id', e.target.value)}
                                                    placeholder="Enter transaction ID"
                                                    className={errors.transaction_id ? 'border-red-500' : ''}
                                                />
                                                {errors.transaction_id && (
                                                    <p className="text-sm text-red-600 mt-1">{errors.transaction_id}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="notes">Notes (Optional)</Label>
                                                <Textarea
                                                    id="notes"
                                                    value={data.notes}
                                                    onChange={(e) => setData('notes', e.target.value)}
                                                    placeholder="Add payment notes"
                                                    rows={3}
                                                    className={errors.notes ? 'border-red-500' : ''}
                                                />
                                                {errors.notes && (
                                                    <p className="text-sm text-red-600 mt-1">{errors.notes}</p>
                                                )}
                                            </div>

                                            <div className="flex justify-end space-x-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => setIsPaymentDialogOpen(false)}
                                                    disabled={processing}
                                                >
                                                    Cancel
                                                </Button>
                                                <Button 
                                                    type="submit" 
                                                    disabled={processing || !data.amount || !data.payment_method}
                                                >
                                                    {processing ? (
                                                        <>
                                                            <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                            Recording Payment...
                                                        </>
                                                    ) : (
                                                        'Record Payment'
                                                    )}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}

                            {/* Create Print Job Button */}
                            {canCreatePrintJob() && (
                                <Dialog open={isPrintJobDialogOpen} onOpenChange={setIsPrintJobDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="default">
                                            <Factory className="h-4 w-4 mr-1" />
                                            Create Print Job
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="sm:max-w-lg">
                                        <DialogHeader>
                                            <DialogTitle>Create Print Job for Invoice {invoice.invoice_number}</DialogTitle>
                                        </DialogHeader>

                                        <Alert className="mb-4">
                                            <CheckCircle className="h-4 w-4" />
                                            <AlertDescription>
                                                Invoice is fully paid. Ready for production!
                                            </AlertDescription>
                                        </Alert>

                                        <form onSubmit={handleCreatePrintJob} className="space-y-4">
                                            <div>
                                                <Label htmlFor="job_type">Job Type *</Label>
                                                <Select
                                                    value={printJobData.job_type}
                                                    onValueChange={(value) => setPrintJobData('job_type', value)}
                                                >
                                                    <SelectTrigger className={printJobErrors.job_type ? 'border-red-500' : ''}>
                                                        <SelectValue placeholder="Select job type" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(jobTypes).map(([key, label]) => (
                                                            <SelectItem key={key} value={key}>
                                                                {label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {printJobErrors.job_type && (
                                                    <p className="text-sm text-red-600 mt-1">{printJobErrors.job_type}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="priority">Priority *</Label>
                                                <Select
                                                    value={printJobData.priority}
                                                    onValueChange={(value) => setPrintJobData('priority', value)}
                                                >
                                                    <SelectTrigger className={printJobErrors.priority ? 'border-red-500' : ''}>
                                                        <SelectValue placeholder="Select priority" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="low">Low</SelectItem>
                                                        <SelectItem value="normal">Normal</SelectItem>
                                                        <SelectItem value="medium">Medium</SelectItem>
                                                        <SelectItem value="high">High</SelectItem>
                                                        <SelectItem value="urgent">Urgent</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {printJobErrors.priority && (
                                                    <p className="text-sm text-red-600 mt-1">{printJobErrors.priority}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="assigned_to">Assign To (Optional)</Label>
                                                <Select
                                                    value={printJobData.assigned_to}
                                                    onValueChange={(value) => setPrintJobData('assigned_to', value)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select production staff" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {productionStaff.map((staff) => (
                                                            <SelectItem key={staff.id} value={staff.id.toString()}>
                                                                {staff.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>

                                            <div>
                                                <Label htmlFor="estimated_completion">Estimated Completion (Optional)</Label>
                                                <Input
                                                    id="estimated_completion"
                                                    type="datetime-local"
                                                    value={printJobData.estimated_completion}
                                                    onChange={(e) => setPrintJobData('estimated_completion', e.target.value)}
                                                    min={new Date().toISOString().slice(0, 16)}
                                                    className={printJobErrors.estimated_completion ? 'border-red-500' : ''}
                                                />
                                                {printJobErrors.estimated_completion && (
                                                    <p className="text-sm text-red-600 mt-1">{printJobErrors.estimated_completion}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="customer_instructions">Customer Instructions</Label>
                                                <Textarea
                                                    id="customer_instructions"
                                                    value={printJobData.customer_instructions}
                                                    onChange={(e) => setPrintJobData('customer_instructions', e.target.value)}
                                                    placeholder="Any special instructions from the customer"
                                                    rows={3}
                                                    className={printJobErrors.customer_instructions ? 'border-red-500' : ''}
                                                />
                                                {printJobErrors.customer_instructions && (
                                                    <p className="text-sm text-red-600 mt-1">{printJobErrors.customer_instructions}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="design_files">Design Files (Optional)</Label>
                                                <Input
                                                    id="design_files"
                                                    type="file"
                                                    multiple
                                                    accept=".pdf,.jpg,.jpeg,.png,.ai,.psd"
                                                    onChange={(e) => setPrintJobData('design_files', e.target.files)}
                                                    className={printJobErrors.design_files ? 'border-red-500' : ''}
                                                />
                                                <p className="text-xs text-gray-500 mt-1">
                                                    Accepted formats: PDF, JPG, PNG, AI, PSD (Max 10MB each)
                                                </p>
                                                {printJobErrors.design_files && (
                                                    <p className="text-sm text-red-600 mt-1">{printJobErrors.design_files}</p>
                                                )}
                                            </div>

                                            <div className="flex justify-end space-x-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => setIsPrintJobDialogOpen(false)}
                                                    disabled={printJobProcessing}
                                                >
                                                    Cancel
                                                </Button>
                                                <Button 
                                                    type="submit" 
                                                    disabled={printJobProcessing}
                                                >
                                                    {printJobProcessing ? (
                                                        <>
                                                            <Loader2 className="h-4 w-4 animate-spin mr-2" />
                                                            Creating Print Job...
                                                        </>
                                                    ) : (
                                                        'Create Print Job'
                                                    )}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Content */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* DEBUG: Print Job Creation Status - Remove this in production */}
                            {process.env.NODE_ENV === 'development' && (
                                <Card className="border-dashed border-orange-300">
                                    <CardHeader>
                                        <CardTitle className="text-sm text-orange-600">DEBUG: Print Job Status</CardTitle>
                                    </CardHeader>
                                    <CardContent className="text-xs space-y-1">
                                        <div>Payment Status: <strong>{invoice.payment_status}</strong></div>
                                        <div>Remaining Balance: <strong>{formatCurrency(paymentData.remaining_balance)}</strong></div>
                                        <div>Print Job Exists: <strong>{printJob ? 'Yes' : 'No'}</strong></div>
                                        <div>Create Permission: <strong>{permissions.create_print_job ? 'Yes' : 'No'}</strong></div>
                                        <div>Can Create Print Job: <strong>{canCreatePrintJob() ? 'Yes' : 'No'}</strong></div>
                                        {!canCreatePrintJob() && (
                                            <div className="text-red-600 font-medium">
                                                Reason: {!permissions.create_print_job ? 'No Permission' : 
                                                         printJob ? 'Print Job Exists' : 
                                                         paymentData.remaining_balance > 0 ? 'Payment Incomplete' : 'Unknown'}
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}

                            {/* Invoice Status */}
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-2">
                                            <Badge className={getStatusColorClass(invoice.status)}>
                                                {invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                                            </Badge>
                                            <Badge className={getPaymentStatusColorClass(invoice.payment_status)}>
                                                {invoice.payment_status.replace('_', ' ').charAt(0).toUpperCase() + 
                                                 invoice.payment_status.replace('_', ' ').slice(1)}
                                            </Badge>
                                            {isOverdue && (
                                                <Badge variant="destructive">
                                                    <AlertCircle className="h-3 w-3 mr-1" />
                                                    Overdue
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="text-right">
                                            <div className="text-2xl font-bold">{invoice.formatted_total}</div>
                                            <div className="text-sm text-gray-500">Total Amount</div>
                                        </div>
                                    </div>
                                    
                                    <div className="mt-4 grid grid-cols-2 gap-4 text-sm">
                                        <div className="flex items-center text-gray-600">
                                            <Calendar className="h-4 w-4 mr-2" />
                                            Invoice Date: {formatDate(invoice.invoice_date)}
                                        </div>
                                        <div className="flex items-center text-gray-600">
                                            <Clock className="h-4 w-4 mr-2" />
                                            Due Date: {formatDate(invoice.due_date)}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Print Job Status Card - Show if print job exists */}
                            {printJob && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center justify-between">
                                            <div className="flex items-center">
                                                <Factory className="h-5 w-5 mr-2" />
                                                Print Job Status
                                            </div>
                                            <div className="flex space-x-2">
                                                <Button asChild variant="outline" size="sm">
                                                    <Link href={route('production.print-jobs.show', printJob.id)}>
                                                        <Eye className="h-4 w-4 mr-1" />
                                                        View Details
                                                    </Link>
                                                </Button>
                                                {permissions.manage_production && printJob.can_start_production && printJob.production_status === 'pending' && (
                                                    <Button size="sm" onClick={handleStartProduction}>
                                                        <PlayCircle className="h-4 w-4 mr-1" />
                                                        Start Production
                                                    </Button>
                                                )}
                                            </div>
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            <div>
                                                <div className="space-y-2">
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">Job Number:</span>
                                                        <span className="font-medium">{printJob.job_number}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">Job Type:</span>
                                                        <span className="font-medium">{jobTypes[printJob.job_type] || printJob.job_type}</span>
                                                    </div>
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">Priority:</span>
                                                        <Badge variant={printJob.priority === 'urgent' ? 'destructive' : printJob.priority === 'high' ? 'default' : 'secondary'}>
                                                            {printJob.priority.charAt(0).toUpperCase() + printJob.priority.slice(1)}
                                                        </Badge>
                                                    </div>
                                                    {printJob.assigned_to && (
                                                        <div className="flex justify-between">
                                                            <span className="text-sm text-gray-600">Assigned To:</span>
                                                            <span className="font-medium">{printJob.assigned_to.name}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                            <div>
                                                <div className="space-y-2">
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">Status:</span>
                                                        <Badge className={getProductionStatusColorClass(printJob.production_status)}>
                                                            {printJob.production_status.replace('_', ' ').charAt(0).toUpperCase() + 
                                                             printJob.production_status.replace('_', ' ').slice(1)}
                                                        </Badge>
                                                    </div>
                                                    {printJob.current_stage && (
                                                        <div className="flex justify-between">
                                                            <span className="text-sm text-gray-600">Current Stage:</span>
                                                            <span className="font-medium">{printJob.current_stage}</span>
                                                        </div>
                                                    )}
                                                    <div className="flex justify-between">
                                                        <span className="text-sm text-gray-600">Est. Completion:</span>
                                                        <span className="font-medium">{formatDate(printJob.estimated_completion)}</span>
                                                    </div>
                                                    {printJob.actual_completion && (
                                                        <div className="flex justify-between">
                                                            <span className="text-sm text-gray-600">Completed:</span>
                                                            <span className="font-medium text-green-600">{formatDate(printJob.actual_completion)}</span>
                                                        </div>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                        
                                        {/* Production Progress */}
                                        <div className="mt-4">
                                            <div className="flex justify-between text-sm mb-2">
                                                <span>Production Progress</span>
                                                <span>{printJob.progress_percentage}%</span>
                                            </div>
                                            <Progress 
                                                value={printJob.progress_percentage} 
                                                className="h-2"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Customer Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <User className="h-5 w-5 mr-2" />
                                        Customer Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <h3 className="font-semibold text-lg">{invoice.customer.name}</h3>
                                            <div className="mt-2 space-y-1 text-sm text-gray-600">
                                                {invoice.customer.email && (
                                                    <div>Email: {invoice.customer.email}</div>
                                                )}
                                                <div>Phone: {invoice.customer.phone}</div>
                                                <div>Code: {invoice.customer.customer_code}</div>
                                            </div>
                                        </div>
                                        {invoice.customer.address && (
                                            <div>
                                                <h4 className="font-medium">Billing Address</h4>
                                                <p className="mt-1 text-sm text-gray-600">
                                                    {invoice.customer.address}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Invoice Items */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <FileText className="h-5 w-5 mr-2" />
                                        Invoice Items
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto">
                                        <table className="w-full">
                                            <thead>
                                                <tr className="border-b">
                                                    <th className="text-left py-2">Item</th>
                                                    <th className="text-center py-2">Qty</th>
                                                    <th className="text-center py-2">Weight</th>
                                                    <th className="text-right py-2">Unit Price</th>
                                                    <th className="text-right py-2">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {invoice.items.map((item) => (
                                                    <tr key={item.id} className="border-b">
                                                        <td className="py-3">
                                                            <div>
                                                                <div className="font-medium">
                                                                    {item.product?.name || item.item_description}
                                                                </div>
                                                                {item.specifications && (
                                                                    <div className="text-xs text-gray-500 mt-1">
                                                                        {Object.entries(item.specifications).map(([key, value]) => (
                                                                            <span key={key} className="mr-2">
                                                                                {key}: {String(value)}
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                )}
                                                            </div>
                                                        </td>
                                                        <td className="text-center py-3">{item.quantity}</td>
                                                        <td className="text-center py-3">{getLineWeight(item.line_weight).toFixed(2)}kg</td>
                                                        <td className="text-right py-3">{formatCurrency(item.unit_price)}</td>
                                                        <td className="text-right py-3">{formatCurrency(item.line_total)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot className="border-t bg-gray-50">
                                                <tr>
                                                    <td colSpan={2} className="py-2 font-medium">Subtotal</td>
                                                    <td className="text-center py-2">{(parseFloat(String(invoice.total_weight)) || 0).toFixed(2)}kg</td>
                                                    <td></td>
                                                    <td className="text-right py-2 font-medium">{invoice.formatted_subtotal}</td>
                                                </tr>
                                                {invoice.weight_charge > 0 && (
                                                    <tr>
                                                        <td colSpan={4} className="py-2">Delivery Charge</td>
                                                        <td className="text-right py-2">{invoice.formatted_weight_charge}</td>
                                                    </tr>
                                                )}
                                                {invoice.tax_amount > 0 && (
                                                    <tr>
                                                        <td colSpan={4} className="py-2">Tax</td>
                                                        <td className="text-right py-2">{invoice.formatted_tax_amount}</td>
                                                    </tr>
                                                )}
                                                {invoice.discount_amount > 0 && (
                                                    <tr>
                                                        <td colSpan={4} className="py-2">Discount</td>
                                                        <td className="text-right py-2 text-red-600">-{invoice.formatted_discount_amount}</td>
                                                    </tr>
                                                )}
                                                <tr className="border-t-2 bg-gray-100">
                                                    <td colSpan={4} className="py-2 font-bold">Total Amount</td>
                                                    <td className="text-right py-2 font-bold text-lg">{invoice.formatted_total}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Notes */}
                            {invoice.notes && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Notes</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <p className="text-gray-700">{invoice.notes}</p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* Payment & Production Sidebar */}
                        <div className="space-y-6">
                            {/* Payment Summary */}
                            <Card>
                                <CardHeader className="flex flex-row items-center justify-between">
                                    <CardTitle className="flex items-center">
                                        <CreditCard className="h-5 w-5 mr-2" />
                                        Payment Summary
                                    </CardTitle>
                                    {permissions.create_payment && paymentData.remaining_balance > 0 && (
                                        <Button 
                                            size="sm"
                                            onClick={() => setIsPaymentDialogOpen(true)}
                                        >
                                            <Plus className="h-4 w-4" />
                                        </Button>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-2 gap-4 text-center mb-4">
                                        <div>
                                            <div className="text-2xl font-bold text-green-600">
                                                {formatCurrency(paymentData.total_paid)}
                                            </div>
                                            <div className="text-xs text-gray-500">Total Paid</div>
                                        </div>
                                        <div>
                                            <div className={`text-2xl font-bold ${
                                                paymentData.remaining_balance > 0 ? 'text-red-600' : 'text-green-600'
                                            }`}>
                                                {formatCurrency(paymentData.remaining_balance)}
                                            </div>
                                            <div className="text-xs text-gray-500">Remaining</div>
                                        </div>
                                    </div>

                                    {paymentData.pending_amount > 0 && (
                                        <Alert className="mb-4">
                                            <AlertCircle className="h-4 w-4" />
                                            <AlertDescription>
                                                Pending Verification: {formatCurrency(paymentData.pending_amount)}
                                            </AlertDescription>
                                        </Alert>
                                    )}

                                    {/* Payment Progress */}
                                    <div className="space-y-2">
                                        <div className="flex justify-between text-sm">
                                            <span>Payment Progress</span>
                                            <span>{paymentProgress.toFixed(1)}%</span>
                                        </div>
                                        <Progress 
                                            value={paymentProgress} 
                                            className="h-2"
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Production Quick Actions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Factory className="h-5 w-5 mr-2" />
                                        Production
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {printJob ? (
                                        <>
                                            <Button asChild variant="outline" className="w-full justify-start">
                                                <Link href={route('production.print-jobs.show', printJob.id)}>
                                                    <Eye className="h-4 w-4 mr-2" />
                                                    View Print Job Details
                                                </Link>
                                            </Button>
                                            
                                            {permissions.manage_production && printJob.can_start_production && printJob.production_status === 'pending' && (
                                                <Button 
                                                    className="w-full justify-start" 
                                                    onClick={handleStartProduction}
                                                >
                                                    <PlayCircle className="h-4 w-4 mr-2" />
                                                    Start Production
                                                </Button>
                                            )}
                                            
                                            {permissions.manage_production && (
                                                <Button asChild variant="outline" className="w-full justify-start">
                                                    <Link href={route('production.print-jobs.edit', printJob.id)}>
                                                        <Settings className="h-4 w-4 mr-2" />
                                                        Update Job Details
                                                    </Link>
                                                </Button>
                                            )}
                                        </>
                                    ) : (
                                        <>
                                            {/* Show Create Print Job button if conditions are met */}
                                            {canCreatePrintJob() ? (
                                                <Button 
                                                    className="w-full justify-start" 
                                                    onClick={() => setIsPrintJobDialogOpen(true)}
                                                >
                                                    <Plus className="h-4 w-4 mr-2" />
                                                    Create Print Job
                                                </Button>
                                            ) : (
                                                <div className="text-center py-4">
                                                    <Factory className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                                                    <p className="text-sm text-gray-500">
                                                        {!permissions.create_print_job ? 'No print job permission' :
                                                         paymentData.remaining_balance > 0 ? 'Payment required first' :
                                                         'Print job not available'}
                                                    </p>
                                                    {paymentData.remaining_balance > 0 && permissions.create_payment && (
                                                        <Button 
                                                            size="sm" 
                                                            className="mt-2"
                                                            onClick={() => setIsPaymentDialogOpen(true)}
                                                        >
                                                            Record Payment
                                                        </Button>
                                                    )}
                                                </div>
                                            )}
                                        </>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Payment History */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <History className="h-5 w-5 mr-2" />
                                        Payment History
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {paymentData.payments.length > 0 ? (
                                        <div className="space-y-4">
                                            {paymentData.payments.map((payment) => (
                                                <div key={payment.id} className="border-b pb-4 last:border-b-0">
                                                    <div className="flex justify-between items-start mb-2">
                                                        <div className="flex-1">
                                                            <div className="flex items-center space-x-2 mb-1">
                                                                <Badge className={`${payment.status === 'completed' ? 'bg-green-500' : payment.status === 'pending' ? 'bg-yellow-500' : 'bg-gray-500'}`}>
                                                                    {payment.status_label}
                                                                </Badge>
                                                                <span className="text-xs text-gray-500">
                                                                    {payment.payment_method_label}
                                                                </span>
                                                            </div>
                                                            <div className="text-xs text-gray-500 space-y-1">
                                                                <div className="flex items-center">
                                                                    <Calendar className="h-3 w-3 mr-1" />
                                                                    {formatDate(payment.payment_date)}
                                                                </div>
                                                                {payment.transaction_id && (
                                                                    <div>ID: {payment.transaction_id}</div>
                                                                )}
                                                                {payment.notes && (
                                                                    <div>{payment.notes.substring(0, 50)}{payment.notes.length > 50 ? '...' : ''}</div>
                                                                )}
                                                            </div>
                                                        </div>
                                                        <div className="text-right">
                                                            <div className={`font-semibold ${
                                                                payment.status === 'completed' ? 'text-green-600' : 'text-gray-500'
                                                            }`}>
                                                                {formatCurrency(payment.amount)}
                                                            </div>
                                                            <div className="flex space-x-1 mt-1">
                                                                <Button asChild size="sm" variant="outline">
                                                                    <Link href={route('payments.show', payment.id)}>
                                                                        <Eye className="h-3 w-3" />
                                                                    </Link>
                                                                </Button>
                                                                {permissions.verify_payment && payment.verification_status === 'pending' && (
                                                                    <>
                                                                        <Button 
                                                                            size="sm" 
                                                                            variant="outline"
                                                                            onClick={() => handleVerifyPayment(payment.id)}
                                                                        >
                                                                            <Check className="h-3 w-3" />
                                                                        </Button>
                                                                        <Button 
                                                                            size="sm" 
                                                                            variant="outline"
                                                                            onClick={() => handleRejectPayment(payment.id)}
                                                                        >
                                                                            <X className="h-3 w-3" />
                                                                        </Button>
                                                                    </>
                                                                )}
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8">
                                            <CreditCard className="h-12 w-12 text-gray-400 mx-auto mb-4" />
                                            <p className="text-gray-500 mb-4">No payments recorded yet</p>
                                            {permissions.create_payment && (
                                                <Button onClick={() => setIsPaymentDialogOpen(true)}>
                                                    Record First Payment
                                                </Button>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Quick Actions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <DollarSign className="h-5 w-5 mr-2" />
                                        Quick Actions
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    <Button asChild variant="outline" className="w-full justify-start">
                                        <Link href={route('payment-verifications.create', { invoice_id: invoice.id })}>
                                            <CheckCircle className="h-4 w-4 mr-2" />
                                            Customer Payment Claim
                                        </Link>
                                    </Button>
                                    
                                    <Button asChild variant="outline" className="w-full justify-start">
                                        <Link href={route('payments.index', { invoice_id: invoice.id })}>
                                            <History className="h-4 w-4 mr-2" />
                                            View All Payments
                                        </Link>
                                    </Button>
                                    
                                    <Button 
                                        variant="outline" 
                                        className="w-full justify-start"
                                        onClick={handlePrint}
                                    >
                                        <Printer className="h-4 w-4 mr-2" />
                                        Print Invoice
                                    </Button>

                                    {permissions.generate_pdf && (
                                        <Button asChild variant="outline" className="w-full justify-start">
                                            <Link href={route('invoices.pdf', invoice.id)} target="_blank">
                                                <Download className="h-4 w-4 mr-2" />
                                                Download Invoice PDF
                                            </Link>
                                        </Button>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}