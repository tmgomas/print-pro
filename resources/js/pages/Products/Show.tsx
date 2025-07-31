// ===============================
// resources/js/pages/Products/Show.tsx
// Product Catalog System - Show Page
// ===============================

import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
// import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'; // Component not available
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Package, 
    ArrowLeft,
    Edit,
    Trash2,
    Calculator,
    Weight,
    Tags,
    Image as ImageIcon,
    FileText,
    TrendingUp,
    Eye,
    Copy,
    Share2,
    Download
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
    weight_unit: 'grams' | 'kg';
    tax_rate: number;
    image?: string;
    image_url?: string;
    specifications?: Record<string, any>;
    status: 'active' | 'inactive';
    created_at: string;
    updated_at: string;
    
    // Relations
    category?: {
        id: number;
        name: string;
        code: string;
        parent?: {
            id: number;
            name: string;
        };
    };
    company?: {
        id: number;
        company_name: string;
    };
    
    // Computed
    formatted_price?: string;
    total_weight_display?: string;
    requires_customization?: boolean;
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
    pricingTiers: WeightPricingTier[];
    canEdit: boolean;
    canDelete: boolean;
}

export default function ProductShow({ product, pricingTiers, canEdit, canDelete }: Props) {
    const [quantity, setQuantity] = useState(1);
    const [priceCalculation, setPriceCalculation] = useState<any>(null);

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Products',
            href: '/products',
        },
        {
            title: product.name,
            href: `/products/${product.id}`,
        },
    ];

    const calculatePrice = (qty: number) => {
        const basePrice = product.base_price * qty;
        const totalWeight = product.weight_per_unit * qty;
        
        // Convert weight to kg for calculation
        const weightInKg = product.weight_unit === 'grams' ? totalWeight / 1000 : totalWeight;
        
        // Find applicable pricing tier
        const tier = pricingTiers.find(t => 
            weightInKg >= t.min_weight && 
            (t.max_weight === null || weightInKg <= t.max_weight)
        );

        let deliveryCharge = 0;
        if (tier) {
            deliveryCharge = tier.base_price + (tier.per_kg_rate ? (weightInKg * tier.per_kg_rate) : 0);
        }

        const taxAmount = (basePrice + deliveryCharge) * (product.tax_rate / 100);
        const totalAmount = basePrice + deliveryCharge + taxAmount;

        setPriceCalculation({
            quantity: qty,
            base_price: basePrice,
            delivery_charge: deliveryCharge,
            tax_amount: taxAmount,
            total_amount: totalAmount,
            total_weight: totalWeight,
            weight_in_kg: weightInKg,
            tier_used: tier?.tier_name || 'No tier found',
            weight_display: `${totalWeight} ${product.weight_unit}`
        });
    };

    const handleQuantityChange = (value: string) => {
        const qty = parseInt(value) || 1;
        setQuantity(qty);
        calculatePrice(qty);
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
            router.delete(`/products/${product.id}`, {
                onSuccess: () => {
                    router.visit('/products');
                },
            });
        }
    };

    const copyProductCode = () => {
        navigator.clipboard.writeText(product.product_code);
        // You could add a toast notification here
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'active':
                return 'bg-green-100 text-green-800 border-green-300';
            case 'inactive':
                return 'bg-red-100 text-red-800 border-red-300';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-300';
        }
    };

    // Calculate initial price
    useState(() => {
        calculatePrice(1);
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Product: ${product.name}`} />
            
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
                            <h1 className="text-3xl font-bold text-gray-900">{product.name}</h1>
                            <div className="flex items-center space-x-2 mt-2">
                                <span className="text-gray-600">Code:</span>
                                <code className="bg-gray-100 px-2 py-1 rounded text-sm font-mono">
                                    {product.product_code}
                                </code>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={copyProductCode}
                                >
                                    <Copy className="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                    
                    <div className="flex space-x-3">
                        <Button variant="outline" size="sm">
                            <Share2 className="w-4 h-4 mr-2" />
                            Share
                        </Button>
                        {canEdit && (
                            <Link href={`/products/${product.id}/edit`}>
                                <Button variant="outline">
                                    <Edit className="w-4 h-4 mr-2" />
                                    Edit
                                </Button>
                            </Link>
                        )}
                        {canDelete && (
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                <Trash2 className="w-4 h-4 mr-2" />
                                Delete
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Main Content */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Product Overview */}
                        <Card>
                            <CardHeader>
                                <div className="flex items-center justify-between">
                                    <CardTitle className="flex items-center">
                                        <Package className="w-5 h-5 mr-2" />
                                        Product Overview
                                    </CardTitle>
                                    <Badge className={getStatusColor(product.status)}>
                                        {product.status}
                                    </Badge>
                                </div>
                            </CardHeader>
                            <CardContent>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    {/* Product Image */}
                                    <div>
                                        <div className="aspect-square bg-gray-100 rounded-lg flex items-center justify-center">
                                            {product.image_url ? (
                                                <img
                                                    src={product.image_url}
                                                    alt={product.name}
                                                    className="w-full h-full object-cover rounded-lg"
                                                />
                                            ) : (
                                                <ImageIcon className="w-16 h-16 text-gray-400" />
                                            )}
                                        </div>
                                    </div>
                                    
                                    {/* Product Details */}
                                    <div className="space-y-4">
                                        <div>
                                            <h3 className="text-lg font-semibold">{product.name}</h3>
                                            {product.description && (
                                                <p className="text-gray-600 mt-2">{product.description}</p>
                                            )}
                                        </div>

                                        <div className="space-y-3">
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">Category:</span>
                                                <span className="font-medium">
                                                    {product.category?.parent && (
                                                        <span className="text-gray-500">
                                                            {product.category.parent.name} / 
                                                        </span>
                                                    )}
                                                    {product.category?.name}
                                                </span>
                                            </div>
                                            
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">Base Price:</span>
                                                <span className="font-semibold text-lg">
                                                    {product.formatted_price || `Rs. ${product.base_price}`}
                                                </span>
                                            </div>
                                            
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">Weight:</span>
                                                <span className="font-medium">
                                                    {product.weight_per_unit} {product.weight_unit}
                                                </span>
                                            </div>
                                            
                                            <div className="flex justify-between">
                                                <span className="text-gray-600">Tax Rate:</span>
                                                <span className="font-medium">{product.tax_rate}%</span>
                                            </div>
                                            
                                            {product.requires_customization && (
                                                <div className="flex justify-between">
                                                    <span className="text-gray-600">Customization:</span>
                                                    <Badge variant="secondary">Required</Badge>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Product Details Sections */}
                        <div className="space-y-6">
                            {/* Specifications */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Tags className="w-5 h-5 mr-2" />
                                        Product Specifications
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {product.specifications && Object.keys(product.specifications).length > 0 ? (
                                        <div className="space-y-3">
                                            {Object.entries(product.specifications).map(([key, value]) => (
                                                <div key={key} className="flex justify-between py-2 border-b border-gray-100 last:border-b-0">
                                                    <span className="font-medium text-gray-700">{key}:</span>
                                                    <span className="text-gray-900">{value as string}</span>
                                                </div>
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="text-center py-8 text-gray-500">
                                            <Tags className="w-12 h-12 mx-auto mb-4 text-gray-300" />
                                            <p>No specifications available</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Pricing Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Weight className="w-5 h-5 mr-2" />
                                        Weight-Based Pricing Tiers
                                    </CardTitle>
                                    <CardDescription>
                                        Delivery charges are calculated based on product weight
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {pricingTiers.map((tier) => (
                                            <div key={tier.id} className="p-4 border rounded-lg">
                                                <div className="flex justify-between items-center mb-2">
                                                    <h4 className="font-semibold">{tier.tier_name}</h4>
                                                    <Badge variant="outline">
                                                        {tier.min_weight}kg - {tier.max_weight ? `${tier.max_weight}kg` : '∞'}
                                                    </Badge>
                                                </div>
                                                <div className="text-sm text-gray-600">
                                                    Base Price: Rs. {tier.base_price}
                                                    {tier.per_kg_rate && (
                                                        <span> + Rs. {tier.per_kg_rate}/kg</span>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Activity Log */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <TrendingUp className="w-5 h-5 mr-2" />
                                        Recent Activity
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                            <div className="w-2 h-2 bg-blue-500 rounded-full"></div>
                                            <div className="flex-1">
                                                <p className="text-sm">Product created</p>
                                                <p className="text-xs text-gray-500">
                                                    {new Date(product.created_at).toLocaleDateString()}
                                                </p>
                                            </div>
                                        </div>
                                        
                                        {product.updated_at !== product.created_at && (
                                            <div className="flex items-center space-x-3 p-3 bg-gray-50 rounded-lg">
                                                <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                                <div className="flex-1">
                                                    <p className="text-sm">Product updated</p>
                                                    <p className="text-xs text-gray-500">
                                                        {new Date(product.updated_at).toLocaleDateString()}
                                                    </p>
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {/* Price Calculator */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <Calculator className="w-5 h-5 mr-2" />
                                    Price Calculator
                                </CardTitle>
                                <CardDescription>
                                    Calculate total price including delivery charges
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div>
                                    <Label htmlFor="quantity">Quantity</Label>
                                    <Input
                                        id="quantity"
                                        type="number"
                                        min="1"
                                        value={quantity}
                                        onChange={(e) => handleQuantityChange(e.target.value)}
                                        className="mt-1"
                                    />
                                </div>

                                {priceCalculation && (
                                    <div className="space-y-3 pt-4 border-t">
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Base Price:</span>
                                            <span>Rs. {priceCalculation.base_price.toFixed(2)}</span>
                                        </div>
                                        
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Delivery Charge:</span>
                                            <span>Rs. {priceCalculation.delivery_charge.toFixed(2)}</span>
                                        </div>
                                        
                                        <div className="flex justify-between text-sm">
                                            <span className="text-gray-600">Tax ({product.tax_rate}%):</span>
                                            <span>Rs. {priceCalculation.tax_amount.toFixed(2)}</span>
                                        </div>
                                        
                                        <Separator />
                                        
                                        <div className="flex justify-between font-semibold">
                                            <span>Total Amount:</span>
                                            <span className="text-lg">Rs. {priceCalculation.total_amount.toFixed(2)}</span>
                                        </div>
                                        
                                        <div className="text-xs text-gray-500 space-y-1">
                                            <p>Total Weight: {priceCalculation.weight_display}</p>
                                            <p>Pricing Tier: {priceCalculation.tier_used}</p>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button className="w-full" variant="outline">
                                    <Eye className="w-4 h-4 mr-2" />
                                    View in Catalog
                                </Button>
                                
                                <Button className="w-full" variant="outline">
                                    <Copy className="w-4 h-4 mr-2" />
                                    Duplicate Product
                                </Button>
                                
                                <Button className="w-full" variant="outline">
                                    <Download className="w-4 h-4 mr-2" />
                                    Export Details
                                </Button>
                                
                                {canEdit && (
                                    <Link href={`/products/${product.id}/edit`} className="block">
                                        <Button className="w-full">
                                            <Edit className="w-4 h-4 mr-2" />
                                            Edit Product
                                        </Button>
                                    </Link>
                                )}
                            </CardContent>
                        </Card>

                        {/* Product Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Product Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Company:</span>
                                    <span>{product.company?.company_name}</span>
                                </div>
                                
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Product Code:</span>
                                    <code className="bg-gray-100 px-1 rounded text-xs">
                                        {product.product_code}
                                    </code>
                                </div>
                                
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Status:</span>
                                    <Badge className={getStatusColor(product.status)} variant="secondary">
                                        {product.status}
                                    </Badge>
                                </div>
                                
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Created:</span>
                                    <span>{new Date(product.created_at).toLocaleDateString()}</span>
                                </div>
                                
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-600">Last Updated:</span>
                                    <span>{new Date(product.updated_at).toLocaleDateString()}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}