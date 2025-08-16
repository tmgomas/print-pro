// resources/js/pages/Expenses/Create.tsx

import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import InputError from '@/components/input-error';
import { 
    ArrowLeft, 
    Receipt, 
    Save,
    Send,
    AlertCircle,
    DollarSign,
    Calendar,
    Building2,
    User,
    CreditCard,
    FileText,
    Upload,
    X,
    Plus,
    Loader2,
    Tag,
    Repeat,
    AlertTriangle
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface ExpenseCategory {
    id: number;
    name: string;
    code: string;
    color?: string;
    icon?: string;
    full_name?: string;
}

interface Branch {
    id: number;
    branch_name: string;
    branch_code: string;
}

interface Props {
    categories: ExpenseCategory[];
    branches: Branch[];
    statusOptions: Record<string, string>;
    priorityOptions: Record<string, string>;
    paymentMethodOptions: Record<string, string>;
    recurringPeriodOptions: Record<string, string>;
}

interface FormData {
    branch_id: string;
    category_id: string;
    expense_date: string;
    amount: string;
    description: string;
    vendor_name: string;
    vendor_address: string;
    vendor_phone: string;
    vendor_email: string;
    payment_method: string;
    payment_reference: string;
    receipt_number: string;
    priority: string;
    is_recurring: boolean;
    recurring_period: string;
    notes: string;
    receipt_files: File[];
    submit_for_approval: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Management', href: '/expenses' },
    { title: 'Create Expense', href: '/expenses/create' },
];

export default function CreateExpense({ 
    categories, 
    branches, 
    statusOptions,
    priorityOptions,
    paymentMethodOptions,
    recurringPeriodOptions 
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
        branch_id: '',
        category_id: '',
        expense_date: new Date().toISOString().split('T')[0],
        amount: '',
        description: '',
        vendor_name: '',
        vendor_address: '',
        vendor_phone: '',
        vendor_email: '',
        payment_method: 'cash',
        payment_reference: '',
        receipt_number: '',
        priority: 'medium',
        is_recurring: false,
        recurring_period: '',
        notes: '',
        receipt_files: [],
        submit_for_approval: false,
    });

    const [uploadedFiles, setUploadedFiles] = useState<File[]>([]);
    const [dragActive, setDragActive] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        const formData = new FormData();
        
        // Add all form fields
        Object.entries(data).forEach(([key, value]) => {
            if (key === 'receipt_files') return; // Handle separately
            if (key === 'is_recurring' || key === 'submit_for_approval') {
                formData.append(key, value ? '1' : '0');
            } else {
                formData.append(key, value.toString());
            }
        });
        
        // Add files
        uploadedFiles.forEach((file, index) => {
            formData.append(`receipt_files[${index}]`, file);
        });
        
        post('/expenses', {
            data: formData,
            forceFormData: true,
            onSuccess: () => {
                console.log('✅ Expense created successfully!');
            },
            onError: (errors) => {
                console.log('✗ Validation errors:', errors);
            }
        });
    };

    const handleFileUpload = (files: FileList | null) => {
        if (!files) return;
        
        const newFiles = Array.from(files).filter(file => {
            // Validate file type and size
            const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf'];
            const maxSize = 5 * 1024 * 1024; // 5MB
            
            return allowedTypes.includes(file.type) && file.size <= maxSize;
        });
        
        setUploadedFiles(prev => [...prev, ...newFiles].slice(0, 5)); // Max 5 files
    };

    const removeFile = (index: number) => {
        setUploadedFiles(prev => prev.filter((_, i) => i !== index));
    };

    const handleDrag = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === "dragenter" || e.type === "dragover") {
            setDragActive(true);
        } else if (e.type === "dragleave") {
            setDragActive(false);
        }
    };

    const handleDrop = (e: React.DragEvent) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        
        if (e.dataTransfer.files && e.dataTransfer.files[0]) {
            handleFileUpload(e.dataTransfer.files);
        }
    };

    const selectedCategory = categories.find(cat => cat.id.toString() === data.category_id);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Expense" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Button variant="outline" asChild>
                        <Link href="/expenses">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Expenses
                        </Link>
                    </Button>
                    <div className="text-right">
                        <h1 className="text-3xl font-bold tracking-tight">Create New Expense</h1>
                        <p className="text-muted-foreground">Record a new business expense</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit}>
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Form */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Receipt className="h-5 w-5" />
                                        Basic Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Branch */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="branch_id">Branch *</Label>
                                            <Select value={data.branch_id} onValueChange={(value) => setData('branch_id', value)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select branch" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {branches.map((branch) => (
                                                        <SelectItem key={branch.id} value={branch.id.toString()}>
                                                            {branch.branch_name} ({branch.branch_code})
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.branch_id} />
                                        </div>

                                        {/* Category */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="category_id">Category *</Label>
                                            <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categories.map((category) => (
                                                        <SelectItem key={category.id} value={category.id.toString()}>
                                                            <div className="flex items-center gap-2">
                                                                <div 
                                                                    className="w-3 h-3 rounded-full"
                                                                    style={{ backgroundColor: category.color || '#6B7280' }}
                                                                />
                                                                {category.full_name || category.name} ({category.code})
                                                            </div>
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.category_id} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Date */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="expense_date">Expense Date *</Label>
                                            <Input
                                                id="expense_date"
                                                type="date"
                                                value={data.expense_date}
                                                onChange={(e) => setData('expense_date', e.target.value)}
                                                required
                                            />
                                            <InputError message={errors.expense_date} />
                                        </div>

                                        {/* Amount */}
                                        <div className="grid gap-2">
                                            <Label htmlFor="amount">Amount (Rs.) *</Label>
                                            <div className="relative">
                                                <DollarSign className="absolute left-3 top-3 h-4 w-4 text-muted-foreground" />
                                                <Input
                                                    id="amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    value={data.amount}
                                                    onChange={(e) => setData('amount', e.target.value)}
                                                    placeholder="0.00"
                                                    className="pl-9"
                                                    required
                                                />
                                            </div>
                                            <InputError message={errors.amount} />
                                        </div>
                                    </div>

                                    {/* Description */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">Description *</Label>
                                        <Textarea
                                            id="description"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Describe the expense purpose and details..."
                                            rows={3}
                                            maxLength={1000}
                                            required
                                        />
                                        <InputError message={errors.description} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Vendor Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Building2 className="h-5 w-5" />
                                        Vendor Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_name">Vendor Name</Label>
                                            <Input
                                                id="vendor_name"
                                                type="text"
                                                value={data.vendor_name}
                                                onChange={(e) => setData('vendor_name', e.target.value)}
                                                placeholder="Company or person name"
                                            />
                                            <InputError message={errors.vendor_name} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_phone">Vendor Phone</Label>
                                            <Input
                                                id="vendor_phone"
                                                type="tel"
                                                value={data.vendor_phone}
                                                onChange={(e) => setData('vendor_phone', e.target.value)}
                                                placeholder="+94 XX XXX XXXX"
                                            />
                                            <InputError message={errors.vendor_phone} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="vendor_email">Vendor Email</Label>
                                            <Input
                                                id="vendor_email"
                                                type="email"
                                                value={data.vendor_email}
                                                onChange={(e) => setData('vendor_email', e.target.value)}
                                                placeholder="vendor@example.com"
                                            />
                                            <InputError message={errors.vendor_email} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="receipt_number">Receipt/Invoice Number</Label>
                                            <Input
                                                id="receipt_number"
                                                type="text"
                                                value={data.receipt_number}
                                                onChange={(e) => setData('receipt_number', e.target.value)}
                                                placeholder="Receipt or invoice number"
                                            />
                                            <InputError message={errors.receipt_number} />
                                        </div>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label htmlFor="vendor_address">Vendor Address</Label>
                                        <Textarea
                                            id="vendor_address"
                                            value={data.vendor_address}
                                            onChange={(e) => setData('vendor_address', e.target.value)}
                                            placeholder="Complete vendor address"
                                            rows={2}
                                        />
                                        <InputError message={errors.vendor_address} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Payment Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="h-5 w-5" />
                                        Payment Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="grid gap-2">
                                            <Label htmlFor="payment_method">Payment Method *</Label>
                                            <Select value={data.payment_method} onValueChange={(value) => setData('payment_method', value)}>
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select payment method" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Object.entries(paymentMethodOptions).map(([key, label]) => (
                                                        <SelectItem key={key} value={key}>{label}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.payment_method} />
                                        </div>

                                        <div className="grid gap-2">
                                            <Label htmlFor="payment_reference">Payment Reference</Label>
                                            <Input
                                                id="payment_reference"
                                                type="text"
                                                value={data.payment_reference}
                                                onChange={(e) => setData('payment_reference', e.target.value)}
                                                placeholder="Transaction ID, cheque number, etc."
                                            />
                                            <InputError message={errors.payment_reference} />
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Receipt Upload */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Upload className="h-5 w-5" />
                                        Receipt Attachments
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {/* Upload Area */}
                                        <div
                                            className={`border-2 border-dashed rounded-lg p-6 text-center transition-colors ${
                                                dragActive ? 'border-blue-500 bg-blue-50' : 'border-gray-300'
                                            }`}
                                            onDragEnter={handleDrag}
                                            onDragLeave={handleDrag}
                                            onDragOver={handleDrag}
                                            onDrop={handleDrop}
                                        >
                                            <Upload className="h-8 w-8 text-gray-400 mx-auto mb-2" />
                                            <p className="text-sm text-gray-600 mb-2">
                                                Drag and drop receipt files here, or click to browse
                                            </p>
                                            <p className="text-xs text-gray-500 mb-4">
                                                Supports JPG, PNG, PDF up to 5MB (max 5 files)
                                            </p>
                                            <input
                                                type="file"
                                                multiple
                                                accept=".jpg,.jpeg,.png,.pdf"
                                                onChange={(e) => handleFileUpload(e.target.files)}
                                                className="hidden"
                                                id="receipt-upload"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => document.getElementById('receipt-upload')?.click()}
                                            >
                                                Choose Files
                                            </Button>
                                        </div>

                                        {/* Uploaded Files */}
                                        {uploadedFiles.length > 0 && (
                                            <div className="space-y-2">
                                                <Label>Uploaded Files</Label>
                                                {uploadedFiles.map((file, index) => (
                                                    <div key={index} className="flex items-center justify-between p-2 border rounded">
                                                        <div className="flex items-center gap-2">
                                                            <FileText className="h-4 w-4 text-gray-500" />
                                                            <span className="text-sm">{file.name}</span>
                                                            <span className="text-xs text-gray-500">
                                                                ({(file.size / 1024 / 1024).toFixed(2)} MB)
                                                            </span>
                                                        </div>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeFile(index)}
                                                        >
                                                            <X className="h-4 w-4" />
                                                        </Button>
                                                    </div>
                                                ))}
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Additional Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="h-5 w-5" />
                                        Additional Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Notes */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="notes">Notes & Comments</Label>
                                        <Textarea
                                            id="notes"
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                            placeholder="Any additional notes or comments about this expense..."
                                            rows={3}
                                        />
                                        <InputError message={errors.notes} />
                                    </div>

                                    {/* Recurring Expense */}
                                    <div className="space-y-3">
                                        <div className="flex items-center space-x-2">
                                            <Checkbox
                                                id="is_recurring"
                                                checked={data.is_recurring}
                                                onCheckedChange={(checked) => setData('is_recurring', checked as boolean)}
                                            />
                                            <Label htmlFor="is_recurring" className="flex items-center gap-2">
                                                <Repeat className="h-4 w-4" />
                                                This is a recurring expense
                                            </Label>
                                        </div>

                                        {data.is_recurring && (
                                            <div className="grid gap-2 ml-6">
                                                <Label htmlFor="recurring_period">Recurring Period *</Label>
                                                <Select 
                                                    value={data.recurring_period} 
                                                    onValueChange={(value) => setData('recurring_period', value)}
                                                >
                                                    <SelectTrigger className="w-48">
                                                        <SelectValue placeholder="Select frequency" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {Object.entries(recurringPeriodOptions).map(([key, label]) => (
                                                            <SelectItem key={key} value={key}>{label}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <InputError message={errors.recurring_period} />
                                                <p className="text-xs text-muted-foreground">
                                                    Future expenses will be automatically created based on this schedule
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Tag className="h-5 w-5" />
                                        Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Priority */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="priority">Priority *</Label>
                                        <Select value={data.priority} onValueChange={(value) => setData('priority', value)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select priority" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {Object.entries(priorityOptions).map(([key, label]) => (
                                                    <SelectItem key={key} value={key}>{label}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.priority} />
                                    </div>

                                    {/* Submit for Approval */}
                                    <div className="space-y-3">
                                        <div className="flex items-center space-x-2">
                                            <Checkbox
                                                id="submit_for_approval"
                                                checked={data.submit_for_approval}
                                                onCheckedChange={(checked) => setData('submit_for_approval', checked as boolean)}
                                            />
                                            <Label htmlFor="submit_for_approval" className="flex items-center gap-2">
                                                <Send className="h-4 w-4" />
                                                Submit for approval immediately
                                            </Label>
                                        </div>
                                        <p className="text-xs text-muted-foreground ml-6">
                                            If unchecked, expense will be saved as draft for later submission
                                        </p>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Category Preview */}
                            {selectedCategory && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <Tag className="h-5 w-5" />
                                            Category Preview
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="flex items-center gap-3 p-3 border rounded-lg bg-gray-50">
                                            <div
                                                className="w-8 h-8 rounded flex items-center justify-center text-white text-sm"
                                                style={{ backgroundColor: selectedCategory.color || '#6B7280' }}
                                            >
                                                {selectedCategory.icon ? 
                                                    selectedCategory.icon.charAt(0).toUpperCase() : 
                                                    <Tag className="h-4 w-4" />
                                                }
                                            </div>
                                            <div>
                                                <div className="font-medium">{selectedCategory.name}</div>
                                                <div className="text-sm text-gray-500">({selectedCategory.code})</div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Amount Summary */}
                            {data.amount && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5" />
                                            Amount Summary
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Base Amount:</span>
                                                <span className="font-medium">Rs. {parseFloat(data.amount || '0').toLocaleString()}</span>
                                            </div>
                                            <div className="border-t pt-2">
                                                <div className="flex justify-between text-lg font-bold">
                                                    <span>Total Amount:</span>
                                                    <span>Rs. {parseFloat(data.amount || '0').toLocaleString()}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Help Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <AlertCircle className="h-5 w-5" />
                                        Guidelines
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3 text-sm text-muted-foreground">
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Ensure all required fields are completed accurately</p>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Upload clear photos or scans of receipts for verification</p>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Choose appropriate categories for better expense tracking</p>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Set correct priority for approval workflow management</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Action Buttons */}
                            <Card>
                                <CardContent className="p-6">
                                    <div className="flex flex-col gap-3">
                                        <Button 
                                            type="submit" 
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            {processing ? (
                                                <>
                                                    <Loader2 className="h-4 w-4 mr-2 animate-spin" />
                                                    {data.submit_for_approval ? 'Creating & Submitting...' : 'Creating Expense...'}
                                                </>
                                            ) : (
                                                <>
                                                    {data.submit_for_approval ? (
                                                        <>
                                                            <Send className="h-4 w-4 mr-2" />
                                                            Create & Submit for Approval
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Save className="h-4 w-4 mr-2" />
                                                            Save as Draft
                                                        </>
                                                    )}
                                                </>
                                            )}
                                        </Button>
                                        
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            onClick={() => reset()}
                                            disabled={processing}
                                            className="w-full"
                                        >
                                            Reset Form
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Warnings */}
                            {data.amount && parseFloat(data.amount) > 50000 && (
                                <Alert>
                                    <AlertTriangle className="h-4 w-4" />
                                    <AlertDescription>
                                        High-value expense detected. This may require additional approval levels.
                                    </AlertDescription>
                                </Alert>
                            )}
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}