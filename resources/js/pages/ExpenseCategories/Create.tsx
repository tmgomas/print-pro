// resources/js/pages/ExpenseCategories/Create.tsx

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
    FolderTree, 
    Save,
    AlertCircle,
    Palette,
    Hash,
    FileText,
    Tags,
    Folder,
    Plus,
    Loader2
} from 'lucide-react';
import { BreadcrumbItem } from '@/types';

interface ParentCategory {
    id: number;
    name: string;
    code: string;
    full_name: string;
}

interface Props {
    parentCategories: ParentCategory[];
}

interface FormData {
    name: string;
    code: string;
    description: string;
    icon: string;
    color: string;
    parent_id: string;
    sort_order: number;
    status: 'active' | 'inactive';
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Expense Management', href: '/expenses' },
    { title: 'Categories', href: '/expense-categories' },
    { title: 'Create Category', href: '/expense-categories/create' },
];

// Common icons for expense categories
const categoryIcons = [
    { value: 'building', label: 'Building', icon: '🏢' },
    { value: 'car', label: 'Transportation', icon: '🚗' },
    { value: 'megaphone', label: 'Marketing', icon: '📢' },
    { value: 'zap', label: 'Utilities', icon: '⚡' },
    { value: 'wrench', label: 'Maintenance', icon: '🔧' },
    { value: 'user-tie', label: 'Professional', icon: '👔' },
    { value: 'utensils', label: 'Food', icon: '🍽️' },
    { value: 'more-horizontal', label: 'Miscellaneous', icon: '📦' },
    { value: 'laptop', label: 'Technology', icon: '💻' },
    { value: 'home', label: 'Office', icon: '🏠' },
    { value: 'plane', label: 'Travel', icon: '✈️' },
    { value: 'phone', label: 'Communication', icon: '📱' },
    { value: 'shield', label: 'Insurance', icon: '🛡️' },
    { value: 'book', label: 'Training', icon: '📚' },
    { value: 'heart', label: 'Health', icon: '❤️' },
];

// Common colors for categories
const categoryColors = [
    '#3B82F6', // Blue
    '#10B981', // Green
    '#F59E0B', // Yellow
    '#EF4444', // Red
    '#8B5CF6', // Purple
    '#06B6D4', // Cyan
    '#84CC16', // Lime
    '#6B7280', // Gray
    '#F97316', // Orange
    '#EC4899', // Pink
    '#14B8A6', // Teal
    '#A855F7', // Violet
];

export default function CreateExpenseCategory({ parentCategories }: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<FormData>({
        name: '',
        code: '',
        description: '',
        icon: '',
        color: '#3B82F6',
        parent_id: '',
        sort_order: 0,
        status: 'active',
    });

    const [isGeneratingCode, setIsGeneratingCode] = useState(false);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        post('/expense-categories', {
            onSuccess: () => {
                console.log('✅ Category created successfully!');
            },
            onError: (errors) => {
                console.log('✗ Validation errors:', errors);
            }
        });
    };

    const generateCodeFromName = () => {
        if (!data.name) return;
        
        setIsGeneratingCode(true);
        
        // Simple code generation logic
        const code = data.name
            .replace(/[^a-zA-Z0-9\s]/g, '') // Remove special characters
            .split(' ')
            .map(word => word.substring(0, 3).toUpperCase())
            .join('')
            .substring(0, 6);
        
        setData('code', code);
        
        setTimeout(() => setIsGeneratingCode(false), 500);
    };

    const handleNameChange = (name: string) => {
        setData('name', name);
        
        // Auto-generate code if it's empty
        if (!data.code && name) {
            generateCodeFromName();
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Expense Category" />

            <div className="space-y-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <Button variant="outline" asChild>
                        <Link href="/expense-categories">
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            Back to Categories
                        </Link>
                    </Button>
                    <div className="text-right">
                        <h1 className="text-3xl font-bold tracking-tight">Create Expense Category</h1>
                        <p className="text-muted-foreground">Add a new category to organize your expenses</p>
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
                                        <FolderTree className="h-5 w-5" />
                                        Basic Information
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Category Name */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="name">Category Name *</Label>
                                        <Input
                                            id="name"
                                            type="text"
                                            value={data.name}
                                            onChange={(e) => handleNameChange(e.target.value)}
                                            placeholder="Enter category name..."
                                            maxLength={255}
                                            required
                                        />
                                        <InputError message={errors.name} />
                                    </div>

                                    {/* Category Code */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="code" className="flex items-center gap-2">
                                            Category Code *
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={generateCodeFromName}
                                                disabled={!data.name || isGeneratingCode}
                                                className="h-6 text-xs"
                                            >
                                                {isGeneratingCode ? (
                                                    <Loader2 className="h-3 w-3 animate-spin mr-1" />
                                                ) : (
                                                    <Hash className="h-3 w-3 mr-1" />
                                                )}
                                                Generate
                                            </Button>
                                        </Label>
                                        <Input
                                            id="code"
                                            type="text"
                                            value={data.code}
                                            onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                            placeholder="CATEGORY_CODE"
                                            maxLength={20}
                                            style={{ textTransform: 'uppercase' }}
                                            required
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Unique identifier for this category (e.g., OFFICE, TRAVEL, MARKETING)
                                        </p>
                                        <InputError message={errors.code} />
                                    </div>

                                    {/* Description */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="description">Description</Label>
                                        <Textarea
                                            id="description"
                                            value={data.description}
                                            onChange={(e) => setData('description', e.target.value)}
                                            placeholder="Brief description of this category..."
                                            rows={3}
                                            maxLength={1000}
                                        />
                                        <InputError message={errors.description} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Visual Appearance */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Palette className="h-5 w-5" />
                                        Visual Appearance
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Icon Selection */}
                                    <div className="grid gap-2">
                                        <Label>Category Icon</Label>
                                        <div className="grid grid-cols-4 gap-2">
                                            {categoryIcons.map((iconOption) => (
                                                <Button
                                                    key={iconOption.value}
                                                    type="button"
                                                    variant={data.icon === iconOption.value ? "default" : "outline"}
                                                    className="h-12 flex flex-col items-center gap-1 text-xs"
                                                    onClick={() => setData('icon', iconOption.value)}
                                                >
                                                    <span className="text-lg">{iconOption.icon}</span>
                                                    <span className="truncate">{iconOption.label}</span>
                                                </Button>
                                            ))}
                                        </div>
                                        <InputError message={errors.icon} />
                                    </div>

                                    {/* Color Selection */}
                                    <div className="grid gap-2">
                                        <Label>Category Color</Label>
                                        <div className="flex flex-wrap gap-2">
                                            {categoryColors.map((color) => (
                                                <Button
                                                    key={color}
                                                    type="button"
                                                    variant="outline"
                                                    className={`w-10 h-10 p-0 border-2 ${
                                                        data.color === color ? 'ring-2 ring-offset-2 ring-blue-500' : ''
                                                    }`}
                                                    style={{ backgroundColor: color }}
                                                    onClick={() => setData('color', color)}
                                                />
                                            ))}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Label htmlFor="custom-color" className="text-sm">Custom Color:</Label>
                                            <Input
                                                id="custom-color"
                                                type="color"
                                                value={data.color}
                                                onChange={(e) => setData('color', e.target.value)}
                                                className="w-16 h-8 p-1 rounded"
                                            />
                                            <span className="text-sm text-muted-foreground">{data.color}</span>
                                        </div>
                                        <InputError message={errors.color} />
                                    </div>

                                    {/* Preview */}
                                    <div className="grid gap-2">
                                        <Label>Preview</Label>
                                        <div className="flex items-center gap-3 p-3 border rounded-lg bg-gray-50">
                                            <div
                                                className="w-8 h-8 rounded flex items-center justify-center text-white"
                                                style={{ backgroundColor: data.color }}
                                            >
                                                {data.icon && categoryIcons.find(i => i.value === data.icon)?.icon}
                                            </div>
                                            <div>
                                                <div className="font-medium">{data.name || 'Category Name'}</div>
                                                <div className="text-sm text-gray-500">({data.code || 'CODE'})</div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Organization */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Folder className="h-5 w-5" />
                                        Organization
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Parent Category */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="parent_id">Parent Category</Label>
                                        <Select value={data.parent_id} onValueChange={(value) => setData('parent_id', value)}>
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select parent category (optional)" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="null">No Parent (Top Level)</SelectItem>
                                                {parentCategories.map((category) => (
                                                    <SelectItem key={category.id} value={category.id.toString()}>
                                                        {category.full_name || category.name} ({category.code})
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <p className="text-xs text-muted-foreground">
                                            Leave empty to create a top-level category
                                        </p>
                                        <InputError message={errors.parent_id} />
                                    </div>

                                    {/* Sort Order */}
                                    <div className="grid gap-2">
                                        <Label htmlFor="sort_order">Sort Order</Label>
                                        <Input
                                            id="sort_order"
                                            type="number"
                                            value={data.sort_order}
                                            onChange={(e) => setData('sort_order', parseInt(e.target.value) || 0)}
                                            placeholder="0"
                                            min="0"
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Lower numbers appear first in lists
                                        </p>
                                        <InputError message={errors.sort_order} />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Tags className="h-5 w-5" />
                                        Settings
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    {/* Status */}
                                    <div className="grid gap-2">
                                        <Label>Status</Label>
                                        <div className="flex gap-4">
                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="radio"
                                                    name="status"
                                                    value="active"
                                                    checked={data.status === 'active'}
                                                    onChange={(e) => setData('status', e.target.value as 'active' | 'inactive')}
                                                    className="text-primary"
                                                />
                                                <span>Active</span>
                                            </label>
                                            <label className="flex items-center space-x-2">
                                                <input
                                                    type="radio"
                                                    name="status"
                                                    value="inactive"
                                                    checked={data.status === 'inactive'}
                                                    onChange={(e) => setData('status', e.target.value as 'active' | 'inactive')}
                                                    className="text-primary"
                                                />
                                                <span>Inactive</span>
                                            </label>
                                        </div>
                                        <InputError message={errors.status} />
                                    </div>
                                </CardContent>
                            </Card>

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
                                            <p>Choose descriptive names that clearly identify the expense type</p>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Use short, memorable codes (3-6 characters)</p>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Select appropriate icons and colors for easy identification</p>
                                        </div>
                                        <div className="flex items-start gap-2">
                                            <div className="w-1 h-1 bg-blue-500 rounded-full mt-2"></div>
                                            <p>Use parent categories to create logical groupings</p>
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
                                                    Creating Category...
                                                </>
                                            ) : (
                                                <>
                                                    <Save className="h-4 w-4 mr-2" />
                                                    Create Category
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
                        </div>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}