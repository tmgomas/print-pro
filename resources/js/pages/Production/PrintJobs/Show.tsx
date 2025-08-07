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
    Factory, 
    Plus, 
    Eye, 
    Edit,
    Trash2,
    PlayCircle,
    Settings,
    UserPlus,
    AlertTriangle,
    CheckCircle,
    Package,
    Printer,
    Timer,
    Target,
    Pause,
    RotateCcw,
    X,
    ArrowRight,
    FileCheck,
    Clock4,
    AlertCircle
} from 'lucide-react';

interface ProductionStage {
    id: number;
    stage_name: string;
    stage_status: string;
    started_at?: string;
    completed_at?: string;
    estimated_duration?: number;
    actual_duration?: number;
    stage_order: number;
    notes?: string;
    requires_customer_approval?: boolean;
}

interface PrintJob {
    id: number;
    job_number: string;
    job_type: string;
    job_title?: string;
    job_description?: string;
    production_status: string;
    priority: string;
    estimated_completion: string;
    actual_completion?: string;
    estimated_cost?: number;
    actual_cost?: number;
    completion_percentage: number;
    production_notes?: string;
    customer_instructions?: string;
    special_instructions?: string;
    specifications?: Record<string, any>;
    created_at: string;
    updated_at: string;
    
    // Relationships
    invoice?: {
        id: number;
        invoice_number: string;
        customer: {
            id: number;
            name: string;
            email?: string;
            phone: string;
        };
        items: Array<{
            id: number;
            item_description: string;
            quantity: number;
            unit_price: number;
            line_total: number;
            product?: {
                id: number;
                name: string;
            };
        }>;
    };
    branch: {
        id: number;
        name: string;
        code: string;
    };
    assignedTo?: {
        id: number;
        name: string;
        email: string;
    };
    productionStages: ProductionStage[];
}

interface ProductionStaff {
    id: number;
    name: string;
    email: string;
}

interface Props {
    printJob: PrintJob;
    productionStaff: ProductionStaff[];
    permissions: {
        edit: boolean;
        delete: boolean;
        manage_production: boolean;
        assign_staff: boolean;
        update_priority: boolean;
    };
    jobTypes: Record<string, string>;
}

export default function PrintJobShow({ printJob, productionStaff, permissions, jobTypes }: Props) {
    // Debug: Log the received data
    console.log('PrintJob Show Component Received Data:', {
        printJob,
        productionStagesCount: printJob?.productionStages?.length || 0,
        productionStages: printJob?.productionStages || [],
        productionStatus: printJob?.production_status,
        permissions,
        jobTypes
    });

    const [isAssignDialogOpen, setIsAssignDialogOpen] = useState(false);
    const [isPriorityDialogOpen, setIsPriorityDialogOpen] = useState(false);
    const [stageActionDialog, setStageActionDialog] = useState<{
        isOpen: boolean;
        stage: ProductionStage | null;
        action: 'start' | 'complete' | 'hold' | 'resume' | 'approve' | 'reject' | null;
    }>({
        isOpen: false,
        stage: null,
        action: null
    });

    // Add safety checks for potentially undefined arrays
    const safeProductionStaff = productionStaff || [];
    const safeProductionStages = printJob?.productionStages || [];
    const safeInvoiceItems = printJob?.invoice?.items || [];

    // Debug production stages
    console.log('Safe Production Stages Debug:', {
        printJobExists: !!printJob,
        hasProductionStages: !!printJob?.productionStages,
        productionStagesType: typeof printJob?.productionStages,
        productionStagesLength: printJob?.productionStages?.length,
        safeProductionStagesLength: safeProductionStages.length,
        firstStage: safeProductionStages[0] || 'No first stage',
        allStages: safeProductionStages
    });

    // Assignment form
    const { data: assignData, setData: setAssignData, post: postAssign, processing: assignProcessing, errors: assignErrors, reset: resetAssign } = useForm({
        assigned_to: printJob.assignedTo?.id?.toString() || '',
        notes: '',
    });

    // Priority form
    const { data: priorityData, setData: setPriorityData, post: postPriority, processing: priorityProcessing, errors: priorityErrors, reset: resetPriority } = useForm({
        priority: printJob.priority,
        reason: '',
    });

    // Stage action form
    const { data: stageActionData, setData: setStageActionData, post: postStageAction, processing: stageActionProcessing, errors: stageActionErrors, reset: resetStageAction } = useForm({
        notes: '',
        reason: '',
        stage_data: {},
    });

    const statusColors: Record<string, string> = {
        pending: 'bg-gray-500',
        ready: 'bg-blue-400',
        in_progress: 'bg-blue-500',
        design_review: 'bg-purple-500',
        design_approved: 'bg-green-500',
        pre_press: 'bg-indigo-500',
        printing: 'bg-orange-500',
        finishing: 'bg-pink-500',
        quality_check: 'bg-yellow-500',
        completed: 'bg-green-600',
        on_hold: 'bg-gray-600',
        cancelled: 'bg-red-500',
        requires_approval: 'bg-orange-500',
        rejected: 'bg-red-600'
    };

    const priorityColors: Record<string, string> = {
        low: 'bg-green-500',
        normal: 'bg-blue-500',
        medium: 'bg-yellow-500',
        high: 'bg-orange-500',
        urgent: 'bg-red-500'
    };

    const handleAssignStaff = (e: React.FormEvent) => {
        e.preventDefault();
        
        postAssign(route('production.print-jobs.assign', printJob.id), {
            onSuccess: () => {
                setIsAssignDialogOpen(false);
                resetAssign();
            }
        });
    };

    const handleUpdatePriority = (e: React.FormEvent) => {
        e.preventDefault();
        
        postPriority(route('production.print-jobs.update-priority', printJob.id), {
            onSuccess: () => {
                setIsPriorityDialogOpen(false);
                resetPriority();
            }
        });
    };

    const handleStartProduction = () => {
        router.post(route('production.print-jobs.start-production', printJob.id), {}, {
            onSuccess: () => {
                // Success handled by redirect
            }
        });
    };

    const handleStageAction = (stage: ProductionStage, action: 'start' | 'complete' | 'hold' | 'resume' | 'approve' | 'reject') => {
        setStageActionDialog({
            isOpen: true,
            stage,
            action
        });
        resetStageAction();
    };

    const submitStageAction = (e: React.FormEvent) => {
        e.preventDefault();
        
        const { stage, action } = stageActionDialog;
        if (!stage || !action) return;

        const routeName = `production.stages.${action}`;
        
        postStageAction(route(routeName, stage.id), {
            onSuccess: () => {
                setStageActionDialog({ isOpen: false, stage: null, action: null });
                resetStageAction();
                // Refresh the page to show updated stage data
                router.reload();
            }
        });
    };

    const handleDelete = () => {
        if (confirm('Are you sure you want to delete this print job?')) {
            router.delete(route('production.print-jobs.destroy', printJob.id));
        }
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleString();
    };

    const formatCurrency = (amount?: number) => {
        if (!amount) return 'N/A';
        return new Intl.NumberFormat('en-LK', {
            style: 'currency',
            currency: 'LKR',
            minimumFractionDigits: 2
        }).format(amount).replace('LKR', 'Rs.');
    };

    const getStageStatusColor = (status: string) => {
        const colors = {
            pending: 'bg-gray-100 text-gray-800',
            ready: 'bg-blue-100 text-blue-800',
            in_progress: 'bg-blue-100 text-blue-800',
            completed: 'bg-green-100 text-green-800',
            on_hold: 'bg-yellow-100 text-yellow-800',
            requires_approval: 'bg-orange-100 text-orange-800',
            rejected: 'bg-red-100 text-red-800',
        };
        return colors[status as keyof typeof colors] || 'bg-gray-100 text-gray-800';
    };

    const getStageIcon = (status: string) => {
        const icons = {
            pending: Clock4,
            ready: ArrowRight,
            in_progress: PlayCircle,
            completed: CheckCircle,
            on_hold: Pause,
            requires_approval: FileCheck,
            rejected: X,
        };
        const Icon = icons[status as keyof typeof icons] || Clock4;
        return <Icon className="h-4 w-4" />;
    };

    const canPerformStageAction = (stage: ProductionStage, action: string): boolean => {
        if (!permissions.manage_production) return false;

        switch (action) {
            case 'start':
                return stage.stage_status === 'ready' || stage.stage_status === 'pending';
            case 'complete':
                return stage.stage_status === 'in_progress';
            case 'hold':
                return stage.stage_status === 'in_progress' || stage.stage_status === 'ready';
            case 'resume':
                return stage.stage_status === 'on_hold';
            case 'approve':
                return stage.stage_status === 'requires_approval';
            case 'reject':
                return stage.stage_status === 'requires_approval' || stage.stage_status === 'in_progress';
            default:
                return false;
        }
    };

    const getStageActionButtons = (stage: ProductionStage) => {
        const buttons = [];

        // Only show actions if user has manage_production permission
        if (!permissions.manage_production) return null;

        if (canPerformStageAction(stage, 'start')) {
            buttons.push(
                <Button
                    key="start"
                    size="sm"
                    variant="outline"
                    onClick={() => handleStageAction(stage, 'start')}
                    className="text-green-600 hover:text-green-700 hover:bg-green-50 border-green-200"
                >
                    <PlayCircle className="h-3 w-3 mr-1" />
                    Start
                </Button>
            );
        }

        if (canPerformStageAction(stage, 'complete')) {
            buttons.push(
                <Button
                    key="complete"
                    size="sm"
                    variant="outline"
                    onClick={() => handleStageAction(stage, 'complete')}
                    className="text-blue-600 hover:text-blue-700 hover:bg-blue-50 border-blue-200"
                >
                    <CheckCircle className="h-3 w-3 mr-1" />
                    Complete
                </Button>
            );
        }

        if (canPerformStageAction(stage, 'hold')) {
            buttons.push(
                <Button
                    key="hold"
                    size="sm"
                    variant="outline"
                    onClick={() => handleStageAction(stage, 'hold')}
                    className="text-yellow-600 hover:text-yellow-700 hover:bg-yellow-50 border-yellow-200"
                >
                    <Pause className="h-3 w-3 mr-1" />
                    Hold
                </Button>
            );
        }

        if (canPerformStageAction(stage, 'resume')) {
            buttons.push(
                <Button
                    key="resume"
                    size="sm"
                    variant="outline"
                    onClick={() => handleStageAction(stage, 'resume')}
                    className="text-green-600 hover:text-green-700 hover:bg-green-50 border-green-200"
                >
                    <RotateCcw className="h-3 w-3 mr-1" />
                    Resume
                </Button>
            );
        }

        if (canPerformStageAction(stage, 'approve')) {
            buttons.push(
                <Button
                    key="approve"
                    size="sm"
                    variant="outline"
                    onClick={() => handleStageAction(stage, 'approve')}
                    className="text-green-600 hover:text-green-700 hover:bg-green-50 border-green-200"
                >
                    <CheckCircle className="h-3 w-3 mr-1" />
                    Approve
                </Button>
            );
        }

        if (canPerformStageAction(stage, 'reject')) {
            buttons.push(
                <Button
                    key="reject"
                    size="sm"
                    variant="outline"
                    onClick={() => handleStageAction(stage, 'reject')}
                    className="text-red-600 hover:text-red-700 hover:bg-red-50 border-red-200"
                >
                    <X className="h-3 w-3 mr-1" />
                    Reject
                </Button>
            );
        }

        return buttons.length > 0 ? (
            <div className="flex gap-1 flex-wrap">
                {buttons}
            </div>
        ) : null;
    };

    const getActionDialogTitle = () => {
        const { action, stage } = stageActionDialog;
        if (!action || !stage) return '';

        const stageDisplayName = stage.stage_name.replace('_', ' ').charAt(0).toUpperCase() + 
                                stage.stage_name.replace('_', ' ').slice(1);
        
        const actionNames = {
            start: 'Start',
            complete: 'Complete',
            hold: 'Put on Hold',
            resume: 'Resume',
            approve: 'Approve',
            reject: 'Reject'
        };

        return `${actionNames[action]} Stage: ${stageDisplayName}`;
    };

    // Early return if printJob is not available
    if (!printJob) {
        return (
            <AppLayout>
                <Head title="Print Job Not Found" />
                <div className="py-6">
                    <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                        <Alert>
                            <AlertTriangle className="h-4 w-4" />
                            <AlertDescription>
                                Print job not found or failed to load.
                            </AlertDescription>
                        </Alert>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <Head title={`Print Job ${printJob.job_number}`} />

            <div className="py-6">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    {/* Header */}
                    <div className="mb-6 flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">
                                Print Job {printJob.job_number}
                            </h1>
                            <nav className="flex space-x-2 text-sm text-gray-500">
                                <Link href={route('dashboard')} className="hover:text-gray-700">
                                    Dashboard
                                </Link>
                                <span>/</span>
                                <Link href={route('production.print-jobs.index')} className="hover:text-gray-700">
                                    Print Jobs
                                </Link>
                                <span>/</span>
                                <span>{printJob.job_number}</span>
                            </nav>
                        </div>

                        <div className="flex space-x-2">
                            {permissions.edit && (
                                <Button asChild variant="outline">
                                    <Link href={route('production.print-jobs.edit', printJob.id)}>
                                        <Edit className="h-4 w-4 mr-1" />
                                        Edit
                                    </Link>
                                </Button>
                            )}
                            
                            {permissions.manage_production && printJob.production_status === 'pending' && (
                                <Button onClick={handleStartProduction}>
                                    <PlayCircle className="h-4 w-4 mr-1" />
                                    Start Production
                                </Button>
                            )}
                            
                            {permissions.assign_staff && (
                                <Dialog open={isAssignDialogOpen} onOpenChange={setIsAssignDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline">
                                            <UserPlus className="h-4 w-4 mr-1" />
                                            Assign Staff
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Assign Production Staff</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleAssignStaff} className="space-y-4">
                                            <div>
                                                <Label htmlFor="assigned_to">Staff Member</Label>
                                                <Select
                                                    value={assignData.assigned_to}
                                                    onValueChange={(value) => setAssignData('assigned_to', value)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select staff member" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {safeProductionStaff.map((staff) => (
                                                            <SelectItem key={staff.id} value={staff.id.toString()}>
                                                                {staff.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {assignErrors.assigned_to && (
                                                    <p className="text-sm text-red-600 mt-1">{assignErrors.assigned_to}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="notes">Notes</Label>
                                                <Textarea
                                                    id="notes"
                                                    value={assignData.notes}
                                                    onChange={(e) => setAssignData('notes', e.target.value)}
                                                    placeholder="Assignment notes"
                                                    rows={3}
                                                />
                                                {assignErrors.notes && (
                                                    <p className="text-sm text-red-600 mt-1">{assignErrors.notes}</p>
                                                )}
                                            </div>

                                            <div className="flex justify-end space-x-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => setIsAssignDialogOpen(false)}
                                                    disabled={assignProcessing}
                                                >
                                                    Cancel
                                                </Button>
                                                <Button type="submit" disabled={assignProcessing}>
                                                    {assignProcessing ? 'Assigning...' : 'Assign'}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}

                            {permissions.update_priority && (
                                <Dialog open={isPriorityDialogOpen} onOpenChange={setIsPriorityDialogOpen}>
                                    <DialogTrigger asChild>
                                        <Button variant="outline">
                                            <Target className="h-4 w-4 mr-1" />
                                            Priority
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent>
                                        <DialogHeader>
                                            <DialogTitle>Update Priority</DialogTitle>
                                        </DialogHeader>
                                        <form onSubmit={handleUpdatePriority} className="space-y-4">
                                            <div>
                                                <Label htmlFor="priority">Priority Level</Label>
                                                <Select
                                                    value={priorityData.priority}
                                                    onValueChange={(value) => setPriorityData('priority', value)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="low">Low</SelectItem>
                                                        <SelectItem value="normal">Normal</SelectItem>
                                                        <SelectItem value="medium">Medium</SelectItem>
                                                        <SelectItem value="high">High</SelectItem>
                                                        <SelectItem value="urgent">Urgent</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                                {priorityErrors.priority && (
                                                    <p className="text-sm text-red-600 mt-1">{priorityErrors.priority}</p>
                                                )}
                                            </div>

                                            <div>
                                                <Label htmlFor="reason">Reason for Change</Label>
                                                <Textarea
                                                    id="reason"
                                                    value={priorityData.reason}
                                                    onChange={(e) => setPriorityData('reason', e.target.value)}
                                                    placeholder="Why is this priority change needed?"
                                                    rows={3}
                                                />
                                            </div>

                                            <div className="flex justify-end space-x-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    onClick={() => setIsPriorityDialogOpen(false)}
                                                    disabled={priorityProcessing}
                                                >
                                                    Cancel
                                                </Button>
                                                <Button type="submit" disabled={priorityProcessing}>
                                                    {priorityProcessing ? 'Updating...' : 'Update Priority'}
                                                </Button>
                                            </div>
                                        </form>
                                    </DialogContent>
                                </Dialog>
                            )}

                            {permissions.delete && (
                                <Button variant="destructive" onClick={handleDelete}>
                                    <Trash2 className="h-4 w-4 mr-1" />
                                    Delete
                                </Button>
                            )}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        {/* Main Content */}
                        <div className="lg:col-span-2 space-y-6">
                            {/* Job Status */}
                            <Card>
                                <CardContent className="pt-6">
                                    <div className="flex items-center justify-between mb-4">
                                        <div className="flex items-center space-x-2">
                                            <Badge className={statusColors[printJob.production_status] || 'bg-gray-500'}>
                                                {printJob.production_status.replace('_', ' ').charAt(0).toUpperCase() + 
                                                 printJob.production_status.replace('_', ' ').slice(1)}
                                            </Badge>
                                            <Badge className={priorityColors[printJob.priority] || 'bg-gray-500'}>
                                                {printJob.priority.charAt(0).toUpperCase() + printJob.priority.slice(1)} Priority
                                            </Badge>
                                        </div>
                                        <div className="text-right">
                                            <div className="text-2xl font-bold">{printJob.completion_percentage}%</div>
                                            <div className="text-sm text-gray-500">Complete</div>
                                        </div>
                                    </div>
                                    
                                    <div className="mb-4">
                                        <div className="flex justify-between text-sm mb-2">
                                            <span>Progress</span>
                                            <span>{printJob.completion_percentage}%</span>
                                        </div>
                                        <Progress value={printJob.completion_percentage} className="h-2" />
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 text-sm">
                                        <div className="flex items-center text-gray-600">
                                            <Calendar className="h-4 w-4 mr-2" />
                                            Created: {formatDate(printJob.created_at)}
                                        </div>
                                        <div className="flex items-center text-gray-600">
                                            <Timer className="h-4 w-4 mr-2" />
                                            Est. Completion: {formatDate(printJob.estimated_completion)}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Job Details */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Package className="h-5 w-5 mr-2" />
                                        Job Details
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-4">
                                            <div>
                                                <Label className="text-sm font-medium text-gray-500">Job Type</Label>
                                                <p className="mt-1">{jobTypes[printJob.job_type] || printJob.job_type}</p>
                                            </div>
                                            
                                            {printJob.job_title && (
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-500">Job Title</Label>
                                                    <p className="mt-1">{printJob.job_title}</p>
                                                </div>
                                            )}

                                            <div>
                                                <Label className="text-sm font-medium text-gray-500">Branch</Label>
                                                <p className="mt-1">{printJob.branch.name} ({printJob.branch.code})</p>
                                            </div>

                                            {printJob.assignedTo && (
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-500">Assigned To</Label>
                                                    <p className="mt-1">{printJob.assignedTo.name}</p>
                                                </div>
                                            )}
                                        </div>

                                        <div className="space-y-4">
                                            {printJob.estimated_cost && (
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-500">Estimated Cost</Label>
                                                    <p className="mt-1">{formatCurrency(printJob.estimated_cost)}</p>
                                                </div>
                                            )}

                                            {printJob.actual_cost && (
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-500">Actual Cost</Label>
                                                    <p className="mt-1">{formatCurrency(printJob.actual_cost)}</p>
                                                </div>
                                            )}

                                            {printJob.actual_completion && (
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-500">Completed At</Label>
                                                    <p className="mt-1">{formatDate(printJob.actual_completion)}</p>
                                                </div>
                                            )}
                                        </div>
                                    </div>

                                    {printJob.job_description && (
                                        <div className="mt-4">
                                            <Label className="text-sm font-medium text-gray-500">Description</Label>
                                            <p className="mt-1 text-gray-700">{printJob.job_description}</p>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Enhanced Production Stages */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center">
                                        <Settings className="h-5 w-5 mr-2" />
                                        Production Stages
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-4">
                                        {safeProductionStages.length > 0 ? (
                                            <div className="space-y-4">
                                                {safeProductionStages.map((stage, index) => (
                                                    <div key={stage.id} className="border rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                                        <div className="flex items-start space-x-4">
                                                            <div className="flex-shrink-0">
                                                                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium ${
                                                                    stage.stage_status === 'completed' ? 'bg-green-100 text-green-700' :
                                                                    stage.stage_status === 'in_progress' ? 'bg-blue-100 text-blue-700' :
                                                                    stage.stage_status === 'ready' ? 'bg-blue-50 text-blue-600' :
                                                                    stage.stage_status === 'on_hold' ? 'bg-yellow-100 text-yellow-700' :
                                                                    stage.stage_status === 'requires_approval' ? 'bg-orange-100 text-orange-700' :
                                                                    stage.stage_status === 'rejected' ? 'bg-red-100 text-red-700' :
                                                                    'bg-gray-100 text-gray-600'
                                                                }`}>
                                                                    {getStageIcon(stage.stage_status)}
                                                                </div>
                                                            </div>
                                                            <div className="flex-1 min-w-0">
                                                                <div className="flex items-start justify-between mb-2">
                                                                    <div className="flex-1">
                                                                        <div className="flex items-center space-x-3 mb-2">
                                                                            <h4 className="text-base font-medium text-gray-900">
                                                                                {stage.stage_name.replace('_', ' ').charAt(0).toUpperCase() + 
                                                                                 stage.stage_name.replace('_', ' ').slice(1)}
                                                                            </h4>
                                                                            <Badge className={getStageStatusColor(stage.stage_status)}>
                                                                                {stage.stage_status.replace('_', ' ').charAt(0).toUpperCase() + 
                                                                                 stage.stage_status.replace('_', ' ').slice(1)}
                                                                            </Badge>
                                                                        </div>

                                                                        {/* Duration and Progress Info */}
                                                                        <div className="flex items-center gap-4 text-xs text-gray-500 mb-2">
                                                                            {stage.estimated_duration && (
                                                                                <span className="flex items-center gap-1">
                                                                                    <Clock className="h-3 w-3" />
                                                                                    Est: {stage.estimated_duration}min
                                                                                </span>
                                                                            )}
                                                                            {stage.actual_duration && (
                                                                                <span className="flex items-center gap-1">
                                                                                    <CheckCircle className="h-3 w-3" />
                                                                                    Actual: {stage.actual_duration}min
                                                                                </span>
                                                                            )}
                                                                            {stage.requires_customer_approval && (
                                                                                <span className="flex items-center gap-1 text-orange-600">
                                                                                    <FileCheck className="h-3 w-3" />
                                                                                    Customer Approval Required
                                                                                </span>
                                                                            )}
                                                                            <span className="text-gray-400 ml-auto">#{stage.stage_order}</span>
                                                                        </div>
                                                                    </div>

                                                                    {/* Stage Actions */}
                                                                    <div className="flex-shrink-0 ml-4">
                                                                        {getStageActionButtons(stage)}
                                                                    </div>
                                                                </div>

                                                                {stage.notes && (
                                                                    <div className="mt-2 text-sm text-gray-600 bg-gray-50 p-2 rounded">
                                                                        {stage.notes}
                                                                    </div>
                                                                )}

                                                                <div className="mt-2 flex justify-between items-center text-xs text-gray-400">
                                                                    <div className="flex gap-4">
                                                                        {stage.started_at && (
                                                                            <span>Started: {formatDate(stage.started_at)}</span>
                                                                        )}
                                                                        {stage.completed_at && (
                                                                            <span>Completed: {formatDate(stage.completed_at)}</span>
                                                                        )}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <div className="text-center py-8 text-gray-500">
                                                <Settings className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                                <p>No production stages defined for this job.</p>
                                                <p className="text-sm mt-2">Stages will be created automatically when production starts.</p>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Stage Action Dialog */}
                            <Dialog open={stageActionDialog.isOpen} onOpenChange={(open) => {
                                if (!open) {
                                    setStageActionDialog({ isOpen: false, stage: null, action: null });
                                }
                            }}>
                                <DialogContent className="max-w-md">
                                    <DialogHeader>
                                        <DialogTitle>{getActionDialogTitle()}</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={submitStageAction} className="space-y-4">
                                        <div>
                                            <Label htmlFor="stage_notes">Notes</Label>
                                            <Textarea
                                                id="stage_notes"
                                                value={stageActionData.notes}
                                                onChange={(e) => setStageActionData('notes', e.target.value)}
                                                placeholder={`Add notes for this ${stageActionDialog.action}...`}
                                                rows={3}
                                            />
                                            {stageActionErrors.notes && (
                                                <p className="text-sm text-red-600 mt-1">{stageActionErrors.notes}</p>
                                            )}
                                        </div>

                                        {stageActionDialog.action === 'reject' && (
                                            <div>
                                                <Label htmlFor="reason">Reason for Rejection <span className="text-red-500">*</span></Label>
                                                <Textarea
                                                    id="reason"
                                                    value={stageActionData.reason}
                                                    onChange={(e) => setStageActionData('reason', e.target.value)}
                                                    placeholder="Please provide a reason for rejecting this stage..."
                                                    rows={3}
                                                    required
                                                />
                                                {stageActionErrors.reason && (
                                                    <p className="text-sm text-red-600 mt-1">{stageActionErrors.reason}</p>
                                                )}
                                            </div>
                                        )}

                                        {stageActionDialog.action === 'hold' && (
                                            <div>
                                                <Label htmlFor="reason">Reason for Hold <span className="text-red-500">*</span></Label>
                                                <Textarea
                                                    id="reason"
                                                    value={stageActionData.reason}
                                                    onChange={(e) => setStageActionData('reason', e.target.value)}
                                                    placeholder="Please provide a reason for putting this stage on hold..."
                                                    rows={3}
                                                    required
                                                />
                                                {stageActionErrors.reason && (
                                                    <p className="text-sm text-red-600 mt-1">{stageActionErrors.reason}</p>
                                                )}
                                            </div>
                                        )}

                                        <div className="flex justify-end space-x-2">
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => setStageActionDialog({ isOpen: false, stage: null, action: null })}
                                                disabled={stageActionProcessing}
                                            >
                                                Cancel
                                            </Button>
                                            <Button 
                                                type="submit" 
                                                disabled={stageActionProcessing}
                                                className={
                                                    stageActionDialog.action === 'reject' ? 'bg-red-600 hover:bg-red-700' :
                                                    stageActionDialog.action === 'approve' ? 'bg-green-600 hover:bg-green-700' :
                                                    stageActionDialog.action === 'complete' ? 'bg-blue-600 hover:bg-blue-700' :
                                                    stageActionDialog.action === 'start' ? 'bg-green-600 hover:bg-green-700' :
                                                    ''
                                                }
                                            >
                                                {stageActionProcessing ? 'Processing...' : 
                                                    stageActionDialog.action?.charAt(0).toUpperCase() + stageActionDialog.action?.slice(1)}
                                            </Button>
                                        </div>
                                    </form>
                                </DialogContent>
                            </Dialog>

                            {/* Instructions & Notes */}
                            {(printJob.customer_instructions || printJob.production_notes || printJob.special_instructions) && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Instructions & Notes</CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {printJob.customer_instructions && (
                                            <div>
                                                <Label className="text-sm font-medium text-gray-500">Customer Instructions</Label>
                                                <p className="mt-1 text-gray-700">{printJob.customer_instructions}</p>
                                            </div>
                                        )}
                                        
                                        {printJob.special_instructions && (
                                            <div>
                                                <Label className="text-sm font-medium text-gray-500">Special Instructions</Label>
                                                <p className="mt-1 text-gray-700">{printJob.special_instructions}</p>
                                            </div>
                                        )}
                                        
                                        {printJob.production_notes && (
                                            <div>
                                                <Label className="text-sm font-medium text-gray-500">Production Notes</Label>
                                                <p className="mt-1 text-gray-700">{printJob.production_notes}</p>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            )}
                        </div>

                        {/* Sidebar */}
                        <div className="space-y-6">
                            {/* Invoice Information */}
                            {printJob.invoice && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex items-center">
                                            <FileText className="h-5 w-5 mr-2" />
                                            Related Invoice
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            <div>
                                                <Button asChild variant="outline" className="w-full justify-start">
                                                    <Link href={route('invoices.show', printJob.invoice.id)}>
                                                        <Eye className="h-4 w-4 mr-2" />
                                                        View Invoice {printJob.invoice.invoice_number}
                                                    </Link>
                                                </Button>
                                            </div>
                                            
                                            <div>
                                                <Label className="text-sm font-medium text-gray-500">Customer</Label>
                                                <p className="mt-1">{printJob.invoice.customer.name}</p>
                                                <p className="text-sm text-gray-500">{printJob.invoice.customer.phone}</p>
                                            </div>

                                            {safeInvoiceItems.length > 0 && (
                                                <div>
                                                    <Label className="text-sm font-medium text-gray-500">Items ({safeInvoiceItems.length})</Label>
                                                    <div className="mt-1 space-y-1">
                                                        {safeInvoiceItems.slice(0, 3).map((item) => (
                                                            <div key={item.id} className="text-sm">
                                                                {item.quantity}x {item.product?.name || item.item_description}
                                                            </div>
                                                        ))}
                                                        {safeInvoiceItems.length > 3 && (
                                                            <div className="text-sm text-gray-500">
                                                                +{safeInvoiceItems.length - 3} more items
                                                            </div>
                                                        )}
                                                    </div>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Quick Actions */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Quick Actions</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-2">
                                    {permissions.manage_production && printJob.production_status === 'pending' && (
                                        <Button 
                                            className="w-full justify-start" 
                                            onClick={handleStartProduction}
                                        >
                                            <PlayCircle className="h-4 w-4 mr-2" />
                                            Start Production
                                        </Button>
                                    )}
                                    
                                    {permissions.edit && (
                                        <Button asChild variant="outline" className="w-full justify-start">
                                            <Link href={route('production.print-jobs.edit', printJob.id)}>
                                                <Edit className="h-4 w-4 mr-2" />
                                                Edit Job Details
                                            </Link>
                                        </Button>
                                    )}

                                    <Button asChild variant="outline" className="w-full justify-start">
                                        <Link href={route('production.print-jobs.index')}>
                                            <Factory className="h-4 w-4 mr-2" />
                                            Back to Production Queue
                                        </Link>
                                    </Button>
                                </CardContent>
                            </Card>

                            {/* Production Progress Summary */}
                            {safeProductionStages.length > 0 && (
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Stage Summary</CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            <div className="flex justify-between text-sm">
                                                <span>Total Stages:</span>
                                                <span className="font-medium">{safeProductionStages.length}</span>
                                            </div>
                                            <div className="flex justify-between text-sm">
                                                <span>Completed:</span>
                                                <span className="font-medium text-green-600">
                                                    {safeProductionStages.filter(s => s.stage_status === 'completed').length}
                                                </span>
                                            </div>
                                            <div className="flex justify-between text-sm">
                                                <span>In Progress:</span>
                                                <span className="font-medium text-blue-600">
                                                    {safeProductionStages.filter(s => s.stage_status === 'in_progress').length}
                                                </span>
                                            </div>
                                            <div className="flex justify-between text-sm">
                                                <span>Pending:</span>
                                                <span className="font-medium text-gray-600">
                                                    {safeProductionStages.filter(s => ['pending', 'ready'].includes(s.stage_status)).length}
                                                </span>
                                            </div>
                                            {safeProductionStages.filter(s => s.stage_status === 'on_hold').length > 0 && (
                                                <div className="flex justify-between text-sm">
                                                    <span>On Hold:</span>
                                                    <span className="font-medium text-yellow-600">
                                                        {safeProductionStages.filter(s => s.stage_status === 'on_hold').length}
                                                    </span>
                                                </div>
                                            )}
                                            {safeProductionStages.filter(s => s.stage_status === 'requires_approval').length > 0 && (
                                                <div className="flex justify-between text-sm">
                                                    <span>Awaiting Approval:</span>
                                                    <span className="font-medium text-orange-600">
                                                        {safeProductionStages.filter(s => s.stage_status === 'requires_approval').length}
                                                    </span>
                                                </div>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}