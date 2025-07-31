// ===============================
// resources/js/pages/Products/Create.tsx
// Product Catalog System - Create Page - Complete Fixed
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
import { 
    Package, 
    ArrowLeft,
    Upload,
    AlertCircle,
    Save,
    Calculator,
    Weight,
    Tags,
    Image as ImageIcon
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

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
    categories?: ProductCategory[];
    pricingTiers?: WeightPricingTier[];
}

interface ProductFormData {
    category_id: number | '';
    name: string;
    description: string;
    base_price: string;
    unit_type: string;
    weight_per_unit: string;
    weight_unit: 'kg' | 'g' | 'lb' | 'oz';
    tax_rate: string;
    status: 'active' | 'inactive';
    specifications: Record<string, any>;
    image?: File;
    requires_customization: boolean;
    minimum_quantity: string;
    maximum_quantity: string;
    customization_options: string;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Products',
        href: '/products',
    },
    {
        title: 'Create Product',
        href: '/products/create',
    },
];

export default function ProductCreate({ categories = [], pricingTiers = [] }: Props) {
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [specifications, setSpecifications] = useState<Array<{ key: string; value: string }>>([
        { key: '', value: '' }
    ]);
    const [priceCalculation, setPriceCalculation] = useState<any>(null);

    const { data, setData, post, processing, errors } = useForm<ProductFormData>({
        category_id: '',
        name: '',
        description: '',
        base_price: '',
        unit_type: 'piece',
        weight_per_unit: '',
        weight_unit: 'kg',
        tax_rate: '0',
        status: 'active',
        specifications: {},
        requires_customization: false,
        minimum_quantity: '1',
        maximum_quantity: '',
        customization_options: '',
    });

    // Calculate pricing when weight changes
    useEffect(() => {
        if (data.weight_per_unit && data.base_price && pricingTiers.length > 0) {
            calculatePricing();
        }
    }, [data.weight_per_unit, data.base_price, data.weight_unit, pricingTiers]);

    const calculatePricing = () => {
        const weight = parseFloat(data.weight_per_unit);
        const price = parseFloat(data.base_price);
        
        if (!weight || !price || pricingTiers.length === 0) return;

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
        post('/products', {
            onSuccess: () => {
                // Handle success
            },
        });
    };

    const getCategoryDisplayName = (category: ProductCategory): string => {
        const indent = '—'.repeat((category.level || 0));
        return `${indent} ${category.name}`;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Product" />
            
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
                            <h1 className="text-3xl font-bold text-gray-900">Create Product</h1>
                            <p className="mt-2 text-gray-600">
                                Add a new product to your catalog
                            </p>
                        </div>
                    </div>
                </div>

                {/* Show error if no categories */}
                {categories.length === 0 && (
                    <Alert>
                        <AlertCircle className="h-4 w-4" />
                        <AlertDescription>
                            No product categories found. Please create categories first before adding products.
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Form */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Package className="w-5 h-5 mr-2" />
                                        Basic Information
                                    </CardTitle>
                                    <CardDescription>
                                        Enter the basic details of your product
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="name">Product Name *</Label>
                                            <Input
                                                id="name"
                                                value={data.name}
                                                onChange={(e) => setData('name', e.target.value)}
                                                placeholder="Enter product name"
                                                className={errors.name ? 'border-red-500' : ''}
                                            />
                                            {errors.name && (
                                                <p className="text-sm text-red-600 mt-1">{errors.name}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="category_id">Category *</Label>
                                            <Select 
                                                value={data.category_id.toString()} 
                                                onValueChange={(value) => setData('category_id', parseInt(value))}
                                            >
                                                <SelectTrigger className={errors.category_id ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Select category" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {categories.length > 0 ? (
                                                        categories.map((category) => (
                                                            <SelectItem key={category.id} value={category.id.toString()}>
                                                                {getCategoryDisplayName(category)}
                                                            </SelectItem>
                                                        ))
                                                    ) : (
                                                        <SelectItem value="" disabled>
                                                            No categories available
                                                        </SelectItem>
                                                    )}
                                                </SelectContent>
                                            </Select>
                                            {errors.category_id && (
                                                <p className="text-sm text-red-600 mt-1">{errors.category_id}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="description">Description</Label>
                                        <Textarea
                                            id="description"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Enter product description"
                                            rows={3}
                                            className={errors.description ? 'border-red-500' : ''}
                                        />
                                        {errors.description && (
                                            <p className="text-sm text-red-600 mt-1">{errors.description}</p>
                                        )}
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
                                        Set pricing and weight information
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <Label htmlFor="base_price">Base Price (Rs.) *</Label>
                                            <Input
                                                id="base_price"
                                                type="number"
                                                step="0.01"
                                                value={data.base_price}
                                                onChange={(e) => setData('base_price', e.target.value)}
                                                placeholder="0.00"
                                                className={errors.base_price ? 'border-red-500' : ''}
                                            />
                                            {errors.base_price && (
                                                <p className="text-sm text-red-600 mt-1">{errors.base_price}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="unit_type">Unit Type *</Label>
                                            <Select 
                                                value={data.unit_type} 
                                                onValueChange={(value) => setData('unit_type', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="piece">Piece</SelectItem>
                                                    <SelectItem value="sheet">Sheet</SelectItem>
                                                    <SelectItem value="roll">Roll</SelectItem>
                                                    <SelectItem value="meter">Meter</SelectItem>
                                                    <SelectItem value="square_meter">Square Meter</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>

                                        <div>
                                            <Label htmlFor="weight_per_unit">Weight per Unit *</Label>
                                            <Input
                                                id="weight_per_unit"
                                                type="number"
                                                step="0.001"
                                                value={data.weight_per_unit}
                                                onChange={(e) => setData('weight_per_unit', e.target.value)}
                                                placeholder="0.000"
                                                className={errors.weight_per_unit ? 'border-red-500' : ''}
                                            />
                                            {errors.weight_per_unit && (
                                                <p className="text-sm text-red-600 mt-1">{errors.weight_per_unit}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="weight_unit">Weight Unit *</Label>
                                            <Select 
                                                value={data.weight_unit} 
                                                onValueChange={(value: 'kg' | 'g' | 'lb' | 'oz') => setData('weight_unit', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="g">Grams</SelectItem>
                                                    <SelectItem value="kg">Kilograms</SelectItem>
                                                    <SelectItem value="lb">Pounds</SelectItem>
                                                    <SelectItem value="oz">Ounces</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <Label htmlFor="tax_rate">Tax Rate (%)</Label>
                                            <Input
                                                id="tax_rate"
                                                type="number"
                                                step="0.01"
                                                value={data.tax_rate}
                                                onChange={(e) => setData('tax_rate', e.target.value)}
                                                placeholder="0.00"
                                                className={errors.tax_rate ? 'border-red-500' : ''}
                                            />
                                            {errors.tax_rate && (
                                                <p className="text-sm text-red-600 mt-1">{errors.tax_rate}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="minimum_quantity">Minimum Quantity</Label>
                                            <Input
                                                id="minimum_quantity"
                                                type="number"
                                                min="1"
                                                value={data.minimum_quantity}
                                                onChange={(e) => setData('minimum_quantity', e.target.value)}
                                                placeholder="1"
                                                className={errors.minimum_quantity ? 'border-red-500' : ''}
                                            />
                                            {errors.minimum_quantity && (
                                                <p className="text-sm text-red-600 mt-1">{errors.minimum_quantity}</p>
                                            )}
                                        </div>

                                        <div>
                                            <Label htmlFor="maximum_quantity">Maximum Quantity</Label>
                                            <Input
                                                id="maximum_quantity"
                                                type="number"
                                                min="1"
                                                value={data.maximum_quantity}
                                                onChange={(e) => setData('maximum_quantity', e.target.value)}
                                                placeholder="No limit"
                                                className={errors.maximum_quantity ? 'border-red-500' : ''}
                                            />
                                            {errors.maximum_quantity && (
                                                <p className="text-sm text-red-600 mt-1">{errors.maximum_quantity}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                        </div>
                                    </div>

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
                                            {errors.customization_options && (
                                                <p className="text-sm text-red-600 mt-1">{errors.customization_options}</p>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Product Image */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <ImageIcon className="w-5 h-5 mr-2" />
                                        Product Image
                                    </CardTitle>
                                    <CardDescription>
                                        Upload an image for your product
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center justify-center w-full">
                                            <label htmlFor="image" className="flex flex-col items-center justify-center w-full h-64 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                                {imagePreview ? (
                                                    <img
                                                        src={imagePreview}
                                                        alt="Preview"
                                                        className="w-full h-full object-cover rounded-lg"
                                                    />
                                                ) : (
                                                    <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                                        <Upload className="w-8 h-8 mb-4 text-gray-500" />
                                                        <p className="mb-2 text-sm text-gray-500">
                                                            <span className="font-semibold">Click to upload</span> or drag and drop
                                                        </p>
                                                        <p className="text-xs text-gray-500">PNG, JPG or GIF (MAX. 800x400px)</p>
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
                                        {errors.image && (
                                            <p className="text-sm text-red-600">{errors.image}</p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Specifications */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Tags className="w-5 h-5 mr-2" />
                                        Product Specifications
                                    </CardTitle>
                                    <CardDescription>
                                        Add custom specifications for your product
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-3">
                                        {specifications.map((spec, index) => (
                                            <div key={index} className="grid grid-cols-5 gap-2">
                                                <div className="col-span-2">
                                                    <Input
                                                        placeholder="Specification name"
                                                        value={spec.key}
                                                        onChange={(e) => handleSpecificationChange(index, 'key', e.target.value)}
                                                    />
                                                </div>
                                                <div className="col-span-2">
                                                    <Input
                                                        placeholder="Specification value"
                                                        value={spec.value}
                                                        onChange={(e) => handleSpecificationChange(index, 'value', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => removeSpecification(index)}
                                                        disabled={specifications.length === 1}
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={addSpecification}
                                        >
                                            Add Specification
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Price Calculation */}
                            {priceCalculation && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center">
                                            <Calculator className="w-5 h-5 mr-2" />
                                            Price Calculation
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Base Price:</span>
                                                <span className="font-medium">Rs. {priceCalculation.base_price.toFixed(2)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Delivery Charge:</span>
                                                <span className="font-medium">Rs. {priceCalculation.delivery_charge.toFixed(2)}</span>
                                            </div>
                                            <div className="flex justify-between">
                                                <span className="text-sm text-gray-600">Tax Amount:</span>
                                                <span className="font-medium">Rs. {priceCalculation.tax_amount.toFixed(2)}</span>
                                            </div>
                                            <hr />
                                            <div className="flex justify-between">
                                                <span className="font-semibold">Total Amount:</span>
                                                <span className="font-bold text-lg">Rs. {priceCalculation.total_amount.toFixed(2)}</span>
                                            </div>
                                            <div className="text-xs text-gray-500 mt-2">
                                                <p>Weight: {priceCalculation.weight_display}</p>
                                                <p>Tier: {priceCalculation.tier_used}</p>
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Pricing Tiers Info */}
                            {pricingTiers.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center">
                                            <Weight className="w-5 h-5 mr-2" />
                                            Pricing Tiers
                                        </CardTitle>
                                        <CardDescription>
                                            Current weight-based pricing tiers
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-2">
                                            {pricingTiers.map((tier) => (
                                                <div key={tier.id} className="text-xs p-2 bg-gray-50 rounded">
                                                    <div className="font-medium">{tier.tier_name}</div>
                                                    <div className="text-gray-600">
                                                        {tier.min_weight}kg - {tier.max_weight ? `${tier.max_weight}kg` : '∞'}
                                                    </div>
                                                    <div className="text-gray-600">
                                                        Base: Rs. {tier.base_price}
                                                        {tier.per_kg_rate && ` + Rs. ${tier.per_kg_rate}/kg`}
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
                                            disabled={processing || categories.length === 0}
                                        >
                                            <Save className="w-4 h-4 mr-2" />
                                            {processing ? 'Creating...' : 'Create Product'}
                                        </Button>
                                        
                                        <Link href="/products" className="block">
                                            <Button variant="outline" className="w-full">
                                                Cancel
                                            </Button>
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}