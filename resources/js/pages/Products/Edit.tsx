// ===============================
// resources/js/pages/Products/Edit.tsx
// Product Catalog System - Edit Page - Complete
// ===============================

import { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
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
    Package, 
    ArrowLeft,
    Upload,
    AlertCircle,
    Save,
    Calculator,
    Weight,
    Tags,
    Image as ImageIcon,
    X,
    Plus,
    Trash2
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface Product {
    id: number;
    company_id: number;
    category_id: number;
    product_code: string;
    name: string;
    description?: string;
    base_price: number;
    weight_per_unit: number;
    weight_unit: 'kg' | 'g' | 'lb' | 'oz';
    tax_rate: number;
    status: 'active' | 'inactive';
    image?: string;
    image_url?: string;
    specifications?: Record<string, any>;
    requires_customization?: boolean;
    customization_options?: string;
    minimum_quantity?: number;
    stock_quantity?: number;
    reorder_level?: number;
    is_featured?: boolean;
    is_digital?: boolean;
    production_time_days?: number;
    keywords?: string;
    meta_title?: string;
    meta_description?: string;
    created_at: string;
    updated_at: string;
}

interface ProductCategory {
    id: number;
    name: string;
    code: string;
    parent_id?: number;
    level?: number;
}

interface WeightPricingTier {
    id: number;
    tier_name: string;
    min_weight: number;
    max_weight?: number;
    base_price: number;
    per_kg_rate?: number;
}

interface Props {
    product: Product;
    categories?: ProductCategory[];
    pricingTiers?: WeightPricingTier[];
}

interface ProductFormData {
    category_id: number;
    name: string;
    product_code: string;
    description: string;
    base_price: string;
    unit_type: string;
    weight_per_unit: string;
    weight_unit: 'kg' | 'g' | 'lb' | 'oz';
    tax_rate: string;
    status: 'active' | 'inactive';
    specifications: Record<string, any>;
    requires_customization: boolean;
    customization_options: string;
    minimum_quantity: number;
    stock_quantity: number;
    reorder_level: number;
    is_featured: boolean;
    is_digital: boolean;
    production_time_days: number;
    keywords: string;
    meta_title: string;
    meta_description: string;
    image?: File | null;
}

interface Specification {
    key: string;
    value: string;
}

export default function ProductEdit({ product, categories = [], pricingTiers = [] }: Props) {
    const [imagePreview, setImagePreview] = useState<string | null>(product.image_url || null);
    const [specifications, setSpecifications] = useState<Specification[]>(() => {
        if (product.specifications && typeof product.specifications === 'object') {
            return Object.entries(product.specifications).map(([key, value]) => ({
                key,
                value: String(value)
            }));
        }
        return [{ key: '', value: '' }];
    });
    const [priceCalculation, setPriceCalculation] = useState<any>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: product.name, href: `/products/${product.id}` },
        { title: 'Edit', href: `/products/${product.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm<ProductFormData>({
        category_id: product.category_id,
        name: product.name,
        product_code: product.product_code,
        description: product.description || '',
        base_price: product.base_price.toString(),
        unit_type: 'piece', // Default unit type - you may need to add this to your Product interface
        weight_per_unit: product.weight_per_unit.toString(),
        weight_unit: product.weight_unit,
        tax_rate: product.tax_rate.toString(),
        status: product.status,
        specifications: product.specifications || {},
        requires_customization: product.requires_customization || false,
        customization_options: product.customization_options || '',
        minimum_quantity: product.minimum_quantity || 1,
        stock_quantity: product.stock_quantity || 0,
        reorder_level: product.reorder_level || 0,
        is_featured: product.is_featured || false,
        is_digital: product.is_digital || false,
        production_time_days: product.production_time_days || 0,
        keywords: product.keywords || '',
        meta_title: product.meta_title || '',
        meta_description: product.meta_description || '',
        image: null,
    });

    // Calculate pricing when quantity or weight changes
    useEffect(() => {
        if (data.base_price && data.weight_per_unit) {
            calculatePrice(1);
        }
    }, [data.base_price, data.weight_per_unit, data.weight_unit, data.tax_rate]);

    const calculatePrice = (quantity: number) => {
        const price = parseFloat(data.base_price) * quantity;
        const weight = parseFloat(data.weight_per_unit) * quantity;
        
        // Convert weight to kg for calculation
        const weightInKg = data.weight_unit === 'g' ? weight / 1000 : 
                          data.weight_unit === 'lb' ? weight * 0.453592 :
                          data.weight_unit === 'oz' ? weight * 0.0283495 : weight;
        
        // Find applicable pricing tier
        const tier = pricingTiers.find(t => 
            weightInKg >= t.min_weight && 
            (t.max_weight === null || weightInKg <= (t.max_weight || Infinity))
        );

        let deliveryCharge = 0;
        if (tier) {
            deliveryCharge = tier.base_price + (tier.per_kg_rate ? (weightInKg * tier.per_kg_rate) : 0);
        }

        const taxAmount = (price + deliveryCharge) * (parseFloat(data.tax_rate) / 100);
        const totalAmount = price + deliveryCharge + taxAmount;

        setPriceCalculation({
            base_price: price,
            delivery_charge: deliveryCharge,
            tax_amount: taxAmount,
            total_amount: totalAmount,
            tier_used: tier?.tier_name || 'No tier found',
            weight_display: `${weight} ${data.weight_unit}`
        });
    };

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            
            const reader = new FileReader();
            reader.onload = (e) => {
                setImagePreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const removeImage = () => {
        setData('image', null);
        setImagePreview(product.image_url || null);
    };

    const handleSpecificationChange = (index: number, field: 'key' | 'value', value: string) => {
        const newSpecs = [...specifications];
        newSpecs[index][field] = value;
        setSpecifications(newSpecs);
        
        // Update form data
        const specsObject = newSpecs.reduce((acc, spec) => {
            if (spec.key && spec.value) {
                acc[spec.key] = spec.value;
            }
            return acc;
        }, {} as Record<string, any>);
        
        setData('specifications', specsObject);
    };

    const addSpecification = () => {
        setSpecifications([...specifications, { key: '', value: '' }]);
    };

    const removeSpecification = (index: number) => {
        if (specifications.length <= 1) return;
        
        const newSpecs = specifications.filter((_, i) => i !== index);
        setSpecifications(newSpecs);
        
        const specsObject = newSpecs.reduce((acc, spec) => {
            if (spec.key && spec.value) {
                acc[spec.key] = spec.value;
            }
            return acc;
        }, {} as Record<string, any>);
        
        setData('specifications', specsObject);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        put(`/products/${product.id}`, {
            forceFormData: true,
            onSuccess: () => {
                // Redirect will be handled by the backend
            },
            onError: (errors) => {
                console.log('Validation errors:', errors);
            },
        });
    };

    const getCategoryDisplayName = (category: ProductCategory): string => {
        const indent = '—'.repeat((category.level || 0));
        return `${indent} ${category.name}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${product.name}`} />
            
            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-4">
                        <Link href="/products">
                            <Button variant="outline" size="sm">
                                <ArrowLeft className="w-4 h-4 mr-2" />
                                Back to Products
                            </Button>
                        </Link>
                        <div>
                            <h1 className="text-3xl font-bold text-gray-900">Edit Product</h1>
                            <p className="mt-2 text-gray-600">
                                Update product information and settings
                            </p>
                        </div>
                    </div>
                </div>

                {/* Show error if no categories */}
                {categories.length === 0 && (
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            No product categories found. Please create categories first before editing products.
                            <Link href="/product-categories/create" className="ml-2 text-blue-600 hover:underline">
                                Create Category
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Left Column - Main Product Info */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Package className="w-5 h-5 mr-2" />
                                        Basic Information
                                    </CardTitle>
                                    <CardDescription>
                                        Essential product details and identification
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="name">Product Name *</Label>
                                            <Input
                                                id="name"
                                                type="text"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                className={errors.name ? 'border-red-500' : ''}
                                                placeholder="Enter product name"
                                                required
                                            />
                                            <InputError message={errors.name} />
                                        </div>

                                        <div>
                                            <Label htmlFor="product_code">Product Code</Label>
                                            <Input
                                                id="product_code"
                                                type="text"
                                                value={data.product_code}
                                                onChange={(e) => setData('product_code', e.target.value)}
                                                className={errors.product_code ? 'border-red-500' : ''}
                                                placeholder="Auto-generated if empty"
                                            />
                                            <InputError message={errors.product_code} />
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="category_id">Category *</Label>
                                        <Select 
                                            value={data.category_id.toString()} 
                                            onValueChange={(value) => setData('category_id', parseInt(value))}
                                        >
                                            <SelectTrigger className={errors.category_id ? 'border-red-500' : ''}>
                                                <SelectValue placeholder="Select a category" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {categories.map((category) => (
                                                    <SelectItem key={category.id} value={category.id.toString()}>
                                                        {getCategoryDisplayName(category)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.category_id} />
                                    </div>

                                    <div>
                                        <Label htmlFor="description">Description</Label>
                                        <Textarea
                                            id="description"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Describe your product..."
                                            rows={3}
                                            className={errors.description ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.description} />
                                    </div>

                                    <div>
                                        <Label htmlFor="status">Status</Label>
                                        <Select 
                                            value={data.status} 
                                            onValueChange={(value: 'active' | 'inactive') => setData('status', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="active">Active</SelectItem>
                                                <SelectItem value="inactive">Inactive</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.status} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Pricing & Weight */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Calculator className="w-5 h-5 mr-2" />
                                        Pricing & Weight
                                    </CardTitle>
                                    <CardDescription>
                                        Set pricing and weight specifications
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <Label htmlFor="base_price">Base Price (LKR) *</Label>
                                            <Input
                                                id="base_price"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={data.base_price}
                                                onChange={(e) => setData('base_price', e.target.value)}
                                                className={errors.base_price ? 'border-red-500' : ''}
                                                placeholder="0.00"
                                                required
                                            />
                                            <InputError message={errors.base_price} />
                                        </div>

                                        <div>
                                            <Label htmlFor="unit_type">Unit Type *</Label>
                                            <Select 
                                                value={data.unit_type} 
                                                onValueChange={(value: string) => setData('unit_type', value)}
                                            >
                                                <SelectTrigger className={errors.unit_type ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Select unit type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="piece">Piece</SelectItem>
                                                    <SelectItem value="sheet">Sheet</SelectItem>
                                                    <SelectItem value="roll">Roll</SelectItem>
                                                    <SelectItem value="meter">Meter</SelectItem>
                                                    <SelectItem value="square_meter">Square Meter</SelectItem>
                                                    <SelectItem value="kilogram">Kilogram</SelectItem>
                                                    <SelectItem value="gram">Gram</SelectItem>
                                                    <SelectItem value="pound">Pound</SelectItem>
                                                    <SelectItem value="liter">Liter</SelectItem>
                                                    <SelectItem value="milliliter">Milliliter</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.unit_type} />
                                        </div>

                                        <div>
                                            <Label htmlFor="tax_rate">Tax Rate (%)</Label>
                                            <Input
                                                id="tax_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                value={data.tax_rate}
                                                onChange={(e) => setData('tax_rate', e.target.value)}
                                                className={errors.tax_rate ? 'border-red-500' : ''}
                                                placeholder="0.00"
                                            />
                                            <InputError message={errors.tax_rate} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="weight_per_unit">Weight per Unit *</Label>
                                            <Input
                                                id="weight_per_unit"
                                                type="number"
                                                step="0.001"
                                                min="0"
                                                value={data.weight_per_unit}
                                                onChange={(e) => setData('weight_per_unit', e.target.value)}
                                                className={errors.weight_per_unit ? 'border-red-500' : ''}
                                                placeholder="0.000"
                                                required
                                            />
                                            <InputError message={errors.weight_per_unit} />
                                        </div>

                                        <div>
                                            <Label htmlFor="weight_unit">Weight Unit *</Label>
                                            <Select 
                                                value={data.weight_unit} 
                                                onValueChange={(value: 'kg' | 'g' | 'lb' | 'oz') => setData('weight_unit', value)}
                                            >
                                                <SelectTrigger className={errors.weight_unit ? 'border-red-500' : ''}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="kg">Kilogram (kg)</SelectItem>
                                                    <SelectItem value="g">Gram (g)</SelectItem>
                                                    <SelectItem value="lb">Pound (lb)</SelectItem>
                                                    <SelectItem value="oz">Ounce (oz)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            <InputError message={errors.weight_unit} />
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="minimum_quantity">Minimum Quantity</Label>
                                            <Input
                                                id="minimum_quantity"
                                                type="number"
                                                min="1"
                                                value={data.minimum_quantity}
                                                onChange={(e) => setData('minimum_quantity', parseInt(e.target.value) || 1)}
                                                className={errors.minimum_quantity ? 'border-red-500' : ''}
                                            />
                                            <InputError message={errors.minimum_quantity} />
                                        </div>

                                        <div>
                                            <Label htmlFor="production_time_days">Production Time (Days)</Label>
                                            <Input
                                                id="production_time_days"
                                                type="number"
                                                min="0"
                                                value={data.production_time_days}
                                                onChange={(e) => setData('production_time_days', parseInt(e.target.value) || 0)}
                                                className={errors.production_time_days ? 'border-red-500' : ''}
                                            />
                                            <InputError message={errors.production_time_days} />
                                        </div>
                                    </div>

                                    {/* Price Calculation Display */}
                                    {priceCalculation && (
                                        <div className="bg-blue-50 p-4 rounded-lg border">
                                            <h4 className="font-medium text-blue-900 mb-2">Price Calculation Preview</h4>
                                            <div className="text-sm space-y-1">
                                                <div className="flex justify-between">
                                                    <span>Base Price:</span>
                                                    <span>LKR {priceCalculation.base_price.toFixed(2)}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Delivery Charge:</span>
                                                    <span>LKR {priceCalculation.delivery_charge.toFixed(2)}</span>
                                                </div>
                                                <div className="flex justify-between">
                                                    <span>Tax ({data.tax_rate}%):</span>
                                                    <span>LKR {priceCalculation.tax_amount.toFixed(2)}</span>
                                                </div>
                                                <hr className="my-1" />
                                                <div className="flex justify-between font-medium">
                                                    <span>Total:</span>
                                                    <span>LKR {priceCalculation.total_amount.toFixed(2)}</span>
                                                </div>
                                                <div className="text-xs text-gray-600 mt-1">
                                                    Weight: {priceCalculation.weight_display} | Tier: {priceCalculation.tier_used}
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Inventory Management */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Weight className="w-5 h-5 mr-2" />
                                        Inventory Management
                                    </CardTitle>
                                    <CardDescription>
                                        Stock levels and inventory settings
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="stock_quantity">Current Stock</Label>
                                            <Input
                                                id="stock_quantity"
                                                type="number"
                                                min="0"
                                                value={data.stock_quantity}
                                                onChange={(e) => setData('stock_quantity', parseInt(e.target.value) || 0)}
                                                className={errors.stock_quantity ? 'border-red-500' : ''}
                                            />
                                            <InputError message={errors.stock_quantity} />
                                        </div>

                                        <div>
                                            <Label htmlFor="reorder_level">Reorder Level</Label>
                                            <Input
                                                id="reorder_level"
                                                type="number"
                                                min="0"
                                                value={data.reorder_level}
                                                onChange={(e) => setData('reorder_level', parseInt(e.target.value) || 0)}
                                                className={errors.reorder_level ? 'border-red-500' : ''}
                                            />
                                            <InputError message={errors.reorder_level} />
                                        </div>
                                    </div>

                                    <div className="flex items-center space-x-6">
                                        <div className="flex items-center space-x-2">
                                            <input
                                                type="checkbox"
                                                id="is_featured"
                                                checked={data.is_featured}
                                                onChange={(e) => setData('is_featured', e.target.checked)}
                                                className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            />
                                            <Label htmlFor="is_featured">Featured Product</Label>
                                        </div>

                                        <div className="flex items-center space-x-2">
                                            <input
                                                type="checkbox"
                                                id="is_digital"
                                                checked={data.is_digital}
                                                onChange={(e) => setData('is_digital', e.target.checked)}
                                                className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                            />
                                            <Label htmlFor="is_digital">Digital Product</Label>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Product Specifications */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Tags className="w-5 h-5 mr-2" />
                                        Product Specifications
                                    </CardTitle>
                                    <CardDescription>
                                        Add technical specifications and features
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {specifications.map((spec, index) => (
                                        <div key={index} className="flex gap-3 items-start">
                                            <div className="flex-1">
                                                <Input
                                                    placeholder="Specification name (e.g., Material)"
                                                    value={spec.key}
                                                    onChange={(e) => handleSpecificationChange(index, 'key', e.target.value)}
                                                />
                                            </div>
                                            <div className="flex-1">
                                                <Input
                                                    placeholder="Value (e.g., Premium Paper)"
                                                    value={spec.value}
                                                    onChange={(e) => handleSpecificationChange(index, 'value', e.target.value)}
                                                />
                                            </div>
                                            {specifications.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => removeSpecification(index)}
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            )}
                                        </div>
                                    ))}

                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addSpecification}
                                        className="w-full"
                                    >
                                        <Plus className="w-4 h-4 mr-2" />
                                        Add Specification
                                    </Button>
                                </CardContent>
                            </Card>

                            {/* Customization Options */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Customization Options</CardTitle>
                                    <CardDescription>
                                        Configure if this product allows customization
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center space-x-2">
                                        <input
                                            type="checkbox"
                                            id="requires_customization"
                                            checked={data.requires_customization}
                                            onChange={(e) => setData('requires_customization', e.target.checked)}
                                            className="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50"
                                        />
                                        <Label htmlFor="requires_customization">Requires Customization</Label>
                                    </div>

                                    {data.requires_customization && (
                                        <div>
                                            <Label htmlFor="customization_options">Customization Options</Label>
                                            <Textarea
                                                id="customization_options"
                                                value={data.customization_options}
                                                onChange={(e) => setData('customization_options', e.target.value)}
                                                placeholder="Describe available customization options..."
                                                rows={3}
                                                className={errors.customization_options ? 'border-red-500' : ''}
                                            />
                                            <InputError message={errors.customization_options} />
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* SEO Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>SEO & Search</CardTitle>
                                    <CardDescription>
                                        Optimize for search engines and internal search
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <Label htmlFor="keywords">Keywords</Label>
                                        <Input
                                            id="keywords"
                                            value={data.keywords}
                                            onChange={(e) => setData('keywords', e.target.value)}
                                            placeholder="printing, business cards, flyers (comma separated)"
                                            className={errors.keywords ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.keywords} />
                                    </div>

                                    <div>
                                        <Label htmlFor="meta_title">Meta Title</Label>
                                        <Input
                                            id="meta_title"
                                            value={data.meta_title}
                                            onChange={(e) => setData('meta_title', e.target.value)}
                                            placeholder="SEO title for search engines"
                                            className={errors.meta_title ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.meta_title} />
                                    </div>

                                    <div>
                                        <Label htmlFor="meta_description">Meta Description</Label>
                                        <Textarea
                                            id="meta_description"
                                            value={data.meta_description}
                                            onChange={(e) => setData('meta_description', e.target.value)}
                                            placeholder="Brief description for search engines (160 characters max)"
                                            rows={2}
                                            className={errors.meta_description ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.meta_description} />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Right Column - Image & Actions */}
                        <div className="space-y-6">
                            {/* Product Image */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <ImageIcon className="w-5 h-5 mr-2" />
                                        Product Image
                                    </CardTitle>
                                    <CardDescription>
                                        Upload or update product image
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-center w-full">
                                            <label htmlFor="image" className="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                                {imagePreview ? (
                                                    <div className="relative w-full h-full">
                                                        <img
                                                            src={imagePreview}
                                                            alt="Product preview"
                                                            className="w-full h-full object-cover rounded-lg"
                                                        />
                                                        <div className="absolute inset-0 bg-black bg-opacity-50 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity rounded-lg">
                                                            <p className="text-white text-sm">Click to change image</p>
                                                        </div>
                                                    </div>
                                                ) : (
                                                    <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                        <Upload className="w-10 h-10 mb-3 text-gray-400" />
                                                        <p className="mb-2 text-sm text-gray-500">
                                                            <span className="font-semibold">Click to upload</span> or drag and drop
                                                        </p>
                                                        <p className="text-xs text-gray-500">PNG, JPG or JPEG (MAX. 2MB)</p>
                                                    </div>
                                                )}
                                                <input
                                                    id="image"
                                                    type="file"
                                                    className="hidden"
                                                    accept="image/*"
                                                    onChange={handleImageChange}
                                                />
                                            </label>
                                        </div>

                                        {imagePreview && data.image && (
                                            <div className="flex justify-center">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={removeImage}
                                                >
                                                    <X className="w-4 h-4 mr-2" />
                                                    Remove New Image
                                                </Button>
                                            </div>
                                        )}

                                        <InputError message={errors.image} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Current Product Info */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Current Product Info</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Product ID:</span>
                                        <span className="font-mono">#{product.id}</span>
                                    </div>
                                    
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Current Code:</span>
                                        <span className="font-mono">{product.product_code}</span>
                                    </div>
                                    
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Created:</span>
                                        <span>{new Date(product.created_at).toLocaleDateString()}</span>
                                    </div>
                                    
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Last Updated:</span>
                                        <span>{new Date(product.updated_at).toLocaleDateString()}</span>
                                    </div>
                                    
                                    <div className="flex justify-between text-sm">
                                        <span className="text-gray-600">Status:</span>
                                        <span className={`px-2 py-1 rounded-full text-xs ${
                                            product.status === 'active' 
                                                ? 'bg-green-100 text-green-800' 
                                                : 'bg-gray-100 text-gray-800'
                                        }`}>
                                            {product.status}
                                        </span>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Pricing Tiers Info */}
                            {pricingTiers.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Available Pricing Tiers</CardTitle>
                                        <CardDescription>
                                            Delivery charges based on weight
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            {pricingTiers.map((tier, index) => (
                                                <div key={tier.id} className="text-sm p-2 bg-gray-50 rounded">
                                                    <div className="font-medium">{tier.tier_name}</div>
                                                    <div className="text-gray-600">
                                                        {tier.min_weight}kg - {tier.max_weight ? `${tier.max_weight}kg` : '∞'}
                                                    </div>
                                                    <div className="text-gray-600">
                                                        Base: LKR {tier.base_price} 
                                                        {tier.per_kg_rate && ` + LKR ${tier.per_kg_rate}/kg`}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Action Buttons */}
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="space-y-3">
                                        <Button 
                                            type="submit" 
                                            className="w-full" 
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <>
                                                    <div className="animate-spin rounded-full h-4 w-4 border-b-2 border-white mr-2"></div>
                                                    Updating Product...
                                                </>
                                            ) : (
                                                <>
                                                    <Save className="w-4 h-4 mr-2" />
                                                    Update Product
                                                </>
                                            )}
                                        </Button>

                                        <Link href={`/products/${product.id}`} className="block">
                                            <Button variant="outline" className="w-full">
                                                Cancel
                                            </Button>
                                        </Link>

                                        <div className="pt-2 border-t">
                                            <Link href="/products" className="block">
                                                <Button variant="ghost" size="sm" className="w-full">
                                                    Back to Products List
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>

                {/* Debug Information (Remove in production) */}
                {process.env.NODE_ENV === 'development' && (
                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle className="text-sm">Debug Info (Development Only)</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <details className="text-xs">
                                <summary className="cursor-pointer font-medium mb-2">Form Data</summary>
                                <pre className="bg-gray-100 p-2 rounded overflow-auto">
                                    {JSON.stringify(data, null, 2)}
                                </pre>
                            </details>
                            
                            {Object.keys(errors).length > 0 && (
                                <details className="text-xs mt-2">
                                    <summary className="cursor-pointer font-medium mb-2 text-red-600">
                                        Validation Errors
                                    </summary>
                                    <pre className="bg-red-50 p-2 rounded overflow-auto text-red-700">
                                        {JSON.stringify(errors, null, 2)}
                                    </pre>
                                </details>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}