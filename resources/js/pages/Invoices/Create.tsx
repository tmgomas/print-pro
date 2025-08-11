import { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import InputError from '@/components/input-error';
import { 
    ArrowLeft, 
    Plus, 
    Trash2, 
    Calculator, 
    FileText, 
    User, 
    Building2, 
    Calendar, 
    DollarSign,
    Weight,
    Package,
    Save,
    AlertCircle,
    Info,
    Eye,
    Settings,
    Search,
    X,
    ChevronDown,
    Check
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

// Interfaces
interface Customer {
    value: number;
    label: string;
    display_name: string;
    credit_limit: number;
    current_balance: number;
    phone?: string;
    email?: string;
}

interface Product {
    value: number;
    label: string;
    name: string;
    base_price: number;
    weight_per_unit: number;
    weight_unit: string;
    tax_rate: number;
    unit_type: string;
}

interface Branch {
    value: number;
    label: string;
    name: string;
    code: string;
}

interface InvoiceItem {
    id: string;
    product_id: number | '';
    item_description: string;
    quantity: string;
    unit_price: string;
    unit_weight: string;
    line_total: number;
    line_weight: number;
    tax_amount: number;
    specifications?: string;
}

interface Props {
    customers?: Customer[];
    products?: Product[];
    branches?: Branch[];
    default_branch_id?: number;
    error?: string;
}

// Generic Searchable Select Component
interface SearchableSelectProps<T> {
    options: T[];
    value: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    getLabel: (option: T) => string;
    getValue: (option: T) => string;
    getSearchText?: (option: T) => string;
    renderOption?: (option: T) => React.ReactNode;
    disabled?: boolean;
    className?: string;
}

function SearchableSelect<T>({ 
    options, 
    value, 
    onValueChange, 
    placeholder = "Search and select...",
    getLabel,
    getValue,
    getSearchText,
    renderOption,
    disabled = false,
    className = ""
}: SearchableSelectProps<T>) {
    const [isOpen, setIsOpen] = useState(false);
    const [searchTerm, setSearchTerm] = useState('');
    const [filteredOptions, setFilteredOptions] = useState<T[]>(options);

    // Get selected option for display
    const selectedOption = options.find(option => getValue(option) === value);

    // Filter options based on search term
    useEffect(() => {
        if (!searchTerm.trim()) {
            setFilteredOptions(options);
        } else {
            const filtered = options.filter(option => {
                const label = getLabel(option)?.toLowerCase() || '';
                const searchText = getSearchText ? getSearchText(option)?.toLowerCase() || '' : '';
                const searchValue = getValue(option)?.toLowerCase() || '';
                
                return label.includes(searchTerm.toLowerCase()) ||
                       searchText.includes(searchTerm.toLowerCase()) ||
                       searchValue.includes(searchTerm.toLowerCase());
            });
            setFilteredOptions(filtered);
        }
    }, [searchTerm, options, getLabel, getSearchText, getValue]);

    const handleSelect = (optionValue: string) => {
        onValueChange(optionValue);
        setIsOpen(false);
        setSearchTerm('');
    };

    const clearSelection = (e: React.MouseEvent) => {
        e.stopPropagation();
        onValueChange('');
        setSearchTerm('');
    };

    if (disabled) {
        return (
            <div className={`flex h-10 w-full items-center justify-between rounded-md border border-input bg-muted px-3 py-2 text-sm text-muted-foreground ${className}`}>
                <span>{selectedOption ? getLabel(selectedOption) : placeholder}</span>
                <ChevronDown className="h-4 w-4" />
            </div>
        );
    }

    return (
        <div className={`relative ${className}`}>
            {/* Trigger Button */}
            <div
                className="flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background cursor-pointer hover:bg-accent focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2"
                onClick={() => setIsOpen(!isOpen)}
            >
                <span className={selectedOption ? "text-foreground" : "text-muted-foreground"}>
                    {selectedOption ? getLabel(selectedOption) : placeholder}
                </span>
                <div className="flex items-center gap-2">
                    {selectedOption && (
                        <X 
                            className="h-4 w-4 text-muted-foreground hover:text-foreground" 
                            onClick={clearSelection}
                        />
                    )}
                    <ChevronDown className="h-4 w-4 text-muted-foreground" />
                </div>
            </div>

            {/* Dropdown Content */}
            {isOpen && (
                <div className="absolute top-full left-0 right-0 z-50 mt-1 bg-popover border rounded-md shadow-lg">
                    {/* Search Input */}
                    <div className="p-2 border-b">
                        <div className="relative">
                            <Search className="absolute left-2 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search..."
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                                className="pl-8"
                                autoFocus
                            />
                        </div>
                    </div>

                    {/* Options List */}
                    <div className="max-h-60 overflow-y-auto">
                        {filteredOptions.length > 0 ? (
                            filteredOptions.map((option, index) => {
                                const optionValue = getValue(option);
                                const isSelected = optionValue === value;
                                
                                return (
                                    <div
                                        key={index}
                                        className={`flex items-center justify-between p-3 hover:bg-accent cursor-pointer border-b last:border-b-0 ${isSelected ? 'bg-accent' : ''}`}
                                        onClick={() => handleSelect(optionValue)}
                                    >
                                        <div className="flex-1">
                                            {renderOption ? renderOption(option) : (
                                                <div className="font-medium">{getLabel(option)}</div>
                                            )}
                                        </div>
                                        {isSelected && (
                                            <Check className="h-4 w-4 text-primary" />
                                        )}
                                    </div>
                                );
                            })
                        ) : (
                            <div className="p-3 text-center text-muted-foreground">
                                No options found matching "{searchTerm}"
                            </div>
                        )}
                    </div>
                </div>
            )}

            {/* Overlay to close dropdown */}
            {isOpen && (
                <div 
                    className="fixed inset-0 z-40" 
                    onClick={() => setIsOpen(false)}
                />
            )}
        </div>
    );
}

// Weight pricing calculation (based on documentation)
const calculateWeightCharge = (totalWeight: number): number => {
    if (totalWeight <= 1) return 200;
    if (totalWeight <= 3) return 300;
    if (totalWeight <= 5) return 400;
    if (totalWeight <= 10) return 500 + ((totalWeight - 5) * 50);
    return 750 + ((totalWeight - 10) * 75);
};

export default function CreateInvoice({ 
    customers = [], 
    products = [], 
    branches = [], 
    default_branch_id,
    error 
}: Props) {
    const [items, setItems] = useState<InvoiceItem[]>([]);
    const [selectedCustomer, setSelectedCustomer] = useState<Customer | null>(null);
    const [invoiceTotals, setInvoiceTotals] = useState({
        subtotal: 0,
        totalWeight: 0,
        weightCharge: 0,
        taxAmount: 0,
        discountAmount: 0,
        total: 0
    });

    // Get customer ID from URL query parameter
    const urlParams = new URLSearchParams(window.location.search);
    const preSelectedCustomerId = urlParams.get('customer');

    const { data, setData, post, processing, errors, reset } = useForm({
        customer_id: preSelectedCustomerId ? parseInt(preSelectedCustomerId) : '',
        branch_id: default_branch_id || '',
        invoice_date: new Date().toISOString().split('T')[0],
        due_date: new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
        notes: '',
        terms_conditions: '',
        discount_amount: '0',
        status: 'draft',
        items: [] as any[]
    });

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Invoices', href: '/invoices' },
        { title: 'Create Invoice', href: '/invoices/create' },
    ];

    // Status options
    const statusOptions = [
        { value: 'draft', label: 'Draft', description: 'Work in progress' },
        { value: 'pending', label: 'Pending', description: 'Awaiting approval' },
        { value: 'processing', label: 'Processing', description: 'Being processed' },
        { value: 'completed', label: 'Completed', description: 'Finished' }
    ];

    // Initialize with pre-selected customer
    useEffect(() => {
        if (preSelectedCustomerId && customers.length > 0) {
            const customer = customers.find(c => c.value === parseInt(preSelectedCustomerId));
            if (customer) {
                setSelectedCustomer(customer);
                setData('customer_id', customer.value);
            }
        }
    }, [preSelectedCustomerId, customers]);

    // Add new item
    const addItem = () => {
        const newItem: InvoiceItem = {
            id: Date.now().toString(),
            product_id: '',
            item_description: '',
            quantity: '1',
            unit_price: '0',
            unit_weight: '0',
            line_total: 0,
            line_weight: 0,
            tax_amount: 0,
            specifications: ''
        };
        
        const newItems = [...items, newItem];
        setItems(newItems);
        
        // Update form data
        setData('items', newItems.map(item => ({
            product_id: item.product_id,
            item_description: item.item_description,
            quantity: parseFloat(item.quantity) || 0,
            unit_price: parseFloat(item.unit_price) || 0,
            unit_weight: parseFloat(item.unit_weight) || 0,
            specifications: item.specifications ? 
                (typeof item.specifications === 'string' ? 
                    { notes: item.specifications } : 
                    item.specifications
                ) : {}
        })));
        
        console.log('Added new item, total items:', newItems.length);
    };

    // Remove item
    const removeItem = (id: string) => {
        const filteredItems = items.filter(item => item.id !== id);
        setItems(filteredItems);
        
        // Update form data
        setData('items', filteredItems.map(item => ({
            product_id: item.product_id,
            item_description: item.item_description,
            quantity: parseFloat(item.quantity) || 0,
            unit_price: parseFloat(item.unit_price) || 0,
            unit_weight: parseFloat(item.unit_weight) || 0,
            specifications: item.specifications ? 
                (typeof item.specifications === 'string' ? 
                    { notes: item.specifications } : 
                    item.specifications
                ) : {}
        })));
        
        console.log('Removed item, remaining items:', filteredItems.length);
    };

    const updateItem = (id: string, field: keyof InvoiceItem, value: any) => {
        const updatedItems = items.map(item => {
            if (item.id === id) {
                const updatedItem = { ...item, [field]: value };
                
                // Auto-fill product details when product is selected
                if (field === 'product_id' && value) {
                    const product = products.find(p => p.value === parseInt(value.toString()));
                    if (product) {
                        updatedItem.item_description = product.name;
                        updatedItem.unit_price = (parseFloat(product.base_price?.toString()) || 0).toString();
                        
                        // Convert weight to kg based on weight_unit
                        let weightInKg = parseFloat(product.weight_per_unit?.toString()) || 0;
                        if (product.weight_unit === 'grams' || product.weight_unit === 'g') {
                            weightInKg = weightInKg / 1000; // Convert grams to kg
                        } else if (product.weight_unit === 'lb') {
                            weightInKg = weightInKg * 0.453592; // Convert pounds to kg
                        } else if (product.weight_unit === 'oz') {
                            weightInKg = weightInKg * 0.0283495; // Convert ounces to kg
                        }
                        
                        updatedItem.unit_weight = weightInKg.toString();
                        
                        console.log(`Weight Conversion: ${product.weight_per_unit} ${product.weight_unit} → ${weightInKg} kg`);
                    }
                }
                
                // Recalculate line totals
                if (field === 'quantity' || field === 'unit_price') {
                    const quantity = parseFloat(updatedItem.quantity) || 0;
                    const unitPrice = parseFloat(updatedItem.unit_price) || 0;
                    updatedItem.line_total = quantity * unitPrice;
                    
                    // Calculate tax
                    if (updatedItem.product_id) {
                        const product = products.find(p => p.value === parseInt(updatedItem.product_id.toString()));
                        if (product) {
                            const taxRate = parseFloat(product.tax_rate?.toString()) || 0;
                            updatedItem.tax_amount = updatedItem.line_total * (taxRate / 100);
                        }
                    }
                }
                
                if (field === 'quantity' || field === 'unit_weight') {
                    const quantity = parseFloat(updatedItem.quantity) || 0;
                    const unitWeight = parseFloat(updatedItem.unit_weight) || 0;
                    updatedItem.line_weight = quantity * unitWeight;
                }
                
                return updatedItem;
            }
            return item;
        });
        
        setItems(updatedItems);
        
        // Update form data
        setData('items', updatedItems.map(item => ({
            product_id: item.product_id,
            item_description: item.item_description,
            quantity: parseFloat(item.quantity) || 0,
            unit_price: parseFloat(item.unit_price) || 0,
            unit_weight: parseFloat(item.unit_weight) || 0,
            specifications: item.specifications ? 
                (typeof item.specifications === 'string' ? 
                    { notes: item.specifications } : 
                    item.specifications
                ) : {}
        })));
    };

    // Calculate invoice totals
    const calculateTotals = (currentItems: InvoiceItem[]) => {
        let subtotal = 0;
        let totalWeight = 0;
        let taxAmount = 0;
        
        currentItems.forEach(item => {
            const quantity = parseFloat(item.quantity) || 0;
            const unitPrice = parseFloat(item.unit_price) || 0;
            const unitWeight = parseFloat(item.unit_weight) || 0;
            
            const lineTotal = quantity * unitPrice;
            const lineWeight = quantity * unitWeight;
            
            subtotal += lineTotal;
            totalWeight += lineWeight;
            taxAmount += item.tax_amount || 0;
        });
        
        const weightCharge = calculateWeightCharge(totalWeight);
        const discountAmount = parseFloat(data.discount_amount) || 0;
        const total = subtotal + weightCharge + taxAmount - discountAmount;
        
        setInvoiceTotals({
            subtotal,
            totalWeight,
            weightCharge,
            taxAmount,
            discountAmount,
            total
        });
    };

    // Auto-calculate totals when items or discount changes
    useEffect(() => {
        calculateTotals(items);
    }, [items, data.discount_amount]);

    // Handle customer selection
    const handleCustomerChange = (customerId: string) => {
        if (customers.length > 0) {
            const customer = customers.find(c => c.value === parseInt(customerId));
            setSelectedCustomer(customer || null);
            setData('customer_id', parseInt(customerId));
        }
    };

    // Handle form submission
    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        // Validation check before submitting
        if (items.length === 0) {
            alert('Please add at least one item to the invoice');
            return;
        }
        
        // Check if all items have required fields
        const invalidItems = items.filter(item => 
            !item.product_id || 
            parseFloat(item.quantity) <= 0 || 
            parseFloat(item.unit_price) < 0
        );
        
        if (invalidItems.length > 0) {
            alert('Please fill in all required fields for each item');
            return;
        }
        
        const formData = {
            ...data,
            items: items.map(item => ({
                product_id: parseInt(item.product_id.toString()),
                item_description: item.item_description,
                quantity: parseFloat(item.quantity),
                unit_price: parseFloat(item.unit_price),
                unit_weight: parseFloat(item.unit_weight),
                specifications: item.specifications ? 
                    (typeof item.specifications === 'string' ? 
                        { notes: item.specifications } : 
                        item.specifications
                    ) : {}
            })).filter(item => item.product_id > 0),
            subtotal: invoiceTotals.subtotal,
            weight_charge: invoiceTotals.weightCharge,
            tax_amount: invoiceTotals.taxAmount,
            total_amount: invoiceTotals.total,
            total_weight: invoiceTotals.totalWeight
        };

        console.log('Submitting invoice data:', formData);

        post('/invoices', {
            data: formData,
            onSuccess: () => {
                console.log('Invoice created successfully');
            },
            onError: (errors) => {
                console.error('Invoice creation failed:', errors);
            }
        });
    };

    // Debug function
    const debugCurrentState = () => {
        console.log('Current items state:', items);
        console.log('Current form data:', data);
        console.log('Items length:', items.length);
        console.log('Form items length:', data.items.length);
        console.log('Invoice totals:', invoiceTotals);
        console.log('Products sample:', products.slice(0, 2));
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Invoice" />
            
            <div className="space-y-6">
                {/* Show error if data loading failed */}
                {error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            {error}
                        </AlertDescription>
                    </Alert>
                )}

                {/* Show loading or error state if data is missing */}
                {(!customers || !products || !branches) && !error && (
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            Loading invoice data... If this persists, please refresh the page.
                        </AlertDescription>
                    </Alert>
                )}

                {/* Debug Info (remove in production) */}
                

                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Create Invoice</h1>
                        <p className="text-muted-foreground">
                            Create a new invoice for your customer
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link href="/invoices">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Back to Invoices
                        </Link>
                    </Button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Invoice Details */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <FileText className="h-5 w-5" />
                                Invoice Details
                            </CardTitle>
                            <CardDescription>
                                Basic invoice information and customer selection
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                {/* Customer Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="customer">Customer *</Label>
                                    <SearchableSelect
                                        options={customers}
                                        value={data.customer_id.toString()}
                                        onValueChange={handleCustomerChange}
                                        placeholder="Search and select customer..."
                                        getLabel={(customer) => customer.label}
                                        getValue={(customer) => customer.value.toString()}
                                        getSearchText={(customer) => `${customer.display_name} ${customer.phone || ''} ${customer.email || ''}`}
                                        renderOption={(customer) => (
                                            <div>
                                                <div className="font-medium">{customer.display_name}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    Credit: Rs. {customer.credit_limit.toLocaleString()} • 
                                                    Balance: Rs. {customer.current_balance.toLocaleString()}
                                                    {customer.phone && ` • ${customer.phone}`}
                                                </div>
                                            </div>
                                        )}
                                    />
                                    <InputError message={errors.customer_id} />
                                </div>

                                {/* Branch Selection */}
                                <div className="space-y-2">
                                    <Label htmlFor="branch">Branch *</Label>
                                    <SearchableSelect
                                        options={branches}
                                        value={data.branch_id.toString()}
                                        onValueChange={(value) => setData('branch_id', parseInt(value))}
                                        placeholder="Search and select branch..."
                                        getLabel={(branch) => branch.label}
                                        getValue={(branch) => branch.value.toString()}
                                        getSearchText={(branch) => `${branch.name} ${branch.code}`}
                                        renderOption={(branch) => (
                                            <div>
                                                <div className="font-medium">{branch.name}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    Code: {branch.code}
                                                </div>
                                            </div>
                                        )}
                                    />
                                    <InputError message={errors.branch_id} />
                                </div>

                                {/* Status */}
                                <div className="space-y-2">
                                    <Label htmlFor="status">Status</Label>
                                    <SearchableSelect
                                        options={statusOptions}
                                        value={data.status}
                                        onValueChange={(value) => setData('status', value)}
                                        placeholder="Select status..."
                                        getLabel={(status) => status.label}
                                        getValue={(status) => status.value}
                                        renderOption={(status) => (
                                            <div>
                                                <div className="font-medium">{status.label}</div>
                                                <div className="text-sm text-muted-foreground">
                                                    {status.description}
                                                </div>
                                            </div>
                                        )}
                                    />
                                </div>

                                {/* Invoice Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="invoice_date">Invoice Date</Label>
                                    <Input
                                        type="date"
                                        value={data.invoice_date}
                                        onChange={(e) => setData('invoice_date', e.target.value)}
                                    />
                                    <InputError message={errors.invoice_date} />
                                </div>

                                {/* Due Date */}
                                <div className="space-y-2">
                                    <Label htmlFor="due_date">Due Date</Label>
                                    <Input
                                        type="date"
                                        value={data.due_date}
                                        onChange={(e) => setData('due_date', e.target.value)}
                                    />
                                    <InputError message={errors.due_date} />
                                </div>

                                {/* Discount Amount */}
                                <div className="space-y-2">
                                    <Label htmlFor="discount_amount">Discount Amount (Rs.)</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={data.discount_amount}
                                        onChange={(e) => setData('discount_amount', e.target.value)}
                                        placeholder="0.00"
                                    />
                                    <InputError message={errors.discount_amount} />
                                </div>
                            </div>

                            {/* Customer Info Alert */}
                            {selectedCustomer && (
                                <Alert>
                                    <User className="h-4 w-4" />
                                    <AlertDescription>
                                        <strong>{selectedCustomer.display_name}</strong> • 
                                        Credit Limit: <strong>Rs. {selectedCustomer.credit_limit.toLocaleString()}</strong> • 
                                        Current Balance: <strong>Rs. {selectedCustomer.current_balance.toLocaleString()}</strong>
                                        {selectedCustomer.phone && ` • Phone: ${selectedCustomer.phone}`}
                                    </AlertDescription>
                                </Alert>
                            )}
                        </CardContent>
                    </Card>

                    {/* Invoice Items */}
                    <Card>
                        <CardHeader>
                            <div className="flex items-center justify-between">
                                <div>
                                    <CardTitle className="flex items-center gap-2">
                                        <Package className="h-5 w-5" />
                                        Invoice Items
                                    </CardTitle>
                                    <CardDescription>
                                        Add products and services to this invoice
                                    </CardDescription>
                                </div>
                                <Button type="button" onClick={addItem} size="sm">
                                    <Plus className="mr-2 h-4 w-4" />
                                    Add Item
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            {items.length === 0 ? (
                                <div className="text-center py-8 text-muted-foreground">
                                    <Package className="h-12 w-12 mx-auto mb-4 opacity-50" />
                                    <p>No items added yet. Click "Add Item" to get started.</p>
                                </div>
                            ) : (
                                <div className="space-y-4">
                                    {items.map((item, index) => (
                                        <div key={item.id} className="border rounded-lg p-4 space-y-4">
                                            <div className="flex items-center justify-between">
                                                <h4 className="font-medium">Item {index + 1}</h4>
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeItem(item.id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            
                                            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-4">
                                                {/* Product Selection - Now with Search */}
                                                <div className="lg:col-span-2 space-y-2">
                                                    <Label>Product *</Label>
                                                    <SearchableSelect
                                                        options={products}
                                                        value={item.product_id.toString()}
                                                        onValueChange={(value) => updateItem(item.id, 'product_id', parseInt(value))}
                                                        placeholder="Search and select product..."
                                                        getLabel={(product) => product.label}
                                                        getValue={(product) => product.value.toString()}
                                                        getSearchText={(product) => `${product.name} ${product.label} ${product.unit_type || ''}`}
                                                        renderOption={(product) => (
                                                            <div>
                                                                <div className="font-medium">{product.name}</div>
                                                                <div className="text-sm text-muted-foreground">
                                                                    Rs. {(parseFloat(product.base_price?.toString()) || 0).toFixed(2)} • 
                                                                    {product.weight_per_unit} {product.weight_unit} • 
                                                                    Tax: {product.tax_rate}%
                                                                </div>
                                                                <div className="text-xs text-muted-foreground">
                                                                    ID: #{product.value} • Type: {product.unit_type}
                                                                </div>
                                                            </div>
                                                        )}
                                                    />
                                                </div>

                                                {/* Quantity */}
                                                <div className="space-y-2">
                                                    <Label>Quantity *</Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(item.id, 'quantity', e.target.value)}
                                                        placeholder="1"
                                                    />
                                                </div>

                                                {/* Unit Price */}
                                                <div className="space-y-2">
                                                    <Label>Unit Price (Rs.)</Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={item.unit_price}
                                                        onChange={(e) => updateItem(item.id, 'unit_price', e.target.value)}
                                                        placeholder="0.00"
                                                    />
                                                </div>

                                                {/* Unit Weight */}
                                                <div className="space-y-2">
                                                    <Label>Weight (kg)</Label>
                                                    <Input
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={item.unit_weight}
                                                        onChange={(e) => updateItem(item.id, 'unit_weight', e.target.value)}
                                                        placeholder="0.00"
                                                    />
                                                </div>

                                                {/* Line Total */}
                                                <div className="space-y-2">
                                                    <Label>Total</Label>
                                                    <div className="flex items-center h-10 px-3 border rounded-md bg-muted">
                                                        Rs. {item.line_total.toFixed(2)}
                                                    </div>
                                                </div>
                                            </div>

                                            {/* Description */}
                                            {/* <div className="space-y-2">
                                                <Label>Description</Label>
                                                <Textarea
                                                    value={item.item_description}
                                                    onChange={(e) => updateItem(item.id, 'item_description', e.target.value)}
                                                    placeholder="Item description..."
                                                    rows={2}
                                                />
                                            </div> */}

                                            {/* Specifications */}
                                            {/* <div className="space-y-2">
                                                <Label>Specifications</Label>
                                                <Textarea
                                                    value={typeof item.specifications === 'string' ? item.specifications : ''}
                                                    onChange={(e) => updateItem(item.id, 'specifications', e.target.value)}
                                                    placeholder="Additional specifications or notes..."
                                                    rows={2}
                                                />
                                            </div> */}

                                            {/* Item Summary */}
                                            {/* <div className="flex items-center justify-between text-sm text-muted-foreground bg-muted/50 p-3 rounded">
                                                <span>Line Weight: <strong>{item.line_weight.toFixed(2)} kg</strong></span>
                                                <span>Tax Amount: <strong>Rs. {item.tax_amount.toFixed(2)}</strong></span>
                                                <span>Line Total: <strong>Rs. {item.line_total.toFixed(2)}</strong></span>
                                            </div> */}
                                        </div>
                                    ))}
                                </div>
                            )}
                            
                            <InputError message={errors.items} />
                        </CardContent>
                    </Card>

                    {/* Additional Information */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        {/* Notes & Terms */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <FileText className="h-5 w-5" />
                                    Additional Information
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="notes">Notes</Label>
                                    <Textarea
                                        value={data.notes}
                                        onChange={(e) => setData('notes', e.target.value)}
                                        placeholder="Internal notes about this invoice..."
                                        rows={3}
                                    />
                                    <InputError message={errors.notes} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="terms_conditions">Terms & Conditions</Label>
                                    <Textarea
                                        value={data.terms_conditions}
                                        onChange={(e) => setData('terms_conditions', e.target.value)}
                                        placeholder="Payment terms and conditions..."
                                        rows={3}
                                    />
                                    <InputError message={errors.terms_conditions} />
                                </div>
                            </CardContent>
                        </Card>

                        {/* Invoice Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Calculator className="h-5 w-5" />
                                    Invoice Summary
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-3">
                                    <div className="flex justify-between">
                                        <span>Subtotal:</span>
                                        <span>Rs. {invoiceTotals.subtotal.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Total Weight:</span>
                                        <span>{invoiceTotals.totalWeight.toFixed(2)} kg</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Weight Charge:</span>
                                        <span>Rs. {invoiceTotals.weightCharge.toFixed(2)}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>Tax Amount:</span>
                                        <span>Rs. {invoiceTotals.taxAmount.toFixed(2)}</span>
                                    </div>
                                    {invoiceTotals.discountAmount > 0 && (
                                        <div className="flex justify-between text-green-600">
                                            <span>Discount:</span>
                                            <span>-Rs. {invoiceTotals.discountAmount.toFixed(2)}</span>
                                        </div>
                                    )}
                                    <Separator />
                                    <div className="flex justify-between text-lg font-semibold">
                                        <span>Total Amount:</span>
                                        <span>Rs. {invoiceTotals.total.toFixed(2)}</span>
                                    </div>
                                </div>

                                {/* Quick Actions */}
                                <div className="pt-4 border-t">
                                    <div className="text-sm text-muted-foreground mb-2">Quick Actions:</div>
                                    <div className="flex gap-2">
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            size="sm"
                                            onClick={addItem}
                                        >
                                            <Plus className="h-3 w-3 mr-1" />
                                            Add Item
                                        </Button>
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            size="sm"
                                            onClick={debugCurrentState}
                                        >
                                            <Settings className="h-3 w-3 mr-1" />
                                            Debug
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Submit Button */}
                    <div className="flex items-center justify-end space-x-4">
                        <Button variant="outline" asChild>
                            <Link href="/invoices">Cancel</Link>
                        </Button>
                        <Button 
                            type="submit" 
                            disabled={processing || items.length === 0}
                            className="min-w-[120px]"
                        >
                            {processing ? (
                                <>
                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                    Creating...
                                </>
                            ) : (
                                <>
                                    <Save className="mr-2 h-4 w-4" />
                                    Create Invoice
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}