import React, { useState, useEffect } from 'react';
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
    LoaderCircle
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
    total_weight: number;
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
    line_weight: number;
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

interface Props {
    invoice: Invoice;
    paymentSummary: PaymentSummary;
    recentPayments: Payment[];
    permissions: {
        edit: boolean;
        delete: boolean;
        create_payment: boolean;
        verify_payment: boolean;
        generate_pdf: boolean;
    };
    paymentMethods: Record<string, string>;
}

export default function InvoiceShow({ invoice, paymentSummary, recentPayments, permissions, paymentMethods }: Props) {
    const [isPaymentDialogOpen, setIsPaymentDialogOpen] = useState(false);
    const [paymentData, setPaymentData] = useState(paymentSummary);
    
    // Initialize form with proper default values
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: paymentSummary.remaining_balance > 0 ? paymentSummary.remaining_balance.toString() : '0',
        payment_method: 'cash', // Default to cash
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
        bank_name: '',
        transaction_id: '',
        gateway_reference: '', // Required for online payments
        cheque_number: '', // Required for cheques
    });

    const statusColors = {
        draft: 'bg-gray-500',
        pending: 'bg-yellow-500',
        processing: 'bg-blue-500',
        completed: 'bg-green-500',
        cancelled: 'bg-red-500'
    };

    const paymentStatusColors = {
        pending: 'bg-red-500',
        partially_paid: 'bg-yellow-500',
        paid: 'bg-green-500',
        refunded: 'bg-purple-500'
    };

    // Enhanced payment form handler with proper validation and error handling
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
        console.log('Route:', route('invoices.record-payment', invoice.id));
        
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
                reset({
                    amount: '',
                    payment_method: 'cash',
                    payment_date: new Date().toISOString().split('T')[0],
                    notes: '',
                    bank_name: '',
                    transaction_id: '',
                    gateway_reference: '',
                    cheque_number: '',
                });
                
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
                                                            <LoaderCircle className="h-4 w-4 animate-spin mr-2" />
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
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Content */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Invoice Status */}
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center space-x-2">
                                            <Badge className={statusColors[invoice.status as keyof typeof statusColors]}>
                                                {invoice.status.charAt(0).toUpperCase() + invoice.status.slice(1)}
                                            </Badge>
                                            <Badge className={paymentStatusColors[invoice.payment_status as keyof typeof paymentStatusColors]}>
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
                                                        <td className="text-center py-3">{(parseFloat(item.line_weight) || 0).toFixed(2)}kg</td>
                                                        <td className="text-right py-3">{formatCurrency(item.unit_price)}</td>
                                                        <td className="text-right py-3">{formatCurrency(item.line_total)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot className="border-t bg-gray-50">
                                                <tr>
                                                    <td colSpan={2} className="py-2 font-medium">Subtotal</td>
                                                    <td className="text-center py-2">{(parseFloat(invoice.total_weight) || 0).toFixed(2)}kg</td>
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

                        {/* Payment Sidebar */}
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
                                            className={`h-2 ${
                                                paymentData.payment_status === 'paid' ? 'bg-green-200' :
                                                paymentData.payment_status === 'partially_paid' ? 'bg-yellow-200' : 'bg-red-200'
                                            }`}
                                        />
                                    </div>
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
                                                                <Badge className={`bg-${payment.status_color}-500`}>
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
                                                                    <div>{payment.notes.substring(0, 50)}...</div>
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