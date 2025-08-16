<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id', 'branch_id', 'expense_category_id', 'submitted_by', 'approved_by',
        'expense_number', 'expense_date', 'amount', 'description', 'vendor_name',
        'vendor_address', 'vendor_phone', 'vendor_email', 'payment_method',
        'payment_reference', 'receipt_number', 'receipt_attachments', 'status',
        'approval_status', 'payment_status', 'priority', 'is_recurring', 
        'recurring_period', 'due_date', 'notes', 'approval_notes', 
        'rejection_reason', 'approved_at', 'paid_at', 'metadata', 'tax_details'
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
        'receipt_attachments' => 'json',
        'is_recurring' => 'boolean',
        'due_date' => 'date',
        'approved_at' => 'datetime',
        'paid_at' => 'datetime',
        'metadata' => 'json',
        'tax_details' => 'json',
    ];

    // Relationships
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        return $query->where('company_id', $companyId);
    }

    public function scopeForBranch(Builder $query, int $branchId): Builder
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByApprovalStatus(Builder $query, string $approvalStatus): Builder
    {
        return $query->where('approval_status', $approvalStatus);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    public function scopeThisMonth(Builder $query): Builder
    {
        return $query->whereMonth('expense_date', now()->month)
                    ->whereYear('expense_date', now()->year);
    }

    // Accessors
    public function getFormattedAmountAttribute(): string
    {
        return 'Rs. ' . number_format($this->amount, 2);
    }

    public function getCanApproveAttribute(): bool
    {
        return $this->approval_status === 'pending';
    }

    public function getCanEditAttribute(): bool
    {
        return in_array($this->approval_status, ['draft', 'rejected']);
    }

    // Methods
    public function submitForApproval(string $notes = null): bool
    {
        if ($this->approval_status !== 'draft') {
            return false;
        }

        return $this->update([
            'approval_status' => 'pending',
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function approve(User $approver, string $notes = null): bool
    {
        if ($this->approval_status !== 'pending') {
            return false;
        }

        return $this->update([
            'approval_status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'approval_notes' => $notes,
        ]);
    }

    public function reject(User $approver, string $reason): bool
    {
        if ($this->approval_status !== 'pending') {
            return false;
        }

        return $this->update([
            'approval_status' => 'rejected',
            'approved_by' => $approver->id,
            'approved_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }

    public static function generateExpenseNumber(int $companyId, int $branchId): string
    {
        $branch = Branch::find($branchId);
        $branchCode = $branch ? $branch->code : 'BR';
        
        $lastExpense = static::where('company_id', $companyId)
                            ->where('branch_id', $branchId)
                            ->whereYear('created_at', now()->year)
                            ->orderBy('id', 'desc')
                            ->first();

        $sequence = $lastExpense ? 
            intval(substr($lastExpense->expense_number, -6)) + 1 : 
            1;

        return sprintf('EXP-%s-%s-%06d', 
            $branchCode, 
            now()->format('Y'), 
            $sequence
        );
    }

    public static function getStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'submitted' => 'Submitted',
            'cancelled' => 'Cancelled',
        ];
    }

    public static function getApprovalStatusOptions(): array
    {
        return [
            'draft' => 'Draft',
            'pending' => 'Pending Approval',
            'approved' => 'Approved',
            'rejected' => 'Rejected',
        ];
    }

    public static function getPaymentStatusOptions(): array
    {
        return [
            'pending' => 'Pending',
            'paid' => 'Paid',
            'partially_paid' => 'Partially Paid',
            'overdue' => 'Overdue',
        ];
    }

    public static function getPriorityOptions(): array
    {
        return [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent',
        ];
    }

    public static function getPaymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'bank_transfer' => 'Bank Transfer',
            'credit_card' => 'Credit Card',
            'debit_card' => 'Debit Card',
            'cheque' => 'Cheque',
            'online_payment' => 'Online Payment',
            'petty_cash' => 'Petty Cash',
        ];
    }
}