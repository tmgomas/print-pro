// resources/js/pages/Products/Edit.tsx
// Complete Product Edit Form with Validation

import { useState, useEffect } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
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
    Trash2,
    Star,
    Loader2
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
    unit_type?: string;
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

interface Props {
    product: Product;
    categories?: ProductCategory[];
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

interface ValidationErrors {
    [key: string]: string;
}

export default function ProductEdit({ product, categories = [] }: Props) {
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
    const [validationErrors, setValidationErrors] = useState<ValidationErrors>({});
    const [priceCalculation, setPriceCalculation] = useState<any>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Products', href: '/products' },
        { title: product.name, href: `/products/${product.id}` },
        { title: 'Edit', href: `/products/${product.id}/edit` },
    ];

    const { data, setData, put, processing, errors } = useForm<ProductFormData>({
        category_id: product.category_id || 0,
        name: product.name || '',
        product_code: product.product_code || '',
        description: product.description || '',
        base_price: product.base_price?.toString() || '0',
        unit_type: product.unit_type || 'piece',
        weight_per_unit: product.weight_per_unit?.toString() || '0',
        weight_unit: product.weight_unit || 'kg',
        tax_rate: product.tax_rate?.toString() || '0',
        status: product.status || 'active',
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

    // Frontend Validation Function
    const validateForm = (): boolean => {
        const newErrors: ValidationErrors = {};
        
        // Required field validations
        if (!data.name.trim()) {
            newErrors.name = 'නිෂ්පාදන නාමය අවශ්‍යයි / Product name is required';
        }
        
        if (!data.category_id || data.category_id === 0) {
            newErrors.category_id = 'නිෂ්පාදන කාණ්ඩය අවශ්‍යයි / Product category is required';
        }
        
        if (!data.base_price || parseFloat(data.base_price) <= 0) {
            newErrors.base_price = 'මූලික මිල අවශ්‍යයි / Base price is required';
        }
        
        if (!data.unit_type) {
            newErrors.unit_type = 'ඒකක වර්ගය අවශ්‍යයි / Unit type is required';
        }
        
        if (!data.weight_per_unit || parseFloat(data.weight_per_unit) <= 0) {
            newErrors.weight_per_unit = 'ඒකකයක බර අවශ්‍යයි / Weight per unit is required';
        }
        
        if (!data.weight_unit) {
            newErrors.weight_unit = 'බර ඒකකය අවශ්‍යයි / Weight unit is required';
        }
        
        if (!data.status) {
            newErrors.status = 'තත්ත්වය අවශ්‍යයි / Status is required';
        }

        // Price validation
        if (data.base_price && (isNaN(parseFloat(data.base_price)) || parseFloat(data.base_price) < 0.01)) {
            newErrors.base_price = 'මූලික මිල වලංගු අගයක් විය යුතුයි / Base price must be a valid amount';
        }

        // Weight validation
        if (data.weight_per_unit && (isNaN(parseFloat(data.weight_per_unit)) || parseFloat(data.weight_per_unit) < 0.001)) {
            newErrors.weight_per_unit = 'බර වලංගු අගයක් විය යුතුයි / Weight must be a valid amount';
        }

        // Tax rate validation
        if (data.tax_rate && (isNaN(parseFloat(data.tax_rate)) || parseFloat(data.tax_rate) < 0 || parseFloat(data.tax_rate) > 100)) {
            newErrors.tax_rate = 'බදු අනුපාතය 0-100% අතර විය යුතුයි / Tax rate must be between 0-100%';
        }
        
        setValidationErrors(newErrors);
        return Object.keys(newErrors).length === 0;
    };

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
        
        const taxAmount = (price * parseFloat(data.tax_rate || '0')) / 100;
        const totalPrice = price + taxAmount;

        setPriceCalculation({
            basePrice: price,
            weight: weightInKg,
            taxAmount,
            totalPrice,
            quantity
        });
    };

    // Handle image upload
    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            if (file.size > 3 * 1024 * 1024) { // 3MB limit
                alert('රූප ප්‍රමාණය 3MB ට වඩා වැඩි විය නොහැක / Image size cannot exceed 3MB');
                return;
            }
            
            setData('image', file);
            const reader = new FileReader();
            reader.onload = (e) => {
                setImagePreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    // Handle specifications
    const addSpecification = () => {
        setSpecifications([...specifications, { key: '', value: '' }]);
    };

    const updateSpecification = (index: number, field: 'key' | 'value', value: string) => {
        const newSpecs = [...specifications];
        newSpecs[index][field] = value;
        setSpecifications(newSpecs);
        
        const specsObject = newSpecs.reduce((acc, spec) => {
            if (spec.key && spec.value) {
                acc[spec.key] = spec.value;
            }
            return acc;
        }, {} as Record<string, any>);
        
        setData('specifications', specsObject);
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
        
        // Clear previous validation errors
        setValidationErrors({});
        
        // Validate form
        if (!validateForm()) {
            return;
        }
        
        // Prepare form data with proper types
        const formData = {
            ...data,
            category_id: Number(data.category_id),
            base_price: parseFloat(data.base_price) || 0,
            weight_per_unit: parseFloat(data.weight_per_unit) || 0,
            tax_rate: parseFloat(data.tax_rate) || 0,
        };
        
        put(`/products/${product.id}`, {
            data: formData,
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
                            <Link href="/product-categories/create" className="ml-2 underline">
                                Create Category
                            </Link>
                        </AlertDescription>
                    </Alert>
                )}

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Left Column - Main Information */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Basic Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Package className="w-5 h-5 mr-2" />
                                        Basic Information / මූලික තොරතුරු
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Product Name */}
                                    <div>
                                        <Label htmlFor="name">
                                            Product Name / නිෂ්පාදන නාමය <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            placeholder="Enter product name / නිෂ්පාදන නාමය ඇතුළත් කරන්න"
                                            value={data.name}
                                            onChange={(e) => setData('name', e.target.value)}
                                            className={validationErrors.name || errors.name ? 'border-red-500' : ''}
                                            required
                                        />
                                        {validationErrors.name && (
                                            <p className="text-sm text-red-600 mt-1">{validationErrors.name}</p>
                                        )}
                                        <InputError message={errors.name} />
                                    </div>

                                    {/* Category */}
                                    <div>
                                        <Label htmlFor="category_id">
                                            Category / කාණ්ඩය <span className="text-red-500">*</span>
                                        </Label>
                                        <Select 
                                            value={data.category_id.toString()} 
                                            onValueChange={(value) => setData('category_id', parseInt(value))}
                                        >
                                            <SelectTrigger className={validationErrors.category_id || errors.category_id ? 'border-red-500' : ''}>
                                                <SelectValue placeholder="Select category / කාණ්ඩය තෝරන්න" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {categories.map((category) => (
                                                    <SelectItem key={category.id} value={category.id.toString()}>
                                                        {getCategoryDisplayName(category)}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        {validationErrors.category_id && (
                                            <p className="text-sm text-red-600 mt-1">{validationErrors.category_id}</p>
                                        )}
                                        <InputError message={errors.category_id} />
                                    </div>

                                    {/* Product Code */}
                                    <div>
                                        <Label htmlFor="product_code">
                                            Product Code / නිෂ්පාදන කේතය
                                        </Label>
                                        <Input
                                            id="product_code"
                                            type="text"
                                            placeholder="Enter product code / නිෂ්පාදන කේතය ඇතුළත් කරන්න"
                                            value={data.product_code}
                                            onChange={(e) => setData('product_code', e.target.value)}
                                            className={errors.product_code ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.product_code} />
                                    </div>

                                    {/* Description */}
                                    <div>
                                        <Label htmlFor="description">
                                            Description / විස්තරය
                                        </Label>
                                        <Textarea
                                            id="description"
                                            placeholder="Enter product description / නිෂ්පාදන විස්තරය ඇතුළත් කරන්න"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            rows={4}
                                            className={errors.description ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.description} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Pricing & Weight */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Calculator className="w-5 h-5 mr-2" />
                                        Pricing & Weight / මිල සහ බර
                                    </CardTitle>
                                    <CardDescription>
                                        Set pricing and weight specifications
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        {/* Base Price */}
                                        <div>
                                            <Label htmlFor="base_price">
                                                Base Price (LKR) / මූලික මිල (රු.) <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="base_price"
                                                type="number"
                                                step="0.01"
                                                min="0.01"
                                                placeholder="Enter base price / මූලික මිල ඇතුළත් කරන්න"
                                                value={data.base_price}
                                                onChange={(e) => setData('base_price', e.target.value)}
                                                className={validationErrors.base_price || errors.base_price ? 'border-red-500' : ''}
                                                required
                                            />
                                            {validationErrors.base_price && (
                                                <p className="text-sm text-red-600 mt-1">{validationErrors.base_price}</p>
                                            )}
                                            <InputError message={errors.base_price} />
                                        </div>

                                        {/* Unit Type */}
                                        <div>
                                            <Label htmlFor="unit_type">
                                                Unit Type / ඒකක වර්ගය <span className="text-red-500">*</span>
                                            </Label>
                                            <Select 
                                                value={data.unit_type} 
                                                onValueChange={(value) => setData('unit_type', value)}
                                            >
                                                <SelectTrigger className={validationErrors.unit_type || errors.unit_type ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Select unit type / ඒකක වර්ගය තෝරන්න" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="piece">Piece / කෑලම</SelectItem>
                                                    <SelectItem value="box">Box / පෙට්ටිය</SelectItem>
                                                    <SelectItem value="pack">Pack / පැකේජය</SelectItem>
                                                    <SelectItem value="bottle">Bottle / බෝතලය</SelectItem>
                                                    <SelectItem value="kg">Kilogram / කිලෝග්‍රෑම්</SelectItem>
                                                    <SelectItem value="meter">Meter / මීටර්</SelectItem>
                                                    <SelectItem value="roll">Roll / රෝලය</SelectItem>
                                                    <SelectItem value="sheet">Sheet / පත්‍රය</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {validationErrors.unit_type && (
                                                <p className="text-sm text-red-600 mt-1">{validationErrors.unit_type}</p>
                                            )}
                                            <InputError message={errors.unit_type} />
                                        </div>

                                        {/* Weight per Unit */}
                                        <div>
                                            <Label htmlFor="weight_per_unit">
                                                Weight per Unit / ඒකකයක බර <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id="weight_per_unit"
                                                type="number"
                                                step="0.001"
                                                min="0.001"
                                                placeholder="Enter weight per unit / ඒකකයක බර ඇතුළත් කරන්න"
                                                value={data.weight_per_unit}
                                                onChange={(e) => setData('weight_per_unit', e.target.value)}
                                                className={validationErrors.weight_per_unit || errors.weight_per_unit ? 'border-red-500' : ''}
                                                required
                                            />
                                            {validationErrors.weight_per_unit && (
                                                <p className="text-sm text-red-600 mt-1">{validationErrors.weight_per_unit}</p>
                                            )}
                                            <InputError message={errors.weight_per_unit} />
                                        </div>

                                        {/* Weight Unit */}
                                        <div>
                                            <Label htmlFor="weight_unit">
                                                Weight Unit / බර ඒකකය <span className="text-red-500">*</span>
                                            </Label>
                                            <Select 
                                                value={data.weight_unit} 
                                                onValueChange={(value) => setData('weight_unit', value as 'kg' | 'g' | 'lb' | 'oz')}
                                            >
                                                <SelectTrigger className={validationErrors.weight_unit || errors.weight_unit ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Select weight unit / බර ඒකකය තෝරන්න" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="kg">Kilogram (kg) / කිලෝග්‍රෑම්</SelectItem>
                                                    <SelectItem value="g">Gram (g) / ග්‍රෑම්</SelectItem>
                                                    <SelectItem value="lb">Pound (lb) / පවුම්</SelectItem>
                                                    <SelectItem value="oz">Ounce (oz) / අවුන්ස්</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {validationErrors.weight_unit && (
                                                <p className="text-sm text-red-600 mt-1">{validationErrors.weight_unit}</p>
                                            )}
                                            <InputError message={errors.weight_unit} />
                                        </div>

                                        {/* Tax Rate */}
                                        <div>
                                            <Label htmlFor="tax_rate">
                                                Tax Rate (%) / බදු අනුපාතය (%)
                                            </Label>
                                            <Input
                                                id="tax_rate"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                max="100"
                                                placeholder="Enter tax rate / බදු අනුපාතය ඇතුළත් කරන්න"
                                                value={data.tax_rate}
                                                onChange={(e) => setData('tax_rate', e.target.value)}
                                                className={validationErrors.tax_rate || errors.tax_rate ? 'border-red-500' : ''}
                                            />
                                            {validationErrors.tax_rate && (
                                                <p className="text-sm text-red-600 mt-1">{validationErrors.tax_rate}</p>
                                            )}
                                            <InputError message={errors.tax_rate} />
                                        </div>

                                        {/* Status */}
                                        <div>
                                            <Label htmlFor="status">
                                                Status / තත්ත්වය <span className="text-red-500">*</span>
                                            </Label>
                                            <Select 
                                                value={data.status} 
                                                onValueChange={(value) => setData('status', value as 'active' | 'inactive')}
                                            >
                                                <SelectTrigger className={validationErrors.status || errors.status ? 'border-red-500' : ''}>
                                                    <SelectValue placeholder="Select status / තත්ත්වය තෝරන්න" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="active">Active / සක්‍රිය</SelectItem>
                                                    <SelectItem value="inactive">Inactive / අක්‍රිය</SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {validationErrors.status && (
                                                <p className="text-sm text-red-600 mt-1">{validationErrors.status}</p>
                                            )}
                                            <InputError message={errors.status} />
                                        </div>
                                    </div>

                                    {/* Price Calculation Preview */}
                                    {priceCalculation && (
                                        <div className="mt-4 p-4 bg-blue-50 rounded-lg">
                                            <h4 className="font-medium text-blue-900 mb-2">Price Calculation Preview</h4>
                                            <div className="grid grid-cols-2 gap-2 text-sm">
                                                <div>Base Price: LKR {priceCalculation.basePrice.toFixed(2)}</div>
                                                <div>Weight: {priceCalculation.weight.toFixed(3)} kg</div>
                                                <div>Tax: LKR {priceCalculation.taxAmount.toFixed(2)}</div>
                                                <div className="font-medium">Total: LKR {priceCalculation.totalPrice.toFixed(2)}</div>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Specifications */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Tags className="w-5 h-5 mr-2" />
                                        Specifications / විශේෂාංග
                                    </CardTitle>
                                    <CardDescription>
                                        Add product specifications and features
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {specifications.map((spec, index) => (
                                        <div key={index} className="flex space-x-2">
                                            <Input
                                                placeholder="Specification name / විශේෂාංග නාමය"
                                                value={spec.key}
                                                onChange={(e) => updateSpecification(index, 'key', e.target.value)}
                                                className="flex-1"
                                            />
                                            <Input
                                                placeholder="Value / අගය"
                                                value={spec.value}
                                                onChange={(e) => updateSpecification(index, 'value', e.target.value)}
                                                className="flex-1"
                                            />
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
                                        size="sm"
                                        onClick={addSpecification}
                                    >
                                        <Plus className="w-4 h-4 mr-2" />
                                        Add Specification / විශේෂාංගයක් එකතු කරන්න
                                    </Button>
                                </CardContent>
                            </Card>

                            {/* Inventory Management */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Package className="w-5 h-5 mr-2" />
                                        Inventory Management / තොග කළමනාකරණය
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <Label htmlFor="minimum_quantity">
                                                Minimum Quantity / අවම ප්‍රමාණය
                                            </Label>
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
                                            <Label htmlFor="stock_quantity">
                                                Stock Quantity / තොග ප්‍රමාණය
                                            </Label>
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
                                            <Label htmlFor="reorder_level">
                                                Reorder Level / නැවත ඇණවුම් කිරීමේ මට්ටම
                                            </Label>
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
                                </CardContent>
                            </Card>

                            {/* Customization Options */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Customization / අභිරුචිකරණය</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="requires_customization"
                                            checked={data.requires_customization}
                                            onCheckedChange={(checked) => setData('requires_customization', !!checked)}
                                        />
                                        <Label htmlFor="requires_customization">
                                            Requires Customization / අභිරුචිකරණය අවශ්‍යයි
                                        </Label>
                                    </div>

                                    {data.requires_customization && (
                                        <div>
                                            <Label htmlFor="customization_options">
                                                Customization Options / අභිරුචිකරණ විකල්ප
                                            </Label>
                                            <Textarea
                                                id="customization_options"
                                                placeholder="Describe customization options / අභිරුචිකරණ විකල්ප විස්තර කරන්න"
                                                value={data.customization_options}
                                                onChange={(e) => setData('customization_options', e.target.value)}
                                                rows={3}
                                                className={errors.customization_options ? 'border-red-500' : ''}
                                            />
                                            <InputError message={errors.customization_options} />
                                        </div>
                                    )}

                                    <div>
                                        <Label htmlFor="production_time_days">
                                            Production Time (Days) / නිෂ්පාදන කාලය (දින)
                                        </Label>
                                        <Input
                                            id="production_time_days"
                                            type="number"
                                            min="0"
                                            max="365"
                                            value={data.production_time_days}
                                            onChange={(e) => setData('production_time_days', parseInt(e.target.value) || 0)}
                                            className={errors.production_time_days ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.production_time_days} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* SEO Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>SEO Settings / SEO සැකසීම්</CardTitle>
                                    <CardDescription>
                                        Optimize product for search engines
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div>
                                        <Label htmlFor="keywords">
                                            Keywords / මූල පද
                                        </Label>
                                        <Input
                                            id="keywords"
                                            placeholder="Enter keywords separated by commas / කොමා වලින් වෙන් කළ මූල පද ඇතුළත් කරන්න"
                                            value={data.keywords}
                                            onChange={(e) => setData('keywords', e.target.value)}
                                            className={errors.keywords ? 'border-red-500' : ''}
                                        />
                                        <InputError message={errors.keywords} />
                                    </div>

                                    <div>
                                        <Label htmlFor="meta_title">
                                            SEO Title (Max 60 characters)
                                        </Label>
                                        <Input
                                            id="meta_title"
                                            placeholder="Enter SEO title"
                                            value={data.meta_title}
                                            onChange={(e) => setData('meta_title', e.target.value)}
                                            maxLength={60}
                                            className={errors.meta_title ? 'border-red-500' : ''}
                                        />
                                        <div className="text-sm text-gray-500 mt-1">
                                            {data.meta_title.length}/60 characters
                                        </div>
                                        <InputError message={errors.meta_title} />
                                    </div>

                                    <div>
                                        <Label htmlFor="meta_description">
                                            SEO Description (Max 160 characters)
                                        </Label>
                                        <Textarea
                                            id="meta_description"
                                            placeholder="Enter SEO description"
                                            value={data.meta_description}
                                            onChange={(e) => setData('meta_description', e.target.value)}
                                            maxLength={160}
                                            rows={3}
                                            className={errors.meta_description ? 'border-red-500' : ''}
                                        />
                                        <div className="text-sm text-gray-500 mt-1">
                                            {data.meta_description.length}/160 characters
                                        </div>
                                        <InputError message={errors.meta_description} />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Right Column - Image & Settings */}
                        <div className="space-y-6">
                            {/* Product Image */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <ImageIcon className="w-5 h-5 mr-2" />
                                        Product Image / නිෂ්පාදන රූපය
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Image Preview */}
                                    {imagePreview && (
                                        <div className="relative">
                                            <img
                                                src={imagePreview}
                                                alt="Product preview"
                                                className="w-full h-48 object-cover rounded-lg border"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                className="absolute top-2 right-2"
                                                onClick={() => {
                                                    setImagePreview(null);
                                                    setData('image', null);
                                                }}
                                            >
                                                <X className="w-4 h-4" />
                                            </Button>
                                        </div>
                                    )}

                                    {/* Image Upload */}
                                    <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                        <input
                                            type="file"
                                            id="image"
                                            accept="image/*"
                                            onChange={handleImageChange}
                                            className="hidden"
                                        />
                                        <Label htmlFor="image" className="cursor-pointer">
                                            <Upload className="w-8 h-8 text-gray-400 mx-auto mb-2" />
                                            <p className="text-sm text-gray-600">
                                                Click to upload image / රූපයක් උඩුගත කිරීමට ක්ලික් කරන්න
                                            </p>
                                            <p className="text-xs text-gray-500 mt-1">
                                                PNG, JPG, GIF up to 3MB / PNG, JPG, GIF 3MB දක්වා
                                            </p>
                                        </Label>
                                    </div>
                                    <InputError message={errors.image} />
                                </CardContent>
                            </Card>

                            {/* Product Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Product Settings / නිෂ්පාදන සැකසීම්</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_featured"
                                            checked={data.is_featured}
                                            onCheckedChange={(checked) => setData('is_featured', !!checked)}
                                        />
                                        <Label htmlFor="is_featured" className="flex items-center">
                                            <Star className="w-4 h-4 mr-1" />
                                            Featured Product / විශේෂාංගීකෘත නිෂ්පාදනය
                                        </Label>
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Checkbox
                                            id="is_digital"
                                            checked={data.is_digital}
                                            onCheckedChange={(checked) => setData('is_digital', !!checked)}
                                        />
                                        <Label htmlFor="is_digital">
                                            Digital Product / ඩිජිටල් නිෂ්පාදනය
                                        </Label>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Product Information */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Product Information</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Created:</span>
                                        <span>{new Date(product.created_at).toLocaleDateString()}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Last Updated:</span>
                                        <span>{new Date(product.updated_at).toLocaleDateString()}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-600">Product ID:</span>
                                        <span>{product.id}</span>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Submit Button */}
                    <div className="flex justify-end space-x-4 pt-6 border-t">
                        <Link href="/products">
                            <Button type="button" variant="outline">
                                Cancel / අවලංගු කරන්න
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            {processing ? (
                                <>
                                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                                    Updating... / යාවත්කාලීන කරමින්...
                                </>
                            ) : (
                                <>
                                    <Save className="w-4 h-4 mr-2" />
                                    Update Product / නිෂ්පාදනය යාවත්කාලීන කරන්න
                                </>
                            )}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}